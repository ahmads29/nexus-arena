<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('products.manage');

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, c.name category, s.name supplier FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.id=?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) json_response(['ok' => false, 'message' => 'Product not found.'], 404);

$stmt = db()->prepare('SELECT sm.*, u.name user_name FROM stock_movements sm LEFT JOIN users u ON u.id=sm.created_by WHERE sm.product_id=? ORDER BY sm.id DESC LIMIT 16');
$stmt->execute([$id]);
json_response(['ok' => true, 'product' => $product, 'movements' => $stmt->fetchAll()]);

