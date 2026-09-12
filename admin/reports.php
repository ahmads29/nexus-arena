<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'reports';
$pageTitle = 'Reports';
$pageDescription = 'Complete business reporting for revenue, POS, sessions, inventory, debts, expenses and operations.';
require_permission('reports.view');

function valid_date_or(string $value, string $fallback): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
}

$today = date('Y-m-d');
$defaultFrom = date('Y-m-01');
$dateFrom = valid_date_or((string)($_GET['from'] ?? $defaultFrom), $defaultFrom);
$dateTo = valid_date_or((string)($_GET['to'] ?? $today), $today);
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
$fromDateTime = $dateFrom . ' 00:00:00';
$toDateTime = $dateTo . ' 23:59:59';

function scalar_report(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function rows_report(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$rangeParams = [$fromDateTime, $toDateTime];
$dateParams = [$dateFrom, $dateTo];

$salesTotal = (float)scalar_report("SELECT COALESCE(SUM(total),0) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$paidTotal = (float)scalar_report("SELECT COALESCE(SUM(paid_amount),0) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$productRevenue = (float)scalar_report("SELECT COALESCE(SUM(product_subtotal),0) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$gamingRevenue = (float)scalar_report("SELECT COALESCE(SUM(gaming_charge),0) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$discounts = (float)scalar_report("SELECT COALESCE(SUM(discount),0) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$tax = (float)scalar_report("SELECT COALESCE(SUM(tax),0) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$expenseTotal = (float)scalar_report("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?", $dateParams);
$debtCreated = (float)scalar_report("SELECT COALESCE(SUM(original_amount),0) FROM debts WHERE created_at BETWEEN ? AND ?", $rangeParams);
$debtPaid = (float)scalar_report("SELECT COALESCE(SUM(amount),0) FROM debt_payments WHERE created_at BETWEEN ? AND ?", $rangeParams);
$outstandingDebt = (float)scalar_report("SELECT COALESCE(SUM(remaining_amount),0) FROM debts WHERE status <> 'PAID'");
$grossProfit = (float)scalar_report(
    "SELECT COALESCE(SUM(si.total - (p.cost_price * si.quantity)),0)
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID'",
    $rangeParams
);
$profitEstimate = $grossProfit + $gamingRevenue - $expenseTotal;
$salesCount = (int)scalar_report("SELECT COUNT(*) FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID'", $rangeParams);
$sessionsCount = (int)scalar_report("SELECT COUNT(*) FROM gaming_sessions WHERE start_time BETWEEN ? AND ?", $rangeParams);
$activeSessions = (int)scalar_report("SELECT COUNT(*) FROM gaming_sessions WHERE status IN ('ACTIVE','PAUSED')");
$inventoryValue = (float)scalar_report("SELECT COALESCE(SUM(cost_price * current_stock),0) FROM products WHERE status='ACTIVE'");
$lowStock = (int)scalar_report("SELECT COUNT(*) FROM products WHERE status='ACTIVE' AND current_stock <= minimum_stock");
$outStock = (int)scalar_report("SELECT COUNT(*) FROM products WHERE status='ACTIVE' AND current_stock = 0");

$dailySales = rows_report("SELECT DATE(created_at) report_date, COUNT(*) sales_count, COALESCE(SUM(total),0) total, COALESCE(SUM(gaming_charge),0) gaming, COALESCE(SUM(product_subtotal),0) products FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID' GROUP BY DATE(created_at) ORDER BY report_date DESC", $rangeParams);
$paymentMethods = rows_report("SELECT payment_method, COUNT(*) count, COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID' GROUP BY payment_method ORDER BY total DESC", $rangeParams);
$cashiers = rows_report("SELECT COALESCE(u.name,'Unknown') cashier, COUNT(s.id) sales_count, COALESCE(SUM(s.total),0) total FROM sales s LEFT JOIN users u ON u.id=s.cashier_id WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID' GROUP BY cashier ORDER BY total DESC", $rangeParams);
$topProducts = rows_report("SELECT si.product_name, SUM(si.quantity) qty, SUM(si.total) total FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID' GROUP BY si.product_name ORDER BY qty DESC LIMIT 20", $rangeParams);
$categorySales = rows_report("SELECT COALESCE(c.name,'Uncategorized') category, SUM(si.quantity) qty, SUM(si.total) total FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID' GROUP BY category ORDER BY total DESC", $rangeParams);
$lowStockRows = rows_report("SELECT p.name, p.sku, c.name category, p.current_stock, p.minimum_stock, p.cost_price, (p.cost_price*p.current_stock) value FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='ACTIVE' AND p.current_stock <= p.minimum_stock ORDER BY p.current_stock ASC, p.name LIMIT 50");
$stockMoves = rows_report("SELECT sm.created_at, p.name product, sm.movement_type, sm.quantity_change, sm.previous_stock, sm.new_stock, sm.reason, u.name user_name FROM stock_movements sm JOIN products p ON p.id=sm.product_id LEFT JOIN users u ON u.id=sm.created_by WHERE sm.created_at BETWEEN ? AND ? ORDER BY sm.id DESC LIMIT 100", $rangeParams);
$sessionSummary = rows_report("SELECT gs.session_code, COALESCE(s.name, ps.name) station, CASE WHEN gs.station_id IS NULL THEN ps.console_type ELSE 'PC' END station_type, gs.current_game, gs.start_time, gs.end_time, gs.gaming_charge, gs.product_charge, gs.total, gs.status FROM gaming_sessions gs LEFT JOIN stations s ON s.id=gs.station_id LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id WHERE gs.start_time BETWEEN ? AND ? ORDER BY gs.start_time DESC LIMIT 100", $rangeParams);
$stationUtilization = rows_report("SELECT COALESCE(s.name, ps.name) station, CASE WHEN gs.station_id IS NULL THEN ps.console_type ELSE 'PC' END station_type, COUNT(gs.id) sessions, ROUND(COALESCE(SUM(TIMESTAMPDIFF(SECOND, gs.start_time, COALESCE(gs.end_time,NOW())) - gs.paused_seconds),0)/3600,2) hours, COALESCE(SUM(gs.gaming_charge),0) revenue FROM gaming_sessions gs LEFT JOIN stations s ON s.id=gs.station_id LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id WHERE gs.start_time BETWEEN ? AND ? GROUP BY station, station_type ORDER BY hours DESC LIMIT 30", $rangeParams);
$popularGames = rows_report("SELECT COALESCE(NULLIF(current_game,''),'Unknown') game, COUNT(*) sessions, COALESCE(SUM(gaming_charge),0) revenue FROM gaming_sessions WHERE start_time BETWEEN ? AND ? GROUP BY game ORDER BY sessions DESC LIMIT 20", $rangeParams);
$debts = rows_report("SELECT c.name account_name, d.original_amount, d.paid_amount, d.remaining_amount, d.status, d.created_at FROM debts d JOIN customers c ON c.id=d.customer_id WHERE d.created_at BETWEEN ? AND ? OR d.status <> 'PAID' ORDER BY d.remaining_amount DESC LIMIT 100", $rangeParams);
$debtPayments = rows_report("SELECT dp.created_at, c.name account_name, dp.amount, dp.method, u.name user_name FROM debt_payments dp JOIN debts d ON d.id=dp.debt_id JOIN customers c ON c.id=d.customer_id LEFT JOIN users u ON u.id=dp.created_by WHERE dp.created_at BETWEEN ? AND ? ORDER BY dp.id DESC LIMIT 100", $rangeParams);
$expenses = rows_report("SELECT e.expense_date, e.category, e.amount, e.description, u.name user_name FROM expenses e LEFT JOIN users u ON u.id=e.user_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC, e.id DESC LIMIT 100", $dateParams);
$reservations = rows_report("SELECT r.reservation_date, r.start_time, r.duration_minutes, r.station_kind, COALESCE(s.name, ps.name) station, r.status FROM reservations r LEFT JOIN stations s ON s.id=r.station_id LEFT JOIN playstation_stations ps ON ps.id=r.playstation_station_id WHERE r.reservation_date BETWEEN ? AND ? ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 100", $dateParams);
$audit = rows_report("SELECT al.created_at, u.name user_name, al.action, al.entity_type, al.entity_id, al.ip_address FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.created_at BETWEEN ? AND ? ORDER BY al.id DESC LIMIT 100", $rangeParams);

function export_url(string $type, string $from, string $to): string
{
    return API_BASE . '/reports/export.php?type=' . urlencode($type) . '&from=' . urlencode($from) . '&to=' . urlencode($to);
}

function render_rows(array $rows, array $columns, ?string $empty = null): void
{
    if (!$rows) {
        echo '<tr><td colspan="' . count($columns) . '" class="text-center text-on-surface/45 py-8">' . e($empty ?? 'No records found for this range.') . '</td></tr>';
        return;
    }
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $key => $label) {
            $value = $row[$key] ?? '';
            echo '<td>' . e($value) . '</td>';
        }
        echo '</tr>';
    }
}

require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel p-4 mb-6">
  <form class="grid md:grid-cols-[1fr_1fr_auto_auto] gap-3 items-end" method="get">
    <label class="text-sm text-on-surface/60">From
      <input class="input mt-1" type="date" name="from" value="<?= e($dateFrom) ?>">
    </label>
    <label class="text-sm text-on-surface/60">To
      <input class="input mt-1" type="date" name="to" value="<?= e($dateTo) ?>">
    </label>
    <button class="btn-primary px-5 py-3 rounded label uppercase">Run Reports</button>
    <button type="button" class="btn-secondary px-5 py-3 rounded label uppercase" onclick="window.print()">Print</button>
  </form>
</section>

<section class="grid md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
  <?php foreach ([
    ['Revenue', money($salesTotal), 'text-brand-crimson'],
    ['Paid Collected', money($paidTotal), 'text-status-active'],
    ['Gross Profit', money($grossProfit), 'text-status-active'],
    ['Expenses', money($expenseTotal), 'text-status-maintenance'],
    ['Profit Estimate', money($profitEstimate), $profitEstimate >= 0 ? 'text-status-active' : 'text-status-busy'],
    ['Gaming Revenue', money($gamingRevenue), 'text-brand-crimson'],
    ['Product Revenue', money($productRevenue), 'text-brand-crimson'],
    ['Outstanding Debt', money($outstandingDebt), 'text-status-busy'],
    ['Inventory Value', money($inventoryValue), 'text-secondary'],
    ['Low / Out Stock', $lowStock . ' / ' . $outStock, 'text-status-maintenance'],
    ['Sales Count', $salesCount, 'text-white'],
    ['Sessions', $sessionsCount, 'text-white'],
    ['Active Sessions', $activeSessions, 'text-status-active'],
    ['Discounts / Tax', money($discounts) . ' / ' . money($tax), 'text-on-surface'],
  ] as [$label, $value, $class]): ?>
    <div class="panel surface-edge p-4">
      <div class="label text-[10px] uppercase tracking-[.14em] text-on-surface/45"><?= e($label) ?></div>
      <div class="telemetry text-xl font-bold <?= e($class) ?> mt-1"><?= e($value) ?></div>
    </div>
  <?php endforeach; ?>
</section>

<section class="grid xl:grid-cols-2 gap-6 mb-6">
  <div class="panel chart-card surface-edge">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display text-lg font-bold uppercase text-white">Revenue Trend</h2>
        <p class="text-xs text-on-surface/45">Daily total, gaming and product revenue</p>
      </div>
      <span class="status-badge status-available"><span class="status-dot"></span>Live SQL</span>
    </div>
    <div class="chart-wrap"><canvas id="revenue-chart"></canvas></div>
    <div class="chart-legend"><span><i style="background:#FF1E2D"></i>Total</span><span><i style="background:#8B5CF6"></i>Gaming</span><span><i style="background:#10B981"></i>Products</span></div>
  </div>
  <div class="panel chart-card surface-edge">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display text-lg font-bold uppercase text-white">Payment Mix</h2>
        <p class="text-xs text-on-surface/45">Cash, card, debt and other payment totals</p>
      </div>
    </div>
    <div class="chart-wrap"><canvas id="payment-chart"></canvas></div>
    <div class="chart-legend"><span><i style="background:#10B981"></i>Cash</span><span><i style="background:#8B5CF6"></i>Card</span><span><i style="background:#F59E0B"></i>Debt</span><span><i style="background:#64748B"></i>Other</span></div>
  </div>
  <div class="panel chart-card surface-edge">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display text-lg font-bold uppercase text-white">Top Products</h2>
        <p class="text-xs text-on-surface/45">Best-selling POS items by quantity</p>
      </div>
    </div>
    <div class="chart-wrap"><canvas id="products-chart"></canvas></div>
  </div>
  <div class="panel chart-card surface-edge">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display text-lg font-bold uppercase text-white">Station Utilization</h2>
        <p class="text-xs text-on-surface/45">Most used PCs and PlayStation stations by hours</p>
      </div>
    </div>
    <div class="chart-wrap"><canvas id="station-chart"></canvas></div>
  </div>
  <div class="panel chart-card surface-edge">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display text-lg font-bold uppercase text-white">Inventory Risk</h2>
        <p class="text-xs text-on-surface/45">Low-stock products compared to minimum levels</p>
      </div>
    </div>
    <div class="chart-wrap"><canvas id="inventory-chart"></canvas></div>
  </div>
  <div class="panel chart-card surface-edge">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display text-lg font-bold uppercase text-white">Business Balance</h2>
        <p class="text-xs text-on-surface/45">Revenue, profit estimate, expenses and debt</p>
      </div>
    </div>
    <div class="chart-wrap"><canvas id="balance-chart"></canvas></div>
  </div>
</section>

<section class="flex flex-wrap gap-2 mb-4" data-report-tabs>
  <?php foreach (['Overview','Sales','POS','Sessions','Stations','Inventory','Debts','Expenses','Reservations','Audit'] as $i => $tab): ?>
    <button class="filter-chip <?= $i === 0 ? 'active' : '' ?>" data-report-tab="<?= e($tab) ?>"><?= e($tab) ?></button>
  <?php endforeach; ?>
</section>

<div data-report-panel="Overview" class="space-y-6">
  <section class="grid xl:grid-cols-2 gap-6">
    <div class="panel overflow-auto">
      <div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Daily Sales</h2><a class="btn-secondary" href="<?= e(export_url('daily-sales', $dateFrom, $dateTo)) ?>">CSV</a></div>
      <table class="table"><thead><tr><th>Date</th><th>Sales</th><th>Gaming</th><th>Products</th><th>Total</th></tr></thead><tbody><?php render_rows($dailySales, ['report_date'=>'Date','sales_count'=>'Sales','gaming'=>'Gaming','products'=>'Products','total'=>'Total']); ?></tbody></table>
    </div>
    <div class="panel overflow-auto">
      <div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Payment Methods</h2><a class="btn-secondary" href="<?= e(export_url('payment-methods', $dateFrom, $dateTo)) ?>">CSV</a></div>
      <table class="table"><thead><tr><th>Method</th><th>Count</th><th>Total</th><th>Paid</th></tr></thead><tbody><?php render_rows($paymentMethods, ['payment_method'=>'Method','count'=>'Count','total'=>'Total','paid'=>'Paid']); ?></tbody></table>
    </div>
  </section>
</div>

<div data-report-panel="Sales" class="hidden space-y-6">
  <section class="grid xl:grid-cols-2 gap-6">
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Cashier Performance</h2><a class="btn-secondary" href="<?= e(export_url('cashiers', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Cashier</th><th>Sales</th><th>Total</th></tr></thead><tbody><?php render_rows($cashiers, ['cashier'=>'Cashier','sales_count'=>'Sales','total'=>'Total']); ?></tbody></table></div>
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Category Sales</h2><a class="btn-secondary" href="<?= e(export_url('category-sales', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Category</th><th>Qty</th><th>Total</th></tr></thead><tbody><?php render_rows($categorySales, ['category'=>'Category','qty'=>'Qty','total'=>'Total']); ?></tbody></table></div>
  </section>
</div>

<div data-report-panel="POS" class="hidden">
  <section class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Top Products</h2><a class="btn-secondary" href="<?= e(export_url('top-products', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Product</th><th>Quantity</th><th>Total</th></tr></thead><tbody><?php render_rows($topProducts, ['product_name'=>'Product','qty'=>'Quantity','total'=>'Total']); ?></tbody></table></section>
</div>

<div data-report-panel="Sessions" class="hidden">
  <section class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Gaming Sessions</h2><a class="btn-secondary" href="<?= e(export_url('sessions', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Code</th><th>Station</th><th>Type</th><th>Game</th><th>Start</th><th>End</th><th>Gaming</th><th>Products</th><th>Total</th><th>Status</th></tr></thead><tbody><?php render_rows($sessionSummary, ['session_code'=>'Code','station'=>'Station','station_type'=>'Type','current_game'=>'Game','start_time'=>'Start','end_time'=>'End','gaming_charge'=>'Gaming','product_charge'=>'Products','total'=>'Total','status'=>'Status']); ?></tbody></table></section>
</div>

<div data-report-panel="Stations" class="hidden space-y-6">
  <section class="grid xl:grid-cols-2 gap-6">
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Station Utilization</h2><a class="btn-secondary" href="<?= e(export_url('station-utilization', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Station</th><th>Type</th><th>Sessions</th><th>Hours</th><th>Revenue</th></tr></thead><tbody><?php render_rows($stationUtilization, ['station'=>'Station','station_type'=>'Type','sessions'=>'Sessions','hours'=>'Hours','revenue'=>'Revenue']); ?></tbody></table></div>
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Popular Games</h2><a class="btn-secondary" href="<?= e(export_url('popular-games', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Game</th><th>Sessions</th><th>Revenue</th></tr></thead><tbody><?php render_rows($popularGames, ['game'=>'Game','sessions'=>'Sessions','revenue'=>'Revenue']); ?></tbody></table></div>
  </section>
</div>

<div data-report-panel="Inventory" class="hidden space-y-6">
  <section class="grid xl:grid-cols-2 gap-6">
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Low Stock</h2><a class="btn-secondary" href="<?= e(export_url('low-stock', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Current</th><th>Minimum</th><th>Cost</th><th>Value</th></tr></thead><tbody><?php render_rows($lowStockRows, ['name'=>'Product','sku'=>'SKU','category'=>'Category','current_stock'=>'Current','minimum_stock'=>'Minimum','cost_price'=>'Cost','value'=>'Value']); ?></tbody></table></div>
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Stock Movements</h2><a class="btn-secondary" href="<?= e(export_url('stock-movements', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Change</th><th>Prev</th><th>New</th><th>Reason</th><th>User</th></tr></thead><tbody><?php render_rows($stockMoves, ['created_at'=>'Date','product'=>'Product','movement_type'=>'Type','quantity_change'=>'Change','previous_stock'=>'Prev','new_stock'=>'New','reason'=>'Reason','user_name'=>'User']); ?></tbody></table></div>
  </section>
</div>

<div data-report-panel="Debts" class="hidden space-y-6">
  <section class="grid xl:grid-cols-2 gap-6">
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Debt Report</h2><a class="btn-secondary" href="<?= e(export_url('debts', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Account</th><th>Original</th><th>Paid</th><th>Remaining</th><th>Status</th><th>Created</th></tr></thead><tbody><?php render_rows($debts, ['account_name'=>'Account','original_amount'=>'Original','paid_amount'=>'Paid','remaining_amount'=>'Remaining','status'=>'Status','created_at'=>'Created']); ?></tbody></table></div>
    <div class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Debt Payments</h2><a class="btn-secondary" href="<?= e(export_url('debt-payments', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Date</th><th>Account</th><th>Amount</th><th>Method</th><th>User</th></tr></thead><tbody><?php render_rows($debtPayments, ['created_at'=>'Date','account_name'=>'Account','amount'=>'Amount','method'=>'Method','user_name'=>'User']); ?></tbody></table></div>
  </section>
</div>

<div data-report-panel="Expenses" class="hidden">
  <section class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Expenses</h2><a class="btn-secondary" href="<?= e(export_url('expenses', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Description</th><th>User</th></tr></thead><tbody><?php render_rows($expenses, ['expense_date'=>'Date','category'=>'Category','amount'=>'Amount','description'=>'Description','user_name'=>'User']); ?></tbody></table></section>
</div>

<div data-report-panel="Reservations" class="hidden">
  <section class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Reservations</h2><a class="btn-secondary" href="<?= e(export_url('reservations', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Date</th><th>Start</th><th>Duration</th><th>Kind</th><th>Station</th><th>Status</th></tr></thead><tbody><?php render_rows($reservations, ['reservation_date'=>'Date','start_time'=>'Start','duration_minutes'=>'Duration','station_kind'=>'Kind','station'=>'Station','status'=>'Status']); ?></tbody></table></section>
</div>

<div data-report-panel="Audit" class="hidden">
  <section class="panel overflow-auto"><div class="flex items-center justify-between p-4"><h2 class="font-display text-lg font-bold uppercase">Audit Activity</h2><a class="btn-secondary" href="<?= e(export_url('audit', $dateFrom, $dateTo)) ?>">CSV</a></div><table class="table"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Entity</th><th>ID</th><th>IP</th></tr></thead><tbody><?php render_rows($audit, ['created_at'=>'Date','user_name'=>'User','action'=>'Action','entity_type'=>'Entity','entity_id'=>'ID','ip_address'=>'IP']); ?></tbody></table></section>
</div>

<script>
const reportCharts = {
  dailySales: <?= json_encode(array_reverse($dailySales), JSON_THROW_ON_ERROR) ?>,
  paymentMethods: <?= json_encode($paymentMethods, JSON_THROW_ON_ERROR) ?>,
  topProducts: <?= json_encode(array_slice($topProducts, 0, 10), JSON_THROW_ON_ERROR) ?>,
  stationUtilization: <?= json_encode(array_slice($stationUtilization, 0, 10), JSON_THROW_ON_ERROR) ?>,
  lowStock: <?= json_encode(array_slice($lowStockRows, 0, 10), JSON_THROW_ON_ERROR) ?>,
  balance: <?= json_encode([
      ['label' => 'Revenue', 'value' => $salesTotal],
      ['label' => 'Gross Profit', 'value' => $grossProfit],
      ['label' => 'Profit Est.', 'value' => $profitEstimate],
      ['label' => 'Expenses', 'value' => $expenseTotal],
      ['label' => 'Debt', 'value' => $outstandingDebt],
  ], JSON_THROW_ON_ERROR) ?>
};

function chartCanvas(id) {
  const canvas = document.getElementById(id);
  if (!canvas) return null;
  const rect = canvas.parentElement.getBoundingClientRect();
  const ratio = window.devicePixelRatio || 1;
  canvas.width = Math.max(1, Math.floor(rect.width * ratio));
  canvas.height = Math.max(1, Math.floor(rect.height * ratio));
  const ctx = canvas.getContext('2d');
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  return {canvas, ctx, width: rect.width, height: rect.height};
}

function drawGrid(ctx, width, height, pad) {
  ctx.strokeStyle = 'rgba(255,255,255,.07)';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const y = pad.top + ((height - pad.top - pad.bottom) / 4) * i;
    ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(width - pad.right, y); ctx.stroke();
  }
}

function drawLineChart(id, labels, series) {
  const chart = chartCanvas(id); if (!chart) return;
  const {ctx, width, height} = chart;
  const pad = {left: 36, right: 12, top: 14, bottom: 28};
  ctx.clearRect(0, 0, width, height);
  drawGrid(ctx, width, height, pad);
  const values = series.flatMap(s => s.values).map(Number);
  const max = Math.max(1, ...values);
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  series.forEach(item => {
    ctx.strokeStyle = item.color;
    ctx.lineWidth = 2;
    ctx.beginPath();
    item.values.forEach((value, index) => {
      const x = pad.left + (labels.length <= 1 ? plotW : (plotW / (labels.length - 1)) * index);
      const y = pad.top + plotH - (Number(value) / max) * plotH;
      index ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
    });
    ctx.stroke();
    item.values.forEach((value, index) => {
      const x = pad.left + (labels.length <= 1 ? plotW : (plotW / (labels.length - 1)) * index);
      const y = pad.top + plotH - (Number(value) / max) * plotH;
      ctx.fillStyle = item.color; ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2); ctx.fill();
    });
  });
  ctx.fillStyle = 'rgba(229,226,225,.55)';
  ctx.font = '10px Inter';
  labels.forEach((label, index) => {
    if (labels.length > 8 && index % Math.ceil(labels.length / 6) !== 0) return;
    const x = pad.left + (labels.length <= 1 ? plotW : (plotW / (labels.length - 1)) * index);
    ctx.fillText(String(label).slice(5), x - 16, height - 8);
  });
}

function drawBarChart(id, labels, values, color = '#FF1E2D') {
  const chart = chartCanvas(id); if (!chart) return;
  const {ctx, width, height} = chart;
  const pad = {left: 38, right: 12, top: 12, bottom: 48};
  ctx.clearRect(0, 0, width, height);
  drawGrid(ctx, width, height, pad);
  const max = Math.max(1, ...values.map(Number));
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  const barW = Math.max(8, plotW / Math.max(1, values.length) * .62);
  labels.forEach((label, index) => {
    const x = pad.left + (plotW / Math.max(1, values.length)) * index + (plotW / Math.max(1, values.length) - barW) / 2;
    const h = (Number(values[index]) / max) * plotH;
    const gradient = ctx.createLinearGradient(0, pad.top + plotH - h, 0, pad.top + plotH);
    gradient.addColorStop(0, color);
    gradient.addColorStop(1, 'rgba(255,30,45,.18)');
    ctx.fillStyle = gradient;
    ctx.fillRect(x, pad.top + plotH - h, barW, h);
    ctx.fillStyle = 'rgba(229,226,225,.55)';
    ctx.font = '10px Inter';
    ctx.save();
    ctx.translate(x + barW / 2, height - 8);
    ctx.rotate(-0.65);
    ctx.fillText(String(label).slice(0, 13), 0, 0);
    ctx.restore();
  });
}

function drawHorizontalBarChart(id, labels, values, color = '#8B5CF6') {
  const chart = chartCanvas(id); if (!chart) return;
  const {ctx, width, height} = chart;
  const pad = {left: 112, right: 18, top: 12, bottom: 12};
  ctx.clearRect(0, 0, width, height);
  const max = Math.max(1, ...values.map(Number));
  const rowH = (height - pad.top - pad.bottom) / Math.max(1, values.length);
  labels.forEach((label, index) => {
    const y = pad.top + rowH * index + rowH * .2;
    const w = (Number(values[index]) / max) * (width - pad.left - pad.right);
    ctx.fillStyle = 'rgba(255,255,255,.06)';
    ctx.fillRect(pad.left, y, width - pad.left - pad.right, rowH * .5);
    ctx.fillStyle = color;
    ctx.fillRect(pad.left, y, w, rowH * .5);
    ctx.fillStyle = 'rgba(229,226,225,.72)';
    ctx.font = '11px Inter';
    ctx.fillText(String(label).slice(0, 16), 6, y + rowH * .36);
    ctx.fillStyle = '#fff';
    ctx.fillText(String(values[index]), pad.left + w + 6, y + rowH * .36);
  });
}

function drawDonutChart(id, labels, values, colors) {
  const chart = chartCanvas(id); if (!chart) return;
  const {ctx, width, height} = chart;
  ctx.clearRect(0, 0, width, height);
  const total = values.reduce((sum, v) => sum + Number(v), 0) || 1;
  const cx = width / 2, cy = height / 2, radius = Math.min(width, height) * .36;
  let start = -Math.PI / 2;
  values.forEach((value, i) => {
    const angle = (Number(value) / total) * Math.PI * 2;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, radius, start, start + angle);
    ctx.closePath();
    ctx.fillStyle = colors[i % colors.length];
    ctx.fill();
    start += angle;
  });
  ctx.beginPath(); ctx.arc(cx, cy, radius * .58, 0, Math.PI * 2); ctx.fillStyle = '#151515'; ctx.fill();
  ctx.fillStyle = '#fff'; ctx.font = '700 18px Space Grotesk'; ctx.textAlign = 'center'; ctx.fillText(total.toFixed(2), cx, cy + 6);
  ctx.textAlign = 'left';
}

