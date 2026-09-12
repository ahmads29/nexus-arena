<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('employees.manage');
verify_csrf();

$data = request_data();
$id = (int)($data['id'] ?? 0);
$password = (string)($data['password'] ?? '');
$errors = [];

if ($id <= 0) $errors['id'] = 'Invalid user.';
if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
if ($errors) json_response(['ok' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);

$stmt = db()->prepare('SELECT id FROM users WHERE id=?');
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) json_response(['ok' => false, 'message' => 'User not found.'], 404);

db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
audit_log('RESET_EMPLOYEE_PASSWORD', 'users', $id);
json_response(['ok' => true, 'message' => 'Password reset successfully.']);
