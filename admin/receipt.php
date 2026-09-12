<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT s.*, u.name cashier FROM sales s LEFT JOIN users u ON u.id=s.cashier_id WHERE s.id=?');
$stmt->execute([$id]); $sale = $stmt->fetch();
if (!$sale) { http_response_code(404); exit('Sale not found'); }
$items = db()->prepare('SELECT * FROM sale_items WHERE sale_id=?');
$items->execute([$id]); $items = $items->fetchAll();
$printEnabled = setting('enable_invoice_printing', '1') === '1';
$autoPrint = $printEnabled && ($_GET['print'] ?? '') === '1';
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt <?= e($sale['invoice_number']) ?></title><style>
body{font-family:monospace;width:280px;margin:20px auto;color:#111}.center{text-align:center}.line{border-top:1px dashed #111;margin:8px 0}.row{display:flex;justify-content:space-between;gap:8px}button{width:100%;padding:8px;margin-top:12px}@media print{button{display:none}}
</style></head><body<?= $autoPrint ? ' onload="setTimeout(function(){ print(); }, 250)"' : '' ?>>
<div class="center"><h2><?= e(setting('business_name','Gaming Lounge')) ?></h2><div><?= e(setting('phone','')) ?></div><div><?= e(setting('address','')) ?></div></div>
<div class="line"></div><div>Invoice: <?= e($sale['invoice_number']) ?></div><div>Date: <?= e($sale['created_at']) ?></div><div>Cashier: <?= e($sale['cashier']) ?></div>
<div class="line"></div><?php if ((float)$sale['gaming_charge'] > 0): ?><div class="row"><span>Gaming Session</span><span><?= money($sale['gaming_charge']) ?></span></div><?php endif; ?>
<?php foreach ($items as $item): ?><div class="row"><span><?= e($item['quantity']) ?>x <?= e($item['product_name']) ?></span><span><?= money($item['total']) ?></span></div><?php endforeach; ?>
<div class="line"></div><div class="row"><span>Subtotal</span><span><?= money($sale['subtotal']) ?></span></div><div class="row"><span>Discount</span><span><?= money($sale['discount']) ?></span></div><div class="row"><span>Tax</span><span><?= money($sale['tax']) ?></span></div><div class="row"><strong>Total</strong><strong><?= money($sale['total']) ?></strong></div><div class="row"><span>Paid</span><span><?= money($sale['paid_amount']) ?> <?= e($sale['payment_method']) ?></span></div>
<div class="line"></div><div class="center">Thank you. Game on.</div><?php if ($printEnabled): ?><button onclick="print()">Print</button><?php endif; ?></body></html>
