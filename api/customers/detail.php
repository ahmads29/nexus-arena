<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('customers.manage');
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM customers WHERE id=?');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) json_response(['ok' => false, 'message' => 'Customer not found.'], 404);
$sales = db()->prepare('SELECT invoice_number,total,created_at,status FROM sales WHERE customer_id=? ORDER BY id DESC LIMIT 8');
$sales->execute([$id]);
$sessions = db()->prepare('SELECT session_code,start_time,total,status FROM gaming_sessions WHERE customer_id=? ORDER BY id DESC LIMIT 8');
$sessions->execute([$id]);
$debts = db()->prepare('SELECT original_amount,paid_amount,remaining_amount,status,created_at FROM debts WHERE customer_id=? ORDER BY id DESC LIMIT 8');
$debts->execute([$id]);
json_response(['ok' => true, 'customer' => $customer, 'sales' => $sales->fetchAll(), 'sessions' => $sessions->fetchAll(), 'debts' => $debts->fetchAll()]);

