<?php
$status = strtoupper($row['status'] ?? 'OFFLINE');
$klass = station_status_class($status);
$title = $row['name'];
$type = $isPs ?? false ? ($row['console_type'] ?? 'PlayStation') : ($row['type_name'] ?? $row['tier'] ?? 'PC');
$zone = $row['zone_name'] ?? '';
$mapped = array_key_exists('mapped', $row) ? (bool)$row['mapped'] : true;
$rate = (float)($row['hourly_rate'] ?? 0);
$hasExternalDetails = array_key_exists('username', $row);
$username = trim((string)($row['username'] ?? ''));
$game = $row['current_game'] ?: ($status === 'AVAILABLE' ? ($mapped && $rate > 0 ? money($rate) . '/hr' : 'iCafeCloud PC') : ($row['notes'] ?? ''));
$elapsed = isset($row['elapsed']) && $row['elapsed'] !== null ? sprintf('%02d:%02d:%02d', floor($row['elapsed'] / 3600), floor(($row['elapsed'] % 3600) / 60), $row['elapsed'] % 60) : '';
$sessionDuration = trim((string)($row['icafe_session_duration'] ?? ''));
$timeLeft = trim((string)($row['icafe_time_left'] ?? ''));
$connection = isset($row['icafe_connected']) ? ((int)$row['icafe_connected'] === 1 ? 'ONLINE' : 'OFFLINE') : ($row['connection_status'] ?? '');
$syncStatus = $row['icafe_sync_status'] ?? ($row['icafe_synced'] ?? '');
?>
<div class="station-tile <?= e($klass) ?> surface-edge cursor-pointer" data-station data-id="<?= e($row['id'] ?? '') ?>" data-local-id="<?= e($row['mapped_station_id'] ?? $row['id'] ?? '') ?>" data-mapped="<?= $mapped ? '1' : '0' ?>" data-status="<?= e($status) ?>" data-type="<?= e($type) ?>" data-zone="<?= e($zone) ?>" data-tier="<?= e($row['tier'] ?? $type) ?>" data-name="<?= e($title) ?>" data-rate="<?= e($row['hourly_rate'] ?? '') ?>" data-game="<?= e($game ?? '') ?>" data-username="<?= e($username) ?>" data-elapsed="<?= e($elapsed ?? '') ?>" data-connection="<?= e($connection) ?>" data-icafe-sync="<?= e($syncStatus) ?>" data-icafe-member="<?= e($row['icafe_member_account'] ?? '') ?>" data-icafe-left="<?= e($timeLeft) ?>" data-icafe-duration="<?= e($sessionDuration) ?>" data-icafe-price="<?= e($row['icafe_price_name'] ?? '') ?>" data-group="<?= e($row['group_name'] ?? $zone) ?>">
  <div>
    <div class="flex items-start justify-between gap-2">
      <div class="font-display text-lg font-bold text-white leading-tight"><?= e($title) ?></div>
      <span class="status-dot mt-1"></span>
    </div>
    <div class="status-text label text-[10px] uppercase tracking-[.12em] mt-1"><?= e($status === 'PLAYING' ? 'Playing' : $status) ?></div>
  </div>
  <div class="mt-3 text-xs text-on-surface/65 min-h-[32px]">
    <?php if ($hasExternalDetails): ?>
      <?php if ($username): ?>
        <div class="truncate text-white/90"><span class="label text-[9px] text-on-surface/40">User:</span> <?= e($username) ?></div>
      <?php else: ?>
        <div class="text-on-surface/55">No active user</div>
      <?php endif; ?>
      <?php if ($sessionDuration): ?><div class="flex justify-between gap-2 mt-2"><span class="label text-[9px] text-on-surface/40">Session</span><span class="telemetry text-white/75"><?= e($sessionDuration) ?></span></div><?php endif; ?>
      <?php if ($timeLeft): ?><div class="flex justify-between gap-2"><span class="label text-[9px] text-on-surface/40">Remaining</span><span class="telemetry text-white/75"><?= e($timeLeft) ?></span></div><?php endif; ?>
    <?php else: ?>
      <?php if ($game): ?><div class="truncate text-white/85"><?= e($game) ?></div><?php endif; ?>
      <?php if ($elapsed): ?><div class="telemetry text-on-surface/55"><?= e($elapsed) ?></div><?php endif; ?>
      <?php if ($zone): ?><div class="text-on-surface/35 truncate"><?= e($zone) ?></div><?php endif; ?>
    <?php endif; ?>
    <?php if ($connection): ?><div class="label text-[9px] text-on-surface/40 mt-1"><?= e($connection) ?></div><?php endif; ?>
  </div>
</div>
