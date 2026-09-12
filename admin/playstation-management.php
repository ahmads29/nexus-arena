<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'playstation-management';
$pageTitle = 'PlayStation Management';
$pageDescription = 'Manage PS4 and PS5 stations, zones, rates, notes and visibility.';
require_permission('playstation.manage');

function redirect_ps(string $message, string $type = 'success'): never
{
    header('Location: ' . ADMIN_BASE . '/playstation-management.php?toast=' . urlencode($message) . '&toast_type=' . urlencode($type));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_station') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $console = in_array(($_POST['console_type'] ?? 'PS5'), ['PS4','PS5'], true) ? $_POST['console_type'] : 'PS5';
            $zoneId = (int)($_POST['station_zone_id'] ?? 0) ?: null;
            $status = in_array(($_POST['status'] ?? 'AVAILABLE'), ['AVAILABLE','PLAYING','RESERVED','MAINTENANCE','OFFLINE'], true) ? $_POST['status'] : 'AVAILABLE';
            $rate = max(0, (float)($_POST['hourly_rate'] ?? 0));
            $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
            $active = isset($_POST['active']) ? 1 : 0;
            if ($name === '') {
                throw new RuntimeException('Station name is required.');
            }
            if ($id > 0) {
                db()->prepare('UPDATE playstation_stations SET name=?, console_type=?, station_zone_id=?, status=?, hourly_rate=?, notes=?, active=? WHERE id=?')
                    ->execute([$name, $console, $zoneId, $status, $rate, $notes, $active, $id]);
                audit_log('UPDATE_PS_STATION', 'playstation_stations', $id);
                redirect_ps('PlayStation station updated successfully.');
            }
            db()->prepare('INSERT INTO playstation_stations (name, console_type, station_zone_id, status, hourly_rate, notes, active) VALUES (?,?,?,?,?,?,?)')
                ->execute([$name, $console, $zoneId, $status, $rate, $notes, $active]);
            audit_log('CREATE_PS_STATION', 'playstation_stations', (int)db()->lastInsertId());
            redirect_ps('PlayStation station created successfully.');
        }
        if ($action === 'toggle_station') {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('UPDATE playstation_stations SET active = IF(active=1,0,1) WHERE id=?')->execute([$id]);
            audit_log('TOGGLE_PS_STATION', 'playstation_stations', $id);
            redirect_ps('PlayStation visibility updated.');
        }
    } catch (Throwable $e) {
        redirect_ps($e->getMessage(), 'error');
    }
}

$stations = db()->query("SELECT p.*, z.name zone_name FROM playstation_stations p LEFT JOIN station_zones z ON z.id=p.station_zone_id ORDER BY p.active DESC, p.console_type, p.name")->fetchAll();
$zones = db()->query('SELECT * FROM station_zones ORDER BY name')->fetchAll();
$pageAction = '<button class="btn-primary py-2.5 px-4 rounded label uppercase" data-open-template-modal="ps-station-template" data-modal-title="Add PlayStation Station" data-modal-icon="videogame_asset" data-modal-subtitle="Create a PS4 or PS5 station for the console floor.">Add Console</button>';
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="ps-station-template">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_station"><input type="hidden" name="active" value="0">
    <div class="grid md:grid-cols-2 gap-3">
      <label class="block text-sm text-on-surface/65">Station name<input class="input mt-1" name="name" placeholder="PS5 #01" required></label>
      <label class="block text-sm text-on-surface/65">Console type<select class="input mt-1" name="console_type"><option>PS4</option><option selected>PS5</option></select></label>
      <label class="block text-sm text-on-surface/65">Zone<select class="input mt-1" name="station_zone_id"><option value="">No zone</option><?php foreach ($zones as $zone): ?><option value="<?= (int)$zone['id'] ?>"><?= e($zone['name']) ?></option><?php endforeach; ?></select></label>
      <label class="block text-sm text-on-surface/65">Status<select class="input mt-1" name="status"><option>AVAILABLE</option><option>PLAYING</option><option>RESERVED</option><option>MAINTENANCE</option><option>OFFLINE</option></select></label>
      <label class="block text-sm text-on-surface/65">Hourly rate<input class="input mt-1" type="number" step="0.01" min="0" name="hourly_rate" value="5.00"></label>
    </div>
    <label class="block text-sm text-on-surface/65">Notes<textarea class="input mt-1" name="notes" placeholder="Controllers, TV, room notes"></textarea></label>
    <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" checked><span class="setting-toggle-track"></span><span><strong>Active station</strong><small>Active consoles appear in admin and public live station grids.</small></span></label>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Console</button></div>
  </form>
