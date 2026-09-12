<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'dashboard';
$pageTitle = 'Dashboard';
$pageDescription = 'Operational overview for today: sessions, sales, stock and outstanding balances.';
require_permission('dashboard.view');

$todayRevenue = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=CURDATE() AND status <> 'VOID'")->fetchColumn();
$activeSessionCount = (int)db()->query("SELECT COUNT(*) FROM gaming_sessions WHERE status IN ('ACTIVE','PAUSED')")->fetchColumn();
$availablePc = (int)db()->query("SELECT COUNT(*) FROM stations WHERE active=1 AND status='AVAILABLE'")->fetchColumn();
$playingPc = (int)db()->query("SELECT COUNT(*) FROM stations WHERE active=1 AND status='PLAYING'")->fetchColumn();
$availablePs = (int)db()->query("SELECT COUNT(*) FROM playstation_stations WHERE active=1 AND status='AVAILABLE'")->fetchColumn();
$todaySales = (int)db()->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE() AND status <> 'VOID'")->fetchColumn();
$debts = (float)db()->query("SELECT COALESCE(SUM(remaining_amount),0) FROM debts WHERE status <> 'PAID'")->fetchColumn();
$lowStock = (int)db()->query("SELECT COUNT(*) FROM products WHERE status='ACTIVE' AND current_stock <= minimum_stock")->fetchColumn();

