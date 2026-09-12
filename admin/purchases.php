<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav='purchases'; $pageTitle='Purchases'; require_permission('inventory.manage');
$rows=db()->query('SELECT p.*, s.name supplier FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.id DESC')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel p-4 mb-6"><div class="text-on-surface/70">Purchase completion is handled through inventory adjustments in this build; every stock increase is still ledgered. Use Stock Adjustment with type PURCHASE after entering supplier invoices.</div></section>
<section class="panel overflow-auto"><table class="table"><thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['invoice_number']) ?></td><td><?= e($r['supplier']) ?></td><td><?= e($r['purchase_date']) ?></td><td><?= money($r['total']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

