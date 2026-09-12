<?php
$activeNav = $activeNav ?? '';
$user = current_user();
$navGroups = [
    'Operations' => [
        ['dashboard', 'Dashboard', 'dashboard', ADMIN_BASE . '/index.php'],
        ['pos', 'POS Register', 'point_of_sale', ADMIN_BASE . '/pos.php'],
        ['stations', 'Live Stations', 'desktop_windows', ADMIN_BASE . '/stations.php'],
        ['playstation', 'PlayStation', 'sports_esports', ADMIN_BASE . '/playstation.php'],
        ['pc-management', 'PC Management', 'computer', ADMIN_BASE . '/pc-management.php'],
        ['playstation-management', 'PS Management', 'videogame_asset', ADMIN_BASE . '/playstation-management.php'],
        ['zone-management', 'Zone Management', 'location_on', ADMIN_BASE . '/zone-management.php'],
        ['sessions', 'Sessions', 'timer', ADMIN_BASE . '/sessions.php'],
        ['reservations', 'Reservations', 'calendar_month', ADMIN_BASE . '/reservations.php'],
    ],
    'Inventory' => [
        ['products', 'Products', 'fastfood', ADMIN_BASE . '/products.php'],
        ['categories', 'Categories', 'category', ADMIN_BASE . '/categories.php'],
        ['inventory', 'Inventory', 'inventory_2', ADMIN_BASE . '/inventory.php'],
        ['purchases', 'Purchases', 'local_shipping', ADMIN_BASE . '/purchases.php'],
        ['stock', 'Stock Movements', 'sync_alt', ADMIN_BASE . '/stock-movements.php'],
        ['suppliers', 'Suppliers', 'warehouse', ADMIN_BASE . '/suppliers.php'],
    ],
    'Business' => [
        ['sales', 'Sales', 'receipt_long', ADMIN_BASE . '/sales.php'],
        ['debts', 'Debts', 'account_balance_wallet', ADMIN_BASE . '/debts.php'],
        ['expenses', 'Expenses', 'payments', ADMIN_BASE . '/expenses.php'],
        ['reports', 'Reports', 'analytics', ADMIN_BASE . '/reports.php'],
        ['website-content', 'Website Content', 'web', ADMIN_BASE . '/website-content.php'],
    ],
    'System' => [
        ['employees', 'Employees', 'badge', ADMIN_BASE . '/employees.php'],
        ['roles', 'Roles & Permissions', 'admin_panel_settings', ADMIN_BASE . '/roles.php'],
        ['settings', 'Settings', 'settings', ADMIN_BASE . '/settings.php'],
    ],
];
?>
<aside class="admin-sidebar">
  <div class="flex flex-col gap-4 min-h-0">
    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
      <a href="<?= ADMIN_BASE ?>/index.php" class="sidebar-logo flex items-center gap-2">
        <div class="w-8 h-8 rounded bg-surface-card border border-brand-crimson/50 flex items-center justify-center text-brand-crimson">
          <span class="material-symbols-outlined">sports_esports</span>
        </div>
        <div class="sidebar-text">
          <div class="font-display text-lg uppercase tracking-widest text-white leading-none">NEXUS OS</div>
          <div class="sidebar-subtitle label text-[10px] text-on-surface/40 mt-1">FLOOR MASTER</div>
        </div>
      </a>
      <div class="flex items-center gap-1 bg-surface-elevated px-2 py-0.5 rounded border border-border-subtle">
        <span class="w-1.5 h-1.5 rounded-full bg-status-active animate-ping"></span>
        <span class="label text-[10px] text-status-active uppercase">Live</span>
      </div>
    </div>
    <a href="<?= ADMIN_BASE ?>/sessions.php?action=new" class="btn-primary text-center py-2 rounded label uppercase" title="New Walk-In Session"><span class="material-symbols-outlined align-middle text-base">add</span> <span class="sidebar-action-text">New Walk-In Session</span></a>
    <nav class="overflow-y-auto pr-1 flex-1 space-y-4">
      <?php foreach ($navGroups as $group => $items): ?>
        <?php $groupActive = in_array($activeNav, array_column($items, 0), true); ?>
        <div class="sidebar-group" data-sidebar-group="<?= e(strtolower($group)) ?>" data-active="<?= $groupActive ? '1' : '0' ?>">
          <button type="button" class="sidebar-group-toggle w-full flex items-center justify-between label text-[10px] uppercase tracking-[.16em] text-on-surface/35 mb-1 hover:text-on-surface/70" data-sidebar-group-toggle>
            <span class="sidebar-group-title"><?= e($group) ?></span>
            <span class="chevron material-symbols-outlined text-sm sidebar-group-title">expand_more</span>
          </button>
          <div class="sidebar-group-items"><div class="space-y-1">
            <?php foreach ($items as [$key, $label, $icon, $href]): $isActive = $activeNav === $key; ?>
              <a class="sidebar-link flex items-center gap-2 px-3 py-2 rounded <?= $isActive ? 'bg-surface-elevated text-brand-crimson border-l-2 border-brand-crimson' : 'text-on-surface/60 hover:text-white hover:bg-surface-card' ?>" href="<?= e($href) ?>" data-tooltip="<?= e($label) ?>">
                <span class="material-symbols-outlined text-[20px]"><?= e($icon) ?></span>
                <span class="sidebar-text label text-[11px] tracking-wide"><?= e($label) ?></span>
              </a>
            <?php endforeach; ?>
          </div></div>
        </div>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="border-t border-border-subtle pt-3 mt-4">
    <div class="sidebar-user-card panel p-3 flex items-center justify-between">
      <div>
        <div class="label text-white"><?= e($user['name'] ?? 'Operator') ?></div>
        <div class="text-xs text-on-surface/45"><?= e($user['role'] ?? 'Staff') ?></div>
      </div>
      <div class="text-right">
        <div class="label text-[10px] text-on-surface/45">Drawer</div>
        <div class="telemetry text-status-active font-bold"><?= money(0) ?></div>
      </div>
    </div>
  </div>
</aside>