$pcStatuses = [];
foreach (db()->query("SELECT status, COUNT(*) total FROM stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
    $pcStatuses[$row['status']] = (int)$row['total'];
}
$psStatuses = [];
foreach (db()->query("SELECT status, COUNT(*) total FROM playstation_stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
    $psStatuses[$row['status']] = (int)$row['total'];
}

$activeSessions = db()->query("
    SELECT gs.*, s.name pc_name, ps.name ps_name,
           TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) - gs.paused_seconds elapsed_seconds
    FROM gaming_sessions gs
    LEFT JOIN stations s ON s.id=gs.station_id
    LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id
    WHERE gs.status IN ('ACTIVE','PAUSED')
    ORDER BY gs.start_time
    LIMIT 8
")->fetchAll();
$recentSales = db()->query("SELECT s.*, u.name cashier FROM sales s LEFT JOIN users u ON u.id=s.cashier_id ORDER BY s.id DESC LIMIT 6")->fetchAll();
$alerts = db()->query("SELECT * FROM products WHERE status='ACTIVE' AND current_stock <= minimum_stock ORDER BY current_stock ASC LIMIT 6")->fetchAll();
$recentDebts = db()->query("SELECT d.*, c.name account_name FROM debts d JOIN customers c ON c.id=d.customer_id WHERE d.status <> 'PAID' ORDER BY d.id DESC LIMIT 5")->fetchAll();
$activity = db()->query("SELECT action, entity_type, created_at FROM audit_logs ORDER BY id DESC LIMIT 6")->fetchAll();

function dashboard_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid md:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
  <div class="panel p-4">
    <div class="label text-[10px] uppercase tracking-[.14em] text-on-surface/45">Today's Revenue</div>
    <div class="telemetry text-2xl font-bold text-brand-crimson mt-1"><?= money($todayRevenue) ?></div>
    <div class="text-xs text-on-surface/45 mt-1"><?= $todaySales ?> sales today</div>
  </div>
  <div class="panel p-4">
    <div class="label text-[10px] uppercase tracking-[.14em] text-on-surface/45">Active Sessions</div>
    <div class="telemetry text-2xl font-bold text-status-active mt-1"><?= $activeSessionCount ?></div>
    <div class="text-xs text-on-surface/45 mt-1"><?= $playingPc ?> PCs currently playing</div>
  </div>
  <div class="panel p-4">
    <div class="label text-[10px] uppercase tracking-[.14em] text-on-surface/45">Available Stations</div>
    <div class="telemetry text-2xl font-bold text-white mt-1"><?= $availablePc ?> PC / <?= $availablePs ?> PS</div>
    <div class="text-xs text-on-surface/45 mt-1">Ready for new sessions</div>
  </div>
  <div class="panel p-4">
    <div class="label text-[10px] uppercase tracking-[.14em] text-on-surface/45">Needs Attention</div>
    <div class="telemetry text-2xl font-bold text-status-maintenance mt-1"><?= $lowStock ?></div>
    <div class="text-xs text-on-surface/45 mt-1">Low stock products</div>
  </div>
</section>

<section class="panel p-3 mb-4">
  <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-2">
    <a class="btn-primary text-center py-3 rounded label uppercase" href="<?= ADMIN_BASE ?>/pos.php">Open POS</a>
    <a class="btn-secondary text-center py-3 rounded label uppercase" href="<?= ADMIN_BASE ?>/sessions.php?action=new">Start Session</a>
    <a class="btn-secondary text-center py-3 rounded label uppercase" href="<?= ADMIN_BASE ?>/inventory.php">Inventory</a>
    <a class="btn-secondary text-center py-3 rounded label uppercase" href="<?= ADMIN_BASE ?>/reports.php">Reports</a>
  </div>
</section>

<section class="grid xl:grid-cols-[1fr_360px] gap-4">
  <div class="space-y-4">
    <div class="panel overflow-auto">
      <div class="p-4 flex items-center justify-between border-b border-border-subtle">
        <h2 class="font-display text-lg font-bold uppercase text-white">Active Sessions</h2>
        <a class="btn-secondary" href="<?= ADMIN_BASE ?>/sessions.php">Manage</a>
      </div>
      <table class="table">
        <thead><tr><th>Station</th><th>Game</th><th>Elapsed</th><th>Charge</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($activeSessions as $session): ?>
            <tr>
              <td class="text-white font-semibold"><?= e($session['pc_name'] ?: $session['ps_name']) ?></td>
              <td><?= e($session['current_game'] ?: '-') ?></td>
              <td class="telemetry"><?= e(dashboard_duration((int)$session['elapsed_seconds'])) ?></td>
              <td class="telemetry"><?= money(active_session_charge($session)) ?></td>
              <td><span class="status-badge <?= $session['status'] === 'PAUSED' ? 'status-maintenance' : 'status-playing' ?>"><span class="status-dot"></span><?= e($session['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$activeSessions): ?>
        <div class="empty-state"><div><div class="font-display text-lg text-white uppercase">No Active Sessions</div><div class="mt-1">Start sessions from POS or the Sessions page.</div></div></div>
      <?php endif; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
      <div class="panel p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-display text-lg font-bold uppercase text-white">PC Status</h2>
          <a class="btn-secondary" href="<?= ADMIN_BASE ?>/stations.php">Open</a>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach (['AVAILABLE', 'PLAYING', 'RESERVED', 'MAINTENANCE'] as $status): ?>
            <div class="panel p-3 status-<?= strtolower($status) ?>">
              <div class="label text-[10px] uppercase status-text"><?= e($status) ?></div>
              <div class="telemetry text-xl text-white font-bold"><?= $pcStatuses[$status] ?? 0 ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-display text-lg font-bold uppercase text-white">PlayStation Status</h2>
          <a class="btn-secondary" href="<?= ADMIN_BASE ?>/playstation.php">Open</a>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach (['AVAILABLE', 'PLAYING', 'RESERVED', 'MAINTENANCE'] as $status): ?>
            <div class="panel p-3 status-<?= strtolower($status) ?>">
              <div class="label text-[10px] uppercase status-text"><?= e($status) ?></div>
              <div class="telemetry text-xl text-white font-bold"><?= $psStatuses[$status] ?? 0 ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <aside class="space-y-4">
    <div class="panel p-4">
      <h2 class="font-display text-lg font-bold uppercase text-white mb-3">Recent Sales</h2>
      <div class="space-y-2">
        <?php foreach ($recentSales as $sale): ?>
          <a class="flex justify-between gap-3 border-b border-border-subtle pb-2 text-sm" href="<?= ADMIN_BASE ?>/receipt.php?id=<?= (int)$sale['id'] ?>">
            <span class="truncate"><?= e($sale['invoice_number']) ?></span>
            <span class="telemetry text-brand-crimson"><?= money($sale['total']) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (!$recentSales): ?><div class="text-sm text-on-surface/45">No sales yet.</div><?php endif; ?>
      </div>
    </div>

    <div class="panel p-4">
      <h2 class="font-display text-lg font-bold uppercase text-white mb-3">Low Stock</h2>
      <div class="space-y-2">
        <?php foreach ($alerts as $product): ?>
          <div class="flex justify-between gap-3 text-sm">
            <span class="truncate"><?= e($product['name']) ?></span>
            <span class="<?= (int)$product['current_stock'] === 0 ? 'out-stock' : 'low-stock' ?>"><?= e($product['current_stock']) ?> left</span>
          </div>
        <?php endforeach; ?>
        <?php if (!$alerts): ?><div class="text-sm text-on-surface/45">Stock levels are healthy.</div><?php endif; ?>
      </div>
    </div>

    <div class="panel p-4">
      <h2 class="font-display text-lg font-bold uppercase text-white mb-3">Debts</h2>
      <div class="flex justify-between text-sm border-b border-border-subtle pb-2 mb-2">
        <span>Outstanding total</span>
        <span class="telemetry text-status-busy"><?= money($debts) ?></span>
      </div>
      <div class="space-y-2">
        <?php foreach ($recentDebts as $debt): ?>
          <div class="flex justify-between gap-3 text-sm">
            <span class="truncate"><?= e($debt['account_name']) ?></span>
            <span class="telemetry text-status-busy"><?= money($debt['remaining_amount']) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$recentDebts): ?><div class="text-sm text-on-surface/45">No open debts.</div><?php endif; ?>
      </div>
    </div>

    <div class="panel p-4">
      <h2 class="font-display text-lg font-bold uppercase text-white mb-3">Activity</h2>
      <div class="space-y-2">
        <?php foreach ($activity as $log): ?>
          <div class="text-xs border-b border-border-subtle pb-2">
            <div class="label text-on-surface/70"><?= e($log['action']) ?> <?= e($log['entity_type']) ?></div>
            <div class="telemetry text-on-surface/40 mt-1"><?= e(date('H:i:s', strtotime($log['created_at']))) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$activity): ?><div class="text-sm text-on-surface/45">No recent activity.</div><?php endif; ?>
      </div>
    </div>
  </aside>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
