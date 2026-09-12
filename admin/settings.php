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
        if ($k === 'currency_code') {
            $v = in_array($v, ['USD', 'LBP'], true) ? $v : 'LBP';
            $stmt->execute(['currency', $v === 'LBP' ? 'LBP ' : '$']);
        }
        if ($k === 'usd_lbp_rate') {
            $v = max(1, (float)$v);
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
                <?php else: ?>
                <input class="input mt-1" name="settings[<?= e($key) ?>]" value="<?= e(setting($key, '')) ?>">
                <?php endif; ?>
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
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
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
