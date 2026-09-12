<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'pc-management';
$pageTitle = 'PC Management';
$pageDescription = 'Manage PC stations, tiers, zones, rates and station types.';
require_permission('stations.manage');

function redirect_pc(string $message, string $type = 'success'): never
{
    header('Location: ' . ADMIN_BASE . '/pc-management.php?toast=' . urlencode($message) . '&toast_type=' . urlencode($type));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_station') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $typeId = (int)($_POST['station_type_id'] ?? 0);
            $zoneId = (int)($_POST['station_zone_id'] ?? 0) ?: null;
            $tier = trim((string)($_POST['tier'] ?? 'Standard')) ?: 'Standard';
            $status = in_array(($_POST['status'] ?? 'AVAILABLE'), ['AVAILABLE','PLAYING','RESERVED','MAINTENANCE','OFFLINE'], true) ? $_POST['status'] : 'AVAILABLE';
            $rate = max(0, (float)($_POST['hourly_rate'] ?? 0));
            $specs = trim((string)($_POST['specifications'] ?? '')) ?: null;
            $active = isset($_POST['active']) ? 1 : 0;
            if ($name === '' || $typeId <= 0) {
                throw new RuntimeException('PC name and type are required.');
            }
            if ($id > 0) {
                db()->prepare('UPDATE stations SET name=?, station_type_id=?, station_zone_id=?, tier=?, status=?, hourly_rate=?, specifications=?, active=? WHERE id=?')
                    ->execute([$name, $typeId, $zoneId, $tier, $status, $rate, $specs, $active, $id]);
                audit_log('UPDATE_PC_STATION', 'stations', $id);
                redirect_pc('PC station updated successfully.');
            }
            db()->prepare('INSERT INTO stations (name, station_type_id, station_zone_id, tier, status, hourly_rate, specifications, active) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$name, $typeId, $zoneId, $tier, $status, $rate, $specs, $active]);
            audit_log('CREATE_PC_STATION', 'stations', (int)db()->lastInsertId());
            redirect_pc('PC station created successfully.');
        }

        if ($action === 'toggle_station') {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('UPDATE stations SET active = IF(active=1,0,1) WHERE id=?')->execute([$id]);
            audit_log('TOGGLE_PC_STATION', 'stations', $id);
            redirect_pc('PC station visibility updated.');
        }

        if ($action === 'save_type') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $tier = trim((string)($_POST['tier'] ?? 'Standard')) ?: 'Standard';
            $rate = max(0, (float)($_POST['default_rate'] ?? 0));
            if ($name === '') {
                throw new RuntimeException('Type name is required.');
            }
            if ($id > 0) {
                db()->prepare('UPDATE station_types SET name=?, tier=?, default_rate=? WHERE id=?')->execute([$name, $tier, $rate, $id]);
                audit_log('UPDATE_PC_TYPE', 'station_types', $id);
                redirect_pc('PC type updated successfully.');
            }
            db()->prepare('INSERT INTO station_types (name, tier, default_rate) VALUES (?,?,?)')->execute([$name, $tier, $rate]);
            audit_log('CREATE_PC_TYPE', 'station_types', (int)db()->lastInsertId());
            redirect_pc('PC type created successfully.');
        }
    } catch (Throwable $e) {
        redirect_pc($e->getMessage(), 'error');
    }
}

