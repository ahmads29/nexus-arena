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
        'MAINTENANCE' => 'status-maintenance',
        default => 'status-offline',
    };
}
