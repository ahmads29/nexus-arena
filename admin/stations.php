<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'stations';
$pageTitle = 'Live Stations';
$pageAction = '<button class="btn-primary px-4 py-2 rounded label uppercase" data-open-template-modal="station-add-template" data-modal-title="Add PC Station" data-modal-icon="desktop_windows" data-modal-subtitle="Create a new PC tile for the live floor grid."><span class="material-symbols-outlined text-base align-middle">add</span> Add PC</button>';
require_permission('stations.manage');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    db()->prepare('INSERT INTO stations (name, station_type_id, station_zone_id, tier, status, hourly_rate, specifications, active) VALUES (?,?,?,?,?,?,?,1)')->execute([
        trim($_POST['name']), (int)$_POST['station_type_id'], (int)$_POST['station_zone_id'] ?: null, trim($_POST['tier']), $_POST['status'], (float)$_POST['hourly_rate'], trim($_POST['specifications'])
    ]);
    audit_log('CREATE_STATION', 'stations', (int)db()->lastInsertId());
    header('Location: ' . ADMIN_BASE . '/stations.php?toast=PC station created successfully.'); exit;
}
$rows = db()->query("SELECT s.*, st.name type_name, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED') WHERE s.active=1 ORDER BY s.name")->fetchAll();
$types = db()->query('SELECT * FROM station_types ORDER BY name')->fetchAll();
$zones = db()->query('SELECT * FROM station_zones ORDER BY name')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="station-add-template">
  <form method="post" class="space-y-3"><?= csrf_field() ?>
    <input class="input" name="name" placeholder="PC 80" required>
    <select class="input" name="station_type_id"><?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select>
    <select class="input" name="station_zone_id"><option value="">Zone</option><?php foreach ($zones as $z): ?><option value="<?= $z['id'] ?>"><?= e($z['name']) ?></option><?php endforeach; ?></select>
    <input class="input" name="tier" placeholder="Tier" value="Standard">
    <select class="input" name="status"><option>AVAILABLE</option><option>MAINTENANCE</option><option>OFFLINE</option></select>
    <input class="input" name="hourly_rate" type="number" step="0.01" placeholder="Rate" value="3.00">
    <input class="input" name="specifications" placeholder="Specs">
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Add PC</button></div>
  </form>
</template>
<section class="mb-4 flex flex-wrap gap-2" data-filter-group data-filter-target="#stations-grid">
  <?php foreach (['all'=>'All','AVAILABLE'=>'Available','PLAYING'=>'Playing','RESERVED'=>'Reserved','MAINTENANCE'=>'Maintenance','Standard'=>'Standard','Pro'=>'Pro','VIP'=>'VIP','Zone A'=>'Zone A','Zone B'=>'Zone B'] as $key=>$label): ?><button class="filter-chip <?= $key==='all'?'active':'' ?>" data-filter="<?= e($key) ?>"><?= e($label) ?></button><?php endforeach; ?>
</section>
<section id="stations-grid" class="station-grid">
  <?php foreach ($rows as $row): require __DIR__ . '/../includes/station_tile.php'; endforeach; ?>
</section>
<script>
document.getElementById('stations-grid').addEventListener('click', event => {
  const tile = event.target.closest('[data-station]');
  if (!tile) return;
  const status = tile.dataset.status;
  const actions = status === 'AVAILABLE'
    ? `<a class="btn-primary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/sessions.php?action=new">Start Session</a><a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/reservations.php">Reserve</a>`
    : status === 'PLAYING'
      ? `<a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/pos.php">Add Product</a><a class="btn-primary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/sessions.php">Stop Session</a>`
      : `<a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/stations.php">Manage Status</a>`;
  const cfg = <?= json_encode(currency_config(), JSON_THROW_ON_ERROR) ?>;
  const base = Number(tile.dataset.rate || 0);
  const primary = cfg.code === 'LBP' ? base * Number(cfg.rate) : base;
  const secondary = cfg.code === 'LBP' ? base : base * Number(cfg.rate);
  const hourly = `${cfg.symbol}${primary.toLocaleString(undefined, {minimumFractionDigits: cfg.decimals, maximumFractionDigits: cfg.decimals})} (${cfg.secondary_symbol}${secondary.toLocaleString(undefined, {minimumFractionDigits: cfg.secondary_decimals, maximumFractionDigits: cfg.secondary_decimals})})`;
  openDrawer({title: tile.dataset.name, body: `<div class="space-y-4"><div class="panel p-4"><div class="status-badge ${tile.classList.contains('status-available') ? 'status-available' : tile.classList.contains('status-playing') ? 'status-playing' : tile.classList.contains('status-reserved') ? 'status-reserved' : 'status-maintenance'}"><span class="status-dot"></span>${status}</div><div class="grid grid-cols-2 gap-3 mt-4"><div><div class="label text-on-surface/45">Hourly</div><div class="telemetry text-xl text-white">${hourly}/hr</div></div><div><div class="label text-on-surface/45">Zone</div><div class="text-white">${tile.dataset.zone || 'Floor'}</div></div></div>${tile.dataset.game ? `<div class="mt-4 text-on-surface/70">${tile.dataset.game}</div>` : ''}${tile.dataset.elapsed ? `<div class="telemetry text-2xl text-brand-crimson mt-1">${tile.dataset.elapsed}</div>` : ''}</div><div class="flex flex-wrap gap-2">${actions}</div></div>`});
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
