<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$business = setting('business_name', 'NEXUS ARENA');
$description = setting('business_description', 'High performance gaming stations, PlayStation lounges, fast internet, and competitive energy.');
ensure_website_content_schema();
if (icafecloud_is_enabled()) {
    $pcs = icafecloud_live_pc_rows(false);
    $pcCounts = station_counts_from_rows($pcs);
} else {
    $pcCounts = ['TOTAL' => 0, 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0, 'OFFLINE' => 0, 'STALE' => 0];
    foreach (db()->query("SELECT status, COUNT(*) count FROM stations WHERE active=1 GROUP BY status")->fetchAll() as $row) {
        $pcCounts[$row['status']] = (int)$row['count'];
        $pcCounts['TOTAL'] += (int)$row['count'];
    }
    $pcs = db()->query("SELECT s.*, st.name type_name, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM stations s JOIN station_types st ON st.id=s.station_type_id LEFT JOIN station_zones z ON z.id=s.station_zone_id LEFT JOIN gaming_sessions gs ON gs.station_id=s.id AND gs.status IN ('ACTIVE','PAUSED') WHERE s.active=1 ORDER BY s.name")->fetchAll();
}
$ps = db()->query("SELECT p.*, z.name zone_name, gs.current_game, TIMESTAMPDIFF(SECOND, gs.start_time, NOW()) elapsed FROM playstation_stations p LEFT JOIN station_zones z ON z.id=p.station_zone_id LEFT JOIN gaming_sessions gs ON gs.playstation_station_id=p.id AND gs.status IN ('ACTIVE','PAUSED') WHERE p.active=1 ORDER BY p.console_type,p.name")->fetchAll();
$games = db()->query('SELECT name, image_path FROM games WHERE active=1 ORDER BY sort_order,name LIMIT 16')->fetchAll();
$pricingItems = db()->query('SELECT title, category, price, unit FROM pricing_items WHERE active=1 ORDER BY sort_order,title')->fetchAll();
$zones = db()->query('SELECT name FROM station_zones ORDER BY name')->fetchAll();
$mapEmbedUrl = trim((string)setting('map_embed_url', ''));
$mapsUrl = trim((string)setting('google_maps_url', '#')) ?: '#';
$locationCta = trim((string)setting('location_cta_label', 'Open Location')) ?: 'Open Location';
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($business) ?> - Gaming Lounge</title>
  <script>
    (() => {
      const theme = localStorage.getItem('ui:theme') || 'dark';
      document.documentElement.classList.toggle('theme-light', theme === 'light');
      document.documentElement.classList.toggle('theme-dark', theme !== 'light');
    })();
  </script>
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>tailwind.config={darkMode:"class",theme:{extend:{colors:{"brand-crimson":"#FF1E2D","brand-crimson-dark":"#8B0000","status-active":"#10B981","status-busy":"#EF4444","status-maintenance":"#F59E0B","status-reserved":"#8B5CF6","bg-canvas":"#080808","surface-base":"#0D0D0D","surface-card":"#151515","surface-elevated":"#1A1A1A","border-subtle":"rgba(255,255,255,.08)","border-strong":"rgba(255,255,255,.16)","on-surface":"#e5e2e1","secondary":"#d0bcff"},fontFamily:{body:["Inter"],display:["Space Grotesk"]}}}};</script>
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
  <script>window.currencyConfig = <?= json_encode(currency_config(), JSON_THROW_ON_ERROR) ?>;</script>
  <script defer src="<?= APP_BASE ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</head>
