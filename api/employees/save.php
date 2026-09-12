<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('employees.manage');
verify_csrf();

$data = request_data();
$id = (int)($data['id'] ?? 0);
$name = trim((string)($data['name'] ?? ''));
$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$roleId = (int)($data['role_id'] ?? 0);
$status = in_array(($data['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], true) ? $data['status'] : 'ACTIVE';
$password = (string)($data['password'] ?? '');
$errors = [];

if ($name === '') $errors['name'] = 'Name is required.';
if ($username === '') $errors['username'] = 'Username is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
if ($roleId <= 0) {
    $errors['role_id'] = 'Role is required.';
} else {
    $stmt = db()->prepare('SELECT 1 FROM roles WHERE id=?');
    $stmt->execute([$roleId]);
    if (!$stmt->fetchColumn()) $errors['role_id'] = 'Selected role does not exist.';
}
if ($id <= 0 && strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
if ($id > 0 && $password !== '' && strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
if ($id === (int)(current_user()['id'] ?? 0) && $status === 'INACTIVE') {
    $errors['status'] = 'You cannot deactivate your own account while logged in.';
}

$stmt = db()->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');
$stmt->execute([$username, $id]);
if ($stmt->fetchColumn()) $errors['username'] = 'Username is already used.';

if ($email !== '') {
    $stmt = db()->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
    $stmt->execute([$email, $id]);
    if ($stmt->fetchColumn()) $errors['email'] = 'Email is already used.';
}

if ($errors) json_response(['ok' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);

if ($id > 0) {
    if ($password !== '') {
        db()->prepare('UPDATE users SET name=?, username=?, email=?, role_id=?, status=?, password_hash=? WHERE id=?')
            ->execute([$name, $username, $email ?: null, $roleId, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
        db()->prepare('UPDATE users SET name=?, username=?, email=?, role_id=?, status=? WHERE id=?')
            ->execute([$name, $username, $email ?: null, $roleId, $status, $id]);
    }
    audit_log('UPDATE_EMPLOYEE', 'users', $id);
    json_response(['ok' => true, 'message' => 'Employee updated successfully.']);
}

db()->prepare('INSERT INTO users (name, username, email, password_hash, role_id, status) VALUES (?,?,?,?,?,?)')
    ->execute([$name, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $roleId, $status]);
$id = (int)db()->lastInsertId();
audit_log('CREATE_EMPLOYEE', 'users', $id);
json_response(['ok' => true, 'message' => 'Employee created successfully.', 'id' => $id]);
