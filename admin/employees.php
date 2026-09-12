<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'employees';
$pageTitle = 'Employees';
$pageDescription = 'Create system users, assign roles, reset passwords and control staff access.';
require_permission('employees.manage');

$rows = db()->query('SELECT u.*, r.name role FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.status, u.name')->fetchAll();
$roles = db()->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
$activeCount = 0;
$inactiveCount = 0;
foreach ($rows as $row) {
    if ($row['status'] === 'ACTIVE') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}
$pageAction = '<button class="btn-primary py-2.5 px-4 rounded label uppercase" id="add-employee"><span class="material-symbols-outlined text-base align-middle mr-1">person_add</span>Add User</button>';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid md:grid-cols-3 gap-3 mb-4">
  <div class="panel p-4">
    <div class="label text-[10px] uppercase text-on-surface/45">System Users</div>
    <div class="telemetry text-3xl text-white font-bold mt-1"><?= count($rows) ?></div>
  </div>
  <div class="panel p-4">
    <div class="label text-[10px] uppercase text-status-active">Active</div>
    <div class="telemetry text-3xl text-white font-bold mt-1"><?= $activeCount ?></div>
  </div>
  <div class="panel p-4">
    <div class="label text-[10px] uppercase text-status-busy">Inactive</div>
    <div class="telemetry text-3xl text-white font-bold mt-1"><?= $inactiveCount ?></div>
  </div>
</section>

<section class="panel p-4 mb-4">
  <div class="grid md:grid-cols-[1fr_180px] gap-3">
    <input class="input" data-table-search="#employees-table" placeholder="Search employees, usernames, email or roles">
    <select class="input" data-table-filter="#employees-table" data-filter-field="status">
      <option value="">All statuses</option>
      <option value="ACTIVE">Active</option>
      <option value="INACTIVE">Inactive</option>
    </select>
  </div>
</section>

<section class="panel overflow-auto">
  <table class="table" id="employees-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr
          data-user='<?= e(json_encode([
              'id' => (int)$row['id'],
              'name' => $row['name'],
              'username' => $row['username'],
              'email' => $row['email'],
              'role_id' => (int)$row['role_id'],
              'status' => $row['status'],
          ], JSON_THROW_ON_ERROR)) ?>'
        >
          <td data-search class="text-white font-semibold"><?= e($row['name']) ?></td>
          <td data-search><?= e($row['username']) ?></td>
          <td data-search><?= e($row['email'] ?: '-') ?></td>
          <td data-search><?= e($row['role']) ?></td>
          <td data-field="status" data-value="<?= e($row['status']) ?>">
            <span class="status-badge <?= $row['status'] === 'ACTIVE' ? 'status-available' : 'status-offline' ?>">
              <span class="status-dot"></span><?= e($row['status']) ?>
            </span>
          </td>
          <td><?= e(substr((string)$row['created_at'], 0, 10)) ?></td>
          <td class="text-right whitespace-nowrap">
            <button class="icon-btn" title="Edit user" data-edit-user><span class="material-symbols-outlined text-base">edit</span></button>
            <button class="icon-btn" title="Reset password" data-reset-password><span class="material-symbols-outlined text-base">key</span></button>
            <button class="icon-btn <?= $row['status'] === 'ACTIVE' ? 'danger' : '' ?>" title="<?= $row['status'] === 'ACTIVE' ? 'Deactivate user' : 'Activate user' ?>" data-toggle-user>
              <span class="material-symbols-outlined text-base"><?= $row['status'] === 'ACTIVE' ? 'person_off' : 'person_check' ?></span>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="empty-state hidden" data-empty-for="employees-table">
    <div>
      <div class="font-display text-lg text-white uppercase">No Employees Found</div>
      <div class="mt-1">Try another search or status filter.</div>
    </div>
  </div>
</section>

<script>
const roles = <?= json_encode($roles, JSON_THROW_ON_ERROR) ?>;
const h = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

function roleOptions(selected = '') {
  return roles.map(role => `<option value="${role.id}" ${String(role.id) === String(selected) ? 'selected' : ''}>${h(role.name)}</option>`).join('');
}

