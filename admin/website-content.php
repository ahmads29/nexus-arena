<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = 'website-content';
$pageTitle = 'Website Content';
$pageDescription = 'Manage homepage games, pricing, location, hours and map content.';
require_permission('settings.manage');
ensure_website_content_schema();

function upload_game_image(string $field, ?string $existing = null): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Game image upload failed.');
    }
    if ((int)$_FILES[$field]['size'] > 2_500_000) {
        throw new RuntimeException('Game image must be smaller than 2.5MB.');
    }
    $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        throw new RuntimeException('Game image must be JPG, PNG, WEBP or GIF.');
    }
    $dir = __DIR__ . '/../assets/images/games';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'game-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('Could not save uploaded game image.');
    }
    return 'assets/images/games/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_game') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $sort = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            if ($name === '') {
                throw new RuntimeException('Game name is required.');
            }
            $existing = trim((string)($_POST['existing_image_path'] ?? '')) ?: null;
            $image = upload_game_image('image', $existing);
            if ($id > 0) {
                db()->prepare('UPDATE games SET name=?, image_path=?, sort_order=?, active=? WHERE id=?')
                    ->execute([$name, $image, $sort, $active, $id]);
                audit_log('UPDATE_WEBSITE_GAME', 'games', $id);
            } else {
                db()->prepare('INSERT INTO games (name, image_path, sort_order, active) VALUES (?,?,?,?)')
                    ->execute([$name, $image, $sort, $active]);
                audit_log('CREATE_WEBSITE_GAME', 'games', (int)db()->lastInsertId());
            }
        }

        if ($action === 'toggle_game') {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('UPDATE games SET active = IF(active=1,0,1) WHERE id=?')->execute([$id]);
            audit_log('TOGGLE_WEBSITE_GAME', 'games', $id);
        }

        if ($action === 'save_pricing') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $category = in_array(($_POST['category'] ?? 'PC'), ['PC', 'PLAYSTATION', 'OTHER'], true) ? $_POST['category'] : 'PC';
            $price = max(0, (float)($_POST['price'] ?? 0));
            $unit = trim((string)($_POST['unit'] ?? 'hour')) ?: 'hour';
            $sort = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            if ($title === '') {
                throw new RuntimeException('Pricing title is required.');
            }
            if ($id > 0) {
                db()->prepare('UPDATE pricing_items SET title=?, category=?, price=?, unit=?, sort_order=?, active=? WHERE id=?')
                    ->execute([$title, $category, $price, $unit, $sort, $active, $id]);
                audit_log('UPDATE_WEBSITE_PRICING', 'pricing_items', $id);
            } else {
                db()->prepare('INSERT INTO pricing_items (title, category, price, unit, sort_order, active) VALUES (?,?,?,?,?,?)')
                    ->execute([$title, $category, $price, $unit, $sort, $active]);
                audit_log('CREATE_WEBSITE_PRICING', 'pricing_items', (int)db()->lastInsertId());
            }
        }

        if ($action === 'toggle_pricing') {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('UPDATE pricing_items SET active = IF(active=1,0,1) WHERE id=?')->execute([$id]);
            audit_log('TOGGLE_WEBSITE_PRICING', 'pricing_items', $id);
        }

        if ($action === 'save_location') {
            foreach (['address', 'phone', 'email', 'opening_hours', 'google_maps_url', 'map_embed_url', 'location_cta_label'] as $key) {
                save_setting($key, trim((string)($_POST[$key] ?? '')));
            }
            audit_log('UPDATE_WEBSITE_LOCATION', 'settings');
        }

        header('Location: ' . ADMIN_BASE . '/website-content.php?toast=Website content saved successfully.');
        exit;
    } catch (Throwable $e) {
        header('Location: ' . ADMIN_BASE . '/website-content.php?toast=' . urlencode($e->getMessage()) . '&toast_type=error');
        exit;
    }
}

$games = db()->query('SELECT * FROM games ORDER BY sort_order, name')->fetchAll();
$pricing = db()->query('SELECT * FROM pricing_items ORDER BY sort_order, title')->fetchAll();
$pageAction = '<div class="flex gap-2"><button class="btn-secondary py-2.5 px-4 rounded label uppercase" data-open-template-modal="price-template" data-modal-title="Add Pricing" data-modal-icon="sell" data-modal-subtitle="Add a pricing card for the public homepage.">Add Pricing</button><button class="btn-primary py-2.5 px-4 rounded label uppercase" data-open-template-modal="game-template" data-modal-title="Add Game" data-modal-icon="stadia_controller" data-modal-subtitle="Upload a game image and publish it to the homepage.">Add Game</button></div>';
require __DIR__ . '/../includes/admin_header.php';
?>
<template id="game-template">
  <form method="post" enctype="multipart/form-data" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_game">
    <input type="hidden" name="active" value="0">
    <label class="block text-sm text-on-surface/65">Game name<input class="input mt-1" name="name" required placeholder="Valorant"></label>
    <label class="block text-sm text-on-surface/65">Image<input class="input mt-1" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif"></label>
    <label class="block text-sm text-on-surface/65">Sort order<input class="input mt-1" type="number" name="sort_order" value="0"></label>
    <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" checked><span class="setting-toggle-track"></span><span><strong>Show on website</strong><small>Published games appear in Popular Games.</small></span></label>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Game</button></div>
  </form>
