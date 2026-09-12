<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'inventory';
$pageTitle = 'Inventory';
$pageDescription = 'Track stock levels, inventory value, and low-stock risk.';
require_permission('inventory.manage');

$products = db()->query('SELECT p.*, c.name category FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.current_stock <= p.minimum_stock DESC, p.name')->fetchAll();
$totalItems = count($products);
$lowCount = 0;
$outCount = 0;
$value = 0.0;
foreach ($products as $p) {
    if ((int)$p['current_stock'] === 0) $outCount++;
    if ((int)$p['current_stock'] <= (int)$p['minimum_stock']) $lowCount++;
    $value += (float)$p['cost_price'] * (int)$p['current_stock'];
}
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid md:grid-cols-4 gap-4 mb-6">
  <?php foreach ([['Inventory Items', $totalItems, 'text-white'], ['Low Stock', $lowCount, 'low-stock'], ['Out Of Stock', $outCount, 'out-stock'], ['Inventory Value', money($value), 'text-brand-crimson']] as [$label, $metric, $class]): ?>
    <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45"><?= e($label) ?></div><div class="telemetry text-2xl font-bold <?= e($class) ?>"><?= e($metric) ?></div></div>
  <?php endforeach; ?>
</section>

<section class="panel p-4 mb-4">
  <div class="grid md:grid-cols-3 gap-3">
    <input class="input" data-page-search data-table-search="#inventory-table" placeholder="Search inventory...">
    <select class="input" data-table-filter="#inventory-table" data-filter-field="status"><option value="">All stock states</option><option>IN STOCK</option><option>LOW STOCK</option><option>OUT OF STOCK</option></select>
    <select class="input" data-table-filter="#inventory-table" data-filter-field="category"><option value="">All categories</option><?php foreach (array_unique(array_filter(array_column($products, 'category'))) as $cat): ?><option><?= e($cat) ?></option><?php endforeach; ?></select>
  </div>
</section>

<section class="panel overflow-auto">
  <table class="table" id="inventory-table">
    <thead><tr><th>Product</th><th>Category</th><th>Current</th><th>Minimum</th><th>Cost</th><th>Inventory Value</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <?php $state = (int)$p['current_stock'] === 0 ? 'OUT OF STOCK' : ((int)$p['current_stock'] <= (int)$p['minimum_stock'] ? 'LOW STOCK' : 'IN STOCK'); ?>
      <tr>
        <td class="font-semibold text-white" data-search><?= e($p['name']) ?> <?= e($p['sku']) ?></td>
        <td data-field="category"><?= e($p['category']) ?></td>
        <td><?= e($p['current_stock']) ?></td>
        <td><?= e($p['minimum_stock']) ?></td>
        <td><?= money($p['cost_price']) ?></td>
        <td><?= money((float)$p['cost_price'] * (int)$p['current_stock']) ?></td>
        <td data-field="status" data-value="<?= e($state) ?>"><span class="<?= $state === 'OUT OF STOCK' ? 'out-stock' : ($state === 'LOW STOCK' ? 'low-stock' : 'text-status-active') ?>"><?= e($state) ?></span></td>
        <td class="whitespace-nowrap">
          <button class="icon-btn" title="Adjust stock" data-adjust-product="<?= $p['id'] ?>" data-adjust-name="<?= e($p['name']) ?>" data-adjust-stock="<?= e($p['current_stock']) ?>"><span class="material-symbols-outlined text-base">sync_alt</span></button>
          <button class="icon-btn" title="View history" data-product-view="<?= $p['id'] ?>"><span class="material-symbols-outlined text-base">history</span></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="empty-state <?= $products ? 'hidden' : '' ?>" data-empty-for="inventory-table"><div><div class="font-display text-lg text-white uppercase">No inventory found</div><div class="mt-1">Add products to start tracking stock.</div></div></div>
</section>
<script>
document.addEventListener('click', async event => {
  const adjust = event.target.closest('[data-adjust-product]');
  if (adjust) openAdjustStock(adjust.dataset.adjustProduct, adjust.dataset.adjustName, adjust.dataset.adjustStock);
  const view = event.target.closest('[data-product-view]');
  if (!view) return;
  try {
    const data = await apiFetch(`<?= API_BASE ?>/products/detail.php?id=${view.dataset.productView}`);
    const p = data.product;
    const rows = data.movements.map(m => `<tr><td>${m.created_at}</td><td>${m.movement_type}</td><td>${m.quantity_change}</td><td>${m.previous_stock}</td><td>${m.new_stock}</td><td>${m.user_name || ''}</td></tr>`).join('');
    openDrawer({title: `${p.name} Stock History`, body: `<div class="panel p-3 mb-4"><div class="label text-on-surface/45">Current Stock</div><div class="telemetry text-2xl text-white">${p.current_stock}</div></div><table class="table"><thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Previous</th><th>New</th><th>User</th></tr></thead><tbody>${rows || '<tr><td colspan="6">No stock movements yet.</td></tr>'}</tbody></table>`});
  } catch (error) { toast(error.message, 'error'); }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
