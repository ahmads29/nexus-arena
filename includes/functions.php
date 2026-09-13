<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(mixed $amount, ?string $currency = null): string
{
    if ($currency !== null) {
        return $currency . number_format((float)$amount, 2);
    }

    $code = strtoupper((string)setting('currency_code', 'LBP'));
    if ($code === '') {
        $legacy = (string)setting('currency', '$');
        $code = str_contains(strtoupper($legacy), '$') ? 'USD' : 'LBP';
    }

    $amount = (float)$amount;
    if ($code === 'LBP') {
        $rate = max(1, (float)setting('usd_lbp_rate', 89500));
        return 'LBP ' . number_format($amount * $rate, 0) . ' ($' . number_format($amount, 2) . ')';
    }

    $rate = max(1, (float)setting('usd_lbp_rate', 89500));
    return '$' . number_format($amount, 2) . ' (LBP ' . number_format($amount * $rate, 0) . ')';
}

function currency_config(): array
{
    $code = strtoupper((string)setting('currency_code', 'LBP'));
    if (!in_array($code, ['USD', 'LBP'], true)) {
        $code = 'LBP';
    }

    return [
        'code' => $code,
        'symbol' => $code === 'LBP' ? 'LBP ' : '$',
        'rate' => max(1, (float)setting('usd_lbp_rate', 89500)),
        'decimals' => $code === 'LBP' ? 0 : 2,
        'secondary_symbol' => $code === 'LBP' ? '$' : 'LBP ',
        'secondary_decimals' => $code === 'LBP' ? 2 : 0,
    ];
}

function ensure_lbp_primary_currency(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (setting('currency_primary_migrated_to_lbp', null) === null) {
            save_setting('currency_code', 'LBP');
            save_setting('currency', 'LBP ');
            save_setting('currency_primary_migrated_to_lbp', '1');
        }
    } catch (Throwable) {
    }
}

function setting(string $key, mixed $default = null): mixed
{
    if (!array_key_exists('_settings_cache', $GLOBALS)) {
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            $GLOBALS['_settings_cache'] = [];
            foreach ($rows as $row) {
                $GLOBALS['_settings_cache'][$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            $GLOBALS['_settings_cache'] = [];
        }
    }
    return $GLOBALS['_settings_cache'][$key] ?? $default;
}

function save_setting(string $key, mixed $value): void
{
    db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
        ->execute([$key, (string)$value]);
    $GLOBALS['_settings_cache'][$key] = (string)$value;
}

function walk_in_customer_id(): int
{
    $stmt = db()->prepare('SELECT id FROM customers WHERE name=? ORDER BY id LIMIT 1');
    $stmt->execute(['Walk-in']);
    $id = (int)$stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }

    db()->prepare('INSERT INTO customers (name, notes) VALUES (?, ?)')
        ->execute(['Walk-in', 'Internal guest record for reservations and legacy financial links.']);
    return (int)db()->lastInsertId();
}

function ensure_website_content_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $column = db()->query("SHOW COLUMNS FROM games LIKE 'image_path'")->fetch();
        if (!$column) {
            db()->exec('ALTER TABLE games ADD image_path VARCHAR(255) NULL AFTER name');
        }
    } catch (Throwable) {
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS pricing_items (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          title VARCHAR(120) NOT NULL,
          category ENUM('PC','PLAYSTATION','OTHER') NOT NULL DEFAULT 'PC',
          price DECIMAL(10,2) NOT NULL DEFAULT 0,
          unit VARCHAR(40) NOT NULL DEFAULT 'hour',
          sort_order INT NOT NULL DEFAULT 0,
          active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_pricing_active (active, sort_order),
          INDEX idx_pricing_category (category)
        ) ENGINE=InnoDB
    ");

    $count = (int)db()->query('SELECT COUNT(*) FROM pricing_items')->fetchColumn();
    if ($count === 0) {
        $stmt = db()->prepare('INSERT INTO pricing_items (title, category, price, unit, sort_order, active) VALUES (?,?,?,?,?,1)');
        foreach ([
            ['Standard PC', 'PC', (float)setting('standard_pc_rate', 3), 'hour', 1],
            ['Pro PC', 'PC', (float)setting('pro_pc_rate', 4.5), 'hour', 2],
            ['VIP PC', 'PC', (float)setting('vip_pc_rate', 6), 'hour', 3],
            ['PS4', 'PLAYSTATION', (float)setting('ps4_rate', 3), 'hour', 4],
            ['PS5', 'PLAYSTATION', (float)setting('ps5_rate', 5), 'hour', 5],
        ] as $item) {
            $stmt->execute($item);
        }
    }
}

