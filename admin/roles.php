<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'roles';
$pageTitle = 'Roles & Permissions';
$pageDescription = 'Review grouped access controls for Admin, Manager, Cashier and Staff roles.';
require_permission('employees.manage');
$roles = db()->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$permissions = db()->query('SELECT p.*, rp.role_id FROM permissions p LEFT JOIN role_permissions rp ON rp.permission_id=p.id ORDER BY p.name')->fetchAll();
$groups = ['OPERATIONS' => [], 'COMMERCE' => [], 'INVENTORY' => [], 'BUSINESS' => [], 'SYSTEM' => []];
foreach ($permissions as $p) {
    if (str_starts_with($p['name'], 'customers')) {
        continue;
    }
    $bucket = str_starts_with($p['name'], 'pos') || str_starts_with($p['name'], 'sales') ? 'COMMERCE' :
        (str_starts_with($p['name'], 'inventory') || str_starts_with($p['name'], 'products') ? 'INVENTORY' :
        (str_starts_with($p['name'], 'debts') ? 'BUSINESS' :
        (str_starts_with($p['name'], 'reports') || str_starts_with($p['name'], 'expenses') ? 'BUSINESS' :
        (str_starts_with($p['name'], 'employees') || str_starts_with($p['name'], 'settings') ? 'SYSTEM' : 'OPERATIONS'))));
    $groups[$bucket][$p['id']] = ['name' => $p['name'], 'label' => $p['label']];
}
$rolePerms = [];
foreach (db()->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll() as $rp) $rolePerms[$rp['role_id']][$rp['permission_id']] = true;
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid xl:grid-cols-4 gap-4">
  <?php foreach ($roles as $role): ?>
    <div class="panel p-4">
      <h2 class="font-display text-lg font-bold uppercase text-white mb-4"><?= e($role['name']) ?></h2>
      <?php foreach ($groups as $group => $perms): ?>
        <div class="mb-4">
          <div class="label text-[10px] uppercase text-brand-crimson mb-2"><?= e($group) ?></div>
          <div class="space-y-2">
            <?php foreach ($perms as $id => $perm): ?>
              <label class="flex items-center gap-2 text-sm text-on-surface/75"><input type="checkbox" disabled <?= isset($rolePerms[$role['id']][$id]) ? 'checked' : '' ?>> <?= e($perm['label']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
