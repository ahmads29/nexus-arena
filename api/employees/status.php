<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('employees.manage');
verify_csrf();

$data = request_data();
$id = (int)($data['id'] ?? 0);
$status = (string)($data['status'] ?? '');

if ($id <= 0 || !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid status update.'], 422);
}
if ($id === (int)(current_user()['id'] ?? 0) && $status === 'INACTIVE') {
    json_response(['ok' => false, 'message' => 'You cannot deactivate your own account while logged in.'], 422);
}

$stmt = db()->prepare('UPDATE users SET status=? WHERE id=?');
$stmt->execute([$status, $id]);
if ($stmt->rowCount() === 0) json_response(['ok' => false, 'message' => 'User not found or status unchanged.'], 404);

audit_log($status === 'ACTIVE' ? 'ACTIVATE_EMPLOYEE' : 'DEACTIVATE_EMPLOYEE', 'users', $id);
json_response(['ok' => true, 'message' => 'User status updated successfully.']);
