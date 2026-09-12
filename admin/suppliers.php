<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav='suppliers'; $pageTitle='Suppliers'; $pageAction='<button class="btn-primary px-4 py-2 rounded label uppercase" data-open-template-modal="supplier-add-template" data-modal-title="Add Supplier" data-modal-icon="warehouse" data-modal-subtitle="Store supplier contact details for purchases and inventory."><span class="material-symbols-outlined text-base align-middle">add</span> Add Supplier</button>'; require_permission('inventory.manage');
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf($_POST['csrf_token']??null);db()->prepare('INSERT INTO suppliers (name,phone,email,address) VALUES (?,?,?,?)')->execute([trim($_POST['name']),trim($_POST['phone']),trim($_POST['email']),trim($_POST['address'])]);header('Location: '.ADMIN_BASE.'/suppliers.php?toast=Supplier created successfully.');exit;}
$rows=db()->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="supplier-add-template"><form method="post" class="space-y-3"><?= csrf_field() ?><input class="input" name="name" placeholder="Supplier" required><input class="input" name="phone" placeholder="Phone"><input class="input" name="email" placeholder="Email"><input class="input" name="address" placeholder="Address"><div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Add Supplier</button></div></form></template>
<section class="panel overflow-auto"><table class="table"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td class="text-white"><?= e($r['name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['address']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
