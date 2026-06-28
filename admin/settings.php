<?php
require_once __DIR__ . '/../includes/layout.php';
require_roles([ROLE_ADMIN]);
$settings = get_settings($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $stmt = $pdo->prepare('UPDATE association_settings SET association_name = ?, legal_name = ?, address = ?, city = ?, postal_code = ?, email = ?, phone = ?, siret = ?, vat_number = ?, iban = ?, bic = ?, billing_note = ?, updated_at = ? WHERE id = 1');
    $stmt->execute([
        trim($_POST['association_name'] ?? ''), trim($_POST['legal_name'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['city'] ?? ''), trim($_POST['postal_code'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['siret'] ?? ''), trim($_POST['vat_number'] ?? ''), trim($_POST['iban'] ?? ''), trim($_POST['bic'] ?? ''), trim($_POST['billing_note'] ?? ''), now(),
    ]);
    set_flash('success', 'Informations association mises à jour.');
    redirect_to('/admin/settings.php');
}
render_header('Association', 'settings');
?>
<div class="card rounded-4 bg-white"><div class="card-body p-4 p-lg-5"><h1 class="h2 section-title mb-4">Informations légales et comptables</h1><form method="post" class="row g-3"><?= csrf_field() ?><div class="col-md-6"><label class="form-label">Nom association</label><input name="association_name" class="form-control" value="<?= e($settings['association_name']) ?>" required></div><div class="col-md-6"><label class="form-label">Raison sociale</label><input name="legal_name" class="form-control" value="<?= e($settings['legal_name']) ?>" required></div><div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" value="<?= e($settings['address']) ?>" required></div><div class="col-md-4"><label class="form-label">Ville</label><input name="city" class="form-control" value="<?= e($settings['city']) ?>" required></div><div class="col-md-4"><label class="form-label">Code postal</label><input name="postal_code" class="form-control" value="<?= e($settings['postal_code']) ?>" required></div><div class="col-md-4"><label class="form-label">E-mail</label><input name="email" class="form-control" value="<?= e($settings['email']) ?>" required></div><div class="col-md-4"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="<?= e($settings['phone']) ?>" required></div><div class="col-md-4"><label class="form-label">SIRET</label><input name="siret" class="form-control" value="<?= e($settings['siret']) ?>" required></div><div class="col-md-4"><label class="form-label">Mention TVA</label><input name="vat_number" class="form-control" value="<?= e($settings['vat_number']) ?>" required></div><div class="col-md-6"><label class="form-label">IBAN</label><input name="iban" class="form-control" value="<?= e($settings['iban']) ?>" required></div><div class="col-md-6"><label class="form-label">BIC</label><input name="bic" class="form-control" value="<?= e($settings['bic']) ?>" required></div><div class="col-12"><label class="form-label">Note facturation</label><textarea name="billing_note" class="form-control" rows="4"><?= e($settings['billing_note']) ?></textarea></div><div class="col-12"><button class="btn btn-primary">Enregistrer</button></div></form></div></div>
<?php render_footer(); ?>
