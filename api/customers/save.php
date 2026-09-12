<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('customers.manage');
verify_csrf();
$data = request_data();
$id = (int)($data['id'] ?? 0);
$name = trim((string)($data['name'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$errors = [];
if ($name === '') $errors['name'] = 'Name is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
if ($errors) json_response(['ok' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);
if ($id > 0) {
    db()->prepare('UPDATE customers SET name=?, phone=?, email=?, notes=? WHERE id=?')->execute([$name, $phone ?: null, $email ?: null, trim((string)($data['notes'] ?? '')) ?: null, $id]);
    audit_log('UPDATE_CUSTOMER', 'customers', $id);
    json_response(['ok' => true, 'message' => 'Customer updated successfully.']);
}
db()->prepare('INSERT INTO customers (name, phone, email, notes) VALUES (?,?,?,?)')->execute([$name, $phone ?: null, $email ?: null, trim((string)($data['notes'] ?? '')) ?: null]);
$id = (int)db()->lastInsertId();
audit_log('CREATE_CUSTOMER', 'customers', $id);
json_response(['ok' => true, 'message' => 'Customer created successfully.', 'id' => $id]);

