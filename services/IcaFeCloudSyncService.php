<?php
declare(strict_types=1);

require_once __DIR__ . '/IcaFeCloudClient.php';

final class IcaFeCloudSyncService
{
    public function __construct(private readonly ?IcaFeCloudClient $client = null)
    {
    }

    public function testConnection(): array
    {
        $client = $this->client ?? new IcaFeCloudClient();
        $pcList = $client->pcList();
        $online = $client->onlinePcList();
        $groups = $client->pricePcGroups();

        return [
            'ok' => true,
            'status' => 'CONNECTED',
            'cafe_id' => $client->cafeId(),
            'pcs_detected' => count($this->recordsFromResponse($pcList)),
            'connected' => count($this->recordsFromResponse($online)),
            'groups_detected' => count($this->recordsFromResponse($groups)),
        ];
    }

    public function sync(bool $force = false): array
    {
        if (setting('icafecloud_enabled', '0') !== '1') {
            save_setting('icafecloud_status', 'DISABLED');
            return ['ok' => true, 'status' => 'DISABLED', 'message' => 'iCafeCloud integration is disabled.'];
        }

        if (!$force && !$this->syncDue()) {
            return $this->cachedSummary('CONNECTED');
        }

        $lock = db()->query("SELECT GET_LOCK('gaming_icafecloud_sync', 0)")->fetchColumn();
        if ((int)$lock !== 1) {
            return $this->cachedSummary('SYNC IN PROGRESS');
        }

        try {
            if (!$force && !$this->syncDue()) {
                return $this->cachedSummary('CONNECTED');
            }
            return $this->performSync();
        } finally {
            db()->query("SELECT RELEASE_LOCK('gaming_icafecloud_sync')");
        }
    }