</template>

<section class="panel overflow-auto">
  <div class="p-4 border-b border-border-subtle flex items-center justify-between">
    <h2 class="font-display text-lg text-white font-bold uppercase">Console Stations</h2>
    <span class="label text-[10px] text-on-surface/45 uppercase"><?= count($stations) ?> records</span>
  </div>
  <table class="table">
    <thead><tr><th>Name</th><th>Type</th><th>Zone</th><th>Status</th><th>Rate</th><th>Active</th><th>Notes</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($stations as $station): ?>
        <tr>
          <td class="text-white font-semibold"><?= e($station['name']) ?></td>
          <td><?= e($station['console_type']) ?></td>
          <td><?= e($station['zone_name'] ?: '-') ?></td>
          <td><span class="status-badge <?= station_status_class($station['status']) ?>"><span class="status-dot"></span><?= e($station['status']) ?></span></td>
          <td class="telemetry"><?= money($station['hourly_rate']) ?></td>
          <td><?= (int)$station['active'] ? 'Yes' : 'No' ?></td>
          <td class="max-w-[220px] truncate"><?= e($station['notes'] ?: '-') ?></td>
          <td class="text-right whitespace-nowrap">
            <button class="icon-btn" data-edit-ps='<?= e(json_encode($station, JSON_THROW_ON_ERROR)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
            <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_station"><input type="hidden" name="id" value="<?= (int)$station['id'] ?>"><button class="icon-btn <?= (int)$station['active'] ? 'danger' : '' ?>"><span class="material-symbols-outlined text-base"><?= (int)$station['active'] ? 'visibility_off' : 'visibility' ?></span></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
const zones = <?= json_encode($zones, JSON_THROW_ON_ERROR) ?>;
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const zoneOptions = selected => `<option value="">No zone</option>${zones.map(zone => `<option value="${zone.id}" ${String(zone.id) === String(selected || '') ? 'selected' : ''}>${esc(zone.name)}</option>`).join('')}`;
document.addEventListener('click', event => {
  const button = event.target.closest('[data-edit-ps]');
  if (!button) return;
  const station = JSON.parse(button.dataset.editPs);
  openModal({title:'Edit PlayStation Station', icon:'videogame_asset', size:'lg', subtitle:'Update console type, zone, status, rate and public visibility.', body:`<form method="post" class="space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_station"><input type="hidden" name="id" value="${station.id}"><input type="hidden" name="active" value="0">
    <div class="grid md:grid-cols-2 gap-3">
      <label class="block text-sm text-on-surface/65">Station name<input class="input mt-1" name="name" value="${esc(station.name)}" required></label>
      <label class="block text-sm text-on-surface/65">Console type<select class="input mt-1" name="console_type"><option ${station.console_type === 'PS4' ? 'selected' : ''}>PS4</option><option ${station.console_type === 'PS5' ? 'selected' : ''}>PS5</option></select></label>
      <label class="block text-sm text-on-surface/65">Zone<select class="input mt-1" name="station_zone_id">${zoneOptions(station.station_zone_id)}</select></label>
      <label class="block text-sm text-on-surface/65">Status<select class="input mt-1" name="status">${['AVAILABLE','PLAYING','RESERVED','MAINTENANCE','OFFLINE'].map(s => `<option ${station.status === s ? 'selected' : ''}>${s}</option>`).join('')}</select></label>
      <label class="block text-sm text-on-surface/65">Hourly rate<input class="input mt-1" type="number" step="0.01" min="0" name="hourly_rate" value="${station.hourly_rate}"></label>
    </div>
    <label class="block text-sm text-on-surface/65">Notes<textarea class="input mt-1" name="notes">${esc(station.notes)}</textarea></label>
    <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" ${Number(station.active) ? 'checked' : ''}><span class="setting-toggle-track"></span><span><strong>Active station</strong><small>Active consoles appear in admin and public live station grids.</small></span></label>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Console</button></div>
  </form>`});
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
