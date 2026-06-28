<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $passwordPart = trim($_POST['password'] ?? '');
    $passwordHash = $user['password_hash'];
    if ($passwordPart !== '') {
        $passwordHash = password_hash($passwordPart, PASSWORD_DEFAULT);
    }

    $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, address = ?, city = ?, postal_code = ?, email = ?, is_organization = ?, password_hash = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([
        trim($_POST['first_name'] ?? ''),
        trim($_POST['last_name'] ?? ''),
        trim($_POST['address'] ?? ''),
        trim($_POST['city'] ?? ''),
        trim($_POST['postal_code'] ?? ''),
        strtolower(trim($_POST['email'] ?? '')),
        isset($_POST['is_organization']) ? 1 : 0,
        $passwordHash,
        now(),
        (int) $user['id'],
    ]);
    set_flash('success', 'Compte mis à jour.');
    redirect_to('/profile.php');
}

$resStmt = $pdo->prepare('SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC');
$resStmt->execute([(int) $user['id']]);
$reservations = $resStmt->fetchAll();

render_header('Mon compte', 'profile');
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4">
                <h1 class="h2 section-title mb-4">Mon profil</h1>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-6"><label class="form-label">Nom</label><input name="last_name" class="form-control" value="<?= e($user['last_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Prénom</label><input name="first_name" class="form-control" value="<?= e($user['first_name']) ?>" required></div>
                    <div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" value="<?= e($user['address']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Ville</label><input name="city" class="form-control" value="<?= e($user['city']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Code postal</label><input name="postal_code" class="form-control" value="<?= e($user['postal_code']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Nouveau mot de passe</label><input name="password" type="password" class="form-control" placeholder="Laisser vide pour conserver"></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_organization" id="profileOrg" <?= (int) $user['is_organization'] ? 'checked' : '' ?>><label class="form-check-label" for="profileOrg">Société ou association</label></div></div>
                    <div class="col-12 d-flex justify-content-between align-items-center"><span class="badge text-bg-secondary"><?= e(role_label((int) $user['role_level'])) ?></span><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card rounded-4 bg-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 section-title">Mes réservations</h2>
                <?php if (!$reservations): ?>
                    <p class="text-muted mb-0">Aucune réservation pour le moment.</p>
                <?php else: ?>
                    <div class="vstack gap-3">
                        <?php foreach ($reservations as $reservation): ?>
                            <div class="soft-panel">
                                <div class="d-flex justify-content-between gap-2"><strong><?= e($reservation['status']) ?></strong><span><?= format_price(get_reservation_total($pdo, (int) $reservation['id'])) ?></span></div>
                                <div class="small text-muted">Créée le <?= e($reservation['created_at']) ?></div>
                                <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="/invoice.php?id=<?= (int) $reservation['id'] ?>">Voir le dossier</a></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
