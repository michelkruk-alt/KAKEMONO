<?php
require_once __DIR__ . '/includes/layout.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    set_flash('danger', 'Lien invalide.');
    redirect_to('/login.php');
}

// Validate token existence (without consuming it yet – for GET display)
$checkStmt = $pdo->prepare('SELECT pr.*, u.first_name FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? AND pr.used_at IS NULL LIMIT 1');
$checkStmt->execute([$token]);
$reset = $checkStmt->fetch();

if (!$reset || $reset['expires_at'] < now()) {
    set_flash('danger', 'Ce lien est expiré ou invalide. Faites une nouvelle demande.');
    redirect_to('/forgot_password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if (strlen($password) < 8) {
        set_flash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
    } elseif ($password !== $confirm) {
        set_flash('danger', 'Les deux mots de passe ne correspondent pas.');
    } else {
        $userId = consume_password_reset($pdo, $token);
        if ($userId === null) {
            set_flash('danger', 'Lien expiré ou déjà utilisé. Veuillez recommencer.');
            redirect_to('/forgot_password.php');
        }
        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), now(), $userId]);
        set_flash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        redirect_to('/login.php');
    }
}

render_header('Nouveau mot de passe');
?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h2 section-title mb-4">Nouveau mot de passe</h1>
                <p class="text-muted">Bonjour <?= e($reset['first_name']) ?>, choisissez un nouveau mot de passe.</p>
                <form method="post" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div><label class="form-label">Nouveau mot de passe</label><input type="password" name="password" class="form-control" minlength="8" required autofocus></div>
                    <div><label class="form-label">Confirmer le mot de passe</label><input type="password" name="confirm" class="form-control" minlength="8" required></div>
                    <button class="btn btn-primary">Définir le mot de passe</button>
                    <a class="text-center small" href="/login.php">Retour à la connexion</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
