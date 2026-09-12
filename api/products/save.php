<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('products.manage');
verify_csrf();

$data = request_data();
$id = (int)($data['id'] ?? 0);
$name = trim((string)($data['name'] ?? ''));
$cost = (float)($data['cost_price'] ?? 0);
$price = (float)($data['selling_price'] ?? 0);
$minStock = (int)($data['minimum_stock'] ?? 0);
$status = in_array(($data['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], true) ? $data['status'] : 'ACTIVE';
$errors = [];

if ($name === '') $errors['name'] = 'Name is required.';
if ($cost < 0) $errors['cost_price'] = 'Cost must be greater than or equal to 0.';
if ($price < 0) $errors['selling_price'] = 'Selling price must be greater than or equal to 0.';
if ($minStock < 0) $errors['minimum_stock'] = 'Minimum stock must be greater than or equal to 0.';
if ($errors) json_response(['ok' => false, 'message' => 'Validation failed.', 'errors' => $errors], 422);

try {
    if ($id > 0) {
        db()->prepare('UPDATE products SET sku=?, barcode=?, name=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, minimum_stock=?, description=?, status=? WHERE id=?')->execute([
            trim((string)($data['sku'] ?? '')) ?: null,
            trim((string)($data['barcode'] ?? '')) ?: null,
            $name,
            (int)($data['category_id'] ?? 0) ?: null,
            (int)($data['supplier_id'] ?? 0) ?: null,
            $cost,
            $price,
            $minStock,
            trim((string)($data['description'] ?? '')) ?: null,
            $status,
            $id,
        ]);
        audit_log('UPDATE_PRODUCT', 'products', $id);
        json_response(['ok' => true, 'message' => 'Product updated successfully.']);
    }

    $stock = (int)($data['current_stock'] ?? 0);
    if ($stock < 0) json_response(['ok' => false, 'message' => 'Validation failed.', 'errors' => ['current_stock' => 'Initial stock must be greater than or equal to 0.']], 422);

    db()->beginTransaction();
    db()->prepare('INSERT INTO products (sku, barcode, name, category_id, supplier_id, cost_price, selling_price, current_stock, minimum_stock, description, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([
        trim((string)($data['sku'] ?? '')) ?: null,
        trim((string)($data['barcode'] ?? '')) ?: null,
        $name,
        (int)($data['category_id'] ?? 0) ?: null,
        (int)($data['supplier_id'] ?? 0) ?: null,
        $cost,
        $price,
        $stock,
        $minStock,
        trim((string)($data['description'] ?? '')) ?: null,
        $status,
    ]);
    $id = (int)db()->lastInsertId();
    db()->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity_change, previous_stock, new_stock, reason, created_by) VALUES (?, "INITIAL_STOCK", ?, 0, ?, "Product created", ?)')->execute([$id, $stock, $stock, current_user()['id']]);
    audit_log('CREATE_PRODUCT', 'products', $id);
    db()->commit();
    json_response(['ok' => true, 'message' => 'Product created successfully.', 'id' => $id]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}

