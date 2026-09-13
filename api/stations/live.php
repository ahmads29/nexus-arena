<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../services/IcaFeCloudSyncService.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (setting('icafecloud_enabled', '0') === '1') {
    try {
        (new IcaFeCloudSyncService())->sync(false);
    } catch (Throwable) {
    }
}

$adminRequest = isset($_GET['admin']) && $_GET['admin'] === '1' && current_user() && user_can('stations.manage');

if (icafecloud_is_enabled()) {
    $pcs = icafecloud_live_pc_rows($adminRequest);
    $pcCounts = station_counts_from_rows($pcs);
    $pcGroups = [];
    foreach ($pcs as $pc) {
        $group = $pc['group_name'] ?: 'iCafeCloud PCs';
        if (!isset($pcGroups[$group])) {
            $pcGroups[$group] = ['name' => $group, 'count' => 0];
        }
        $pcGroups[$group]['count']++;
    }
    $pcGroups = array_values($pcGroups);
} else {
    $pcCounts = ['TOTAL' => 0, 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0, 'OFFLINE' => 0, 'STALE' => 0];
    foreach (db()->query("SELECT status, COUNT(*) count FROM stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
        $pcCounts[$row['status']] = (int)$row['count'];
        $pcCounts['TOTAL'] += (int)$row['count'];
    }
    $pcs = db()->query("SELECT s.id, s.name, s.status, s.tier, s.hourly_rate, st.name type_name, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed, CASE WHEN s.icafe_connected=1 THEN 'ONLINE' WHEN s.icafe_connected=0 THEN 'OFFLINE' ELSE 'UNKNOWN' END connection_status, s.icafe_sync_status icafe_synced, s.icafe_last_sync_at last_sync_at FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED') WHERE s.active=1 ORDER BY s.name")->fetchAll();
    $pcGroups = [];
}
$psCounts = ['TOTAL' => 0, 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0, 'OFFLINE' => 0];
foreach (db()->query("SELECT status, COUNT(*) count FROM playstation_stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
    $psCounts[$row['status']] = (int)$row['count'];
    $psCounts['TOTAL'] += (int)$row['count'];
}
$ps = db()->query("SELECT p.id, p.name, p.console_type, p.status, p.hourly_rate, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM playstation_stations p LEFT JOIN station_zones z ON z.id=p.station_zone_id LEFT JOIN gaming_sessions gs ON gs.playstation_station_id=p.id AND gs.status IN ('ACTIVE','PAUSED') WHERE p.active=1 ORDER BY p.console_type,p.name")->fetchAll();
json_response(['ok' => true, 'icafecloud_status' => icafecloud_status_label(), 'pc_counts' => $pcCounts, 'pc_groups' => $pcGroups, 'ps_counts' => $psCounts, 'pcs' => $pcs, 'playstation' => $ps]);
