<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'categories';
$pageTitle = 'Categories';
$pageDescription = 'Organize POS products into fast cashier-friendly groups.';
$pageAction = '<button class="btn-primary px-4 py-2 rounded label uppercase" data-category-add><span class="material-symbols-outlined text-base align-middle">add</span> Add Category</button>';
require_permission('products.manage');

$rows = db()->query(
    "SELECT c.*,
        COUNT(p.id) product_count,
        COALESCE(SUM(p.current_stock),0) total_stock,
        COALESCE(SUM(p.current_stock * p.cost_price),0) inventory_value
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.status, c.name"
)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel p-4 mb-4">
  <div class="grid md:grid-cols-2 gap-3">
    <input class="input" data-page-search data-table-search="#categories-table" placeholder="Search categories...">
    <select class="input" data-table-filter="#categories-table" data-filter-field="status">
      <option value="">All statuses</option>
      <option>ACTIVE</option>
      <option>INACTIVE</option>
    </select>
  </div>
</section>

<section class="panel overflow-auto">
  <table class="table" id="categories-table">
    <thead>
      <tr>
        <th>Category</th>
        <th>Products</th>
        <th>Total Stock</th>
        <th>Inventory Value</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="text-white font-semibold" data-search><?= e($row['name']) ?></td>
          <td><?= e($row['product_count']) ?></td>
          <td><?= e($row['total_stock']) ?></td>
          <td><?= money($row['inventory_value']) ?></td>
          <td data-field="status">
            <span class="status-badge <?= $row['status'] === 'ACTIVE' ? 'status-available' : 'status-offline' ?>">
              <span class="status-dot"></span><?= e($row['status']) ?>
            </span>
          </td>
          <td class="whitespace-nowrap">
            <button class="icon-btn" title="Edit" aria-label="Edit category" data-category-edit='<?= e(json_encode($row)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
            <?php if ($row['status'] === 'ACTIVE'): ?>
              <button class="icon-btn danger" title="Archive" aria-label="Archive category" data-category-archive="<?= e($row['id']) ?>" data-category-name="<?= e($row['name']) ?>"><span class="material-symbols-outlined text-base">archive</span></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="empty-state <?= $rows ? 'hidden' : '' ?>" data-empty-for="categories-table">
    <div>
      <div class="font-display text-lg text-white uppercase">No categories found</div>
      <div class="mt-1">Create categories like Drinks, Snacks, Food or Accessories.</div>
    </div>
  </div>
</section>

<script>
function categoryForm(category = {}) {
  return `<form id="category-form" class="space-y-3">
    <input type="hidden" name="id" value="${category.id || ''}">
    <label class="block text-sm text-on-surface/65">Category Name
      <input class="input mt-1" name="name" required value="${String(category.name || '').replaceAll('"', '&quot;')}">
      <div class="field-error" data-error-for="name"></div>
    </label>
    <label class="block text-sm text-on-surface/65">Status
      <select class="input mt-1" name="status">
        <option ${category.status === 'ACTIVE' ? 'selected' : ''}>ACTIVE</option>
        <option ${category.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option>
      </select>
    </label>
  </form>`;
}

function openCategoryModal(category = {}) {
  const root = openModal({
    title: category.id ? 'Edit Category' : 'Add Category',
    icon: 'category',
    subtitle: 'Keep POS groups simple so cashiers can find products quickly.',
    body: categoryForm(category),
    footer: '<button class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary" data-save-category>Save Category</button>'
  });
  root.querySelector('[data-save-category]').addEventListener('click', async event => {
    const button = event.currentTarget;
    setLoading(button, true);
    root.querySelectorAll('.field-error').forEach(node => node.textContent = '');
    try {
      const data = await apiFetch('<?= API_BASE ?>/products/categories-save.php', {method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(root.querySelector('#category-form')).entries()))});
      toast(data.message);
      setTimeout(() => location.reload(), 350);
    } catch (error) {
      setLoading(button, false);
      toast(error.message, 'error');
    }
  });
}

document.querySelector('[data-category-add]').addEventListener('click', () => openCategoryModal());
document.addEventListener('click', async event => {
  const edit = event.target.closest('[data-category-edit]');
  if (edit) {
    openCategoryModal(JSON.parse(edit.dataset.categoryEdit));
    return;
  }
  const archive = event.target.closest('[data-category-archive]');
  if (!archive) return;
  const ok = await confirmAction({
    title: 'Archive Category?',
    message: `Archive ${archive.dataset.categoryName}? Existing products keep their history, but the category is hidden from active selection.`,
    confirmText: 'Archive',
    severity: 'warning'
  });
  if (!ok) return;
  try {
    const data = await apiFetch('<?= API_BASE ?>/products/categories-archive.php', {method: 'POST', body: JSON.stringify({id: archive.dataset.categoryArchive})});
    toast(data.message);
    setTimeout(() => location.reload(), 350);
  } catch (error) {
    toast(error.message, 'error');
  }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
