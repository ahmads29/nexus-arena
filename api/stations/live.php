<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$pcCounts = ['TOTAL' => 0, 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0, 'OFFLINE' => 0];
foreach (db()->query("SELECT status, COUNT(*) count FROM stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
    $pcCounts[$row['status']] = (int)$row['count'];
    $pcCounts['TOTAL'] += (int)$row['count'];
}
$psCounts = ['TOTAL' => 0, 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0, 'OFFLINE' => 0];
foreach (db()->query("SELECT status, COUNT(*) count FROM playstation_stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
    $psCounts[$row['status']] = (int)$row['count'];
    $psCounts['TOTAL'] += (int)$row['count'];
}
$pcs = db()->query("SELECT s.id, s.name, s.status, s.tier, s.hourly_rate, st.name type_name, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED') WHERE s.active=1 ORDER BY s.name")->fetchAll();
$ps = db()->query("SELECT p.id, p.name, p.console_type, p.status, p.hourly_rate, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM playstation_stations p LEFT JOIN station_zones z ON z.id=p.station_zone_id LEFT JOIN gaming_sessions gs ON gs.playstation_station_id=p.id AND gs.status IN ('ACTIVE','PAUSED') WHERE p.active=1 ORDER BY p.console_type,p.name")->fetchAll();
json_response(['ok' => true, 'pc_counts' => $pcCounts, 'ps_counts' => $psCounts, 'pcs' => $pcs, 'playstation' => $ps]);
