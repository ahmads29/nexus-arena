<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'products';
$pageTitle = 'Products';
$pageDescription = 'Manage food, drinks, accessories, pricing and stock thresholds.';
$pageAction = '<button class="btn-primary px-4 py-2 rounded label uppercase" data-product-add><span class="material-symbols-outlined text-base align-middle">add</span> Add Product</button>';
require_permission('products.manage');

$products = db()->query('SELECT p.*, c.name category, s.name supplier FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.status, p.name')->fetchAll();
$categories = db()->query("SELECT * FROM categories WHERE status='ACTIVE' ORDER BY name")->fetchAll();
$suppliers = db()->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel p-4 mb-4">
  <div class="grid md:grid-cols-4 gap-3">
    <input class="input" data-page-search data-table-search="#products-table" placeholder="Search products, SKU, barcode...">
    <select class="input" data-table-filter="#products-table" data-filter-field="category">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?><option><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select class="input" data-table-filter="#products-table" data-filter-field="stock">
      <option value="">All stock states</option><option>IN STOCK</option><option>LOW STOCK</option><option>OUT OF STOCK</option>
    </select>
    <select class="input" data-table-filter="#products-table" data-filter-field="status">
      <option value="">All statuses</option><option>ACTIVE</option><option>INACTIVE</option>
    </select>
  </div>
</section>

<section class="panel overflow-auto">
  <table class="table" id="products-table">
    <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Cost</th><th>Stock</th><th>Min</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <?php $stockState = (int)$p['current_stock'] === 0 ? 'OUT OF STOCK' : ((int)$p['current_stock'] <= (int)$p['minimum_stock'] ? 'LOW STOCK' : 'IN STOCK'); ?>
      <tr>
        <td class="font-semibold text-white" data-search><?= e($p['name']) ?> <?= e($p['barcode']) ?></td>
        <td data-search><?= e($p['sku']) ?></td>
        <td data-field="category"><?= e($p['category']) ?></td>
        <td><?= money($p['selling_price']) ?></td>
        <td><?= money($p['cost_price']) ?></td>
        <td data-field="stock" data-value="<?= e($stockState) ?>" class="<?= $stockState === 'OUT OF STOCK' ? 'out-stock' : ($stockState === 'LOW STOCK' ? 'low-stock' : '') ?>"><?= e($p['current_stock']) ?></td>
        <td><?= e($p['minimum_stock']) ?></td>
        <td data-field="status"><span class="status-badge <?= $p['status'] === 'ACTIVE' ? 'status-available' : 'status-offline' ?>"><span class="status-dot"></span><?= e($p['status']) ?></span></td>
        <td class="whitespace-nowrap">
          <button class="icon-btn" title="View" aria-label="View product" data-product-view="<?= $p['id'] ?>"><span class="material-symbols-outlined text-base">visibility</span></button>
          <button class="icon-btn" title="Edit" aria-label="Edit product" data-product-edit='<?= e(json_encode($p)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
          <button class="icon-btn" title="Adjust stock" aria-label="Adjust stock" data-adjust-product="<?= $p['id'] ?>" data-adjust-name="<?= e($p['name']) ?>" data-adjust-stock="<?= e($p['current_stock']) ?>"><span class="material-symbols-outlined text-base">sync_alt</span></button>
          <button class="icon-btn danger" title="Archive" aria-label="Archive product" data-product-archive="<?= $p['id'] ?>" data-product-name="<?= e($p['name']) ?>"><span class="material-symbols-outlined text-base">archive</span></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="empty-state <?= $products ? 'hidden' : '' ?>" data-empty-for="products-table">
    <div><div class="font-display text-lg text-white uppercase">No products found</div><div class="mt-1">You have not added any products yet.</div></div>
  </div>
</section>

