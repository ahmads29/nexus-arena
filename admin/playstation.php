<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'playstation';
$pageTitle = 'PlayStation';
$pageDescription = 'Quick console POS for PS4/PS5 sessions, controller pricing and live timers.';
$pageAction = '<a class="btn-secondary px-4 py-2 rounded label uppercase" href="' . ADMIN_BASE . '/playstation-management.php"><span class="material-symbols-outlined text-base align-middle">settings</span> Manage Consoles</a>';
require_permission('playstation.manage');
ensure_playstation_pos_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    if (($_POST['action'] ?? '') === 'save_ps_rates') {
        $currencyNow = currency_config();
        $inputRate = $currencyNow['code'] === 'LBP' ? max(1, (float)$currencyNow['rate']) : 1;
        $ps4Rate = round(max(0, (float)($_POST['ps4_rate'] ?? 0)) / $inputRate, 2);
        $ps5Rate = round(max(0, (float)($_POST['ps5_rate'] ?? 0)) / $inputRate, 2);
        $ps4Controller = round(max(0, (float)($_POST['ps4_controller_rate'] ?? 0)) / $inputRate, 2);
        $ps5Controller = round(max(0, (float)($_POST['ps5_controller_rate'] ?? 0)) / $inputRate, 2);
        db()->beginTransaction();
        try {
            save_setting('ps4_rate', $ps4Rate);
            save_setting('ps5_rate', $ps5Rate);
            save_setting('ps4_controller_rate', $ps4Controller);
            save_setting('ps5_controller_rate', $ps5Controller);
            db()->prepare("UPDATE playstation_stations SET hourly_rate=? WHERE console_type='PS4'")->execute([$ps4Rate]);
            db()->prepare("UPDATE playstation_stations SET hourly_rate=? WHERE console_type='PS5'")->execute([$ps5Rate]);
            audit_log('UPDATE_PS_RATES', 'settings');
            db()->commit();
            header('Location: ' . ADMIN_BASE . '/playstation.php?toast=PlayStation rates updated successfully.');
            exit;
        } catch (Throwable $e) {
            db()->rollBack();
            header('Location: ' . ADMIN_BASE . '/playstation.php?toast=' . urlencode($e->getMessage()) . '&toast_type=error');
            exit;
        }
    }
}