    private function performSync(): array
    {
        $client = $this->client ?? new IcaFeCloudClient();
        $pcList = null;
        $onlineList = null;
        $groups = null;
        $warnings = [];

        try {
            $pcList = $client->pcList();
        } catch (IcaFeCloudApiException $e) {
            save_setting('icafecloud_status', $e->codeName);
            save_setting('icafecloud_last_error_at', date('Y-m-d H:i:s'));
            audit_log('ICAFECLOUD_SYNC_ERROR', 'icafecloud', null, ['status' => $e->codeName, 'http' => $e->httpStatus]);
            return $this->cachedSummary($e->codeName, $e->getMessage());
        }

        try {
            $onlineList = $client->onlinePcList();
        } catch (IcaFeCloudApiException $e) {
            $warnings[] = 'Connectivity data was not updated: ' . $e->codeName;
        }

        try {
            $groups = $client->pricePcGroups();
        } catch (IcaFeCloudApiException $e) {
            $warnings[] = 'Group data was not updated: ' . $e->codeName;
        }

        $pcs = $this->recordsFromResponse($pcList);
        $online = $onlineList ? $this->recordsFromResponse($onlineList) : [];
        $groupsRows = $groups ? $this->recordsFromResponse($groups) : [];
        $onlineByName = [];
        foreach ($online as $row) {
            $name = normalize_icafe_pc_name($row['pc_name'] ?? '');
            if ($name !== '') {
                $onlineByName[$name] = !empty($row['is_connected']);
            }
        }

        $seenNames = [];
        $duplicates = [];
        $now = date('Y-m-d H:i:s');

        db()->beginTransaction();
        try {
            if ($groupsRows) {
                $groupStmt = db()->prepare('INSERT INTO icafecloud_groups (pc_group_id, pc_group_name, last_sync_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE pc_group_name=VALUES(pc_group_name), last_sync_at=VALUES(last_sync_at)');
                foreach ($groupsRows as $group) {
                    $groupId = (string)($group['pc_group_id'] ?? '');
                    $groupName = (string)($group['pc_group_name'] ?? $groupId);
                    if ($groupId !== '') {
                        $groupStmt->execute([$groupId, $groupName, $now]);
                    }
                }
            }

            db()->exec("UPDATE icafecloud_pcs SET is_present=0, sync_status='MISSING', last_missing_at=COALESCE(last_missing_at, NOW())");
            $pcStmt = db()->prepare('INSERT INTO icafecloud_pcs (pc_name, normalized_name, pc_ip, pc_mac, pc_comment, pc_console_type, pc_group_id, pc_group_name, pc_area_name, pc_enabled, is_connected, pc_in_using, member_id, member_account, member_balance, member_balance_bonus, member_group_id, member_group_name, offer_in_using, price_name, current_game_id, current_game_name, current_game_class, current_game_updated_at, status_connect_time_local, status_disconnect_time_local, status_connect_time_duration, status_connect_time_left, status_total_time, status_offer_time, recent_booking, is_present, sync_status, last_missing_at, raw_json, last_seen_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE normalized_name=VALUES(normalized_name), pc_ip=VALUES(pc_ip), pc_mac=VALUES(pc_mac), pc_comment=VALUES(pc_comment), pc_console_type=VALUES(pc_console_type), pc_group_id=VALUES(pc_group_id), pc_group_name=VALUES(pc_group_name), pc_area_name=VALUES(pc_area_name), pc_enabled=VALUES(pc_enabled), is_connected=COALESCE(VALUES(is_connected), is_connected), pc_in_using=VALUES(pc_in_using), member_id=VALUES(member_id), member_account=VALUES(member_account), member_balance=VALUES(member_balance), member_balance_bonus=VALUES(member_balance_bonus), member_group_id=VALUES(member_group_id), member_group_name=VALUES(member_group_name), offer_in_using=VALUES(offer_in_using), price_name=VALUES(price_name), current_game_id=VALUES(current_game_id), current_game_name=VALUES(current_game_name), current_game_class=VALUES(current_game_class), current_game_updated_at=VALUES(current_game_updated_at), status_connect_time_local=VALUES(status_connect_time_local), status_disconnect_time_local=VALUES(status_disconnect_time_local), status_connect_time_duration=VALUES(status_connect_time_duration), status_connect_time_left=VALUES(status_connect_time_left), status_total_time=VALUES(status_total_time), status_offer_time=VALUES(status_offer_time), recent_booking=VALUES(recent_booking), is_present=1, sync_status="SYNCED", last_missing_at=NULL, raw_json=VALUES(raw_json), last_seen_at=VALUES(last_seen_at)');

            foreach ($pcs as $pc) {
                $pcName = trim((string)($pc['pc_name'] ?? ''));
                $normalized = normalize_icafe_pc_name($pcName);
                if ($pcName === '' || $normalized === '') {
                    continue;
                }
                if (isset($seenNames[$normalized])) {
                    $duplicates[] = $pcName;
                    continue;
                }
                $seenNames[$normalized] = true;
                $connected = array_key_exists($normalized, $onlineByName) ? (int)$onlineByName[$normalized] : null;
                $pcInUsing = array_key_exists('pc_in_using', $pc) ? (int)(bool)$pc['pc_in_using'] : null;
                $activeExternalUser = $pcInUsing === 1;
                $pcStmt->execute([
                    $pcName,
                    $normalized,
                    $pc['pc_ip'] ?? null,
                    $pc['pc_mac'] ?? null,
                    $pc['pc_comment'] ?? null,
                    isset($pc['pc_console_type']) ? (string)$pc['pc_console_type'] : null,
                    isset($pc['pc_group_id']) ? (string)$pc['pc_group_id'] : null,
                    $pc['pc_group_name'] ?? null,
                    $pc['pc_area_name'] ?? null,
                    array_key_exists('pc_enabled', $pc) ? (int)(bool)$pc['pc_enabled'] : null,
                    $connected,
                    $pcInUsing,
                    $activeExternalUser ? (isset($pc['member_id']) ? (string)$pc['member_id'] : ($pc['status_member_id'] ?? null)) : null,
                    $activeExternalUser ? ($pc['member_account'] ?? ($pc['status_member_account'] ?? null)) : null,
                    $activeExternalUser && isset($pc['member_balance']) ? (float)$pc['member_balance'] : null,
                    $activeExternalUser && isset($pc['member_balance_bonus']) ? (float)$pc['member_balance_bonus'] : null,
                    $activeExternalUser && isset($pc['member_group_id']) ? (string)$pc['member_group_id'] : null,
                    $activeExternalUser ? ($pc['member_group_name'] ?? null) : null,
                    $activeExternalUser ? (isset($pc['offer_in_using']) ? (string)$pc['offer_in_using'] : ($pc['status_member_offer_id'] ?? null)) : null,
                    $pc['price_name'] ?? null,
                    null,
                    null,
                    null,
                    null,
                    $pc['status_connect_time_local'] ?? null,
                    $pc['status_disconnect_time_local'] ?? null,
                    $pc['status_connect_time_duration'] ?? null,
                    $pc['status_connect_time_left'] ?? null,
                    $pc['status_total_time'] ?? null,
                    $pc['status_offer_time'] ?? null,
                    isset($pc['recent_booking']) ? json_encode($pc['recent_booking'], JSON_THROW_ON_ERROR) : null,
                    1,
                    'SYNCED',
                    null,
                    json_encode($pc, JSON_THROW_ON_ERROR),
                    $now,
                ]);
            }

            $this->updateMappedStations($now);
            $status = $warnings ? 'CONNECTED' : 'CONNECTED';
            save_setting('icafecloud_status', $status);
            save_setting('icafecloud_last_success_at', $now);
            save_setting('icafecloud_last_sync_at', $now);
            save_setting('icafecloud_last_error_at', '');
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            save_setting('icafecloud_status', 'API ERROR');
            audit_log('ICAFECLOUD_SYNC_ERROR', 'icafecloud', null, ['status' => 'API ERROR']);
            return $this->cachedSummary('API ERROR', 'Sync failed while updating local cache.');
        }

        if ($duplicates) {
            $warnings[] = 'Duplicate iCafeCloud PC mapping detected: ' . implode(', ', array_slice($duplicates, 0, 5));
        }

        audit_log('ICAFECLOUD_SYNC', 'icafecloud', null, ['pcs' => count($pcs), 'warnings' => count($warnings)]);
        return $this->cachedSummary(icafecloud_status_label(), null, $warnings);
    }

