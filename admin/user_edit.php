<?php
require_once __DIR__ . '/../includes/layout.php';
require_roles([ROLE_ADMIN]);
$userId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$editedUser = $stmt->fetch();
if (!$editedUser) {
    http_response_code(404);
    exit('Utilisateur introuvable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $status = $_POST['status'] ?? 'pending';
    $role = (int) ($_POST['role_level'] ?? ROLE_USER);
    $approvedAt = $status === 'active' ? ($editedUser['approved_at'] ?: now()) : null;
    $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, address = ?, city = ?, postal_code = ?, email = ?, is_organization = ?, role_level = ?, status = ?, approved_at = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([
        trim($_POST['first_name'] ?? ''), trim($_POST['last_name'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['city'] ?? ''), trim($_POST['postal_code'] ?? ''), strtolower(trim($_POST['email'] ?? '')),
        isset($_POST['is_organization']) ? 1 : 0,
        $role,
        $status,
        $approvedAt,
        now(),
        $userId,
    ]);
    if ($status === 'active' && $editedUser['status'] !== 'active') {
        queue_mail($pdo, strtolower(trim($_POST['email'] ?? '')), 'Votre compte KAKEMONO est validé', 'Votre compte vient d’être activé. Vous pouvez désormais réserver un stand.');
    }
    set_flash('success', 'Compte mis à jour.');
    redirect_to('/admin/user_edit.php?id=' . $userId);
}

$loginHistory = $pdo->prepare('SELECT * FROM login_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$loginHistory->execute([$userId]);
$logs = $loginHistory->fetchAll();
$reservations = $pdo->prepare('SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC');
$reservations->execute([$userId]);
$userReservations = $reservations->fetchAll();
render_header('Gestion utilisateur', 'users_admin');
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card rounded-4 bg-white"><div class="card-body p-4"><h1 class="h3 section-title mb-4">Modifier le compte</h1><form method="post" class="row g-3"><?= csrf_field() ?><div class="col-md-6"><label class="form-label">Nom</label><input name="last_name" class="form-control" value="<?= e($editedUser['last_name']) ?>" required></div><div class="col-md-6"><label class="form-label">Prénom</label><input name="first_name" class="form-control" value="<?= e($editedUser['first_name']) ?>" required></div><div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" value="<?= e($editedUser['address']) ?>" required></div><div class="col-md-6"><label class="form-label">Ville</label><input name="city" class="form-control" value="<?= e($editedUser['city']) ?>" required></div><div class="col-md-6"><label class="form-label">Code postal</label><input name="postal_code" class="form-control" value="<?= e($editedUser['postal_code']) ?>" required></div><div class="col-md-6"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" value="<?= e($editedUser['email']) ?>" required></div><div class="col-md-3"><label class="form-label">Rôle</label><select name="role_level" class="form-select"><?php foreach ([0,1,2,3,4,99] as $role): ?><option value="<?= $role ?>" <?= (int) $editedUser['role_level'] === $role ? 'selected' : '' ?>><?= e(role_label($role)) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Statut</label><select name="status" class="form-select"><?php foreach (['pending' => 'En attente', 'active' => 'Actif', 'disabled' => 'Désactivé'] as $value => $label): ?><option value="<?= $value ?>" <?= $editedUser['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_organization" id="userOrg" <?= (int) $editedUser['is_organization'] ? 'checked' : '' ?>><label class="form-check-label" for="userOrg">Société ou association</label></div></div><div class="col-12"><button class="btn btn-primary">Enregistrer</button></div></form></div></div>
    </div>
    <div class="col-lg-5">
        <div class="card rounded-4 bg-white mb-4"><div class="card-body p-4"><h2 class="h4 section-title">Historique des connexions</h2><div class="vstack gap-2 mt-3"><?php foreach ($logs as $log): ?><div class="soft-panel"><strong><?= e($log['action']) ?></strong><div class="small text-muted"><?= e($log['created_at']) ?> · <?= e($log['ip_address']) ?></div></div><?php endforeach; ?><?php if (!$logs): ?><p class="text-muted mb-0">Aucune connexion enregistrée.</p><?php endif; ?></div></div></div>
        <div class="card rounded-4 bg-white"><div class="card-body p-4"><h2 class="h4 section-title">Récapitulatif factures / réservations</h2><div class="vstack gap-2 mt-3"><?php foreach ($userReservations as $reservation): ?><div class="soft-panel"><div class="d-flex justify-content-between"><strong><?= e(reservation_status_label($reservation['status'])) ?></strong><span><?= format_price(get_reservation_total($pdo, (int) $reservation['id'])) ?></span></div><a class="btn btn-sm btn-outline-primary mt-2" href="/invoice.php?id=<?= (int) $reservation['id'] ?>">Ouvrir</a></div><?php endforeach; ?><?php if (!$userReservations): ?><p class="text-muted mb-0">Aucune réservation.</p><?php endif; ?></div></div></div>
    </div>
</div>
<?php render_footer(); ?>