$available = db()->query("SELECT p.*, z.name zone_name FROM playstation_stations p LEFT JOIN station_zones z ON z.id=p.station_zone_id WHERE p.active=1 AND p.status='AVAILABLE' ORDER BY p.console_type,p.name")->fetchAll();
$rows = db()->query("SELECT p.*, z.name zone_name, gs.current_game, gs.id session_id, gs.session_code, gs.customer_id, gs.start_time, gs.hourly_rate session_hourly_rate, gs.base_hourly_rate, gs.controller_count, gs.controller_rate, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) - gs.paused_seconds elapsed FROM playstation_stations p LEFT JOIN station_zones z ON z.id=p.station_zone_id LEFT JOIN gaming_sessions gs ON gs.playstation_station_id=p.id AND gs.status IN ('ACTIVE','PAUSED') WHERE p.active=1 ORDER BY p.console_type,p.name")->fetchAll();
$activeSessions = array_values(array_filter($rows, fn($row) => !empty($row['session_id'])));
$currency = currency_config();
$priceInputMultiplier = $currency['code'] === 'LBP' ? (float)$currency['rate'] : 1;
$priceInputStep = $currency['code'] === 'LBP' ? '1' : '0.01';
$priceInputDecimals = $currency['code'] === 'LBP' ? 0 : 2;
$displayPrice = fn(string $key, float $default): string => number_format((float)setting($key, $default) * $priceInputMultiplier, $priceInputDecimals, '.', '');
$psRates = [
    'PS4' => ['base' => (float)setting('ps4_rate', 3), 'controller' => (float)setting('ps4_controller_rate', 0.5)],
    'PS5' => ['base' => (float)setting('ps5_rate', 5), 'controller' => (float)setting('ps5_controller_rate', 1)],
];
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid xl:grid-cols-[420px_1fr] gap-4 mb-4">
  <div class="space-y-4">
  <div class="panel p-4">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <div class="label text-[10px] uppercase tracking-[.14em] text-brand-crimson">Console Checkout</div>
        <h2 class="font-display text-xl text-white font-bold uppercase">PS Quick POS</h2>
      </div>
      <span class="material-symbols-outlined text-brand-crimson">point_of_sale</span>
    </div>
    <form id="ps-start-form" class="space-y-3">
      <input type="hidden" name="kind" value="ps">
      <label class="block text-sm text-on-surface/65">Console station
        <select class="input mt-1" name="station_id" id="ps-station-select" required>
          <option value="">Select available console</option>
          <?php foreach ($available as $station): ?>
            <option value="<?= (int)$station['id'] ?>" data-type="<?= e($station['console_type']) ?>" data-rate="<?= e($station['hourly_rate']) ?>"><?= e($station['name']) ?> - <?= e($station['console_type']) ?> - <?= money($station['hourly_rate']) ?>/hr</option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="grid gap-3">
        <label class="block text-sm text-on-surface/65">Game
          <input class="input mt-1" name="current_game" placeholder="EA FC, Mortal Kombat...">
        </label>
      </div>
      <label class="block text-sm text-on-surface/65">Controllers
        <input class="input mt-1" id="ps-controller-count" name="controller_count" type="number" min="0" max="8" value="2">
      </label>
      <div class="grid grid-cols-3 gap-2">
        <div class="panel p-3">
          <div class="label text-[10px] uppercase text-on-surface/45">Console/hr</div>
          <div class="telemetry text-white font-bold" id="ps-base-rate"><?= money(0) ?></div>
        </div>
        <div class="panel p-3">
          <div class="label text-[10px] uppercase text-on-surface/45">Controllers/hr</div>
          <div class="telemetry text-white font-bold" id="ps-controller-rate"><?= money(0) ?></div>
        </div>
        <div class="panel p-3">
          <div class="label text-[10px] uppercase text-brand-crimson">Total/hr</div>
          <div class="telemetry text-brand-crimson font-bold" id="ps-total-rate"><?= money(0) ?></div>
        </div>
      </div>
      <button class="btn-primary w-full py-3 rounded label uppercase">Start PS Session</button>
    </form>
  </div>

  <div class="panel p-4">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <div class="label text-[10px] uppercase tracking-[.14em] text-brand-crimson">Console Rates</div>
        <h2 class="font-display text-xl text-white font-bold uppercase">PS Prices</h2>
      </div>
      <span class="material-symbols-outlined text-brand-crimson">payments</span>
    </div>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_ps_rates">
      <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm text-on-surface/65">PS4 / hour (<?= e($currency['code']) ?>)
          <input class="input mt-1" type="number" min="0" step="<?= e($priceInputStep) ?>" name="ps4_rate" value="<?= e($displayPrice('ps4_rate', 3)) ?>">
        </label>
        <label class="block text-sm text-on-surface/65">PS4 controller / hour (<?= e($currency['code']) ?>)
          <input class="input mt-1" type="number" min="0" step="<?= e($priceInputStep) ?>" name="ps4_controller_rate" value="<?= e($displayPrice('ps4_controller_rate', 0.5)) ?>">
        </label>
        <label class="block text-sm text-on-surface/65">PS5 / hour (<?= e($currency['code']) ?>)
          <input class="input mt-1" type="number" min="0" step="<?= e($priceInputStep) ?>" name="ps5_rate" value="<?= e($displayPrice('ps5_rate', 5)) ?>">
        </label>
        <label class="block text-sm text-on-surface/65">PS5 controller / hour (<?= e($currency['code']) ?>)
          <input class="input mt-1" type="number" min="0" step="<?= e($priceInputStep) ?>" name="ps5_controller_rate" value="<?= e($displayPrice('ps5_controller_rate', 1)) ?>">
        </label>
      </div>
      <div class="modal-help">Enter prices in <?= e($currency['code']) ?>. The system keeps stable base values internally, updates PS4/PS5 station rates, and shows USD as the secondary reference.</div>
      <button class="btn-secondary w-full py-3 rounded label uppercase">Save PS Prices</button>
    </form>
  </div>
  </div>

  <div class="panel overflow-auto">
    <div class="p-4 border-b border-border-subtle flex items-center justify-between">
      <h2 class="font-display text-lg text-white font-bold uppercase">Active Console Timers</h2>
      <span class="label text-[10px] uppercase text-on-surface/45"><?= count($activeSessions) ?> active</span>
    </div>
    <table class="table">
      <thead><tr><th>Station</th><th>Game</th><th>Controllers</th><th>Rate/hr</th><th>Timer</th><th>Charge</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($activeSessions as $session): ?>
          <tr data-ps-session data-rate="<?= e($session['session_hourly_rate']) ?>" data-started="<?= e(max(0, (int)$session['elapsed'])) ?>">
            <td class="text-white font-semibold"><?= e($session['name']) ?></td>
            <td><?= e($session['current_game'] ?: '-') ?></td>
            <td class="telemetry"><?= (int)$session['controller_count'] ?> x <?= money($session['controller_rate']) ?></td>
            <td class="telemetry"><?= money($session['session_hourly_rate']) ?></td>
            <td class="telemetry text-brand-crimson" data-ps-timer><?= e(sprintf('%02d:%02d:%02d', floor(max(0, (int)$session['elapsed']) / 3600), floor((max(0, (int)$session['elapsed']) % 3600) / 60), max(0, (int)$session['elapsed']) % 60)) ?></td>
            <td class="telemetry" data-ps-charge><?= money(active_session_charge(['start_time' => $session['start_time'], 'end_time' => null, 'paused_seconds' => 0, 'hourly_rate' => $session['session_hourly_rate'], 'status' => 'ACTIVE'])) ?></td>
            <td><button class="btn-secondary" data-stop-session="<?= (int)$session['session_id'] ?>">Stop</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$activeSessions): ?><div class="empty-state"><div><div class="font-display text-lg text-white uppercase">No Active PlayStation Sessions</div><div class="mt-1">Start one from the PS Quick POS panel.</div></div></div><?php endif; ?>
  </div>
