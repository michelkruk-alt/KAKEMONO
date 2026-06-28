<?php
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $userId = is_array($user) ? (int) $user['id'] : null;

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login($pdo, $userId, $email, 'failed');
        set_flash('danger', 'Identifiants invalides.');
    } elseif ($user['status'] !== 'active' || (int) $user['role_level'] === ROLE_DISABLED) {
        record_login($pdo, (int) $user['id'], $email, 'blocked');
        set_flash('warning', 'Votre compte est en attente de validation ou désactivé.');
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        record_login($pdo, (int) $user['id'], $email, 'success');
        set_flash('success', 'Connexion réussie.');
        redirect_to('/profile.php');
    }
}

render_header('Connexion');
?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h2 section-title mb-4">Connexion</h1>
                <form method="post" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <div><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required></div>
                    <div><label class="form-label">Mot de passe</label><input type="password" name="password" class="form-control" required></div>
                    <button class="btn btn-primary">Se connecter</button>
                    <div class="d-flex justify-content-between align-items-center">
                        <a class="small" href="/forgot_password.php">Mot de passe oublié ?</a>
                        <small class="text-muted">Démo admin : admin@kakemono.local / Admin123!</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