$stations = db()->query("SELECT s.*, st.name type_name, z.name zone_name FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id ORDER BY s.active DESC, s.name")->fetchAll();
$types = db()->query('SELECT st.*, COUNT(s.id) station_count FROM station_types st LEFT JOIN stations s ON s.station_type_id=st.id GROUP BY st.id ORDER BY st.name')->fetchAll();
$zones = db()->query('SELECT * FROM station_zones ORDER BY name')->fetchAll();
$pageAction = '<div class="flex gap-2"><button class="btn-secondary py-2.5 px-4 rounded label uppercase" data-open-template-modal="pc-type-template" data-modal-title="Add PC Type" data-modal-icon="memory" data-modal-subtitle="Create a reusable PC type/tier and default rate.">Add Type</button><button class="btn-primary py-2.5 px-4 rounded label uppercase" data-open-template-modal="pc-station-template" data-modal-title="Add PC Station" data-modal-icon="computer" data-modal-subtitle="Create a managed PC station for the lounge floor.">Add PC</button></div>';
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="pc-station-template">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_station"><input type="hidden" name="active" value="0">
    <div class="grid md:grid-cols-2 gap-3">
      <label class="block text-sm text-on-surface/65">PC name<input class="input mt-1" name="name" placeholder="PC 01" required></label>
      <label class="block text-sm text-on-surface/65">Type<select class="input mt-1" name="station_type_id"><?php foreach ($types as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e($type['name']) ?></option><?php endforeach; ?></select></label>
      <label class="block text-sm text-on-surface/65">Zone<select class="input mt-1" name="station_zone_id"><option value="">No zone</option><?php foreach ($zones as $zone): ?><option value="<?= (int)$zone['id'] ?>"><?= e($zone['name']) ?></option><?php endforeach; ?></select></label>
      <label class="block text-sm text-on-surface/65">Tier<input class="input mt-1" name="tier" value="Standard"></label>
      <label class="block text-sm text-on-surface/65">Status<select class="input mt-1" name="status"><option>AVAILABLE</option><option>PLAYING</option><option>RESERVED</option><option>MAINTENANCE</option><option>OFFLINE</option></select></label>
      <label class="block text-sm text-on-surface/65">Hourly rate<input class="input mt-1" type="number" step="0.01" min="0" name="hourly_rate" value="3.00"></label>
    </div>
    <label class="block text-sm text-on-surface/65">Specifications<textarea class="input mt-1" name="specifications" placeholder="CPU, GPU, monitor, peripherals"></textarea></label>
    <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" checked><span class="setting-toggle-track"></span><span><strong>Active station</strong><small>Active PCs appear in admin and public live station grids.</small></span></label>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save PC</button></div>
  </form>
</template>

<template id="pc-type-template">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_type">
    <label class="block text-sm text-on-surface/65">Type name<input class="input mt-1" name="name" placeholder="High Performance PC" required></label>
    <div class="grid md:grid-cols-2 gap-3">
      <label class="block text-sm text-on-surface/65">Default tier<input class="input mt-1" name="tier" value="Standard"></label>
      <label class="block text-sm text-on-surface/65">Default rate<input class="input mt-1" type="number" step="0.01" min="0" name="default_rate" value="3.00"></label>
    </div>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Type</button></div>
  </form>
</template>

<section class="grid xl:grid-cols-[1fr_360px] gap-4">
  <div class="panel overflow-auto">
    <div class="p-4 border-b border-border-subtle flex items-center justify-between">
      <h2 class="font-display text-lg text-white font-bold uppercase">PC Stations</h2>
      <span class="label text-[10px] text-on-surface/45 uppercase"><?= count($stations) ?> records</span>
    </div>
    <table class="table">
      <thead><tr><th>Name</th><th>Type</th><th>Zone</th><th>Tier</th><th>Status</th><th>Rate</th><th>Active</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($stations as $station): ?>
          <tr>
            <td class="text-white font-semibold"><?= e($station['name']) ?></td>
            <td><?= e($station['type_name']) ?></td>
            <td><?= e($station['zone_name'] ?: '-') ?></td>
            <td><?= e($station['tier']) ?></td>
            <td><span class="status-badge <?= station_status_class($station['status']) ?>"><span class="status-dot"></span><?= e($station['status']) ?></span></td>
            <td class="telemetry"><?= money($station['hourly_rate']) ?></td>
            <td><?= (int)$station['active'] ? 'Yes' : 'No' ?></td>
            <td class="text-right whitespace-nowrap">
              <button class="icon-btn" data-edit-pc='<?= e(json_encode($station, JSON_THROW_ON_ERROR)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
              <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_station"><input type="hidden" name="id" value="<?= (int)$station['id'] ?>"><button class="icon-btn <?= (int)$station['active'] ? 'danger' : '' ?>"><span class="material-symbols-outlined text-base"><?= (int)$station['active'] ? 'visibility_off' : 'visibility' ?></span></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <aside class="panel overflow-auto h-max">
    <div class="p-4 border-b border-border-subtle"><h2 class="font-display text-lg text-white font-bold uppercase">PC Types</h2></div>
    <table class="table">
      <thead><tr><th>Type</th><th>Tier</th><th>Rate</th><th>PCs</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($types as $type): ?>
          <tr>
            <td class="text-white font-semibold"><?= e($type['name']) ?></td>
            <td><?= e($type['tier']) ?></td>
            <td class="telemetry"><?= money($type['default_rate']) ?></td>
            <td class="telemetry"><?= (int)$type['station_count'] ?></td>
            <td class="text-right"><button class="icon-btn" data-edit-type='<?= e(json_encode($type, JSON_THROW_ON_ERROR)) ?>'><span class="material-symbols-outlined text-base">edit</span></button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </aside>
</section>

<script>
const pcTypes = <?= json_encode($types, JSON_THROW_ON_ERROR) ?>;
const zones = <?= json_encode($zones, JSON_THROW_ON_ERROR) ?>;
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const optionList = (items, selected, empty = '') => `${empty ? `<option value="">${empty}</option>` : ''}${items.map(item => `<option value="${item.id}" ${String(item.id) === String(selected || '') ? 'selected' : ''}>${esc(item.name)}</option>`).join('')}`;
document.addEventListener('click', event => {
  const pcButton = event.target.closest('[data-edit-pc]');
  if (pcButton) {
    const station = JSON.parse(pcButton.dataset.editPc);
    openModal({title:'Edit PC Station', icon:'computer', size:'lg', subtitle:'Update station config, status and public visibility.', body:`<form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_station"><input type="hidden" name="id" value="${station.id}"><input type="hidden" name="active" value="0">
      <div class="grid md:grid-cols-2 gap-3">
        <label class="block text-sm text-on-surface/65">PC name<input class="input mt-1" name="name" value="${esc(station.name)}" required></label>
        <label class="block text-sm text-on-surface/65">Type<select class="input mt-1" name="station_type_id">${optionList(pcTypes, station.station_type_id)}</select></label>
        <label class="block text-sm text-on-surface/65">Zone<select class="input mt-1" name="station_zone_id">${optionList(zones, station.station_zone_id, 'No zone')}</select></label>
        <label class="block text-sm text-on-surface/65">Tier<input class="input mt-1" name="tier" value="${esc(station.tier)}"></label>
        <label class="block text-sm text-on-surface/65">Status<select class="input mt-1" name="status">${['AVAILABLE','PLAYING','RESERVED','MAINTENANCE','OFFLINE'].map(s => `<option ${station.status === s ? 'selected' : ''}>${s}</option>`).join('')}</select></label>
        <label class="block text-sm text-on-surface/65">Hourly rate<input class="input mt-1" type="number" step="0.01" min="0" name="hourly_rate" value="${station.hourly_rate}"></label>
      </div>
      <label class="block text-sm text-on-surface/65">Specifications<textarea class="input mt-1" name="specifications">${esc(station.specifications)}</textarea></label>
      <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" ${Number(station.active) ? 'checked' : ''}><span class="setting-toggle-track"></span><span><strong>Active station</strong><small>Active PCs appear in admin and public live station grids.</small></span></label>
      <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save PC</button></div>
    </form>`});
  }
  const typeButton = event.target.closest('[data-edit-type]');
  if (typeButton) {
    const type = JSON.parse(typeButton.dataset.editType);
    openModal({title:'Edit PC Type', icon:'memory', subtitle:'Update type/tier defaults used when creating PCs.', body:`<form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_type"><input type="hidden" name="id" value="${type.id}">
      <label class="block text-sm text-on-surface/65">Type name<input class="input mt-1" name="name" value="${esc(type.name)}" required></label>
      <div class="grid md:grid-cols-2 gap-3">
        <label class="block text-sm text-on-surface/65">Default tier<input class="input mt-1" name="tier" value="${esc(type.tier)}"></label>
        <label class="block text-sm text-on-surface/65">Default rate<input class="input mt-1" type="number" step="0.01" min="0" name="default_rate" value="${type.default_rate}"></label>
      </div>
      <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Type</button></div>
    </form>`});
  }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
