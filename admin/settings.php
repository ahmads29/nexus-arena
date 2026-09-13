<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'settings';
$pageTitle = 'Settings';
$pageDescription = 'Configure business info, operations, POS, pricing, website and system values.';
require_permission('settings.manage');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach ($_POST['settings'] ?? [] as $k => $v) {
        if ($k === 'icafecloud_api_key') {
            $v = trim((string)$v);
            if ($v === '' || str_contains($v, '*')) {
                continue;
            }
        }
        if ($k === 'currency_code') {
            $v = in_array($v, ['USD', 'LBP'], true) ? $v : 'LBP';
            $stmt->execute(['currency', $v === 'LBP' ? 'LBP ' : '$']);
        }
        if ($k === 'usd_lbp_rate') {
            $v = max(1, (float)$v);
        }
        if ($k === 'icafecloud_base_url') {
            $v = rtrim(trim((string)$v), '/') ?: 'https://api.icafecloud.com';
        }
        if ($k === 'icafecloud_cafe_id') {
            $v = preg_replace('/[^0-9]/', '', (string)$v) ?: '50761';
        }
        if (in_array($k, ['icafecloud_sync_interval_seconds', 'icafecloud_stale_after_seconds'], true)) {
            $v = max(15, (int)$v);
        }
        $stmt->execute([$k, trim((string)$v)]);
    }
    audit_log('UPDATE_SETTINGS', 'settings');
    header('Location: ' . ADMIN_BASE . '/settings.php?toast=Settings saved successfully.');
    exit;
}
$groups = [
    'General' => ['business_name', 'business_description'],
    'Operations' => ['opening_hours'],
    'POS' => ['currency_code', 'usd_lbp_rate', 'tax_rate', 'enable_invoice_printing'],
    'Pricing' => ['standard_pc_rate', 'pro_pc_rate', 'vip_pc_rate', 'ps4_rate', 'ps5_rate', 'ps4_controller_rate', 'ps5_controller_rate'],
    'Website' => ['phone', 'email', 'address', 'google_maps_url'],
    'iCafeCloud' => ['icafecloud_enabled', 'icafecloud_cafe_id', 'icafecloud_base_url', 'icafecloud_api_key', 'icafecloud_sync_interval_seconds', 'icafecloud_stale_after_seconds'],
    'System' => [],
];
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="panel p-4">
  <div class="flex flex-wrap gap-2 mb-4" data-settings-tabs>
    <?php foreach (array_keys($groups) as $i => $group): ?><button class="filter-chip <?= $i === 0 ? 'active' : '' ?>" data-tab="<?= e($group) ?>"><?= e($group) ?></button><?php endforeach; ?>
  </div>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <?php foreach ($groups as $group => $keys): ?>
      <div data-settings-panel="<?= e($group) ?>" class="<?= $group !== 'General' ? 'hidden' : '' ?>">
        <h2 class="font-display text-lg font-bold uppercase text-white mb-3"><?= e($group) ?></h2>
        <?php if (!$keys): ?>
          <div class="empty-state"><div><div class="font-display text-lg text-white uppercase">System controls</div><div class="mt-1">Security and audit controls are managed through users, roles and audit logs.</div></div></div>
        <?php endif; ?>
        <div class="grid md:grid-cols-2 gap-3">
          <?php foreach ($keys as $key): ?>
            <?php if ($key === 'enable_invoice_printing'): ?>
              <div>
                <div class="text-sm text-on-surface/60"><?= e(str_replace('_', ' ', ucwords($key, '_'))) ?></div>
                <input type="hidden" name="settings[enable_invoice_printing]" value="0">
                <label class="setting-toggle mt-1">
                  <input type="checkbox" name="settings[enable_invoice_printing]" value="1" <?= setting('enable_invoice_printing', '1') === '1' ? 'checked' : '' ?>>
                  <span class="setting-toggle-track" aria-hidden="true"></span>
                  <span>
                    <strong>Print invoice after sale</strong>
                    <small>When enabled, POS opens the receipt and starts printing after checkout.</small>
                  </span>
                </label>
              </div>
            <?php elseif ($key === 'icafecloud_enabled'): ?>
              <div>
                <div class="text-sm text-on-surface/60">iCafeCloud integration</div>
                <input type="hidden" name="settings[icafecloud_enabled]" value="0">
                <label class="setting-toggle mt-1">
                  <input type="checkbox" name="settings[icafecloud_enabled]" value="1" <?= setting('icafecloud_enabled', '0') === '1' ? 'checked' : '' ?>>
                  <span class="setting-toggle-track" aria-hidden="true"></span>
                  <span>
                    <strong>Enable iCafeCloud</strong>
                    <small>Uses cached server-side sync for PC connectivity and external session status.</small>
                  </span>
                </label>
              </div>
            <?php else: ?>
              <label class="block text-sm text-on-surface/60"><?= e(str_replace('_', ' ', ucwords($key, '_'))) ?>
                <?php if ($key === 'currency_code'): ?>
                <select class="input mt-1" name="settings[currency_code]">
                  <option value="LBP" <?= setting('currency_code', 'LBP') === 'LBP' ? 'selected' : '' ?>>LBP - Lebanese Pound</option>
                  <option value="USD" <?= setting('currency_code', 'LBP') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                </select>
                <?php elseif ($key === 'usd_lbp_rate'): ?>
                <input class="input mt-1" type="number" min="1" step="1" name="settings[usd_lbp_rate]" value="<?= e(setting($key, '89500')) ?>">
                <span class="block mt-1 text-xs text-on-surface/40">Used only when displaying prices in LBP. Stored sales/prices remain in base USD.</span>
                <?php elseif ($key === 'icafecloud_api_key'): ?>
                <input class="input mt-1" type="password" autocomplete="new-password" name="settings[icafecloud_api_key]" value="" placeholder="<?= icafecloud_setting('api_key', '') ? '••••••••••••••••' : 'Paste API key' ?>">
                <span class="block mt-1 text-xs text-on-surface/40">Leave blank to keep the existing key. The raw key is never rendered back to the browser.</span>
                <?php elseif (in_array($key, ['icafecloud_sync_interval_seconds', 'icafecloud_stale_after_seconds'], true)): ?>
                <input class="input mt-1" type="number" min="15" step="5" name="settings[<?= e($key) ?>]" value="<?= e(setting($key, $key === 'icafecloud_sync_interval_seconds' ? '60' : '120')) ?>">
                <?php else: ?>
                <input class="input mt-1" name="settings[<?= e($key) ?>]" value="<?= e(setting($key, '')) ?>">
                <?php endif; ?>
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php if ($group === 'iCafeCloud'): ?>
          <div class="panel p-4 mt-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div>
                <div class="label text-[10px] uppercase text-on-surface/45">Integration Status</div>
                <div class="flex items-center gap-2 mt-1">
                  <span class="status-dot <?= icafecloud_status_label() === 'CONNECTED' ? 'bg-status-active' : (icafecloud_status_label() === 'DISABLED' ? 'bg-on-surface/35' : 'bg-status-maintenance') ?>"></span>
                  <span class="font-display text-white font-bold uppercase" data-icafe-status><?= e(icafecloud_status_label()) ?></span>
                </div>
                <div class="text-xs text-on-surface/45 mt-1">Last sync: <span data-icafe-last-sync><?= e(setting('icafecloud_last_success_at', 'Never')) ?></span></div>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary px-4 py-2 rounded label uppercase" data-icafe-test>Test Connection</button>
                <button type="button" class="btn-primary px-4 py-2 rounded label uppercase" data-icafe-sync>Sync Now</button>
              </div>
            </div>
            <div class="modal-help mt-3" data-icafe-result>Browser requests stay local. PHP calls iCafeCloud server-side and caches the result in MySQL.</div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn-primary py-3 px-6 rounded label uppercase">Save Settings</button>
  </form>
