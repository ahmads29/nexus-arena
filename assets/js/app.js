const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function apiFetch(url, options = {}) {
  const response = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
      ...(options.headers || {})
    },
    ...options
  });
  const data = await response.json();
  if (!response.ok || data.ok === false) {
    const error = new Error(data.message || 'Request failed');
    error.payload = data;
    error.errors = data.errors || {};
    throw error;
  }
  return data;
}

function toast(message, type = 'success', title = '') {
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  const node = document.createElement('div');
  node.className = `toast ${type}`;
  const safeTitle = title || (type === 'error' ? 'Error' : type === 'warning' ? 'Warning' : 'Success');
  node.innerHTML = `<div><div class="toast-title">${safeTitle}</div><div class="text-sm text-on-surface/70">${message}</div></div><button aria-label="Close notification" class="text-on-surface/45 hover:text-white">x</button>`;
  node.querySelector('button').addEventListener('click', () => node.remove());
  stack.appendChild(node);
  setTimeout(() => node.remove(), 4200);
}

function setLoading(button, loading, text = 'Saving...') {
  if (!button) return;
  if (loading) {
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = `<span class="inline-block w-3 h-3 border border-white/40 border-t-white rounded-full animate-spin mr-2"></span>${text}`;
    button.disabled = true;
  } else {
    button.innerHTML = button.dataset.originalText || button.innerHTML;
    button.disabled = false;
  }
}

function friendlyFieldName(name = '') {
  const cleaned = String(name || 'Field')
    .replace(/^settings\[|\]$/g, '')
    .replace(/\[\]$/, '')
    .replace(/_/g, ' ');
  return cleaned.replace(/\b\w/g, char => char.toUpperCase());
}

function enhanceModalForms(root) {
  root.querySelectorAll('.modal-body form').forEach(form => {
    form.querySelectorAll('input, select, textarea').forEach(field => {
      if (field.type === 'hidden' || field.closest('label') || field.closest('.modal-field')) return;
      const label = document.createElement('label');
      label.className = 'modal-field';
      if (field.required) label.classList.add('is-required');
      const text = document.createElement('span');
      text.className = 'modal-field-label';
      text.textContent = field.getAttribute('aria-label') || field.getAttribute('placeholder') || friendlyFieldName(field.name);
      field.parentNode.insertBefore(label, field);
      label.appendChild(text);
      label.appendChild(field);
    });
    form.querySelectorAll('label').forEach(label => {
      const field = label.querySelector('input, select, textarea');
      if (field?.required) label.classList.add('is-required');
    });
  });
}

function openModal({title, body, footer = '', size = 'md', subtitle = 'Fill the required fields, then confirm to save.', icon = 'edit_square'} = {}) {
  closeModal();
  const root = document.createElement('div');
  root.className = 'modal-root is-open';
  root.innerHTML = `<div class="modal-backdrop" data-close-modal></div><section role="dialog" aria-modal="true" class="modal-panel ${size === 'sm' ? 'modal-sm' : size === 'lg' ? 'modal-lg' : ''}"><header class="modal-head"><div class="modal-title-wrap"><span class="modal-icon material-symbols-outlined">${icon}</span><div class="min-w-0"><h2 class="font-display text-lg font-bold uppercase text-white truncate">${title || ''}</h2>${subtitle ? `<p class="modal-subtitle">${subtitle}</p>` : ''}</div></div><button class="icon-btn modal-close" aria-label="Close" data-close-modal><span class="material-symbols-outlined text-base">close</span></button></header><div class="modal-body">${body || ''}</div>${footer ? `<footer class="modal-foot">${footer}</footer>` : ''}</section>`;
  document.body.appendChild(root);
  enhanceModalForms(root);
  root.addEventListener('click', event => { if (event.target.closest('[data-close-modal]')) closeModal(); });
  root.querySelector('input,select,textarea,button')?.focus();
  return root;
}

function closeModal() { document.querySelector('.modal-root')?.remove(); }

function openDrawer({title, body} = {}) {
  closeDrawer();
  const root = document.createElement('div');
  root.className = 'drawer-root is-open';
  root.innerHTML = `<div class="modal-backdrop" data-close-drawer></div><aside class="drawer-panel" role="dialog" aria-modal="true"><header class="drawer-head"><h2 class="font-display text-lg font-bold uppercase text-white">${title || ''}</h2><button class="icon-btn" aria-label="Close" data-close-drawer><span class="material-symbols-outlined text-base">close</span></button></header><div class="drawer-body">${body || ''}</div></aside>`;
  document.body.appendChild(root);
  root.addEventListener('click', event => { if (event.target.closest('[data-close-drawer]')) closeDrawer(); });
  return root;
}

