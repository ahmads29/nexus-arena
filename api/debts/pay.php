<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login(); require_permission('debts.manage'); verify_csrf();
$data = request_data(); $debtId = (int)($data['debt_id'] ?? 0); $amount = (float)($data['amount'] ?? 0);
if ($debtId <= 0 || $amount <= 0) json_response(['ok'=>false,'message'=>'Invalid payment.'],422);
db()->beginTransaction();
try {
    $stmt = db()->prepare('SELECT * FROM debts WHERE id=? FOR UPDATE'); $stmt->execute([$debtId]); $debt = $stmt->fetch();
    if (!$debt || $debt['status'] === 'PAID') throw new RuntimeException('Debt not open.');
    $amount = min($amount, (float)$debt['remaining_amount']); $remaining = round((float)$debt['remaining_amount'] - $amount, 2); $status = $remaining <= 0 ? 'PAID' : 'PARTIAL';
    db()->prepare('INSERT INTO debt_payments (debt_id, amount, method, created_by) VALUES (?,?,"CASH",?)')->execute([$debtId,$amount,current_user()['id']]);
    db()->prepare('UPDATE debts SET paid_amount=paid_amount+?, remaining_amount=?, status=? WHERE id=?')->execute([$amount,$remaining,$status,$debtId]);
    db()->prepare('UPDATE customers SET balance=GREATEST(0,balance-?) WHERE id=?')->execute([$amount,$debt['customer_id']]);
    audit_log('DEBT_PAYMENT','debts',$debtId,['amount'=>$amount]); db()->commit(); json_response(['ok'=>true,'remaining'=>$remaining]);
} catch (Throwable $e) { db()->rollBack(); json_response(['ok'=>false,'message'=>$e->getMessage()],422); }