function employeeForm(user = {}) {
  const isEdit = Boolean(user.id);
  return `<form id="employee-form">
    <input type="hidden" name="id" value="${user.id || ''}">
    <div class="grid md:grid-cols-2 gap-3">
      <label class="block text-sm text-on-surface/65">Full name
        <input class="input mt-1" name="name" value="${h(user.name)}" placeholder="Employee full name" required>
        <div class="field-error" data-error-for="name"></div>
      </label>
      <label class="block text-sm text-on-surface/65">Username
        <input class="input mt-1" name="username" value="${h(user.username)}" placeholder="Login username" required>
        <div class="field-error" data-error-for="username"></div>
      </label>
      <label class="block text-sm text-on-surface/65">Email
        <input class="input mt-1" type="email" name="email" value="${h(user.email)}" placeholder="name@example.com">
        <div class="field-error" data-error-for="email"></div>
      </label>
      <label class="block text-sm text-on-surface/65">Role
        <select class="input mt-1" name="role_id" required>${roleOptions(user.role_id)}</select>
        <div class="field-error" data-error-for="role_id"></div>
      </label>
      <label class="block text-sm text-on-surface/65">Status
        <select class="input mt-1" name="status">
          <option value="ACTIVE" ${user.status === 'ACTIVE' ? 'selected' : ''}>ACTIVE</option>
          <option value="INACTIVE" ${user.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option>
        </select>
      </label>
      <label class="block text-sm text-on-surface/65">${isEdit ? 'New password' : 'Password'}
        <input class="input mt-1" type="password" name="password" placeholder="${isEdit ? 'Leave blank to keep current password' : 'Minimum 6 characters'}" ${isEdit ? '' : 'required'}>
        <div class="field-error" data-error-for="password"></div>
      </label>
    </div>
  </form>`;
}

function collectForm(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function showErrors(root, errors = {}) {
  root.querySelectorAll('[data-error-for]').forEach(node => node.textContent = errors[node.dataset.errorFor] || '');
}

function openEmployeeModal(user = {}) {
  const root = openModal({
    title: user.id ? 'Edit System User' : 'Add System User',
    icon: user.id ? 'manage_accounts' : 'person_add',
    size: 'lg',
    subtitle: user.id ? 'Update access, role and account status.' : 'Create a login account for an employee.',
    body: employeeForm(user),
    footer: '<button class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary" data-save-employee>Save User</button>'
  });
  root.querySelector('[data-save-employee]').addEventListener('click', async event => {
    const button = event.currentTarget;
    setLoading(button, true);
    try {
      await apiFetch('<?= API_BASE ?>/employees/save.php', {method: 'POST', body: JSON.stringify(collectForm(root.querySelector('form')))});
      toast('Employee saved successfully.');
      location.reload();
    } catch (error) {
      setLoading(button, false);
      showErrors(root, error.errors || {});
      toast(error.message, 'error');
    }
  });
}

function openPasswordModal(user) {
  const root = openModal({
    title: 'Reset Password',
    icon: 'key',
    subtitle: `Set a new password for ${h(user.name)}.`,
    body: `<form id="password-form">
      <input type="hidden" name="id" value="${user.id}">
      <label class="block text-sm text-on-surface/65">New password
        <input class="input mt-1" type="password" name="password" placeholder="Minimum 6 characters" required>
        <div class="field-error" data-error-for="password"></div>
      </label>
    </form>`,
    footer: '<button class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary" data-save-password>Reset Password</button>'
  });
  root.querySelector('[data-save-password]').addEventListener('click', async event => {
    const button = event.currentTarget;
    setLoading(button, true);
    try {
      await apiFetch('<?= API_BASE ?>/employees/password.php', {method: 'POST', body: JSON.stringify(collectForm(root.querySelector('form')))});
      toast('Password reset successfully.');
      closeModal();
    } catch (error) {
      setLoading(button, false);
      showErrors(root, error.errors || {});
      toast(error.message, 'error');
    }
  });
}

document.getElementById('add-employee')?.addEventListener('click', () => openEmployeeModal());
document.getElementById('employees-table')?.addEventListener('click', async event => {
  const row = event.target.closest('tr[data-user]');
  if (!row) return;
  const user = JSON.parse(row.dataset.user);
  if (event.target.closest('[data-edit-user]')) openEmployeeModal(user);
  if (event.target.closest('[data-reset-password]')) openPasswordModal(user);
  if (event.target.closest('[data-toggle-user]')) {
    const nextStatus = user.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    const ok = await confirmAction({
      title: `${nextStatus === 'ACTIVE' ? 'Activate' : 'Deactivate'} User?`,
      message: `${user.name} will be marked ${nextStatus}.`,
      confirmText: nextStatus === 'ACTIVE' ? 'Activate' : 'Deactivate',
      severity: nextStatus === 'ACTIVE' ? 'normal' : 'danger'
    });
    if (!ok) return;
    try {
      await apiFetch('<?= API_BASE ?>/employees/status.php', {method: 'POST', body: JSON.stringify({id: user.id, status: nextStatus})});
      toast(`User ${nextStatus.toLowerCase()} successfully.`);
      location.reload();
    } catch (error) {
      toast(error.message, 'error');
    }
  }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