</section>
<script>
document.querySelector('[data-settings-tabs]').addEventListener('click', event => {
  const button = event.target.closest('[data-tab]');
  if (!button) return;
  document.querySelectorAll('[data-settings-tabs] button').forEach(b => b.classList.remove('active'));
  button.classList.add('active');
  document.querySelectorAll('[data-settings-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.settingsPanel !== button.dataset.tab));
});

async function runIcafeAction(button, url, loadingText) {
  const result = document.querySelector('[data-icafe-result]');
  setLoading(button, true, loadingText);
  try {
    const data = await apiFetch(url, {method: 'POST', body: JSON.stringify({})});
    document.querySelector('[data-icafe-status]').textContent = data.status || 'CONNECTED';
    document.querySelector('[data-icafe-last-sync]').textContent = data.last_sync_at || new Date().toLocaleString();
    result.textContent = `Status: ${data.status || 'CONNECTED'} | PCs: ${data.pcs_detected ?? 0} | Connected: ${data.connected ?? 0} | Occupied: ${data.occupied ?? 0} | Available: ${data.available ?? 0} | Offline: ${data.offline ?? 0}`;
    if (data.warnings?.length) result.textContent += ` | ${data.warnings.join(' | ')}`;
    toast(data.message || 'iCafeCloud action completed.');
  } catch (error) {
    document.querySelector('[data-icafe-status]').textContent = error.payload?.status || 'API ERROR';
    result.textContent = error.message;
    toast(error.message, 'error');
  } finally {
    setLoading(button, false);
  }
}
document.querySelector('[data-icafe-test]')?.addEventListener('click', event => runIcafeAction(event.currentTarget, '<?= API_BASE ?>/icafecloud/test.php', 'Testing...'));
document.querySelector('[data-icafe-sync]')?.addEventListener('click', event => runIcafeAction(event.currentTarget, '<?= API_BASE ?>/icafecloud/sync.php', 'Syncing...'));
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
