<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('sessions.manage');
verify_csrf();
ensure_playstation_pos_schema();
$data = request_data();
$kind = $data['kind'] === 'ps' ? 'ps' : 'pc';
$stationId = (int)($data['station_id'] ?? 0);
$customerId = (int)($data['customer_id'] ?? 0) ?: null;
$game = trim((string)($data['current_game'] ?? ''));
if ($stationId <= 0) json_response(['ok' => false, 'message' => 'Select a station.'], 422);

db()->beginTransaction();
try {
    if ($kind === 'pc') {
        $stmt = db()->prepare("SELECT * FROM stations WHERE id=? AND active=1 FOR UPDATE");
        $stmt->execute([$stationId]);
        $station = $stmt->fetch();
        if (!$station || $station['status'] !== 'AVAILABLE') throw new RuntimeException('PC is not available.');
        $code = 'S-PC-' . $stationId . '-' . date('YmdHis');
        db()->prepare('INSERT INTO gaming_sessions (session_code, station_id, customer_id, started_by, current_game, start_time, hourly_rate, status) VALUES (?,?,?,?,?,NOW(),?,"ACTIVE")')
            ->execute([$code, $stationId, $customerId, current_user()['id'], $game, $station['hourly_rate']]);
        db()->prepare("UPDATE stations SET status='PLAYING' WHERE id=?")->execute([$stationId]);
    } else {
        $stmt = db()->prepare("SELECT * FROM playstation_stations WHERE id=? AND active=1 FOR UPDATE");
        $stmt->execute([$stationId]);
        $station = $stmt->fetch();
        if (!$station || $station['status'] !== 'AVAILABLE') throw new RuntimeException('PlayStation is not available.');
        $controllerCount = max(0, min(8, (int)($data['controller_count'] ?? 0)));
        $controllerRate = (float)setting(strtolower((string)$station['console_type']) . '_controller_rate', 0);
        $baseRate = (float)$station['hourly_rate'];
        $hourlyRate = round($baseRate + ($controllerCount * $controllerRate), 2);
        $code = 'S-PS-' . $stationId . '-' . date('YmdHis');
        db()->prepare('INSERT INTO gaming_sessions (session_code, playstation_station_id, customer_id, started_by, current_game, start_time, base_hourly_rate, controller_count, controller_rate, hourly_rate, status) VALUES (?,?,?,?,?,NOW(),?,?,?,?,"ACTIVE")')
            ->execute([$code, $stationId, $customerId, current_user()['id'], $game, $baseRate, $controllerCount, $controllerRate, $hourlyRate]);
        db()->prepare("UPDATE playstation_stations SET status='PLAYING' WHERE id=?")->execute([$stationId]);
    }
    $sessionId = (int)db()->lastInsertId();
    audit_log('START_SESSION', 'gaming_sessions', $sessionId, ['kind' => $kind]);
    db()->commit();
    json_response(['ok' => true, 'session_id' => $sessionId]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