function ensure_playstation_pos_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    foreach ([
        'base_hourly_rate' => 'DECIMAL(10,2) NULL AFTER start_time',
        'controller_count' => 'INT NOT NULL DEFAULT 0 AFTER base_hourly_rate',
        'controller_rate' => 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER controller_count',
    ] as $column => $definition) {
        $stmt = db()->query("SHOW COLUMNS FROM gaming_sessions LIKE " . db()->quote($column));
        if (!$stmt->fetch()) {
            db()->exec("ALTER TABLE gaming_sessions ADD {$column} {$definition}");
        }
    }

    if (setting('ps4_controller_rate', null) === null) {
        save_setting('ps4_controller_rate', '0.50');
    }
    if (setting('ps5_controller_rate', null) === null) {
        save_setting('ps5_controller_rate', '1.00');
    }
}

function normalize_icafe_pc_name(?string $name): string
{
    return strtoupper(trim(preg_replace('/\s+/', '', (string)$name)));
}

function icafecloud_setting(string $key, mixed $default = null): mixed
{
    $envKey = 'ICAFECLOUD_' . strtoupper($key);
    $value = getenv($envKey);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $localFile = __DIR__ . '/../config/icafecloud.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local) && array_key_exists(strtolower($key), $local) && $local[strtolower($key)] !== '') {
            return $local[strtolower($key)];
        }
    }

    return setting('icafecloud_' . strtolower($key), $default);
}

function icafecloud_status_label(): string
{
    if (setting('icafecloud_enabled', '0') !== '1') {
        return 'DISABLED';
    }
    $status = strtoupper((string)setting('icafecloud_status', 'DISABLED'));
    $lastSync = setting('icafecloud_last_success_at', null);
    $staleSeconds = max(30, (int)setting('icafecloud_stale_after_seconds', 120));
    if ($lastSync && time() - strtotime((string)$lastSync) > $staleSeconds) {
        return 'STALE';
    }
    return $status ?: 'DISABLED';
}