</section>

<section class="mb-4 flex flex-wrap gap-2" data-filter-group data-filter-target="#ps-grid">
  <?php foreach (['all'=>'All','PS4'=>'PS4','PS5'=>'PS5','AVAILABLE'=>'Available','PLAYING'=>'Playing','RESERVED'=>'Reserved','MAINTENANCE'=>'Maintenance'] as $key=>$label): ?><button class="filter-chip <?= $key==='all'?'active':'' ?>" data-filter="<?= e($key) ?>"><?= e($label) ?></button><?php endforeach; ?>
</section>
<section id="ps-grid" class="station-grid">
  <?php foreach ($rows as $row): $isPs = true; require __DIR__ . '/../includes/station_tile.php'; unset($isPs); endforeach; ?>
</section>

<script>
const psCurrency = <?= json_encode($currency, JSON_THROW_ON_ERROR) ?>;
const psRateSettings = <?= json_encode($psRates, JSON_THROW_ON_ERROR) ?>;
const psMoney = value => {
  const base = Number(value || 0);
  const primary = psCurrency.code === 'LBP' ? base * Number(psCurrency.rate) : base;
  const secondary = psCurrency.code === 'LBP' ? base : base * Number(psCurrency.rate);
  const primaryText = `${psCurrency.symbol}${primary.toLocaleString(undefined, {minimumFractionDigits: psCurrency.decimals, maximumFractionDigits: psCurrency.decimals})}`;
  return `${primaryText} (${psCurrency.secondary_symbol}${secondary.toLocaleString(undefined, {minimumFractionDigits: psCurrency.secondary_decimals, maximumFractionDigits: psCurrency.secondary_decimals})})`;
};
function currentPsType() {
  return document.getElementById('ps-station-select').selectedOptions[0]?.dataset.type || 'PS5';
}
function updatePsEstimate() {
  const option = document.getElementById('ps-station-select').selectedOptions[0];
  const type = currentPsType();
  const base = Number(option?.dataset.rate || psRateSettings[type]?.base || 0);
  const controllers = Math.max(0, Number(document.getElementById('ps-controller-count').value || 0));
  const controllerRate = Number(psRateSettings[type]?.controller || 0);
  document.getElementById('ps-base-rate').textContent = psMoney(base);
  document.getElementById('ps-controller-rate').textContent = psMoney(controllers * controllerRate);
  document.getElementById('ps-total-rate').textContent = psMoney(base + controllers * controllerRate);
}
document.getElementById('ps-station-select').addEventListener('change', updatePsEstimate);
document.getElementById('ps-controller-count').addEventListener('input', updatePsEstimate);
updatePsEstimate();