<script>
const categoriesHtml = `<?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>`;
const suppliersHtml = `<?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>`;
function escapeAttr(value) {
  return String(value ?? '').replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;');
}
function productForm(product = {}) {
  return `<form id="product-form" class="space-y-3">
    <input type="hidden" name="id" value="${escapeAttr(product.id || '')}">
    <label class="block text-sm text-on-surface/65">Product Name<input class="input mt-1" name="name" required value="${escapeAttr(product.name || '')}"><div class="field-error" data-error-for="name"></div></label>
    <div class="grid md:grid-cols-2 gap-2"><label class="block text-sm text-on-surface/65">SKU<input class="input mt-1" name="sku" value="${escapeAttr(product.sku || '')}"></label><label class="block text-sm text-on-surface/65">Barcode<input class="input mt-1" name="barcode" value="${escapeAttr(product.barcode || '')}"></label></div>
    <div class="grid md:grid-cols-2 gap-2"><label class="block text-sm text-on-surface/65">Category<select class="input mt-1" name="category_id"><option value="">Category</option>${categoriesHtml}</select></label><label class="block text-sm text-on-surface/65">Supplier<select class="input mt-1" name="supplier_id"><option value="">Supplier</option>${suppliersHtml}</select></label></div>
    <div class="grid md:grid-cols-4 gap-2"><label class="block text-sm text-on-surface/65">Cost<input class="input mt-1" name="cost_price" type="number" step="0.01" min="0" value="${escapeAttr(product.cost_price || '0')}"><div class="field-error" data-error-for="cost_price"></div></label><label class="block text-sm text-on-surface/65">Price<input class="input mt-1" name="selling_price" type="number" step="0.01" min="0" value="${escapeAttr(product.selling_price || '0')}"><div class="field-error" data-error-for="selling_price"></div></label><label class="block text-sm text-on-surface/65">Min Stock<input class="input mt-1" name="minimum_stock" type="number" min="0" value="${escapeAttr(product.minimum_stock || '0')}"><div class="field-error" data-error-for="minimum_stock"></div></label><label class="block text-sm text-on-surface/65 ${product.id ? 'hidden' : ''}">Initial<input class="input mt-1" name="current_stock" type="number" min="0" value="${escapeAttr(product.current_stock || '0')}"><div class="field-error" data-error-for="current_stock"></div></label></div>
    <label class="block text-sm text-on-surface/65">Status<select class="input mt-1" name="status"><option>ACTIVE</option><option ${product.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option></select></label>
    <label class="block text-sm text-on-surface/65">Description<textarea class="input h-24 py-2 mt-1" name="description">${escapeAttr(product.description || '')}</textarea></label>
  </form>`;
}
function openProductModal(product = {}) {
  const root = openModal({title: product.id ? 'Edit Product' : 'Add Product', icon:'fastfood', subtitle:'Set POS pricing, supplier details and stock thresholds.', size:'lg', body: productForm(product), footer:'<button class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary" data-save-product>Save Product</button>'});
  if (product.category_id) root.querySelector('[name="category_id"]').value = product.category_id;
  if (product.supplier_id) root.querySelector('[name="supplier_id"]').value = product.supplier_id;
  root.querySelector('[data-save-product]').addEventListener('click', async event => {
    const button = event.currentTarget; setLoading(button, true);
    root.querySelectorAll('.field-error').forEach(node => node.textContent = '');
    try {
      const data = await apiFetch('<?= API_BASE ?>/products/save.php', {method:'POST', body: JSON.stringify(Object.fromEntries(new FormData(root.querySelector('#product-form')).entries()))});
      toast(data.message); location.reload();
    } catch (error) {
      setLoading(button, false);
      toast(error.message, 'error');
    }
  });
}
document.querySelector('[data-product-add]').addEventListener('click', () => openProductModal());
document.addEventListener('click', async event => {
  const edit = event.target.closest('[data-product-edit]');
  if (edit) openProductModal(JSON.parse(edit.dataset.productEdit));
  const archive = event.target.closest('[data-product-archive]');
  if (archive && await confirmAction({title:'Archive Product?', message:`Archive ${archive.dataset.productName}? It will no longer appear in POS.`, confirmText:'Archive', severity:'danger'})) {
    try { const data = await apiFetch('<?= API_BASE ?>/products/archive.php', {method:'POST', body:JSON.stringify({id: archive.dataset.productArchive})}); toast(data.message); location.reload(); } catch(e) { toast(e.message, 'error'); }
  }
  const view = event.target.closest('[data-product-view]');
  if (view) {
    try {
      const data = await apiFetch(`<?= API_BASE ?>/products/detail.php?id=${view.dataset.productView}`);
      const p = data.product;
      const moves = data.movements.map(m => `<tr><td>${m.created_at}</td><td>${m.movement_type}</td><td>${m.quantity_change}</td><td>${m.previous_stock}</td><td>${m.new_stock}</td><td>${m.user_name || ''}</td></tr>`).join('');
      openDrawer({title:p.name, body:`<div class="grid grid-cols-2 gap-3 mb-4"><div class="panel p-3"><div class="label text-on-surface/45">Current Stock</div><div class="telemetry text-2xl">${p.current_stock}</div></div><div class="panel p-3"><div class="label text-on-surface/45">Price</div><div class="telemetry text-2xl">${p.selling_price}</div></div></div><table class="table"><thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Prev</th><th>New</th><th>User</th></tr></thead><tbody>${moves || '<tr><td colspan="6">No stock history.</td></tr>'}</tbody></table>`});
    } catch(e) { toast(e.message, 'error'); }
  }
  const adjust = event.target.closest('[data-adjust-product]');
  if (adjust) window.openAdjustStock?.(adjust.dataset.adjustProduct, adjust.dataset.adjustName, adjust.dataset.adjustStock);
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