function renderReportCharts() {
  drawLineChart('revenue-chart',
    reportCharts.dailySales.map(r => r.report_date),
    [
      {color:'#FF1E2D', values:reportCharts.dailySales.map(r => r.total)},
      {color:'#8B5CF6', values:reportCharts.dailySales.map(r => r.gaming)},
      {color:'#10B981', values:reportCharts.dailySales.map(r => r.products)}
    ]
  );
  drawDonutChart('payment-chart', reportCharts.paymentMethods.map(r => r.payment_method), reportCharts.paymentMethods.map(r => r.total), ['#10B981','#8B5CF6','#F59E0B','#64748B','#FF1E2D']);
  drawBarChart('products-chart', reportCharts.topProducts.map(r => r.product_name), reportCharts.topProducts.map(r => r.qty), '#FF1E2D');
  drawHorizontalBarChart('station-chart', reportCharts.stationUtilization.map(r => r.station), reportCharts.stationUtilization.map(r => r.hours), '#8B5CF6');
  drawBarChart('inventory-chart', reportCharts.lowStock.map(r => r.name), reportCharts.lowStock.map(r => r.current_stock), '#F59E0B');
  drawHorizontalBarChart('balance-chart', reportCharts.balance.map(r => r.label), reportCharts.balance.map(r => r.value), '#10B981');
}

window.addEventListener('load', renderReportCharts);
window.addEventListener('resize', () => clearTimeout(window.reportChartTimer) || (window.reportChartTimer = setTimeout(renderReportCharts, 120)));

document.querySelector('[data-report-tabs]').addEventListener('click', event => {
  const button = event.target.closest('[data-report-tab]');
  if (!button) return;
  document.querySelectorAll('[data-report-tab]').forEach(item => item.classList.remove('active'));
  button.classList.add('active');
  document.querySelectorAll('[data-report-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.reportPanel !== button.dataset.reportTab));
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
