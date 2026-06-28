<?php
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Adresse e-mail invalide.');
    } else {
        $stmt = $pdo->prepare('SELECT id, first_name FROM users WHERE email = ? AND status != ? LIMIT 1');
        $stmt->execute([$email, 'disabled']);
        $user = $stmt->fetch();

        if ($user) {
            $token = create_password_reset($pdo, (int) $user['id']);
            $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/reset_password.php?token=' . $token;
            $body = "Bonjour {$user['first_name']},\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\nCliquez sur ce lien (valable 1 heure) :\n{$link}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.";
            queue_mail($pdo, $email, 'Réinitialisation de votre mot de passe KAKEMONO', $body);
        }

        // Always show the same message to avoid user enumeration
        set_flash('success', 'Si cette adresse correspond à un compte actif, un e-mail de réinitialisation a été envoyé.');
        redirect_to('/login.php');
    }
}

render_header('Mot de passe oublié');
?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h2 section-title mb-4">Mot de passe oublié</h1>
                <p class="text-muted">Indiquez votre adresse e-mail. Vous recevrez un lien valable 1 heure pour définir un nouveau mot de passe.</p>
                <form method="post" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <div><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required autofocus></div>
                    <button class="btn btn-primary">Envoyer le lien de réinitialisation</button>
                    <a class="text-center small" href="/login.php">Retour à la connexion</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
