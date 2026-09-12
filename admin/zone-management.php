<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'zone-management';
$pageTitle = 'Zone Management';
$pageDescription = 'Create and organize floor zones used by PCs, PlayStations and reservations.';
require_permission('stations.manage');

function redirect_zone(string $message, string $type = 'success'): never
{
    header('Location: ' . ADMIN_BASE . '/zone-management.php?toast=' . urlencode($message) . '&toast_type=' . urlencode($type));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    try {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'save_zone') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Zone name is required.');
            }
            if ($id > 0) {
                db()->prepare('UPDATE station_zones SET name=? WHERE id=?')->execute([$name, $id]);
                audit_log('UPDATE_ZONE', 'station_zones', $id);
                redirect_zone('Zone updated successfully.');
            }
            db()->prepare('INSERT INTO station_zones (name) VALUES (?)')->execute([$name]);
            audit_log('CREATE_ZONE', 'station_zones', (int)db()->lastInsertId());
            redirect_zone('Zone created successfully.');
        }

        if ($action === 'delete_zone') {
            $stmt = db()->prepare("
                SELECT
                  (SELECT COUNT(*) FROM stations WHERE station_zone_id=?) +
                  (SELECT COUNT(*) FROM playstation_stations WHERE station_zone_id=?) +
                  (SELECT COUNT(*) FROM reservations WHERE station_id IS NOT NULL AND station_id IN (SELECT id FROM stations WHERE station_zone_id=?)) +
                  (SELECT COUNT(*) FROM reservations WHERE playstation_station_id IS NOT NULL AND playstation_station_id IN (SELECT id FROM playstation_stations WHERE station_zone_id=?))
            ");
            $stmt->execute([$id, $id, $id, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('This zone is in use. Move stations to another zone before deleting it.');
            }
            db()->prepare('DELETE FROM station_zones WHERE id=?')->execute([$id]);
            audit_log('DELETE_ZONE', 'station_zones', $id);
            redirect_zone('Zone deleted successfully.');
        }
    } catch (Throwable $e) {
        redirect_zone($e->getMessage(), 'error');
    }
}

$zones = db()->query("
    SELECT z.*,
      (SELECT COUNT(*) FROM stations s WHERE s.station_zone_id=z.id) pc_count,
      (SELECT COUNT(*) FROM playstation_stations p WHERE p.station_zone_id=z.id) ps_count
    FROM station_zones z
    ORDER BY z.name
")->fetchAll();
$pageAction = '<button class="btn-primary py-2.5 px-4 rounded label uppercase" data-open-template-modal="zone-template" data-modal-title="Add Zone" data-modal-icon="location_on" data-modal-subtitle="Create a floor zone for grouping stations.">Add Zone</button>';
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="zone-template">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_zone">
    <label class="block text-sm text-on-surface/65">Zone name<input class="input mt-1" name="name" placeholder="Zone A" required></label>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Zone</button></div>
  </form>
</template>

<section class="grid md:grid-cols-3 gap-3 mb-4">
  <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45">Zones</div><div class="telemetry text-3xl text-white font-bold mt-1"><?= count($zones) ?></div></div>
  <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45">Assigned PCs</div><div class="telemetry text-3xl text-white font-bold mt-1"><?= array_sum(array_map(fn($z) => (int)$z['pc_count'], $zones)) ?></div></div>
  <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45">Assigned Consoles</div><div class="telemetry text-3xl text-white font-bold mt-1"><?= array_sum(array_map(fn($z) => (int)$z['ps_count'], $zones)) ?></div></div>
</section>

<section class="panel overflow-auto">
  <table class="table">
    <thead><tr><th>Zone</th><th>PC Stations</th><th>PlayStations</th><th>Total Stations</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($zones as $zone): $used = (int)$zone['pc_count'] + (int)$zone['ps_count']; ?>
        <tr>
          <td class="text-white font-semibold"><?= e($zone['name']) ?></td>
          <td class="telemetry"><?= (int)$zone['pc_count'] ?></td>
          <td class="telemetry"><?= (int)$zone['ps_count'] ?></td>
          <td class="telemetry"><?= $used ?></td>
          <td class="text-right whitespace-nowrap">
            <button class="icon-btn" data-edit-zone='<?= e(json_encode($zone, JSON_THROW_ON_ERROR)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
            <form class="inline" method="post" data-delete-zone-form>
              <?= csrf_field() ?><input type="hidden" name="action" value="delete_zone"><input type="hidden" name="id" value="<?= (int)$zone['id'] ?>">
              <button class="icon-btn danger" <?= $used > 0 ? 'disabled title="Move assigned stations before deleting"' : 'title="Delete zone"' ?>><span class="material-symbols-outlined text-base">delete</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
document.addEventListener('click', async event => {
  const edit = event.target.closest('[data-edit-zone]');
  if (edit) {
    const zone = JSON.parse(edit.dataset.editZone);
    openModal({title:'Edit Zone', icon:'location_on', subtitle:'Rename this floor zone.', body:`<form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_zone"><input type="hidden" name="id" value="${zone.id}">
      <label class="block text-sm text-on-surface/65">Zone name<input class="input mt-1" name="name" value="${esc(zone.name)}" required></label>
      <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Zone</button></div>
    </form>`});
  }
});
document.addEventListener('submit', async event => {
  if (!event.target.matches('[data-delete-zone-form]')) return;
  event.preventDefault();
  if (await confirmAction({title:'Delete Zone?', message:'This zone is not assigned to any station. Delete it permanently?', confirmText:'Delete Zone', severity:'danger'})) {
    event.target.submit();
  }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