<body class="public-site bg-bg-canvas text-on-surface antialiased min-h-screen font-body overflow-x-hidden">
<header class="public-header sticky top-0 z-50 border-b border-border-subtle bg-surface-base/90 backdrop-blur-md">
  <div class="flex justify-between items-center px-6 py-3">
    <a class="flex items-center gap-2" href="#hero"><span class="w-8 h-8 rounded bg-surface-elevated border border-brand-crimson/50 flex items-center justify-center text-brand-crimson material-symbols-outlined">sports_esports</span><span class="font-display text-xl font-bold tracking-wider uppercase"><?= e($business) ?></span></a>
    <nav class="public-nav hidden md:flex gap-6 label text-xs uppercase">
      <a class="text-brand-crimson" href="#stations">Gaming PCs</a><a class="text-on-surface/70 hover:text-white" href="#playstation">PlayStation</a><a class="text-on-surface/70 hover:text-white" href="#experience">Experience</a><a class="text-on-surface/70 hover:text-white" href="#pricing">Pricing</a><a class="text-on-surface/70 hover:text-white" href="#location">Location</a>
    </nav>
    <div class="flex items-center gap-2">
      <button class="icon-btn" data-theme-toggle aria-label="Toggle light and dark mode" title="Light / Dark Mode"><span class="material-symbols-outlined text-base" data-theme-icon>dark_mode</span></button>
      <button class="icon-btn md:hidden" data-public-menu-toggle aria-label="Open navigation" title="Menu"><span class="material-symbols-outlined text-base">menu</span></button>
      <a class="btn-primary px-4 py-2 rounded label uppercase hidden sm:inline-flex" href="#stations">Book Now</a>
    </div>
  </div>
  <nav class="public-mobile-nav md:hidden" data-public-mobile-nav>
    <a href="#stations">Gaming PCs</a>
    <a href="#playstation">PlayStation</a>
    <a href="#experience">Experience</a>
    <a href="#pricing">Pricing</a>
    <a href="#location">Location</a>
    <a class="public-mobile-cta" href="#stations">Book Now</a>
  </nav>
