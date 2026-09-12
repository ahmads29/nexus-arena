<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'sessions';
$pageTitle = 'Sessions';
$pageAction = '<button class="btn-primary px-4 py-2 rounded label uppercase" data-open-template-modal="start-session-template" data-modal-title="Start Session" data-modal-icon="play_arrow" data-modal-subtitle="Start a PC or PlayStation session using server-side pricing."><span class="material-symbols-outlined text-base align-middle">play_arrow</span> Start Session</button>';
require_permission('sessions.manage');
$pcs = db()->query("SELECT id,name,hourly_rate FROM stations WHERE active=1 AND status='AVAILABLE' ORDER BY name")->fetchAll();
$ps = db()->query("SELECT id,name,hourly_rate FROM playstation_stations WHERE active=1 AND status='AVAILABLE' ORDER BY console_type,name")->fetchAll();
$sessions = db()->query("SELECT gs.*, s.name pc_name, ps.name ps_name FROM gaming_sessions gs LEFT JOIN stations s ON s.id=gs.station_id LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id ORDER BY gs.id DESC LIMIT 100")->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="start-session-template">
  <form id="start-session" class="space-y-3">
    <select class="input" name="kind"><option value="pc">PC</option><option value="ps">PlayStation</option></select>
    <select class="input" name="station_id"><option value="">Station</option><?php foreach ($pcs as $p): ?><option data-kind="pc" value="<?= $p['id'] ?>"><?= e($p['name']) ?> - <?= money($p['hourly_rate']) ?>/hr</option><?php endforeach; ?><?php foreach ($ps as $p): ?><option data-kind="ps" value="<?= $p['id'] ?>"><?= e($p['name']) ?> - <?= money($p['hourly_rate']) ?>/hr</option><?php endforeach; ?></select>
    <input class="input" name="current_game" placeholder="Current game">
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Start</button></div>
  </form>
</template>
<section class="panel overflow-auto">
  <table class="table"><thead><tr><th>Session</th><th>Station</th><th>Game</th><th>Started</th><th>Status</th><th>Charge</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($sessions as $s): ?>
    <tr>
      <td><?= e($s['session_code']) ?></td><td class="text-white font-semibold"><?= e($s['pc_name'] ?: $s['ps_name']) ?></td><td><?= e($s['current_game']) ?></td><td><?= e($s['start_time']) ?></td><td><?= e($s['status']) ?></td><td><?= money($s['status'] === 'COMPLETED' ? $s['gaming_charge'] : active_session_charge($s)) ?></td>
      <td class="space-x-2"><?php if (in_array($s['status'], ['ACTIVE','PAUSED'], true)): ?><button class="text-brand-crimson" data-stop-session="<?= $s['id'] ?>">Stop</button><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
<script>
document.addEventListener('submit', async event => {
  if (event.target.id !== 'start-session') return;
  event.preventDefault();
  const button = event.submitter; setLoading(button, true, 'Starting...');
  try { await apiFetch('<?= API_BASE ?>/sessions/start.php', {method:'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.target).entries()))}); toast('Session started successfully.'); setTimeout(() => location.reload(), 450); }
  catch (error) { setLoading(button, false); toast(error.message, 'error'); }
});
document.addEventListener('click', async event => {
  const id = event.target.dataset.stopSession;
  if (!id || !await confirmAction({title:'Stop Session?', message:'Stop this session and calculate the final gaming charge?', confirmText:'Stop Session', severity:'warning'})) return;
  try { await apiFetch('<?= API_BASE ?>/sessions/stop.php', {method:'POST', body: JSON.stringify({session_id:id})}); toast('Session stopped successfully.'); setTimeout(() => location.reload(), 450); }
  catch (error) { toast(error.message, 'error'); }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
