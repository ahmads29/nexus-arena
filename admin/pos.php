<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'pos';
$pageTitle = 'POS Register';
require_permission('pos.use');
$categories = db()->query("SELECT * FROM categories WHERE status='ACTIVE' ORDER BY name")->fetchAll();
$products = db()->query("SELECT p.*, c.name category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='ACTIVE' ORDER BY p.name")->fetchAll();
$sessions = db()->query("SELECT gs.id, gs.session_code, gs.current_game, gs.start_time, s.name pc_name, ps.name ps_name FROM gaming_sessions gs LEFT JOIN stations s ON s.id=gs.station_id LEFT JOIN playstation_stations ps ON ps.id=gs.playstation_station_id WHERE gs.status IN ('ACTIVE','PAUSED') ORDER BY gs.start_time")->fetchAll();
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="grid xl:grid-cols-[220px_1fr_390px] gap-4 min-h-[calc(100vh-170px)]">
  <aside class="panel p-3">
    <h2 class="label uppercase text-on-surface/45 mb-3">Categories</h2>
    <div class="space-y-2" data-filter-group data-filter-target="#product-grid">
      <button class="filter-chip active w-full text-left" data-filter="all">All</button>
      <?php foreach ($categories as $cat): ?><button class="filter-chip w-full text-left" data-filter="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></button><?php endforeach; ?>
    </div>
    <input id="product-search" class="input mt-4" placeholder="Search / barcode">
  </aside>
  <div class="panel p-3 overflow-auto">
    <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
      <?php foreach ($products as $p): ?>
        <button class="product-tile panel p-3 text-left hover:border-brand-crimson/60 transition <?= (int)$p['current_stock'] <= 0 ? 'opacity-50' : '' ?>" data-station data-status="<?= e($p['category']) ?>" data-id="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-price="<?= e($p['selling_price']) ?>" data-stock="<?= e($p['current_stock']) ?>" data-barcode="<?= e($p['barcode']) ?>">
          <div class="h-20 bg-surface-elevated border border-border-subtle rounded mb-2 flex items-center justify-center text-brand-crimson"><span class="material-symbols-outlined">fastfood</span></div>
          <div class="font-display font-bold text-white truncate"><?= e($p['name']) ?></div>
          <div class="flex justify-between text-xs mt-1"><span class="telemetry"><?= money($p['selling_price']) ?></span><span class="<?= (int)$p['current_stock'] <= 0 ? 'out-stock' : ((int)$p['current_stock'] <= (int)$p['minimum_stock'] ? 'low-stock' : 'text-on-surface/50') ?>"><?= (int)$p['current_stock'] <= 0 ? 'OUT OF STOCK' : 'Stock ' . e($p['current_stock']) ?></span></div>
          <?php if ($p['sku']): ?><div class="text-[10px] text-on-surface/35 mt-1"><?= e($p['sku']) ?></div><?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
  <aside class="panel flex flex-col overflow-hidden">
    <div class="p-4 border-b border-border-subtle space-y-3">
      <h2 class="font-display text-lg font-bold uppercase">Current Cart</h2>
      <select id="session-id" class="input"><option value="">Walk-In Sale</option><?php foreach ($sessions as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['pc_name'] ?: $s['ps_name']) ?> - active session</option><?php endforeach; ?></select>
    </div>
    <div id="cart-items" class="flex-1 overflow-auto p-4 space-y-2"></div>
    <div class="p-4 border-t border-border-subtle space-y-3">
      <div class="flex justify-between text-sm"><span>Product Subtotal</span><span id="subtotal" class="telemetry">$0.00</span></div>
      <div class="grid grid-cols-2 gap-2"><input id="discount" class="input" type="number" step="0.01" value="0" placeholder="Discount"><select id="payment-method" class="input"><option>CASH</option><option>CARD</option><option>OTHER</option></select></div>
      <input id="paid-amount" class="input" type="number" step="0.01" placeholder="Paid amount (blank = full)">
      <div class="flex justify-between items-baseline border-t border-border-subtle pt-2"><span class="label uppercase">Grand Total</span><span id="grand-total" class="telemetry text-2xl text-brand-crimson font-bold">$0.00</span></div>
      <div class="grid grid-cols-2 gap-2"><button id="clear-cart" class="btn-secondary py-3 rounded">Clear</button><button id="complete-sale" class="btn-primary py-3 rounded label uppercase">Complete Sale</button></div>
    </div>
  </aside>