function ensure_icafecloud_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $stationColumns = [
        'icafe_pc_name' => 'VARCHAR(80) NULL AFTER active',
        'icafe_pc_mac' => 'VARCHAR(120) NULL AFTER icafe_pc_name',
        'icafe_pc_ip' => 'VARCHAR(80) NULL AFTER icafe_pc_mac',
        'icafe_group_id' => 'VARCHAR(80) NULL AFTER icafe_pc_ip',
        'icafe_group_name' => 'VARCHAR(120) NULL AFTER icafe_group_id',
        'icafe_console_type' => 'VARCHAR(40) NULL AFTER icafe_group_name',
        'icafe_enabled' => 'TINYINT(1) NULL AFTER icafe_console_type',
        'icafe_connected' => 'TINYINT(1) NULL AFTER icafe_enabled',
        'icafe_in_using' => 'TINYINT(1) NULL AFTER icafe_connected',
        'icafe_member_id' => 'VARCHAR(80) NULL AFTER icafe_in_using',
        'icafe_member_account' => 'VARCHAR(160) NULL AFTER icafe_member_id',
        'icafe_member_balance' => 'DECIMAL(12,2) NULL AFTER icafe_member_account',
        'icafe_member_balance_bonus' => 'DECIMAL(12,2) NULL AFTER icafe_member_balance',
        'icafe_offer' => 'VARCHAR(160) NULL AFTER icafe_member_balance_bonus',
        'icafe_price_name' => 'VARCHAR(160) NULL AFTER icafe_offer',
        'icafe_connect_time' => 'VARCHAR(80) NULL AFTER icafe_price_name',
        'icafe_disconnect_time' => 'VARCHAR(80) NULL AFTER icafe_connect_time',
        'icafe_session_duration' => 'VARCHAR(80) NULL AFTER icafe_disconnect_time',
        'icafe_time_left' => 'VARCHAR(80) NULL AFTER icafe_session_duration',
        'icafe_status_total_time' => 'VARCHAR(80) NULL AFTER icafe_time_left',
        'icafe_status_offer_time' => 'VARCHAR(80) NULL AFTER icafe_status_total_time',
        'icafe_last_sync_at' => 'DATETIME NULL AFTER icafe_status_offer_time',
        'icafe_sync_status' => "ENUM('SYNCED','STALE','ERROR','UNMAPPED','DISABLED') NOT NULL DEFAULT 'DISABLED' AFTER icafe_last_sync_at",
    ];

    foreach ($stationColumns as $column => $definition) {
        $stmt = db()->query("SHOW COLUMNS FROM stations LIKE " . db()->quote($column));
        if (!$stmt->fetch()) {
            db()->exec("ALTER TABLE stations ADD {$column} {$definition}");
        }
    }

    $index = db()->query("SHOW INDEX FROM stations WHERE Key_name='idx_stations_icafe_name'")->fetch();
    if (!$index) {
        db()->exec('CREATE INDEX idx_stations_icafe_name ON stations (icafe_pc_name)');
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS icafecloud_pcs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pc_name VARCHAR(80) NOT NULL,
          normalized_name VARCHAR(100) NOT NULL,
          pc_ip VARCHAR(80) NULL,
          pc_mac VARCHAR(120) NULL,
          pc_comment TEXT NULL,
          pc_console_type VARCHAR(40) NULL,
          pc_group_id VARCHAR(80) NULL,
          pc_group_name VARCHAR(120) NULL,
          pc_area_name VARCHAR(120) NULL,
          pc_enabled TINYINT(1) NULL,
          is_connected TINYINT(1) NULL,
          pc_in_using TINYINT(1) NULL,
          member_id VARCHAR(80) NULL,
          member_account VARCHAR(160) NULL,
          member_balance DECIMAL(12,2) NULL,
          member_balance_bonus DECIMAL(12,2) NULL,
          member_group_id VARCHAR(80) NULL,
          member_group_name VARCHAR(120) NULL,
          offer_in_using VARCHAR(160) NULL,
          price_name VARCHAR(160) NULL,
          current_game_id VARCHAR(80) NULL,
          current_game_name VARCHAR(160) NULL,
          current_game_class VARCHAR(160) NULL,
          current_game_updated_at DATETIME NULL,
          status_connect_time_local VARCHAR(80) NULL,
          status_disconnect_time_local VARCHAR(80) NULL,
          status_connect_time_duration VARCHAR(80) NULL,
          status_connect_time_left VARCHAR(80) NULL,
          status_total_time VARCHAR(80) NULL,
          status_offer_time VARCHAR(80) NULL,
          recent_booking TEXT NULL,
          is_present TINYINT(1) NOT NULL DEFAULT 1,
          sync_status ENUM('SYNCED','STALE','MISSING','ERROR') NOT NULL DEFAULT 'SYNCED',
          last_missing_at DATETIME NULL,
          raw_json JSON NULL,
          last_seen_at DATETIME NOT NULL,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_icafe_pc_name (pc_name),
          INDEX idx_icafe_normalized_name (normalized_name),
          INDEX idx_icafe_group (pc_group_id, sync_status),
          INDEX idx_icafe_seen (last_seen_at)
        ) ENGINE=InnoDB
    ");

    foreach ([
        'is_present' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER recent_booking',
        'sync_status' => "ENUM('SYNCED','STALE','MISSING','ERROR') NOT NULL DEFAULT 'SYNCED' AFTER is_present",
        'last_missing_at' => 'DATETIME NULL AFTER sync_status',
        'current_game_id' => 'VARCHAR(80) NULL AFTER price_name',
        'current_game_name' => 'VARCHAR(160) NULL AFTER current_game_id',
        'current_game_class' => 'VARCHAR(160) NULL AFTER current_game_name',
        'current_game_updated_at' => 'DATETIME NULL AFTER current_game_class',
    ] as $column => $definition) {
        $stmt = db()->query("SHOW COLUMNS FROM icafecloud_pcs LIKE " . db()->quote($column));
        if (!$stmt->fetch()) {
            db()->exec("ALTER TABLE icafecloud_pcs ADD {$column} {$definition}");
        }
    }

    $groupIndex = db()->query("SHOW INDEX FROM icafecloud_pcs WHERE Key_name='idx_icafe_group'")->fetch();
    if (!$groupIndex) {
        db()->exec('CREATE INDEX idx_icafe_group ON icafecloud_pcs (pc_group_id, sync_status)');
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS icafecloud_groups (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pc_group_id VARCHAR(80) NOT NULL,
          pc_group_name VARCHAR(120) NOT NULL,
          last_sync_at DATETIME NOT NULL,
          UNIQUE KEY uq_icafe_group_id (pc_group_id)
        ) ENGINE=InnoDB
    ");

    foreach ([
        'icafecloud_enabled' => '0',
        'icafecloud_base_url' => 'https://api.icafecloud.com',
        'icafecloud_cafe_id' => '50761',
        'icafecloud_sync_interval_seconds' => '60',
        'icafecloud_stale_after_seconds' => '120',
        'icafecloud_status' => 'DISABLED',
    ] as $key => $value) {
        if (setting($key, null) === null) {
            save_setting($key, $value);
        }
    }
}