function closeDrawer() { document.querySelector('.drawer-root')?.remove(); }

function confirmAction({title = 'Confirm Action', message = 'Continue?', confirmText = 'Confirm', severity = 'normal'} = {}) {
  return new Promise(resolve => {
    const danger = severity === 'danger';
    const warning = severity === 'warning';
    const root = openModal({
      title,
      size: 'sm',
      icon: danger ? 'warning' : warning ? 'help' : 'check_circle',
      subtitle: danger ? 'This action needs confirmation.' : 'Please confirm before continuing.',
      body: `<div class="modal-help">${message}</div>`,
      footer: `<button class="btn-secondary" data-confirm-no>Cancel</button><button class="${danger ? 'btn-secondary danger' : 'btn-primary'}" data-confirm-yes>${confirmText}</button>`
    });
    root.querySelector('[data-confirm-no]').addEventListener('click', () => { closeModal(); resolve(false); });
    root.querySelector('[data-confirm-yes]').addEventListener('click', () => { closeModal(); resolve(true); });
  });
}

function formatDuration(seconds) {
  seconds = Math.max(0, Math.floor(seconds || 0));
  const h = String(Math.floor(seconds / 3600)).padStart(2, '0');
  const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
  const s = String(seconds % 60).padStart(2, '0');
  return `${h}:${m}:${s}`;
}

function initStationFilters(root = document) {
  root.querySelectorAll('[data-filter-group]').forEach(group => {
    const target = document.querySelector(group.dataset.filterTarget);
    group.addEventListener('click', event => {
      const button = event.target.closest('[data-filter]');
      if (!button || !target) return;
      group.querySelectorAll('[data-filter]').forEach(item => item.classList.remove('active'));
      button.classList.add('active');
      const filter = button.dataset.filter;
      target.querySelectorAll('[data-station]').forEach(tile => {
        const haystack = `${tile.dataset.status} ${tile.dataset.type} ${tile.dataset.zone} ${tile.dataset.tier} ${tile.dataset.group}`.toLowerCase();
        tile.style.display = filter === 'all' || haystack.includes(filter.toLowerCase()) ? '' : 'none';
      });
    });
  });
}

document.addEventListener('DOMContentLoaded', () => initStationFilters());