</template>

<template id="price-template">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_pricing">
    <input type="hidden" name="active" value="0">
    <div class="grid md:grid-cols-2 gap-3">
      <label class="block text-sm text-on-surface/65">Title<input class="input mt-1" name="title" required placeholder="Standard PC"></label>
      <label class="block text-sm text-on-surface/65">Category<select class="input mt-1" name="category"><option>PC</option><option>PLAYSTATION</option><option>OTHER</option></select></label>
      <label class="block text-sm text-on-surface/65">Price<input class="input mt-1" type="number" step="0.01" min="0" name="price" value="0"></label>
      <label class="block text-sm text-on-surface/65">Unit<input class="input mt-1" name="unit" value="hour" placeholder="hour"></label>
      <label class="block text-sm text-on-surface/65">Sort order<input class="input mt-1" type="number" name="sort_order" value="0"></label>
    </div>
    <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" checked><span class="setting-toggle-track"></span><span><strong>Show on website</strong><small>Published prices appear in Pricing.</small></span></label>
    <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Pricing</button></div>
  </form>
</template>

<section class="grid xl:grid-cols-[1fr_380px] gap-4">
  <div class="space-y-4">
    <div class="panel overflow-auto">
      <div class="p-4 border-b border-border-subtle flex items-center justify-between">
        <h2 class="font-display text-lg font-bold uppercase text-white">Popular Games</h2>
        <span class="label text-[10px] text-on-surface/45 uppercase"><?= count($games) ?> records</span>
      </div>
      <table class="table">
        <thead><tr><th>Image</th><th>Game</th><th>Sort</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($games as $game): ?>
            <tr>
              <td>
                <div class="w-16 h-10 rounded border border-border-subtle bg-surface-elevated overflow-hidden flex items-center justify-center text-brand-crimson">
                  <?php if (!empty($game['image_path'])): ?><img class="w-full h-full object-cover" src="<?= APP_BASE ?>/<?= e($game['image_path']) ?>" alt=""><?php else: ?><span class="material-symbols-outlined text-base">stadia_controller</span><?php endif; ?>
                </div>
              </td>
              <td class="text-white font-semibold"><?= e($game['name']) ?></td>
              <td class="telemetry"><?= (int)$game['sort_order'] ?></td>
              <td><span class="status-badge <?= (int)$game['active'] ? 'status-available' : 'status-offline' ?>"><span class="status-dot"></span><?= (int)$game['active'] ? 'ACTIVE' : 'HIDDEN' ?></span></td>
              <td class="text-right whitespace-nowrap">
                <button class="icon-btn" data-edit-game='<?= e(json_encode($game, JSON_THROW_ON_ERROR)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
                <form class="inline" method="post">
                  <?= csrf_field() ?><input type="hidden" name="action" value="toggle_game"><input type="hidden" name="id" value="<?= (int)$game['id'] ?>">
                  <button class="icon-btn <?= (int)$game['active'] ? 'danger' : '' ?>" title="<?= (int)$game['active'] ? 'Hide' : 'Show' ?>"><span class="material-symbols-outlined text-base"><?= (int)$game['active'] ? 'visibility_off' : 'visibility' ?></span></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel overflow-auto">
      <div class="p-4 border-b border-border-subtle flex items-center justify-between">
        <h2 class="font-display text-lg font-bold uppercase text-white">Pricing</h2>
        <span class="label text-[10px] text-on-surface/45 uppercase"><?= count($pricing) ?> records</span>
      </div>
      <table class="table">
        <thead><tr><th>Title</th><th>Category</th><th>Price</th><th>Unit</th><th>Sort</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($pricing as $item): ?>
            <tr>
              <td class="text-white font-semibold"><?= e($item['title']) ?></td>
              <td><?= e($item['category']) ?></td>
              <td class="telemetry"><?= money($item['price']) ?></td>
              <td><?= e($item['unit']) ?></td>
              <td class="telemetry"><?= (int)$item['sort_order'] ?></td>
              <td><span class="status-badge <?= (int)$item['active'] ? 'status-available' : 'status-offline' ?>"><span class="status-dot"></span><?= (int)$item['active'] ? 'ACTIVE' : 'HIDDEN' ?></span></td>
              <td class="text-right whitespace-nowrap">
                <button class="icon-btn" data-edit-price='<?= e(json_encode($item, JSON_THROW_ON_ERROR)) ?>'><span class="material-symbols-outlined text-base">edit</span></button>
                <form class="inline" method="post">
                  <?= csrf_field() ?><input type="hidden" name="action" value="toggle_pricing"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="icon-btn <?= (int)$item['active'] ? 'danger' : '' ?>" title="<?= (int)$item['active'] ? 'Hide' : 'Show' ?>"><span class="material-symbols-outlined text-base"><?= (int)$item['active'] ? 'visibility_off' : 'visibility' ?></span></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <aside class="panel p-4 h-max">
    <h2 class="font-display text-lg font-bold uppercase text-white mb-3">Location & Hours</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_location">
      <label class="block text-sm text-on-surface/65">Address<textarea class="input mt-1" name="address" placeholder="Business address"><?= e(setting('address', '')) ?></textarea></label>
      <label class="block text-sm text-on-surface/65">Phone<input class="input mt-1" name="phone" value="<?= e(setting('phone', '')) ?>"></label>
      <label class="block text-sm text-on-surface/65">Email<input class="input mt-1" type="email" name="email" value="<?= e(setting('email', '')) ?>"></label>
      <label class="block text-sm text-on-surface/65">Opening hours<textarea class="input mt-1" name="opening_hours" placeholder="Open daily 10:00 - 02:00"><?= e(setting('opening_hours', '')) ?></textarea></label>
      <label class="block text-sm text-on-surface/65">Google Maps link<input class="input mt-1" name="google_maps_url" value="<?= e(setting('google_maps_url', '#')) ?>" placeholder="https://maps.google.com/..."></label>
      <label class="block text-sm text-on-surface/65">Map embed URL<input class="input mt-1" name="map_embed_url" value="<?= e(setting('map_embed_url', '')) ?>" placeholder="https://www.google.com/maps/embed?..."></label>
      <label class="block text-sm text-on-surface/65">Location button label<input class="input mt-1" name="location_cta_label" value="<?= e(setting('location_cta_label', 'Open Location')) ?>"></label>
      <button class="btn-primary w-full py-3 rounded label uppercase">Save Location</button>
    </form>
  </aside>