function icafecloud_is_enabled(): bool
{
    return setting('icafecloud_enabled', '0') === '1';
}

function icafecloud_data_is_fresh(): bool
{
    if (!icafecloud_is_enabled()) {
        return false;
    }
    $lastSync = setting('icafecloud_last_success_at', null);
    if (!$lastSync) {
        return false;
    }
    return time() - strtotime((string)$lastSync) <= max(30, (int)setting('icafecloud_stale_after_seconds', 120));
}

function icafecloud_live_status(array $pc, ?array $station, bool $fresh): string
{
    if ($station && in_array($station['local_status'] ?? '', ['MAINTENANCE', 'RESERVED'], true)) {
        return $station['local_status'];
    }
    if (!$fresh) {
        return 'STALE';
    }
    if ((int)($pc['pc_in_using'] ?? 0) === 1) {
        return 'PLAYING';
    }
    if (array_key_exists('is_connected', $pc) && $pc['is_connected'] !== null && (int)$pc['is_connected'] === 0) {
        return 'OFFLINE';
    }
    return 'AVAILABLE';
}

function icafecloud_live_pc_rows(bool $admin = false): array
{
    $fresh = icafecloud_data_is_fresh();
    $sql = "
        SELECT
            p.*,
            g.pc_group_name discovered_group_name,
            s.id mapped_station_id,
            s.name mapped_station_name,
            s.status local_status,
            s.tier local_tier,
            s.hourly_rate local_hourly_rate,
            st.name local_type_name,
            z.name local_zone_name,
            gs.current_game local_current_game,
            TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) local_elapsed
        FROM icafecloud_pcs p
        LEFT JOIN icafecloud_groups g ON g.pc_group_id = p.pc_group_id
        LEFT JOIN stations s ON p.normalized_name = REPLACE(UPPER(s.icafe_pc_name), ' ', '')
        LEFT JOIN station_types st ON st.id=s.station_type_id
        LEFT JOIN station_zones z ON z.id=s.station_zone_id
        LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED')
        WHERE p.sync_status <> 'MISSING'
        ORDER BY COALESCE(g.pc_group_name, p.pc_group_name, p.pc_area_name, 'iCafeCloud PCs'), p.pc_name
    ";
    $rows = [];
    foreach (db()->query($sql)->fetchAll() as $row) {
        $status = icafecloud_live_status($row, $row['mapped_station_id'] ? $row : null, $fresh);
        $group = $row['discovered_group_name'] ?: ($row['pc_group_name'] ?: ($row['pc_area_name'] ?: 'iCafeCloud PCs'));
        $item = [
            'id' => 'icafe-' . $row['id'],
            'mapped' => (bool)$row['mapped_station_id'],
            'name' => $row['pc_name'],
            'status' => $status,
            'tier' => $row['local_tier'] ?: ($row['pc_group_name'] ?: $group),
            'hourly_rate' => $row['local_hourly_rate'] ?: 0,
            'type_name' => $row['local_type_name'] ?: 'iCafeCloud PC',
            'zone_name' => $row['local_zone_name'] ?: $group,
            'group' => $group,
            'group_id' => $row['pc_group_id'],
            'group_name' => $group,
            'current_game' => $row['local_current_game'] ?: '',
            'elapsed' => $row['local_elapsed'],
            'connection_status' => $row['is_connected'] === null ? 'UNKNOWN' : ((int)$row['is_connected'] === 1 ? 'ONLINE' : 'OFFLINE'),
            'icafe_synced' => $fresh ? $row['sync_status'] : 'STALE',
            'sync_status' => $fresh ? $row['sync_status'] : 'STALE',
            'last_sync_at' => $row['last_seen_at'],
        ];
        $activeExternalUser = (int)($row['pc_in_using'] ?? 0) === 1;
        $item += [
            'username' => $activeExternalUser ? (string)($row['member_account'] ?: $row['member_id'] ?: '') : '',
            'icafe_session_duration' => $activeExternalUser ? $row['status_connect_time_duration'] : null,
            'icafe_time_left' => $activeExternalUser ? $row['status_connect_time_left'] : null,
        ];
        if ($admin) {
            $item += [
                'external_id' => (int)$row['id'],
                'mapped_station_id' => $row['mapped_station_id'] ? (int)$row['mapped_station_id'] : null,
                'connected' => $row['is_connected'] === null ? null : (int)$row['is_connected'],
                'pc_in_using' => (int)($row['pc_in_using'] ?? 0),
                'icafe_member_account' => $row['member_account'],
                'icafe_member_id' => $row['member_id'],
                'icafe_member_balance' => $row['member_balance'],
                'icafe_member_balance_bonus' => $row['member_balance_bonus'],
                'icafe_offer' => $row['offer_in_using'],
                'icafe_price_name' => $row['price_name'],
                'icafe_session_duration' => $row['status_connect_time_duration'],
                'icafe_time_left' => $row['status_connect_time_left'],
                'icafe_status_total_time' => $row['status_total_time'],
                'icafe_status_offer_time' => $row['status_offer_time'],
                'mapped_station_name' => $row['mapped_station_name'],
            ];
        }
        $rows[] = $item;
    }
    return $rows;
}

