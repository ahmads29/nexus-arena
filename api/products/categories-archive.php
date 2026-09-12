<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('products.manage');
verify_csrf();

$id = (int)(request_data()['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Invalid category.'], 422);
}

db()->prepare("UPDATE categories SET status='INACTIVE' WHERE id=?")->execute([$id]);
audit_log('ARCHIVE_CATEGORY', 'categories', $id);
json_response(['ok' => true, 'message' => 'Category archived successfully.']);

