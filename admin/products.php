<?php
require_once __DIR__ . '/../includes/layout.php';
require_roles([ROLE_ADMIN, ROLE_MODERATOR]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $imagePath = null;
    try {
        $imagePath = handle_image_upload('image_path', 'products');
    } catch (Throwable $throwable) {
        set_flash('danger', $throwable->getMessage());
        redirect_to('/admin/products.php');
    }
    $productId = (int) ($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        $current = $pdo->prepare('SELECT image_path FROM products WHERE id = ?');
        $current->execute([$productId]);
        $currentImage = $current->fetchColumn();
        $stmt = $pdo->prepare('UPDATE products SET name = ?, product_type = ?, description = ?, image_path = ?, price_ht = ?, price_ttc = ?, is_quantity_limited = ?, quantity_limit = ?, active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([
            trim($_POST['name'] ?? ''), $_POST['product_type'] ?? 'space', trim($_POST['description'] ?? ''), $imagePath ?: $currentImage, (float) ($_POST['price_ht'] ?? 0), (float) ($_POST['price_ttc'] ?? 0), isset($_POST['is_quantity_limited']) ? 1 : 0, $_POST['quantity_limit'] !== '' ? (int) $_POST['quantity_limit'] : null, isset($_POST['active']) ? 1 : 0, now(), $productId,
        ]);
        set_flash('success', 'Produit mis à jour.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO products (name, product_type, description, image_path, price_ht, price_ttc, is_quantity_limited, quantity_limit, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim($_POST['name'] ?? ''), $_POST['product_type'] ?? 'space', trim($_POST['description'] ?? ''), $imagePath, (float) ($_POST['price_ht'] ?? 0), (float) ($_POST['price_ttc'] ?? 0), isset($_POST['is_quantity_limited']) ? 1 : 0, $_POST['quantity_limit'] !== '' ? (int) $_POST['quantity_limit'] : null, isset($_POST['active']) ? 1 : 0, now(), now(),
        ]);
        set_flash('success', 'Produit créé.');
    }
    redirect_to('/admin/products.php');
}
$products = $pdo->query('SELECT * FROM products ORDER BY product_type, name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($products as $product) {
    if ((int) $product['id'] === $editId) {
        $editing = $product;
    }
}
render_header('Produits', 'products');
?>
<div class="row g-4"><div class="col-lg-5"><div class="card rounded-4 bg-white"><div class="card-body p-4"><h1 class="h4 section-title mb-4"><?= $editing ? 'Modifier le produit' : 'Créer un produit / sous-produit' ?></h1><form method="post" enctype="multipart/form-data" class="vstack gap-3"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) ($editing['id'] ?? 0) ?>"><div><label class="form-label">Nom</label><input name="name" class="form-control" value="<?= e($editing['name'] ?? '') ?>" required></div><div><label class="form-label">Type</label><select name="product_type" class="form-select"><option value="space" <?= ($editing['product_type'] ?? '') === 'space' ? 'selected' : '' ?>>Produit principal / stand</option><option value="option" <?= ($editing['product_type'] ?? '') === 'option' ? 'selected' : '' ?>>Sous-produit / option</option></select></div><div><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required><?= e($editing['description'] ?? '') ?></textarea></div><div><label class="form-label">Photo PNG/JPG</label><input type="file" name="image_path" accept="image/png,image/jpeg" class="form-control"></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Prix HT</label><input type="number" step="0.01" name="price_ht" class="form-control" value="<?= e((string) ($editing['price_ht'] ?? '0')) ?>" required></div><div class="col-md-6"><label class="form-label">Prix TTC</label><input type="number" step="0.01" name="price_ttc" class="form-control" value="<?= e((string) ($editing['price_ttc'] ?? '0')) ?>" required></div></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_quantity_limited" id="qtyLimited" <?= !empty($editing['is_quantity_limited']) ? 'checked' : '' ?>><label class="form-check-label" for="qtyLimited">Limiter les quantités</label></div><div><label class="form-label">Quantité max</label><input type="number" name="quantity_limit" class="form-control" value="<?= e((string) ($editing['quantity_limit'] ?? '')) ?>"></div><div class="form-check"><input class="form-check-input" type="checkbox" name="active" id="activeProduct" <?= !isset($editing['active']) || (int) $editing['active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="activeProduct">Actif</label></div><button class="btn btn-primary">Enregistrer</button></form></div></div></div><div class="col-lg-7"><div class="card rounded-4 bg-white"><div class="card-body p-4"><h2 class="h4 section-title">Catalogue</h2><div class="table-responsive mt-3"><table class="table align-middle"><thead><tr><th>Nom</th><th>Type</th><th>TTC</th><th>Quantité</th><th></th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><?= e($product['name']) ?></td><td><?= e($product['product_type']) ?></td><td><?= format_price((float) $product['price_ttc']) ?></td><td><?= (int) $product['is_quantity_limited'] ? (int) $product['quantity_limit'] : 'Illimitée' ?></td><td><a class="btn btn-sm btn-outline-primary" href="/admin/products.php?edit=<?= (int) $product['id'] ?>">Modifier</a></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
<?php render_footer(); ?>