    private function updateMappedStations(string $now): void
    {
        $activeLocal = [];
        foreach (db()->query("SELECT station_id FROM gaming_sessions WHERE station_id IS NOT NULL AND status IN ('ACTIVE','PAUSED')")->fetchAll() as $row) {
            $activeLocal[(int)$row['station_id']] = true;
        }

        $stations = db()->query("SELECT s.id station_id, s.status local_status, p.* FROM stations s JOIN icafecloud_pcs p ON p.normalized_name = REPLACE(UPPER(s.icafe_pc_name), ' ', '') WHERE s.icafe_pc_name IS NOT NULL AND s.icafe_pc_name <> ''")->fetchAll();
        $stmt = db()->prepare("UPDATE stations SET status=?, icafe_pc_mac=?, icafe_pc_ip=?, icafe_group_id=?, icafe_group_name=?, icafe_console_type=?, icafe_enabled=?, icafe_connected=?, icafe_in_using=?, icafe_member_id=?, icafe_member_account=?, icafe_member_balance=?, icafe_member_balance_bonus=?, icafe_offer=?, icafe_price_name=?, icafe_connect_time=?, icafe_disconnect_time=?, icafe_session_duration=?, icafe_time_left=?, icafe_status_total_time=?, icafe_status_offer_time=?, icafe_last_sync_at=?, icafe_sync_status='SYNCED' WHERE id=?");
        foreach ($stations as $row) {
            $status = $row['local_status'];
            if (!in_array($status, ['MAINTENANCE', 'RESERVED'], true) && empty($activeLocal[(int)$row['station_id']])) {
                if ((int)$row['pc_in_using'] === 1) {
                    $status = 'PLAYING';
                } elseif ($row['is_connected'] !== null && (int)$row['is_connected'] === 0) {
                    $status = 'OFFLINE';
                } elseif ($row['is_connected'] === null || (int)$row['is_connected'] === 1) {
                    $status = 'AVAILABLE';
                }
            }

            $stmt->execute([
                $status,
                $row['pc_mac'],
                $row['pc_ip'],
                $row['pc_group_id'],
                $row['pc_group_name'],
                $row['pc_console_type'],
                $row['pc_enabled'],
                $row['is_connected'],
                $row['pc_in_using'],
                $row['member_id'],
                $row['member_account'],
                $row['member_balance'],
                $row['member_balance_bonus'],
                $row['offer_in_using'],
                $row['price_name'],
                $row['status_connect_time_local'],
                $row['status_disconnect_time_local'],
                $row['status_connect_time_duration'],
                $row['status_connect_time_left'],
                $row['status_total_time'],
                $row['status_offer_time'],
                $now,
                $row['station_id'],
            ]);
        }

        db()->exec("UPDATE stations SET icafe_sync_status='UNMAPPED' WHERE active=1 AND (icafe_pc_name IS NULL OR icafe_pc_name='') AND icafe_sync_status <> 'DISABLED'");
    }