</section>
<script>
let cart = new Map();
const currencyConfig = <?= json_encode(currency_config(), JSON_THROW_ON_ERROR) ?>;
const invoicePrintingEnabled = <?= setting('enable_invoice_printing', '1') === '1' ? 'true' : 'false' ?>;
const fmt = value => {
  const base = Number(value || 0);
  const primary = currencyConfig.code === 'LBP' ? base * Number(currencyConfig.rate) : base;
  const secondary = currencyConfig.code === 'LBP' ? base : base * Number(currencyConfig.rate);
  const primaryText = `${currencyConfig.symbol}${primary.toLocaleString(undefined, {minimumFractionDigits: currencyConfig.decimals, maximumFractionDigits: currencyConfig.decimals})}`;
  return `${primaryText} (${currencyConfig.secondary_symbol}${secondary.toLocaleString(undefined, {minimumFractionDigits: currencyConfig.secondary_decimals, maximumFractionDigits: currencyConfig.secondary_decimals})})`;
};
function renderCart() {
  const wrap = document.getElementById('cart-items');
  wrap.innerHTML = '';
  let subtotal = 0;
  for (const item of cart.values()) {
    subtotal += item.qty * item.price;
    wrap.insertAdjacentHTML('beforeend', `<div class="panel p-2 flex items-center justify-between gap-2">
      <div class="min-w-0"><div class="font-semibold text-white truncate">${item.name}</div><div class="text-xs text-on-surface/45">${fmt(item.price)} x ${item.qty}</div></div>
      <div class="flex items-center gap-1"><button class="btn-secondary px-2" data-dec="${item.id}">-</button><span class="w-7 text-center">${item.qty}</span><button class="btn-secondary px-2" data-inc="${item.id}">+</button><button class="text-status-busy px-2" data-remove="${item.id}">x</button></div>
    </div>`);
  }
  const discount = Number(document.getElementById('discount').value || 0);
  document.getElementById('subtotal').textContent = fmt(subtotal);
  document.getElementById('grand-total').textContent = fmt(Math.max(0, subtotal - discount));
}
document.getElementById('product-grid').addEventListener('click', event => {
  const tile = event.target.closest('.product-tile');
  if (!tile) return;
  const id = tile.dataset.id, stock = Number(tile.dataset.stock), existing = cart.get(id)?.qty || 0;
  if (stock <= existing) return toast('Insufficient stock.', 'warning');
  cart.set(id, {id, name: tile.dataset.name, price: Number(tile.dataset.price), stock, qty: existing + 1});
  renderCart();
});
document.getElementById('cart-items').addEventListener('click', event => {
  const inc = event.target.dataset.inc, dec = event.target.dataset.dec, remove = event.target.dataset.remove;
  if (inc) { const item = cart.get(inc); if (item.qty >= item.stock) return toast('Insufficient stock.', 'warning'); item.qty++; }
  if (dec) { const item = cart.get(dec); item.qty--; if (item.qty <= 0) cart.delete(dec); }
  if (remove) cart.delete(remove);
  renderCart();
});
document.getElementById('discount').addEventListener('input', renderCart);
document.getElementById('clear-cart').addEventListener('click', async () => { if (cart.size && await confirmAction({title:'Clear Cart?', message:'Remove every item from the current cart?', confirmText:'Clear Cart', severity:'warning'})) { cart.clear(); renderCart(); toast('Cart cleared.'); } });
document.getElementById('product-search').addEventListener('input', event => {
  const q = event.target.value.toLowerCase();
  document.querySelectorAll('.product-tile').forEach(tile => tile.style.display = (tile.dataset.name.toLowerCase().includes(q) || tile.dataset.barcode.includes(q)) ? '' : 'none');
});
document.getElementById('complete-sale').addEventListener('click', async () => {
  const button = document.getElementById('complete-sale');
  setLoading(button, true, 'Completing...');
  try {
    const data = await apiFetch('<?= API_BASE ?>/pos/checkout.php', {method:'POST', body: JSON.stringify({
      session_id: document.getElementById('session-id').value || null,
      customer_id: null,
      discount: document.getElementById('discount').value || 0,
      payment_method: document.getElementById('payment-method').value,
      paid_amount: document.getElementById('paid-amount').value,
      items: [...cart.values()].map(i => ({product_id:i.id, quantity:i.qty}))
    })});
    toast(`Sale completed successfully: ${data.invoice_number}`);
    if (invoicePrintingEnabled) {
      setTimeout(() => location.href = `<?= ADMIN_BASE ?>/receipt.php?id=${data.sale_id}&print=1`, 450);
      return;
    }
    cart.clear();
    document.getElementById('discount').value = 0;
    document.getElementById('paid-amount').value = '';
    renderCart();
    setLoading(button, false);
  } catch (error) { setLoading(button, false); toast(error.message, 'error'); }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
