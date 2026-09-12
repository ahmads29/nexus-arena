<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('products.manage');
verify_csrf();

$id = (int)(request_data()['id'] ?? 0);
if ($id <= 0) json_response(['ok' => false, 'message' => 'Invalid product.'], 422);

db()->prepare("UPDATE products SET status='INACTIVE' WHERE id=?")->execute([$id]);
audit_log('ARCHIVE_PRODUCT', 'products', $id);
json_response(['ok' => true, 'message' => 'Product archived successfully.']);