    public function cachedSummary(string $status, ?string $message = null, array $warnings = []): array
    {
        $counts = db()->query("SELECT COUNT(*) pcs_detected, SUM(is_connected=1) connected, SUM(pc_in_using=1) occupied FROM icafecloud_pcs WHERE sync_status <> 'MISSING'")->fetch();
        $groups = (int)db()->query('SELECT COUNT(*) FROM icafecloud_groups')->fetchColumn();
        $missing = (int)db()->query("SELECT COUNT(*) FROM icafecloud_pcs WHERE sync_status='MISSING'")->fetchColumn();
        $unmapped = db()->query("SELECT p.pc_name FROM icafecloud_pcs p LEFT JOIN stations s ON p.normalized_name = REPLACE(UPPER(s.icafe_pc_name), ' ', '') WHERE p.sync_status <> 'MISSING' AND s.id IS NULL ORDER BY p.pc_name LIMIT 25")->fetchAll();
        $duplicateMappings = db()->query("SELECT icafe_pc_name FROM stations WHERE icafe_pc_name IS NOT NULL AND icafe_pc_name <> '' GROUP BY icafe_pc_name HAVING COUNT(*) > 1")->fetchAll();
        $available = max(0, (int)($counts['connected'] ?? 0) - (int)($counts['occupied'] ?? 0));
        $offline = max(0, (int)($counts['pcs_detected'] ?? 0) - (int)($counts['connected'] ?? 0));

        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
            'last_sync_at' => setting('icafecloud_last_success_at', null),
            'pcs_detected' => (int)($counts['pcs_detected'] ?? 0),
            'connected' => (int)($counts['connected'] ?? 0),
            'occupied' => (int)($counts['occupied'] ?? 0),
            'available' => $available,
            'offline' => $offline,
            'groups_detected' => $groups,
            'missing' => $missing,
            'unmapped' => array_column($unmapped, 'pc_name'),
            'duplicate_mappings' => array_column($duplicateMappings, 'icafe_pc_name'),
            'warnings' => $warnings,
        ];
    }

    private function syncDue(): bool
    {
        $last = setting('icafecloud_last_success_at', null);
        if (!$last) {
            return true;
        }
        return time() - strtotime((string)$last) >= max(15, (int)setting('icafecloud_sync_interval_seconds', 60));
    }

    private function recordsFromResponse(array $response): array
    {
        if (array_is_list($response)) {
            return $response;
        }
        foreach (['data', 'pcs', 'pcList', 'onlinePcList', 'pricePcGroups', 'items', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_is_list($response[$key]) ? $response[$key] : [$response[$key]];
            }
        }
        return [];
    }
}