document.getElementById('ps-start-form').addEventListener('submit', async event => {
  event.preventDefault();
  const button = event.submitter;
  setLoading(button, true, 'Starting...');
  try {
    await apiFetch('<?= API_BASE ?>/sessions/start.php', {method:'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.target).entries()))});
    toast('PlayStation session started.');
    setTimeout(() => location.reload(), 450);
  } catch (error) {
    setLoading(button, false);
    toast(error.message, 'error');
  }
});

document.addEventListener('click', async event => {
  const id = event.target.dataset.stopSession;
  if (!id || !await confirmAction({title:'Stop PlayStation Session?', message:'Stop this session and calculate console plus controller charges?', confirmText:'Stop Session', severity:'warning'})) return;
  try {
    await apiFetch('<?= API_BASE ?>/sessions/stop.php', {method:'POST', body: JSON.stringify({session_id:id})});
    toast('PlayStation session stopped.');
    setTimeout(() => location.reload(), 450);
  } catch (error) {
    toast(error.message, 'error');
  }
});

setInterval(() => {
  document.querySelectorAll('[data-ps-session]').forEach(row => {
    const elapsed = Number(row.dataset.started || 0) + 1;
    row.dataset.started = elapsed;
    const charge = (elapsed / 3600) * Number(row.dataset.rate || 0);
    row.querySelector('[data-ps-timer]').textContent = formatDuration(elapsed);
    row.querySelector('[data-ps-charge]').textContent = psMoney(charge);
  });
}, 1000);

document.getElementById('ps-grid').addEventListener('click', event => {
  const tile = event.target.closest('[data-station]');
  if (!tile) return;
  const status = tile.dataset.status;
  const actions = status === 'AVAILABLE'
    ? `<button class="btn-primary px-3 py-2 rounded label uppercase" onclick="document.getElementById('ps-station-select').value='${tile.dataset.id}'; updatePsEstimate(); closeDrawer(); document.getElementById('ps-station-select').focus();">Use Quick POS</button><a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/reservations.php">Reserve</a>`
    : status === 'PLAYING'
      ? `<a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/pos.php">Add Product</a><a class="btn-primary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/playstation.php">Stop From Timer</a>`
      : `<a class="btn-secondary px-3 py-2 rounded label uppercase" href="<?= ADMIN_BASE ?>/playstation-management.php">Manage Status</a>`;
  const hourly = psMoney(tile.dataset.rate);
  openDrawer({title: tile.dataset.name, body: `<div class="space-y-4"><div class="panel p-4"><div class="status-badge ${tile.classList.contains('status-available') ? 'status-available' : tile.classList.contains('status-playing') ? 'status-playing' : tile.classList.contains('status-reserved') ? 'status-reserved' : 'status-maintenance'}"><span class="status-dot"></span>${status}</div><div class="grid grid-cols-2 gap-3 mt-4"><div><div class="label text-on-surface/45">Console/hr</div><div class="telemetry text-xl text-white">${hourly}/hr</div></div><div><div class="label text-on-surface/45">Zone</div><div class="text-white">${tile.dataset.zone || 'Console Den'}</div></div></div>${tile.dataset.game ? `<div class="mt-4 text-on-surface/70">${tile.dataset.game}</div>` : ''}${tile.dataset.elapsed ? `<div class="telemetry text-2xl text-brand-crimson mt-1">${tile.dataset.elapsed}</div>` : ''}</div><div class="flex flex-wrap gap-2">${actions}</div></div>`});
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
