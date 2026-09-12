<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('inventory.manage');
verify_csrf();
$data = request_data();
$productId = (int)($data['product_id'] ?? 0);
$change = (int)($data['quantity_change'] ?? 0);
$type = strtoupper((string)($data['movement_type'] ?? 'ADJUSTMENT'));
$reason = trim((string)($data['reason'] ?? 'Manual adjustment'));
if ($productId <= 0 || $change === 0 || !in_array($type, ['ADJUSTMENT','PURCHASE','DAMAGE','RETURN'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid adjustment.'], 422);
}
db()->beginTransaction();
try {
    $stmt = db()->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        throw new RuntimeException('Product not found.');
    }
    $newStock = (int)$product['current_stock'] + $change;
    if ($newStock < 0) {
        throw new RuntimeException('Stock cannot go below zero.');
    }
    db()->prepare('UPDATE products SET current_stock=? WHERE id=?')->execute([$newStock, $productId]);
    db()->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity_change, previous_stock, new_stock, reason, created_by) VALUES (?,?,?,?,?,?,?)')
        ->execute([$productId, $type, $change, (int)$product['current_stock'], $newStock, $reason, current_user()['id']]);
    audit_log('STOCK_ADJUSTMENT', 'products', $productId, ['change' => $change, 'reason' => $reason]);
    db()->commit();
    json_response(['ok' => true, 'new_stock' => $newStock]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
