<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_permission('reports.view');

function report_date(string $value, string $fallback): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
}

$today = date('Y-m-d');
$from = report_date((string)($_GET['from'] ?? date('Y-m-01')), date('Y-m-01'));
$to = report_date((string)($_GET['to'] ?? $today), $today);
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$fromDateTime = $from . ' 00:00:00';
$toDateTime = $to . ' 23:59:59';
$type = (string)($_GET['type'] ?? 'daily-sales');

$reports = [
    'daily-sales' => [
        "SELECT DATE(created_at) report_date, COUNT(*) sales_count, COALESCE(SUM(gaming_charge),0) gaming, COALESCE(SUM(product_subtotal),0) products, COALESCE(SUM(total),0) total FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID' GROUP BY DATE(created_at) ORDER BY report_date DESC",
        [$fromDateTime, $toDateTime],
    ],
    'payment-methods' => [
        "SELECT payment_method, COUNT(*) count, COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid FROM sales WHERE created_at BETWEEN ? AND ? AND status <> 'VOID' GROUP BY payment_method ORDER BY total DESC",
        [$fromDateTime, $toDateTime],
    ],
    'cashiers' => [
        "SELECT COALESCE(u.name,'Unknown') cashier, COUNT(s.id) sales_count, COALESCE(SUM(s.total),0) total FROM sales s LEFT JOIN users u ON u.id=s.cashier_id WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID' GROUP BY cashier ORDER BY total DESC",
        [$fromDateTime, $toDateTime],
    ],
    'category-sales' => [
        "SELECT COALESCE(c.name,'Uncategorized') category, SUM(si.quantity) qty, SUM(si.total) total FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID' GROUP BY category ORDER BY total DESC",
        [$fromDateTime, $toDateTime],
    ],
    'top-products' => [
        "SELECT si.product_name, SUM(si.quantity) qty, SUM(si.total) total FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.created_at BETWEEN ? AND ? AND s.status <> 'VOID' GROUP BY si.product_name ORDER BY qty DESC",
        [$fromDateTime, $toDateTime],
    ],
    'sessions' => [
        "SELECT gs.session_code, COALESCE(s.name, ps.name) station, CASE WHEN gs.station_id IS NULL THEN ps.console_type ELSE 'PC' END station_type, gs.current_game, gs.start_time, gs.end_time, gs.gaming_charge, gs.product_charge, gs.total, gs.status FROM gaming_sessions gs LEFT JOIN stations s ON s.id=gs.station_id LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id WHERE gs.start_time BETWEEN ? AND ? ORDER BY gs.start_time DESC",
        [$fromDateTime, $toDateTime],
    ],
    'station-utilization' => [
        "SELECT COALESCE(s.name, ps.name) station, CASE WHEN gs.station_id IS NULL THEN ps.console_type ELSE 'PC' END station_type, COUNT(gs.id) sessions, ROUND(COALESCE(SUM(TIMESTAMPDIFF(SECOND, gs.start_time, COALESCE(gs.end_time,NOW())) - gs.paused_seconds),0)/3600,2) hours, COALESCE(SUM(gs.gaming_charge),0) revenue FROM gaming_sessions gs LEFT JOIN stations s ON s.id=gs.station_id LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id WHERE gs.start_time BETWEEN ? AND ? GROUP BY station, station_type ORDER BY hours DESC",
        [$fromDateTime, $toDateTime],
    ],
    'popular-games' => [
        "SELECT COALESCE(NULLIF(current_game,''),'Unknown') game, COUNT(*) sessions, COALESCE(SUM(gaming_charge),0) revenue FROM gaming_sessions WHERE start_time BETWEEN ? AND ? GROUP BY game ORDER BY sessions DESC",
        [$fromDateTime, $toDateTime],
    ],
    'low-stock' => [
        "SELECT p.name, p.sku, c.name category, p.current_stock, p.minimum_stock, p.cost_price, (p.cost_price*p.current_stock) value FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='ACTIVE' AND p.current_stock <= p.minimum_stock ORDER BY p.current_stock ASC, p.name",
        [],
    ],
    'stock-movements' => [
        "SELECT sm.created_at, p.name product, sm.movement_type, sm.quantity_change, sm.previous_stock, sm.new_stock, sm.reason, u.name user_name FROM stock_movements sm JOIN products p ON p.id=sm.product_id LEFT JOIN users u ON u.id=sm.created_by WHERE sm.created_at BETWEEN ? AND ? ORDER BY sm.id DESC",
        [$fromDateTime, $toDateTime],
    ],
    'debts' => [
        "SELECT c.name account_name, d.original_amount, d.paid_amount, d.remaining_amount, d.status, d.created_at FROM debts d JOIN customers c ON c.id=d.customer_id WHERE d.created_at BETWEEN ? AND ? OR d.status <> 'PAID' ORDER BY d.remaining_amount DESC",
        [$fromDateTime, $toDateTime],
    ],
    'debt-payments' => [
        "SELECT dp.created_at, c.name account_name, dp.amount, dp.method, u.name user_name FROM debt_payments dp JOIN debts d ON d.id=dp.debt_id JOIN customers c ON c.id=d.customer_id LEFT JOIN users u ON u.id=dp.created_by WHERE dp.created_at BETWEEN ? AND ? ORDER BY dp.id DESC",
        [$fromDateTime, $toDateTime],
    ],
    'expenses' => [
        "SELECT e.expense_date, e.category, e.amount, e.description, u.name user_name FROM expenses e LEFT JOIN users u ON u.id=e.user_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC, e.id DESC",
        [$from, $to],
    ],
    'reservations' => [
        "SELECT r.reservation_date, r.start_time, r.duration_minutes, r.station_kind, COALESCE(s.name, ps.name) station, r.status FROM reservations r LEFT JOIN stations s ON s.id=r.station_id LEFT JOIN playstation_stations ps ON ps.id=r.playstation_station_id WHERE r.reservation_date BETWEEN ? AND ? ORDER BY r.reservation_date DESC, r.start_time DESC",
        [$from, $to],
    ],
    'audit' => [
        "SELECT al.created_at, u.name user_name, al.action, al.entity_type, al.entity_id, al.ip_address FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.created_at BETWEEN ? AND ? ORDER BY al.id DESC",
        [$fromDateTime, $toDateTime],
    ],
];

if (!isset($reports[$type])) {
    http_response_code(404);
    exit('Unknown report.');
}

[$sql, $params] = $reports[$type];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9-]/i', '-', $type) . '-' . $from . '-to-' . $to . '.csv"');
$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['message']);
    fputcsv($out, ['No records found for this range.']);
}
fclose($out);
