<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$activeNav = 'debts';
$pageTitle = 'Debts';
$pageAction = '<button class="btn-primary px-4 py-2 rounded label uppercase" data-open-template-modal="debt-add-template" data-modal-title="Add Debt" data-modal-icon="account_balance_wallet" data-modal-subtitle="Create a balance that can be paid down later."><span class="material-symbols-outlined text-base align-middle">add</span> Add Debt</button>';
require_permission('debts.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $amount = max(0, (float)($_POST['amount'] ?? 0));
    $accountName = trim((string)($_POST['account_name'] ?? 'Walk-in'));
    if ($accountName === '') {
        $accountName = 'Walk-in';
    }

    $stmt = db()->prepare('SELECT id FROM customers WHERE name=? ORDER BY id LIMIT 1');
    $stmt->execute([$accountName]);
    $customerId = (int)$stmt->fetchColumn();

    db()->beginTransaction();
    try {
        if (!$customerId) {
            db()->prepare('INSERT INTO customers (name, notes) VALUES (?, ?)')
                ->execute([$accountName, 'Internal debt account.']);
            $customerId = (int)db()->lastInsertId();
        }

        db()->prepare('INSERT INTO debts (customer_id, original_amount, remaining_amount, notes, created_by) VALUES (?,?,?,?,?)')
            ->execute([$customerId, $amount, $amount, trim((string)($_POST['notes'] ?? '')), current_user()['id']]);
        $debtId = (int)db()->lastInsertId();
        db()->prepare('UPDATE customers SET balance=balance+? WHERE id=?')->execute([$amount, $customerId]);
        audit_log('CREATE_DEBT', 'debts', $debtId);
        db()->commit();
        header('Location: ' . ADMIN_BASE . '/debts.php?toast=Debt created successfully.');
        exit;
    } catch (Throwable $e) {
        db()->rollBack();
        header('Location: ' . ADMIN_BASE . '/debts.php?toast=' . urlencode($e->getMessage()) . '&toast_type=error');
        exit;
    }
}

$rows = db()->query('SELECT d.*, c.name account_name, (SELECT MAX(created_at) FROM debt_payments dp WHERE dp.debt_id=d.id) last_payment FROM debts d JOIN customers c ON c.id=d.customer_id ORDER BY d.id DESC')->fetchAll();
$outstanding = db()->query("SELECT COALESCE(SUM(remaining_amount),0) FROM debts WHERE status <> 'PAID'")->fetchColumn();
$paidToday = db()->query("SELECT COALESCE(SUM(amount),0) FROM debt_payments WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$debtAccounts = db()->query("SELECT COUNT(DISTINCT customer_id) FROM debts WHERE status <> 'PAID'")->fetchColumn();

require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid md:grid-cols-3 gap-4 mb-6">
  <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45">Outstanding</div><div class="telemetry text-2xl text-status-busy font-bold"><?= money($outstanding) ?></div></div>
  <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45">Paid Today</div><div class="telemetry text-2xl text-status-active font-bold"><?= money($paidToday) ?></div></div>
  <div class="panel p-4"><div class="label text-[10px] uppercase text-on-surface/45">Open Debt Accounts</div><div class="telemetry text-2xl text-white font-bold"><?= e($debtAccounts) ?></div></div>
</section>

<template id="debt-add-template">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input class="input" name="account_name" placeholder="Account name" required>
    <input class="input" name="amount" type="number" step="0.01" min="0.01" placeholder="Amount" required>
    <input class="input" name="notes" placeholder="Notes">
    <div class="modal-foot -mx-4 -mb-4 mt-4">
      <button type="button" class="btn-secondary" data-close-modal>Cancel</button>
      <button class="btn-primary rounded label uppercase">Add Debt</button>
    </div>
  </form>
</template>

<section class="panel overflow-auto">
  <table class="table">
    <thead><tr><th>Account</th><th>Original</th><th>Paid</th><th>Remaining</th><th>Last Payment</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="text-white font-semibold"><?= e($r['account_name']) ?></td>
          <td><?= money($r['original_amount']) ?></td>
          <td><?= money($r['paid_amount']) ?></td>
          <td class="text-status-busy"><?= money($r['remaining_amount']) ?></td>
          <td><?= e($r['last_payment'] ?: '-') ?></td>
          <td><span class="status-badge <?= $r['status'] === 'PAID' ? 'status-available' : 'status-maintenance' ?>"><span class="status-dot"></span><?= e($r['status']) ?></span></td>
          <td><?php if ($r['status'] !== 'PAID'): ?><button class="icon-btn" title="Record payment" data-pay-debt="<?= (int)$r['id'] ?>"><span class="material-symbols-outlined text-base">payments</span></button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<script>
document.addEventListener('click', async event => {
  const id = event.target.closest('[data-pay-debt]')?.dataset.payDebt;
  if (!id) return;
  const root = openModal({
    title: 'Record Debt Payment',
    icon: 'payments',
    subtitle: 'Apply a payment and update the remaining balance.',
    body: `<form id="debt-payment-form" class="space-y-3"><input type="hidden" name="debt_id" value="${id}"><label class="block text-sm text-on-surface/65">Payment Amount<input class="input mt-1" name="amount" type="number" step="0.01" min="0.01" required autofocus></label></form>`,
    footer: '<button class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary" data-save-payment>Record Payment</button>'
  });
  root.querySelector('[data-save-payment]').addEventListener('click', async saveEvent => {
    setLoading(saveEvent.currentTarget, true, 'Recording...');
    try {
      await apiFetch('<?= API_BASE ?>/debts/pay.php', {method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(root.querySelector('form')).entries()))});
      toast('Payment recorded successfully.');
      setTimeout(() => location.reload(), 450);
    } catch (error) {
      setLoading(saveEvent.currentTarget, false);
      toast(error.message, 'error');
    }
  });
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