function escapeHtml(value = '') {
  return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function publicMoney(value) {
  const cfg = window.currencyConfig || {code: 'LBP', symbol: 'LBP ', rate: 89500, decimals: 0, secondary_symbol: '$', secondary_decimals: 2};
  const base = Number(value || 0);
  const primary = cfg.code === 'LBP' ? base * Number(cfg.rate || 1) : base;
  const secondary = cfg.code === 'LBP' ? base : base * Number(cfg.rate || 1);
  const primaryText = `${cfg.symbol}${primary.toLocaleString(undefined, {minimumFractionDigits: cfg.decimals, maximumFractionDigits: cfg.decimals})}`;
  const secondarySymbol = cfg.secondary_symbol || (cfg.code === 'LBP' ? '$' : 'LBP ');
  const secondaryDecimals = Number.isInteger(cfg.secondary_decimals) ? cfg.secondary_decimals : (cfg.code === 'LBP' ? 2 : 0);
  return `${primaryText} (${secondarySymbol}${secondary.toLocaleString(undefined, {minimumFractionDigits: secondaryDecimals, maximumFractionDigits: secondaryDecimals})})`;
}

function stationStatusClass(status = '') {
  const normalized = String(status || 'OFFLINE').toLowerCase();
  if (normalized === 'available') return 'status-available';
  if (normalized === 'playing' || normalized === 'paused') return 'status-playing';
  if (normalized === 'reserved') return 'status-reserved';
  if (normalized === 'maintenance' || normalized === 'stale') return 'status-maintenance';
  return 'status-offline';
}

function renderLiveStationTile(station, kind = 'pc') {
  const status = String(station.status || 'OFFLINE').toUpperCase();
  const type = kind === 'ps' ? (station.console_type || 'PlayStation') : (station.type_name || station.tier || 'PC');
  const zone = station.zone_name || '';
  const mapped = station.mapped !== false && station.mapped !== 0;
  const rate = Number(station.hourly_rate || 0);
  const hasExternalDetails = Object.prototype.hasOwnProperty.call(station, 'username');
  const username = String(station.username || '').trim();
  const game = station.current_game || (status === 'AVAILABLE' ? (mapped && rate > 0 ? `${publicMoney(rate)}/hr` : 'iCafeCloud PC') : '');
  const elapsed = station.elapsed !== null && station.elapsed !== undefined ? formatDuration(Number(station.elapsed)) : '';
  const connection = station.connection_status || '';
  const icafeSync = station.icafe_synced || '';
  const sessionDuration = String(station.icafe_session_duration || '').trim();
  const timeLeft = String(station.icafe_time_left || '').trim();
  const detailHtml = hasExternalDetails
    ? `${username ? `
        <div class="truncate text-white/90"><span class="label text-[9px] text-on-surface/40">User:</span> ${escapeHtml(username)}</div>
      ` : '<div class="text-on-surface/55">No active user</div>'}
      ${sessionDuration ? `<div class="flex justify-between gap-2 mt-2"><span class="label text-[9px] text-on-surface/40">Session</span><span class="telemetry text-white/75">${escapeHtml(sessionDuration)}</span></div>` : ''}
      ${timeLeft ? `<div class="flex justify-between gap-2"><span class="label text-[9px] text-on-surface/40">Remaining</span><span class="telemetry text-white/75">${escapeHtml(timeLeft)}</span></div>` : ''}`
    : `${game ? `<div class="truncate text-white/85">${escapeHtml(game)}</div>` : ''}
      ${elapsed && status === 'PLAYING' ? `<div class="telemetry text-on-surface/55">${escapeHtml(elapsed)}</div>` : ''}
      ${zone ? `<div class="text-on-surface/35 truncate">${escapeHtml(zone)}</div>` : ''}`;
  return `<div class="station-tile ${stationStatusClass(status)} surface-edge cursor-pointer" data-station data-id="${escapeHtml(station.id || '')}" data-local-id="${escapeHtml(station.mapped_station_id || station.id || '')}" data-mapped="${mapped ? '1' : '0'}" data-status="${escapeHtml(status)}" data-type="${escapeHtml(type)}" data-zone="${escapeHtml(zone)}" data-tier="${escapeHtml(station.tier || type)}" data-name="${escapeHtml(station.name)}" data-rate="${escapeHtml(station.hourly_rate || '')}" data-game="${escapeHtml(game)}" data-username="${escapeHtml(username)}" data-elapsed="${escapeHtml(elapsed)}" data-connection="${escapeHtml(connection)}" data-icafe-sync="${escapeHtml(icafeSync)}" data-icafe-member="${escapeHtml(station.icafe_member_account || '')}" data-icafe-left="${escapeHtml(timeLeft)}" data-icafe-duration="${escapeHtml(sessionDuration)}" data-icafe-price="${escapeHtml(station.icafe_price_name || '')}" data-group="${escapeHtml(station.group_name || zone)}">
    <div>
      <div class="flex items-start justify-between gap-2">
        <div class="font-display text-lg font-bold text-white leading-tight">${escapeHtml(station.name)}</div>
        <span class="status-dot mt-1"></span>
      </div>
      <div class="status-text label text-[10px] uppercase tracking-[.12em] mt-1">${status === 'PLAYING' ? 'Playing' : escapeHtml(status)}</div>
    </div>
    <div class="mt-3 text-xs text-on-surface/65 min-h-[32px]">
      ${detailHtml}
      ${connection ? `<div class="label text-[9px] text-on-surface/40 mt-1">${escapeHtml(connection)}</div>` : ''}
    </div>
  </div>`;
}

function applyCurrentStationFilter(grid) {
  const group = document.querySelector(`[data-filter-target="#${grid.id}"]`);
  const active = group?.querySelector('[data-filter].active')?.dataset.filter || 'all';
  grid.querySelectorAll('[data-station]').forEach(tile => {
    const haystack = `${tile.dataset.status} ${tile.dataset.type} ${tile.dataset.zone} ${tile.dataset.tier} ${tile.dataset.group}`.toLowerCase();
    tile.style.display = active === 'all' || haystack.includes(active.toLowerCase()) ? '' : 'none';
  });
}

function updateLiveCounts(prefix, counts = {}) {
  document.querySelectorAll(`[data-live-count^="${prefix}:"]`).forEach(node => {
    const key = node.dataset.liveCount.split(':')[1];
    node.textContent = counts[key] ?? 0;
  });
}

function initPublicLiveStations() {
  const pcGrid = document.querySelector('[data-live-grid="pc"]');
  const psGrid = document.querySelector('[data-live-grid="ps"]');
  const url = pcGrid?.dataset.liveUrl || psGrid?.dataset.liveUrl;
  if (!url) return;

  const refresh = async () => {
    try {
      const liveUrl = `${url}${url.includes('?') ? '&' : '?'}_=${Date.now()}`;
      const data = await apiFetch(liveUrl, {cache: 'no-store'});
      updateLiveCounts('pc', data.pc_counts || {});
      updateLiveCounts('ps', data.ps_counts || {});
      if (pcGrid) {
        pcGrid.innerHTML = (data.pcs || []).map(station => renderLiveStationTile(station, 'pc')).join('');
        applyCurrentStationFilter(pcGrid);
      }
      if (psGrid) {
        psGrid.innerHTML = (data.playstation || []).map(station => renderLiveStationTile(station, 'ps')).join('');
        applyCurrentStationFilter(psGrid);
      }
      document.querySelectorAll('[data-live-updated]').forEach(node => {
        node.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
      });
    } catch (error) {
      console.warn('Live station refresh failed:', error);
    }
  };

  refresh();
  setInterval(refresh, 5000);
}

document.addEventListener('DOMContentLoaded', initPublicLiveStations);

function applyTableFilters(tableSelector) {
  const table = document.querySelector(tableSelector);
  if (!table) return;
  const search = document.querySelector(`[data-table-search="${tableSelector}"]`)?.value.toLowerCase() || '';
  const filters = [...document.querySelectorAll(`[data-table-filter="${tableSelector}"]`)];
  let visible = 0;
  table.querySelectorAll('tbody tr').forEach(row => {
    const text = [...row.querySelectorAll('[data-search],td')].map(cell => cell.textContent).join(' ').toLowerCase();
    let show = !search || text.includes(search);
    filters.forEach(filter => {
      if (!filter.value) return;
      const cell = row.querySelector(`[data-field="${filter.dataset.filterField}"]`);
      const value = (cell?.dataset.value || cell?.textContent || '').trim();
      if (value !== filter.value) show = false;
    });
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  const empty = document.querySelector(`[data-empty-for="${table.id}"]`);
  if (empty) empty.classList.toggle('hidden', visible > 0);
}

document.addEventListener('DOMContentLoaded', () => {
  const shell = document.querySelector('.admin-shell');
  const syncUiModeButtons = () => {
    const light = document.documentElement.classList.contains('theme-light');
    const kiosk = Boolean(document.fullscreenElement);
    document.querySelectorAll('[data-theme-icon]').forEach(icon => icon.replaceChildren(document.createTextNode(light ? 'light_mode' : 'dark_mode')));
    document.querySelectorAll('[data-kiosk-icon]').forEach(icon => icon.replaceChildren(document.createTextNode(kiosk ? 'fullscreen_exit' : 'fullscreen')));
    document.querySelector('[data-theme-toggle]')?.setAttribute('title', light ? 'Switch to dark mode' : 'Switch to light mode');
    document.querySelectorAll('[data-kiosk-toggle]').forEach(button => button.setAttribute('title', kiosk ? 'Exit kiosk mode' : 'Enter kiosk mode'));
  };
  syncUiModeButtons();
  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    const nextLight = !document.documentElement.classList.contains('theme-light');
    document.documentElement.classList.toggle('theme-light', nextLight);
    document.documentElement.classList.toggle('theme-dark', !nextLight);
    localStorage.setItem('ui:theme', nextLight ? 'light' : 'dark');
    syncUiModeButtons();
    toast(nextLight ? 'Light mode enabled.' : 'Dark mode enabled.', 'success', 'Display');
  });
  document.querySelector('[data-public-menu-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-public-mobile-nav]')?.classList.toggle('is-open');
  });
  document.querySelector('[data-public-mobile-nav]')?.addEventListener('click', event => {
    if (event.target.closest('a')) event.currentTarget.classList.remove('is-open');
  });
  document.querySelectorAll('[data-kiosk-toggle]').forEach(toggle => toggle.addEventListener('click', async () => {
    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen();
      } else {
        await document.documentElement.requestFullscreen({navigationUI: 'hide'});
      }
    } catch (error) {
      toast('Your browser blocked fullscreen. Use F11 if needed.', 'warning', 'Kiosk Mode');
    }
  }));
  document.addEventListener('fullscreenchange', () => {
    const enabled = Boolean(document.fullscreenElement);
    document.documentElement.classList.toggle('kiosk-mode', enabled);
    localStorage.setItem('ui:kiosk', enabled ? '1' : '0');
    syncUiModeButtons();
    toast(enabled ? 'Fullscreen kiosk mode enabled.' : 'Fullscreen kiosk mode disabled.', 'success', 'Display');
  });
  if (shell && localStorage.getItem('sidebar:collapsed') === '1') shell.classList.add('sidebar-collapsed');
  document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    if (matchMedia('(max-width: 900px)').matches) {
      shell?.classList.toggle('sidebar-mobile-open');
    } else {
      shell?.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sidebar:collapsed', shell?.classList.contains('sidebar-collapsed') ? '1' : '0');
    }
  });
  document.querySelectorAll('[data-sidebar-group]').forEach(group => {
    const key = `sidebar:group:${group.dataset.sidebarGroup}`;
    if (group.dataset.active === '1') localStorage.setItem(key, 'open');
    if (localStorage.getItem(key) === 'closed' && group.dataset.active !== '1') group.classList.add('is-collapsed');
    group.querySelector('[data-sidebar-group-toggle]')?.addEventListener('click', () => {
      group.classList.toggle('is-collapsed');
      localStorage.setItem(key, group.classList.contains('is-collapsed') ? 'closed' : 'open');
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { closeModal(); closeDrawer(); }
    if (event.key === 'F2') { event.preventDefault(); document.querySelector('#product-search,[data-page-search]')?.focus(); }
  });
  const params = new URLSearchParams(location.search);
  if (params.get('toast')) toast(params.get('toast'), params.get('toast_type') || 'success');
  document.querySelectorAll('[data-table-search]').forEach(input => input.addEventListener('input', () => applyTableFilters(input.dataset.tableSearch)));
  document.querySelectorAll('[data-table-filter]').forEach(input => input.addEventListener('change', () => applyTableFilters(input.dataset.tableFilter)));
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-open-template-modal]');
    if (!trigger) return;
    const template = document.getElementById(trigger.dataset.openTemplateModal);
    if (!template) return;
    openModal({
      title: trigger.dataset.modalTitle || 'Add Record',
      subtitle: trigger.dataset.modalSubtitle || 'Fill the required fields, then confirm to save.',
      body: template.innerHTML,
      icon: trigger.dataset.modalIcon || 'add_circle'
    });
  });
});