</header>
<main>
  <section id="hero" class="relative py-16 px-6 tactical-grid-bg border-b border-border-subtle overflow-hidden">
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-brand-crimson/15 blur-[120px] pointer-events-none rounded-full"></div>
    <div class="max-w-7xl mx-auto relative z-10">
      <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded bg-surface-card border border-brand-crimson/40 mb-6"><span class="w-2 h-2 rounded-full bg-brand-crimson animate-pulse"></span><span class="label text-[10px] tracking-widest uppercase text-brand-crimson">Live gaming lounge telemetry</span></div>
      <h1 class="font-display text-4xl md:text-6xl text-white font-bold uppercase leading-tight mb-4"><?= e($business) ?><br><span class="text-brand-crimson">Ready To Play.</span></h1>
      <p class="text-on-surface/80 text-lg max-w-2xl mb-8"><?= e($description) ?></p>
      <div class="flex flex-wrap gap-3 mb-10"><a class="btn-primary px-7 py-3 rounded label uppercase" href="#stations">Book Your Station</a><a class="btn-secondary px-7 py-3 rounded label uppercase" href="#pricing">View Pricing</a></div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach ([['Total PCs',$pcCounts['TOTAL'],'pc:TOTAL'],['Available',$pcCounts['AVAILABLE'],'pc:AVAILABLE'],['Playing',$pcCounts['PLAYING'],'pc:PLAYING'],['PlayStations',count($ps),'ps:TOTAL']] as [$label,$value,$liveKey]): ?>
        <div class="panel p-4"><div class="label text-[10px] uppercase tracking-wider text-on-surface/55"><?= e($label) ?></div><div class="telemetry text-3xl text-white font-bold" data-live-count="<?= e($liveKey) ?>"><?= e($value) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="stations" class="py-12 px-6 bg-surface-base border-b border-border-subtle">
    <div class="max-w-7xl mx-auto">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div><div class="label text-[10px] uppercase tracking-widest text-brand-crimson">Arena Telemetry Radar</div><h2 class="font-display text-3xl text-white font-bold uppercase">Live Gaming Stations</h2><div class="text-xs text-on-surface/45 mt-1">Live updated <span class="telemetry" data-live-updated>loading</span></div></div>
        <div class="grid grid-cols-5 gap-2 text-center">
          <?php foreach ([['TOTAL PCs','TOTAL'],['AVAILABLE','AVAILABLE'],['PLAYING','PLAYING'],['RESERVED','RESERVED'],['MAINTENANCE','MAINTENANCE']] as [$label,$key]): ?>
          <div class="panel px-3 py-2"><div class="label text-[9px] text-on-surface/45"><?= e($label) ?></div><div class="telemetry font-bold" data-live-count="pc:<?= e($key) ?>"><?= e($pcCounts[$key]) ?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 mb-6" data-filter-group data-filter-target="#pc-grid">
        <?php foreach (['all'=>'All','AVAILABLE'=>'Available','PLAYING'=>'Playing','RESERVED'=>'Reserved','MAINTENANCE'=>'Maintenance','Standard'=>'Standard','Pro'=>'Pro','VIP'=>'VIP'] as $key=>$label): ?><button class="filter-chip <?= $key==='all'?'active':'' ?>" data-filter="<?= e($key) ?>"><?= e($label) ?></button><?php endforeach; ?>
        <?php foreach ($zones as $zone): ?><button class="filter-chip" data-filter="<?= e($zone['name']) ?>"><?= e($zone['name']) ?></button><?php endforeach; ?>
      </div>
      <div class="station-grid" id="pc-grid" data-live-grid="pc" data-live-url="<?= API_BASE ?>/stations/live.php"><?php foreach ($pcs as $row): require __DIR__ . '/../includes/station_tile.php'; endforeach; ?></div>
    </div>
  </section>

  <section id="playstation" class="py-12 px-6 bg-bg-canvas border-b border-border-subtle">
    <div class="max-w-7xl mx-auto">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div><div class="label text-[10px] uppercase tracking-widest text-secondary">Console Lounge Radar</div><h2 class="font-display text-3xl text-white font-bold uppercase">Live PlayStation Stations</h2></div>
        <div class="grid grid-cols-5 gap-2 text-center">
          <?php
          $psCounts = ['TOTAL' => count($ps), 'AVAILABLE' => 0, 'PLAYING' => 0, 'RESERVED' => 0, 'MAINTENANCE' => 0];
          foreach ($ps as $row) { $psCounts[$row['status']] = ($psCounts[$row['status']] ?? 0) + 1; }
          foreach ([['TOTAL PS','TOTAL'],['AVAILABLE','AVAILABLE'],['PLAYING','PLAYING'],['RESERVED','RESERVED'],['MAINTENANCE','MAINTENANCE']] as [$label,$key]):
          ?>
            <div class="panel px-3 py-2"><div class="label text-[9px] text-on-surface/45"><?= e($label) ?></div><div class="telemetry font-bold" data-live-count="ps:<?= e($key) ?>"><?= e($psCounts[$key]) ?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 mb-6" data-filter-group data-filter-target="#ps-grid"><?php foreach (['all'=>'All','PS4'=>'PS4','PS5'=>'PS5','AVAILABLE'=>'Available','PLAYING'=>'Playing','RESERVED'=>'Reserved','MAINTENANCE'=>'Maintenance'] as $key=>$label): ?><button class="filter-chip <?= $key==='all'?'active':'' ?>" data-filter="<?= e($key) ?>"><?= e($label) ?></button><?php endforeach; ?></div>
      <div class="station-grid" id="ps-grid" data-live-grid="ps" data-live-url="<?= API_BASE ?>/stations/live.php"><?php foreach ($ps as $row): $isPs = true; require __DIR__ . '/../includes/station_tile.php'; unset($isPs); endforeach; ?></div>
    </div>
  </section>

  <section id="experience" class="py-12 px-6 bg-surface-base border-b border-border-subtle"><div class="max-w-7xl mx-auto"><h2 class="font-display text-3xl text-white font-bold uppercase mb-6">Gaming Experience</h2><div class="grid md:grid-cols-3 gap-4">
    <?php foreach (['High Performance PCs'=>'desktop_windows','PlayStation Gaming'=>'sports_esports','High-Speed Internet'=>'speed','Premium Peripherals'=>'keyboard','Comfortable Gaming Stations'=>'event_seat','Competitive Environment'=>'emoji_events'] as $label=>$icon): ?><div class="panel p-5"><span class="material-symbols-outlined text-brand-crimson mb-4"><?= e($icon) ?></span><h3 class="font-display font-bold text-white uppercase"><?= e($label) ?></h3></div><?php endforeach; ?>
  </div></div></section>

  <section id="games" class="py-12 px-6 bg-bg-canvas border-b border-border-subtle">
    <div class="max-w-7xl mx-auto">
      <h2 class="font-display text-3xl text-white font-bold uppercase mb-6">Popular Games</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php foreach ($games as $game): ?>
          <div class="panel overflow-hidden">
            <div class="h-28 bg-surface-elevated border-b border-border-subtle flex items-center justify-center text-brand-crimson">
              <?php if (!empty($game['image_path'])): ?>
                <img class="w-full h-full object-cover" src="<?= APP_BASE ?>/<?= e($game['image_path']) ?>" alt="<?= e($game['name']) ?>">
              <?php else: ?>
                <span class="material-symbols-outlined text-3xl">stadia_controller</span>
              <?php endif; ?>
            </div>
            <div class="p-4 label uppercase text-white truncate"><?= e($game['name']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="pricing" class="py-12 px-6 bg-surface-base border-b border-border-subtle">
    <div class="max-w-7xl mx-auto">
      <h2 class="font-display text-3xl text-white font-bold uppercase mb-6">Pricing</h2>
      <div class="grid md:grid-cols-<?= count($pricingItems) >= 5 ? '5' : '3' ?> gap-4">
        <?php foreach ($pricingItems as $item): ?>
          <div class="panel p-5">
            <div class="label uppercase text-on-surface/55"><?= e($item['category']) ?></div>
            <div class="font-display text-white font-bold uppercase mt-1"><?= e($item['title']) ?></div>
            <div class="telemetry text-2xl text-brand-crimson font-bold mt-2"><?= money($item['price']) ?> / <?= e($item['unit']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="py-12 px-6 bg-bg-canvas border-b border-border-subtle"><div class="max-w-7xl mx-auto"><h2 class="font-display text-3xl text-white font-bold uppercase mb-6">Why Choose Us</h2><div class="grid md:grid-cols-4 gap-4"><?php foreach (['Real-time station availability','Fast session start and checkout','Clean competitive hardware','Transparent hourly pricing'] as $text): ?><div class="panel p-4 text-on-surface/75"><?= e($text) ?></div><?php endforeach; ?></div></div></section>

  <section id="location" class="py-12 px-6 bg-surface-base border-b border-border-subtle">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-6 items-stretch">
      <div class="panel p-6">
        <h2 class="font-display text-3xl text-white font-bold uppercase mb-4">Location & Hours</h2>
        <div class="space-y-3 text-on-surface/75">
          <div class="flex gap-3"><span class="material-symbols-outlined text-brand-crimson">location_on</span><span><?= nl2br(e(setting('address', ''))) ?></span></div>
          <div class="flex gap-3"><span class="material-symbols-outlined text-brand-crimson">call</span><span><?= e(setting('phone', '')) ?></span></div>
          <div class="flex gap-3"><span class="material-symbols-outlined text-brand-crimson">schedule</span><span><?= nl2br(e(setting('opening_hours', ''))) ?></span></div>
        </div>
        <a class="btn-primary px-6 py-3 rounded label uppercase text-center inline-flex mt-6" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener"><?= e($locationCta) ?></a>
      </div>
      <div class="panel overflow-hidden min-h-[320px] bg-surface-elevated">
        <?php if ($mapEmbedUrl !== ''): ?>
          <iframe class="w-full h-full min-h-[320px]" src="<?= e($mapEmbedUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        <?php else: ?>
          <div class="h-full min-h-[320px] flex items-center justify-center text-center p-6">
            <div>
              <span class="material-symbols-outlined text-brand-crimson text-4xl">map</span>
              <div class="font-display text-white uppercase font-bold mt-3">Map Not Configured</div>
              <div class="text-sm text-on-surface/55 mt-1">Add a Google Maps embed URL from Website Content.</div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="relative py-14 px-6 bg-bg-canvas tactical-grid-bg text-center"><h2 class="font-display text-4xl text-white font-bold uppercase mb-3">Ready To Play?</h2><p class="text-on-surface/70 mb-6">Grab your setup, join your squad, and start gaming.</p><a class="btn-primary px-8 py-4 rounded label uppercase inline-flex" href="#stations">Book Your Station</a></section>
</main>
<footer class="bg-surface-base border-t border-border-subtle px-6 py-8 text-on-surface/55"><div class="max-w-7xl mx-auto flex flex-col md:flex-row gap-4 justify-between"><div><div class="font-display text-white font-bold uppercase"><?= e($business) ?></div><div class="text-sm"><?= e($description) ?></div></div><div class="text-sm"><?= e(setting('phone', '')) ?> | <?= e(setting('email', '')) ?></div></div></footer>
</body>
</html>
