<?php
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $fields = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
    ];

    if (in_array('', $fields, true)) {
        set_flash('danger', 'Tous les champs sont obligatoires.');
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Adresse e-mail invalide.');
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, address, city, postal_code, email, password_hash, is_organization, role_level, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $fields['first_name'],
                $fields['last_name'],
                $fields['address'],
                $fields['city'],
                $fields['postal_code'],
                $fields['email'],
                password_hash($fields['password'], PASSWORD_DEFAULT),
                isset($_POST['is_organization']) ? 1 : 0,
                ROLE_USER,
                'pending',
                now(),
                now(),
            ]);
            queue_mail($pdo, $fields['email'], 'Votre compte KAKEMONO est en attente de validation', "Bonjour {$fields['first_name']},\n\nVotre compte a bien été créé. Un administrateur validera votre accès avant toute réservation.");
            set_flash('success', 'Compte créé. Un mail de confirmation a été préparé et votre accès est en attente de validation.');
            redirect_to('/login.php');
        } catch (PDOException $exception) {
            set_flash('danger', 'Impossible de créer le compte, cette adresse e-mail existe déjà.');
        }
    }
}

render_header('Créer un compte');
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h2 section-title mb-4">Créer un compte exposant</h1>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-6"><label class="form-label">Nom</label><input name="last_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Prénom</label><input name="first_name" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Ville</label><input name="city" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Code postal</label><input name="postal_code" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Mot de passe</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_organization" id="isOrg"><label class="form-check-label" for="isOrg">Je m’inscris pour une société ou une association</label></div></div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <small class="text-muted">Les nouveaux comptes sont créés au niveau 1 et restent en attente de validation.</small>
                        <button class="btn btn-primary">Créer mon compte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