window.openAdjustStock = function(productId, productName = 'Product', currentStock = '') {
  const root = openModal({
    title: 'Adjust Stock',
    icon: 'inventory_2',
    subtitle: 'Record the adjustment reason so the stock ledger stays clear.',
    body: `<form id="stock-adjust-modal-form" class="space-y-3">
      <input type="hidden" name="product_id" value="${productId}">
      <div class="panel p-3"><div class="label text-on-surface/45">Product</div><div class="font-display text-white font-bold">${productName}</div><div class="text-sm text-on-surface/55">Current stock: ${currentStock}</div></div>
      <label class="block text-sm text-on-surface/65">Adjustment<input class="input mt-1" name="quantity_change" type="number" required placeholder="+10 or -5"><div class="field-error" data-error-for="quantity_change"></div></label>
      <label class="block text-sm text-on-surface/65">Type<select class="input mt-1" name="movement_type"><option>ADJUSTMENT</option><option>PURCHASE</option><option>DAMAGE</option><option>RETURN</option></select></label>
      <label class="block text-sm text-on-surface/65">Reason<input class="input mt-1" name="reason" placeholder="Damaged, lost, inventory count..."></label>
    </form>`,
    footer: '<button class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary" data-save-adjustment>Save Adjustment</button>'
  });
  root.querySelector('[data-save-adjustment]').addEventListener('click', async event => {
    const button = event.currentTarget;
    setLoading(button, true);
    try {
      await apiFetch('/game/api/inventory/adjust.php', {method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(root.querySelector('form')).entries()))});
      toast('Stock adjusted successfully.');
      location.reload();
    } catch (error) {
      setLoading(button, false);
      toast(error.message, 'error');
    }
  });
};
