<?php
$recentAudit = [];
try {
    $recentAudit = db()->query('SELECT action, entity_type, created_at FROM audit_logs ORDER BY id DESC LIMIT 3')->fetchAll();
} catch (Throwable) {}
?>
  </main>
  <footer class="h-10 bg-surface-base border-t border-border-subtle px-6 flex items-center justify-between text-xs text-on-surface/50">
    <div class="flex items-center gap-3 min-w-0">
      <span class="label text-brand-crimson uppercase">Live Audit Feed</span>
      <span class="truncate">
        <?php foreach ($recentAudit as $log): ?>
          <?= e(date('H:i:s', strtotime($log['created_at']))) ?> - <?= e($log['action']) ?> <?= e($log['entity_type']) ?> &nbsp;
        <?php endforeach; ?>
      </span>
    </div>
    <span class="hidden md:inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-status-active"></span> Sync Active</span>
  </footer>
  <button class="kiosk-exit" data-kiosk-toggle aria-label="Exit kiosk mode" title="Exit kiosk mode"><span class="material-symbols-outlined text-base" data-kiosk-icon>fullscreen_exit</span></button>
</div>
</div>
</body>
</html>
