<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('products.manage');
verify_csrf();

$data = request_data();
$id = (int)($data['id'] ?? 0);
$name = trim((string)($data['name'] ?? ''));
$status = in_array(($data['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], true) ? $data['status'] : 'ACTIVE';

if ($name === '') {
    json_response(['ok' => false, 'message' => 'Category name is required.', 'errors' => ['name' => 'Category name is required.']], 422);
}

try {
    if ($id > 0) {
        db()->prepare('UPDATE categories SET name=?, status=? WHERE id=?')->execute([$name, $status, $id]);
        audit_log('UPDATE_CATEGORY', 'categories', $id);
        json_response(['ok' => true, 'message' => 'Category updated successfully.']);
    }

    db()->prepare('INSERT INTO categories (name, status) VALUES (?, ?)')->execute([$name, $status]);
    $id = (int)db()->lastInsertId();
    audit_log('CREATE_CATEGORY', 'categories', $id);
    json_response(['ok' => true, 'message' => 'Category created successfully.', 'id' => $id]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_response(['ok' => false, 'message' => 'A category with this name already exists.'], 422);
    }
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}