function station_counts_from_rows(array $rows): array
{
    $counts = ['TOTAL' => 0, 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0, 'OFFLINE' => 0, 'STALE' => 0];
    foreach ($rows as $row) {
        $status = strtoupper((string)($row['status'] ?? 'OFFLINE'));
        if (!array_key_exists($status, $counts)) {
            $counts[$status] = 0;
        }
        $counts[$status]++;
        $counts['TOTAL']++;
    }
    return $counts;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function request_data(): array
{
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function audit_log(string $action, string $entityType, ?int $entityId = null, array $meta = []): void
{
    $userId = $_SESSION['user']['id'] ?? null;
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entityType, $entityId, json_encode($meta), $_SERVER['REMOTE_ADDR'] ?? null]);
}

function active_session_charge(array $session): float
{
    $end = $session['end_time'] ? strtotime($session['end_time']) : time();
    $start = strtotime($session['start_time']);
    $paused = (int)($session['paused_seconds'] ?? 0);
    if (($session['status'] ?? '') === 'PAUSED' && !empty($session['last_paused_at'])) {
        $paused += max(0, time() - strtotime($session['last_paused_at']));
    }
    $seconds = max(0, $end - $start - $paused);
    return round(($seconds / 3600) * (float)$session['hourly_rate'], 2);
}

function station_status_class(string $status): string
{
    return match (strtoupper($status)) {
        'AVAILABLE' => 'status-available',
        'PLAYING', 'PAUSED' => 'status-playing',
        'RESERVED' => 'status-reserved',
        'MAINTENANCE', 'STALE' => 'status-maintenance',
        default => 'status-offline',
    };
}
