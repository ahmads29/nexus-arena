<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../services/IcaFeCloudSyncService.php';
$activeNav = 'stations';
$pageTitle = 'Live Stations';
$pageAction = '<div class="flex gap-2"><button class="btn-secondary px-4 py-2 rounded label uppercase" data-icafe-sync-now><span class="material-symbols-outlined text-base align-middle">sync</span> Sync iCafeCloud</button><button class="btn-primary px-4 py-2 rounded label uppercase" data-open-template-modal="station-add-template" data-modal-title="Add PC Station" data-modal-icon="desktop_windows" data-modal-subtitle="Create a new PC tile for the live floor grid."><span class="material-symbols-outlined text-base align-middle">add</span> Add PC</button></div>';
require_permission('stations.manage');
if (setting('icafecloud_enabled', '0') === '1') {
    try {
        (new IcaFeCloudSyncService())->sync(false);
    } catch (Throwable) {
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    db()->prepare('INSERT INTO stations (name, station_type_id, station_zone_id, tier, status, hourly_rate, specifications, active) VALUES (?,?,?,?,?,?,?,1)')->execute([
        trim($_POST['name']), (int)$_POST['station_type_id'], (int)$_POST['station_zone_id'] ?: null, trim($_POST['tier']), $_POST['status'], (float)$_POST['hourly_rate'], trim($_POST['specifications'])
    ]);
    audit_log('CREATE_STATION', 'stations', (int)db()->lastInsertId());
    header('Location: ' . ADMIN_BASE . '/stations.php?toast=PC station created successfully.'); exit;
}
$icafeEnabled = icafecloud_is_enabled();
$icafeSummary = (new IcaFeCloudSyncService())->cachedSummary($icafeEnabled ? icafecloud_status_label() : 'LOCAL MODE');
$rows = $icafeEnabled
    ? icafecloud_live_pc_rows(true)
    : db()->query("SELECT s.*, st.name type_name, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED') WHERE s.active=1 ORDER BY s.name")->fetchAll();
$groupedRows = [];
foreach ($rows as $row) {
    $groupName = $icafeEnabled ? ($row['group_name'] ?: 'iCafeCloud PCs') : 'Local Stations';
    $groupedRows[$groupName][] = $row;
}
$localOnlyRows = [];
if ($icafeEnabled) {
    $localOnlyRows = db()->query("SELECT s.*, st.name type_name, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED') LEFT JOIN icafecloud_pcs p ON p.normalized_name = REPLACE(UPPER(s.icafe_pc_name), ' ', '') WHERE s.active=1 AND (s.icafe_pc_name IS NULL OR s.icafe_pc_name='' OR p.id IS NULL) ORDER BY s.name")->fetchAll();
}
$types = db()->query('SELECT * FROM station_types ORDER BY name')->fetchAll();
$zones = db()->query('SELECT * FROM station_zones ORDER BY name')->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel p-4 mb-4">
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="label text-[10px] uppercase tracking-[.14em] text-on-surface/45"><?= $icafeEnabled ? 'iCafeCloud' : 'Local Mode' ?></div>
      <div class="flex items-center gap-2 mt-1"><span class="status-dot <?= $icafeSummary['status'] === 'CONNECTED' ? 'bg-status-active' : ($icafeSummary['status'] === 'DISABLED' ? 'bg-on-surface/35' : 'bg-status-maintenance') ?>"></span><span class="font-display text-white font-bold uppercase" data-icafe-status><?= e($icafeSummary['status']) ?></span></div>
      <div class="text-xs text-on-surface/45 mt-1">Last sync: <span data-icafe-last-sync><?= e($icafeSummary['last_sync_at'] ?: 'Never') ?></span></div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-center">
      <?php foreach ([['Detected','pcs_detected'],['Groups','groups_detected'],['Connected','connected'],['Occupied','occupied'],['Missing','missing']] as [$label, $key]): ?>
        <div class="panel px-3 py-2"><div class="label text-[9px] text-on-surface/45"><?= e($label) ?></div><div class="telemetry font-bold"><?= e($icafeSummary[$key] ?? 0) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (!empty($icafeSummary['unmapped'])): ?><div class="modal-help mt-3">Unmapped iCafeCloud PCs: <?= e(implode(', ', $icafeSummary['unmapped'])) ?></div><?php endif; ?>
  <?php if (!empty($icafeSummary['duplicate_mappings'])): ?><div class="modal-help mt-3 text-status-busy">Duplicate iCafeCloud mappings: <?= e(implode(', ', $icafeSummary['duplicate_mappings'])) ?></div><?php endif; ?>
</section>
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
  <?php foreach (['all'=>'All','AVAILABLE'=>'Available','PLAYING'=>'Playing','RESERVED'=>'Reserved','MAINTENANCE'=>'Maintenance','OFFLINE'=>'Offline','STALE'=>'Stale'] as $key=>$label): ?><button class="filter-chip <?= $key==='all'?'active':'' ?>" data-filter="<?= e($key) ?>"><?= e($label) ?></button><?php endforeach; ?>
  <?php foreach (array_keys($groupedRows) as $group): ?><button class="filter-chip" data-filter="<?= e($group) ?>"><?= e($group) ?></button><?php endforeach; ?>
  <?php if ($localOnlyRows): ?><button class="filter-chip" data-filter="LOCAL ONLY">Local Only</button><?php endif; ?>
</section>
<section id="stations-grid" class="space-y-6" <?= $icafeEnabled ? 'data-admin-live-url="' . e(API_BASE . '/stations/live.php?admin=1') . '"' : '' ?>>
  <?php foreach ($groupedRows as $group => $items): ?>
    <div data-station-group="<?= e($group) ?>">
      <?php if ($icafeEnabled): ?><div class="flex items-center gap-3 mb-3"><h2 class="label text-xs uppercase tracking-[.18em] text-on-surface/55"><?= e($group) ?></h2><div class="h-px flex-1 bg-border-subtle"></div><span class="telemetry text-xs text-on-surface/45"><?= count($items) ?> PCs</span></div><?php endif; ?>
      <div class="station-grid">
        <?php foreach ($items as $row): require __DIR__ . '/../includes/station_tile.php'; endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($localOnlyRows): ?>
    <div data-station-group="LOCAL ONLY">
      <div class="flex items-center gap-3 mb-3"><h2 class="label text-xs uppercase tracking-[.18em] text-status-maintenance">Local Only</h2><div class="h-px flex-1 bg-border-subtle"></div><span class="telemetry text-xs text-on-surface/45"><?= count($localOnlyRows) ?> stations</span></div>
      <div class="station-grid">
        <?php foreach ($localOnlyRows as $row): $row['group_name'] = 'LOCAL ONLY'; require __DIR__ . '/../includes/station_tile.php'; endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<script>
document.getElementById('stations-grid').addEventListener('click', event => {
  const tile = event.target.closest('[data-station]');
  if (!tile) return;
  const status = tile.dataset.status;
  const mapped = tile.dataset.mapped !== '0';
  const actions = !mapped
    ? `<a class="btn-primary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/pc-management.php">Map Station</a>`
    : status === 'AVAILABLE'
    ? `<a class="btn-primary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/sessions.php?action=new">Start Session</a><a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/reservations.php">Reserve</a>`
    : status === 'PLAYING'
      ? `<a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/pos.php">Add Product</a><a class="btn-primary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/sessions.php">Stop Session</a>`
      : `<a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/stations.php">Manage Status</a>`;
  const cfg = <?= json_encode(currency_config(), JSON_THROW_ON_ERROR) ?>;
  const base = Number(tile.dataset.rate || 0);
  const primary = cfg.code === 'LBP' ? base * Number(cfg.rate) : base;
  const secondary = cfg.code === 'LBP' ? base : base * Number(cfg.rate);
  const hourly = mapped && base > 0 ? `${cfg.symbol}${primary.toLocaleString(undefined, {minimumFractionDigits: cfg.decimals, maximumFractionDigits: cfg.decimals})} (${cfg.secondary_symbol}${secondary.toLocaleString(undefined, {minimumFractionDigits: cfg.secondary_decimals, maximumFractionDigits: cfg.secondary_decimals})})/hr` : 'Not mapped';
  const badgeClass = tile.classList.contains('status-available') ? 'status-available' : tile.classList.contains('status-playing') ? 'status-playing' : tile.classList.contains('status-reserved') ? 'status-reserved' : 'status-maintenance';
  const icafePanel = tile.dataset.connection || tile.dataset.icafeSync ? `<div class="panel p-4">
    <div class="label text-[10px] uppercase text-brand-crimson mb-3">iCafeCloud External Status</div>
    <div class="grid grid-cols-2 gap-3 text-sm">
      <div><div class="label text-on-surface/45">Connection</div><div class="text-white">${tile.dataset.connection || 'UNKNOWN'}</div></div>
      <div><div class="label text-on-surface/45">Sync</div><div class="text-white">${tile.dataset.icafeSync || 'LOCAL ONLY'}</div></div>
      <div><div class="label text-on-surface/45">User</div><div class="text-white">${tile.dataset.username || 'No active user'}</div></div>
      <div><div class="label text-on-surface/45">Remaining</div><div class="telemetry text-white">${tile.dataset.icafeLeft || '-'}</div></div>
      <div><div class="label text-on-surface/45">External Duration</div><div class="telemetry text-white">${tile.dataset.icafeDuration || '-'}</div></div>
      <div><div class="label text-on-surface/45">iCafe Price</div><div class="text-white">${tile.dataset.icafePrice || '-'}</div></div>
    </div>
  </div>` : '';
  openDrawer({title: tile.dataset.name, body: `<div class="space-y-4"><div class="panel p-4"><div class="status-badge ${badgeClass}"><span class="status-dot"></span>${status}</div><div class="grid grid-cols-2 gap-3 mt-4"><div><div class="label text-on-surface/45">Local Hourly</div><div class="telemetry text-xl text-white">${hourly}</div></div><div><div class="label text-on-surface/45">Group / Zone</div><div class="text-white">${tile.dataset.zone || 'Floor'}</div></div></div>${tile.dataset.game ? `<div class="mt-4 text-on-surface/70">${tile.dataset.game}</div>` : ''}${tile.dataset.elapsed ? `<div class="telemetry text-2xl text-brand-crimson mt-1">${tile.dataset.elapsed}</div>` : ''}</div>${icafePanel}<div class="flex flex-wrap gap-2">${actions}</div></div>`});
});

const adminStationsGrid = document.getElementById('stations-grid');
const adminStationsUrl = adminStationsGrid?.dataset.adminLiveUrl;
if (adminStationsUrl) {
  const renderAdminStationGroups = stations => {
    const groups = new Map();
    stations.forEach(station => {
      const group = station.group_name || 'iCafeCloud PCs';
      if (!groups.has(group)) groups.set(group, []);
      groups.get(group).push(station);
    });
    adminStationsGrid.innerHTML = [...groups.entries()].map(([group, items]) => `
      <div data-station-group="${escapeHtml(group)}">
        <div class="flex items-center gap-3 mb-3">
          <h2 class="label text-xs uppercase tracking-[.18em] text-on-surface/55">${escapeHtml(group)}</h2>
          <div class="h-px flex-1 bg-border-subtle"></div>
          <span class="telemetry text-xs text-on-surface/45">${items.length} PCs</span>
        </div>
        <div class="station-grid">${items.map(station => renderLiveStationTile(station, 'pc')).join('')}</div>
      </div>
    `).join('');
    applyCurrentStationFilter(adminStationsGrid);
  };

  const refreshAdminStations = async () => {
    try {
      const url = `${adminStationsUrl}&_=${Date.now()}`;
      const data = await apiFetch(url, {cache: 'no-store'});
      renderAdminStationGroups(data.pcs || []);
      document.querySelector('[data-icafe-status]')?.replaceChildren(document.createTextNode(data.icafecloud_status || 'UNKNOWN'));
      document.querySelector('[data-icafe-last-sync]')?.replaceChildren(document.createTextNode(new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'})));
    } catch (error) {
      console.warn('Admin station refresh failed:', error);
    }
  };
  setInterval(refreshAdminStations, 5000);
}

document.querySelector('[data-icafe-sync-now]')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  setLoading(button, true, 'Syncing...');
  try {
    const data = await apiFetch('<?= API_BASE ?>/icafecloud/sync.php', {method: 'POST', body: JSON.stringify({})});
    toast(`iCafeCloud sync complete. PCs: ${data.pcs_detected || 0}, connected: ${data.connected || 0}.`);
    setTimeout(() => location.reload(), 500);
  } catch (error) {
    setLoading(button, false);
    toast(error.message, 'error');
  }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
