<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('sessions.manage');
verify_csrf();
$data = request_data();
$sessionId = (int)($data['session_id'] ?? 0);
if ($sessionId <= 0) json_response(['ok' => false, 'message' => 'Invalid session.'], 422);

db()->beginTransaction();
try {
    $stmt = db()->prepare("SELECT * FROM gaming_sessions WHERE id=? AND status IN ('ACTIVE','PAUSED') FOR UPDATE");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) throw new RuntimeException('Active session not found.');
    $gamingCharge = active_session_charge($session);
    $chargeStmt = db()->prepare('SELECT COALESCE(SUM(total),0) FROM session_products WHERE gaming_session_id=?');
    $chargeStmt->execute([$sessionId]);
    $productCharge = (float)$chargeStmt->fetchColumn();
    db()->prepare("UPDATE gaming_sessions SET end_time=NOW(), ended_by=?, gaming_charge=?, product_charge=?, total=?, status='COMPLETED' WHERE id=?")
        ->execute([current_user()['id'], $gamingCharge, $productCharge, $gamingCharge + $productCharge, $sessionId]);
    if ($session['station_id']) db()->prepare("UPDATE stations SET status='AVAILABLE' WHERE id=?")->execute([$session['station_id']]);
    if ($session['playstation_station_id']) db()->prepare("UPDATE playstation_stations SET status='AVAILABLE' WHERE id=?")->execute([$session['playstation_station_id']]);
    audit_log('STOP_SESSION', 'gaming_sessions', $sessionId, ['gaming_charge' => $gamingCharge]);
    db()->commit();
    json_response(['ok' => true, 'gaming_charge' => $gamingCharge, 'total' => $gamingCharge + $productCharge]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
