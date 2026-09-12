<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'sales'; $pageTitle = 'Sales'; require_permission('sales.view');
$rows = db()->query('SELECT s.*, u.name cashier FROM sales s LEFT JOIN users u ON u.id=s.cashier_id ORDER BY s.id DESC LIMIT 250')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel overflow-auto"><table class="table"><thead><tr><th>Invoice</th><th>Date</th><th>Cashier</th><th>Gaming</th><th>Products</th><th>Total</th><th>Paid</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="text-white font-semibold"><button class="text-brand-crimson" data-sale-view='<?= e(json_encode($r)) ?>'><?= e($r['invoice_number']) ?></button></td><td><?= e($r['created_at']) ?></td><td><?= e($r['cashier']) ?></td><td><?= money($r['gaming_charge']) ?></td><td><?= money($r['product_subtotal']) ?></td><td><?= money($r['total']) ?></td><td><?= money($r['paid_amount']) ?></td><td><span class="status-badge <?= $r['status']==='PAID'?'status-available':'status-maintenance' ?>"><span class="status-dot"></span><?= e($r['status']) ?></span></td><td><a class="icon-btn" title="Receipt" href="<?= ADMIN_BASE ?>/receipt.php?id=<?= $r['id'] ?>"><span class="material-symbols-outlined text-base">print</span></a></td></tr><?php endforeach; ?>
</tbody></table></section>
<script>
document.addEventListener('click', event => {
  const button = event.target.closest('[data-sale-view]');
  if (!button) return;
  const sale = JSON.parse(button.dataset.saleView);
  openDrawer({title: sale.invoice_number, body: `<div class="grid grid-cols-2 gap-3 mb-4"><div class="panel p-3"><div class="label text-on-surface/45">Payment</div><div>${sale.payment_method} / ${sale.status}</div></div><div class="panel p-3"><div class="label text-on-surface/45">Cashier</div><div>${sale.cashier || '-'}</div></div><div class="panel p-3"><div class="label text-on-surface/45">Gaming</div><div>${sale.gaming_charge}</div></div><div class="panel p-3"><div class="label text-on-surface/45">Total</div><div class="telemetry text-xl text-brand-crimson">${sale.total}</div></div></div><a class="btn-primary px-4 py-2 rounded label uppercase inline-flex" href="<?= ADMIN_BASE ?>/receipt.php?id=${sale.id}">Print Receipt</a>`});
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
