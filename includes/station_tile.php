<?php
$status = strtoupper($row['status'] ?? 'OFFLINE');
$klass = station_status_class($status);
$title = $row['name'];
$type = $isPs ?? false ? ($row['console_type'] ?? 'PlayStation') : ($row['type_name'] ?? $row['tier'] ?? 'PC');
$zone = $row['zone_name'] ?? '';
$game = $row['current_game'] ?: ($status === 'AVAILABLE' ? money($row['hourly_rate']) . '/hr' : ($row['notes'] ?? ''));
$elapsed = isset($row['elapsed']) && $row['elapsed'] !== null ? sprintf('%02d:%02d:%02d', floor($row['elapsed'] / 3600), floor(($row['elapsed'] % 3600) / 60), $row['elapsed'] % 60) : '';
?>
<div class="station-tile <?= e($klass) ?> surface-edge cursor-pointer" data-station data-id="<?= e($row['id'] ?? '') ?>" data-status="<?= e($status) ?>" data-type="<?= e($type) ?>" data-zone="<?= e($zone) ?>" data-tier="<?= e($row['tier'] ?? $type) ?>" data-name="<?= e($title) ?>" data-rate="<?= e($row['hourly_rate'] ?? '') ?>" data-game="<?= e($game ?? '') ?>" data-elapsed="<?= e($elapsed ?? '') ?>">
  <div>
    <div class="flex items-start justify-between gap-2">
      <div class="font-display text-lg font-bold text-white leading-tight"><?= e($title) ?></div>
      <span class="status-dot mt-1"></span>
    </div>
    <div class="status-text label text-[10px] uppercase tracking-[.12em] mt-1"><?= e($status === 'PLAYING' ? 'Playing' : $status) ?></div>
  </div>
  <div class="mt-3 text-xs text-on-surface/65 min-h-[32px]">
    <?php if ($game): ?><div class="truncate text-white/85"><?= e($game) ?></div><?php endif; ?>
    <?php if ($elapsed): ?><div class="telemetry text-on-surface/55"><?= e($elapsed) ?></div><?php endif; ?>
    <?php if ($zone): ?><div class="text-on-surface/35 truncate"><?= e($zone) ?></div><?php endif; ?>
  </div>
</div>
