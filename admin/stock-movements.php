<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'stock';
$pageTitle = 'Stock Movements';
require_permission('inventory.manage');
$rows = db()->query('SELECT sm.*, p.name product, u.name user_name FROM stock_movements sm JOIN products p ON p.id=sm.product_id LEFT JOIN users u ON u.id=sm.created_by ORDER BY sm.id DESC LIMIT 250')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel overflow-auto">
  <table class="table"><thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Previous</th><th>Change</th><th>New</th><th>Reason</th><th>User</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['created_at']) ?></td><td class="text-white font-semibold"><?= e($r['product']) ?></td><td><?= e($r['movement_type']) ?></td><td><?= e($r['previous_stock']) ?></td><td><?= e($r['quantity_change']) ?></td><td><?= e($r['new_stock']) ?></td><td><?= e($r['reason']) ?></td><td><?= e($r['user_name']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

