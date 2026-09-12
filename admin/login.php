<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) {
    header('Location: ' . ADMIN_BASE . '/index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    if (login_user(trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        header('Location: ' . ADMIN_BASE . '/index.php');
        exit;
    }
    $error = 'Invalid login or inactive account.';
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Operator Login</title>
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <script>
    tailwind.config = { darkMode: "class", theme: { extend: { colors: {
      "brand-crimson":"#FF1E2D","status-active":"#10B981","status-busy":"#EF4444","bg-canvas":"#080808","surface-base":"#0D0D0D","surface-card":"#151515","surface-elevated":"#1A1A1A","border-subtle":"rgba(255,255,255,.08)","on-surface":"#e5e2e1"
    }, fontFamily: { body:["Inter"], display:["Space Grotesk"] } } } };
  </script>
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/app.css">
</head>
<body class="login-screen tactical-grid-bg">
  <main class="login-shell">
    <section class="login-copy" aria-label="Gaming lounge operations">
      <div class="login-status-pill">
        <span class="status-dot"></span>
        <span>Live Floor Control</span>
      </div>
      <h1>Gaming Lounge Management</h1>
      <p>Operator access for POS, sessions, inventory, PlayStation stations, debts, and reports.</p>
      <div class="login-metrics">
        <div><span>POS</span><strong>Ready</strong></div>
        <div><span>Stations</span><strong>Live</strong></div>
        <div><span>Stock</span><strong>Tracked</strong></div>
      </div>
    </section>

    <form method="post" class="login-card" autocomplete="on">
      <?= csrf_field() ?>
      <div class="login-card-head">
        <div class="login-mark">
          <span class="material-symbols-outlined">sports_esports</span>
        </div>
        <div>
          <div class="label text-brand-crimson uppercase tracking-[.18em] text-xs">Floor Master</div>
          <h2 class="font-display text-2xl font-bold uppercase text-white">Operator Login</h2>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="login-alert">
          <span class="material-symbols-outlined">error</span>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <label class="login-field">
        <span>Username</span>
        <div class="login-input-wrap">
          <span class="material-symbols-outlined">person</span>
          <input class="input" name="username" placeholder="Enter username" autocomplete="username" autofocus required>
        </div>
      </label>

      <label class="login-field">
        <span>Password</span>
        <div class="login-input-wrap">
          <span class="material-symbols-outlined">lock</span>
          <input class="input" name="password" type="password" placeholder="Enter password" autocomplete="current-password" required>
        </div>
      </label>

      <button class="login-submit" type="submit">
        <span>Enter Command Center</span>
        <span class="material-symbols-outlined">arrow_forward</span>
      </button>

      <div class="login-seed">
        <span>Seed access</span>
        <strong>admin / admin123</strong>
      </div>
    </form>
  </main>
</body>
</html>
