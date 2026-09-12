<?php
declare(strict_types=1);
require_login();
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'Live operational controls for the gaming lounge floor.';
$breadcrumb = $breadcrumb ?? (($activeNav ?? '') ? ucfirst((string)$activeNav) : $pageTitle);
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle) ?> | <?= e(setting('business_name', APP_NAME)) ?></title>
  <script>
    (() => {
      const theme = localStorage.getItem('ui:theme') || 'dark';
      document.documentElement.classList.toggle('theme-light', theme === 'light');
      document.documentElement.classList.toggle('theme-dark', theme !== 'light');
    })();
  </script>
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = { darkMode: "class", theme: { extend: { colors: {
      "brand-crimson":"#FF1E2D","brand-crimson-dark":"#8B0000","status-active":"#10B981","status-busy":"#EF4444","status-maintenance":"#F59E0B","status-reserved":"#8B5CF6","bg-canvas":"#080808","surface-base":"#0D0D0D","surface-card":"#151515","surface-elevated":"#1A1A1A","surface-overlay":"#222222","border-subtle":"rgba(255,255,255,.08)","border-strong":"rgba(255,255,255,.16)","on-surface":"#e5e2e1","secondary":"#d0bcff"
    }, fontFamily: { body:["Inter"], display:["Space Grotesk"] } } } };
  </script>
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
  <script defer src="<?= APP_BASE ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</head>
<body>
<div class="admin-shell">
<?php require __DIR__ . '/admin_sidebar.php'; ?>
<div class="admin-main">
  <header class="h-16 bg-surface-base border-b border-border-subtle px-6 flex items-center justify-between sticky top-0 z-20">
    <div class="flex items-center gap-3 min-w-0">
      <button class="icon-btn shrink-0" data-sidebar-toggle aria-label="Toggle sidebar"><span class="material-symbols-outlined text-base">menu</span></button>
      <div class="min-w-0">
        <div class="label text-[10px] uppercase tracking-[.16em] text-on-surface/45 truncate"><?= e($breadcrumb) ?></div>
        <h1 class="font-display text-xl font-bold uppercase tracking-wide text-white truncate"><?= e($pageTitle) ?></h1>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="hidden lg:flex items-center gap-2 border border-border-subtle bg-surface-card rounded px-2.5 py-1">
        <span class="w-2 h-2 rounded-full bg-status-active animate-pulse"></span>
        <span class="label text-[10px] text-status-active uppercase">Live</span>
        <span class="text-on-surface/25">|</span>
        <span class="label text-[10px] text-on-surface/55 uppercase">LAN: 10 Gbps</span>
      </div>
      <button class="icon-btn" aria-label="Notifications" onclick="toast('No unread operational alerts.', 'success', 'Notifications')"><span class="material-symbols-outlined text-base">notifications</span></button>
      <button class="icon-btn" data-theme-toggle aria-label="Toggle light and dark mode" title="Light / Dark Mode"><span class="material-symbols-outlined text-base" data-theme-icon>dark_mode</span></button>
      <button class="icon-btn" data-kiosk-toggle aria-label="Toggle kiosk mode" title="Kiosk Mode"><span class="material-symbols-outlined text-base" data-kiosk-icon>fullscreen</span></button>
      <div class="hidden md:block text-right">
        <div class="label text-xs text-white"><?= e(current_user()['name'] ?? 'Operator') ?></div>
        <div class="text-[11px] text-on-surface/45"><?= e(current_user()['role'] ?? 'Staff') ?></div>
      </div>
      <form action="<?= ADMIN_BASE ?>/logout.php" method="post">
        <?= csrf_field() ?>
        <button class="btn-secondary">Lock</button>
      </form>
    </div>
  </header>
  <main class="p-6">
    <section class="page-toolbar">
      <div>
        <p class="text-sm text-on-surface/60"><?= e($pageDescription) ?></p>
      </div>
      <?= $pageAction ?? '' ?>
    </section>