</section>

<script>
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
document.addEventListener('click', event => {
  const gameButton = event.target.closest('[data-edit-game]');
  if (gameButton) {
    const game = JSON.parse(gameButton.dataset.editGame);
    openModal({
      title: 'Edit Game',
      icon: 'stadia_controller',
      subtitle: 'Update the homepage game card and image.',
      body: `<form method="post" enctype="multipart/form-data" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_game">
        <input type="hidden" name="id" value="${game.id}">
        <input type="hidden" name="existing_image_path" value="${esc(game.image_path)}">
        <input type="hidden" name="active" value="0">
        <label class="block text-sm text-on-surface/65">Game name<input class="input mt-1" name="name" value="${esc(game.name)}" required></label>
        <label class="block text-sm text-on-surface/65">Replace image<input class="input mt-1" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif"></label>
        <label class="block text-sm text-on-surface/65">Sort order<input class="input mt-1" type="number" name="sort_order" value="${game.sort_order}"></label>
        <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" ${Number(game.active) ? 'checked' : ''}><span class="setting-toggle-track"></span><span><strong>Show on website</strong><small>Published games appear in Popular Games.</small></span></label>
        <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Game</button></div>
      </form>`
    });
  }

  const priceButton = event.target.closest('[data-edit-price]');
  if (priceButton) {
    const item = JSON.parse(priceButton.dataset.editPrice);
    openModal({
      title: 'Edit Pricing',
      icon: 'sell',
      subtitle: 'Update the public homepage pricing card.',
      body: `<form method="post" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_pricing">
        <input type="hidden" name="id" value="${item.id}">
        <input type="hidden" name="active" value="0">
        <div class="grid md:grid-cols-2 gap-3">
          <label class="block text-sm text-on-surface/65">Title<input class="input mt-1" name="title" value="${esc(item.title)}" required></label>
          <label class="block text-sm text-on-surface/65">Category<select class="input mt-1" name="category"><option ${item.category === 'PC' ? 'selected' : ''}>PC</option><option ${item.category === 'PLAYSTATION' ? 'selected' : ''}>PLAYSTATION</option><option ${item.category === 'OTHER' ? 'selected' : ''}>OTHER</option></select></label>
          <label class="block text-sm text-on-surface/65">Price<input class="input mt-1" type="number" step="0.01" min="0" name="price" value="${item.price}"></label>
          <label class="block text-sm text-on-surface/65">Unit<input class="input mt-1" name="unit" value="${esc(item.unit)}"></label>
          <label class="block text-sm text-on-surface/65">Sort order<input class="input mt-1" type="number" name="sort_order" value="${item.sort_order}"></label>
        </div>
        <label class="setting-toggle mt-1"><input type="checkbox" name="active" value="1" ${Number(item.active) ? 'checked' : ''}><span class="setting-toggle-track"></span><span><strong>Show on website</strong><small>Published prices appear in Pricing.</small></span></label>
        <div class="modal-foot -mx-4 -mb-4 mt-4"><button type="button" class="btn-secondary" data-close-modal>Cancel</button><button class="btn-primary rounded label uppercase">Save Pricing</button></div>
      </form>`
    });
  }
});
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
