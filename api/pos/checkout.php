<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('pos.use');
verify_csrf();
$data = request_data();
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$sessionId = (int)($data['session_id'] ?? 0) ?: null;
$customerId = (int)($data['customer_id'] ?? 0) ?: null;
$discount = max(0, (float)($data['discount'] ?? 0));
$method = in_array(($data['payment_method'] ?? 'CASH'), ['CASH','CARD','OTHER','DEBT'], true) ? $data['payment_method'] : 'CASH';
$paidAmountInput = $data['paid_amount'] ?? '';
if (!$items && !$sessionId) json_response(['ok' => false, 'message' => 'Cart is empty.'], 422);

db()->beginTransaction();
try {
    $saleItems = [];
    $productSubtotal = 0.0;
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) throw new RuntimeException('Invalid cart item.');
        $stmt = db()->prepare('SELECT * FROM products WHERE id=? AND status="ACTIVE" FOR UPDATE');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) throw new RuntimeException('Product not found.');
        if ((int)$product['current_stock'] < $qty) throw new RuntimeException($product['name'] . ' does not have enough stock.');
        $lineTotal = round($qty * (float)$product['selling_price'], 2);
        $saleItems[] = [$product, $qty, (float)$product['selling_price'], $lineTotal];
        $productSubtotal += $lineTotal;
    }

    $gamingCharge = 0.0;
    $session = null;
    if ($sessionId) {
        $stmt = db()->prepare('SELECT * FROM gaming_sessions WHERE id=? FOR UPDATE');
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        if (!$session) throw new RuntimeException('Session not found.');
        $customerId = $customerId ?: ($session['customer_id'] ?: null);
        $gamingCharge = $session['status'] === 'COMPLETED' ? (float)$session['gaming_charge'] : active_session_charge($session);
    }

    $taxRate = (float)setting('tax_rate', 0);
    $subtotal = max(0, $productSubtotal + $gamingCharge);
    $discount = min($discount, $subtotal);
    $tax = round(($subtotal - $discount) * ($taxRate / 100), 2);
    $total = round($subtotal - $discount + $tax, 2);
    $paidAmount = $paidAmountInput === '' ? ($method === 'DEBT' ? 0 : $total) : max(0, (float)$paidAmountInput);
    $status = $paidAmount >= $total ? 'PAID' : ($paidAmount > 0 ? 'PARTIAL' : 'DEBT');
    if ($method === 'DEBT' && !$customerId) throw new RuntimeException('Debt sales require a customer.');

    $invoice = 'INV-' . date('Ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    db()->prepare('INSERT INTO sales (invoice_number, customer_id, cashier_id, gaming_session_id, gaming_charge, product_subtotal, subtotal, discount, tax, total, paid_amount, payment_method, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$invoice, $customerId, current_user()['id'], $sessionId, $gamingCharge, $productSubtotal, $subtotal, $discount, $tax, $total, $paidAmount, $method, $status]);
    $saleId = (int)db()->lastInsertId();

    foreach ($saleItems as [$product, $qty, $price, $lineTotal]) {
        db()->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, total) VALUES (?,?,?,?,?,?)')
            ->execute([$saleId, $product['id'], $product['name'], $qty, $price, $lineTotal]);
        if ($sessionId) {
            db()->prepare('INSERT INTO session_products (gaming_session_id, product_id, quantity, unit_price, total) VALUES (?,?,?,?,?)')
                ->execute([$sessionId, $product['id'], $qty, $price, $lineTotal]);
        }
        $newStock = (int)$product['current_stock'] - $qty;
        db()->prepare('UPDATE products SET current_stock=? WHERE id=?')->execute([$newStock, $product['id']]);
        db()->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity_change, previous_stock, new_stock, reason, reference_type, reference_id, created_by) VALUES (?,"SALE",?,?,?,?, "sales", ?, ?)')
            ->execute([$product['id'], -$qty, (int)$product['current_stock'], $newStock, $invoice, $saleId, current_user()['id']]);
    }

    if ($paidAmount > 0 && $method !== 'DEBT') {
        db()->prepare('INSERT INTO payments (sale_id, customer_id, amount, method, created_by) VALUES (?,?,?,?,?)')
            ->execute([$saleId, $customerId, $paidAmount, $method, current_user()['id']]);
    }
    if ($paidAmount < $total) {
        $remaining = round($total - $paidAmount, 2);
        db()->prepare('INSERT INTO debts (customer_id, sale_id, original_amount, paid_amount, remaining_amount, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$customerId, $saleId, $remaining, 0, $remaining, 'OPEN', 'POS balance from ' . $invoice, current_user()['id']]);
        db()->prepare('UPDATE customers SET balance = balance + ? WHERE id=?')->execute([$remaining, $customerId]);
    }
    if ($sessionId && $session) {
        db()->prepare('UPDATE gaming_sessions SET gaming_charge=?, product_charge=product_charge+?, total=? WHERE id=?')
            ->execute([$gamingCharge, $productSubtotal, $total, $sessionId]);
    }
    audit_log('COMPLETE_SALE', 'sales', $saleId, ['invoice' => $invoice, 'total' => $total]);
    db()->commit();
    json_response(['ok' => true, 'sale_id' => $saleId, 'invoice_number' => $invoice, 'total' => $total]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
