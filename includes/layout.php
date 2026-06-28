<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function render_header(string $title, string $active = ''): void
{
    $user = current_user();
    $settings = get_settings($GLOBALS['pdo']);
    $flashes = get_flashes();
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · <?= e($settings['association_name'] ?? 'KAKEMONO Events') ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link href="/assets/css/theme.css" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm sticky-top app-nav">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/index.php">KAKEMONO Events</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link <?= $active === 'home' ? 'active' : '' ?>" href="/index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link <?= $active === 'events' ? 'active' : '' ?>" href="/events.php">Événements</a></li>
                    <li class="nav-item"><a class="nav-link <?= $active === 'touch' ? 'active' : '' ?>" href="/touch.php">Plan visiteurs</a></li>
                    <li class="nav-item"><a class="nav-link <?= $active === 'terms' ? 'active' : '' ?>" href="/cgv.php">CGV</a></li>
                    <?php if ($user): ?>
                        <li class="nav-item"><a class="nav-link <?= $active === 'messages' ? 'active' : '' ?>" href="/messages.php">Messagerie</a></li>
                        <li class="nav-item"><a class="nav-link <?= $active === 'profile' ? 'active' : '' ?>" href="/profile.php">Mon compte</a></li>
                    <?php endif; ?>
                    <?php if ($user && in_array((int) $user['role_level'], [ROLE_ADMIN, ROLE_MODERATOR], true)): ?>
                        <li class="nav-item"><a class="nav-link <?= $active === 'products' ? 'active' : '' ?>" href="/admin/products.php">Produits</a></li>
                        <li class="nav-item"><a class="nav-link <?= $active === 'events_admin' ? 'active' : '' ?>" href="/admin/events.php">Événements admin</a></li>
                    <?php endif; ?>
                    <?php if ($user && in_array((int) $user['role_level'], [ROLE_ADMIN, ROLE_ACCOUNTING, ROLE_MODERATOR], true)): ?>
                        <li class="nav-item"><a class="nav-link <?= $active === 'accounting' ? 'active' : '' ?>" href="/admin/accounting.php">Réservations</a></li>
                    <?php endif; ?>
                    <?php if ($user && (int) $user['role_level'] === ROLE_ADMIN): ?>
                        <li class="nav-item"><a class="nav-link <?= $active === 'users_admin' ? 'active' : '' ?>" href="/admin/users.php">Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link <?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings.php">Association</a></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($user): ?>
                        <span class="badge text-bg-light"><?= e(role_label((int) $user['role_level'])) ?></span>
                        <a class="btn btn-outline-light btn-sm" href="/cart.php">Panier</a>
                        <a class="btn btn-light btn-sm" href="/logout.php">Déconnexion</a>
                    <?php else: ?>
                        <a class="btn btn-outline-light btn-sm" href="/login.php">Connexion</a>
                        <a class="btn btn-light btn-sm" href="/register.php">Créer un compte</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="py-4">
        <div class="container">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    ?>
        </div>
    </main>
    <footer class="py-4 border-top bg-light-subtle">
        <div class="container small d-flex flex-column flex-lg-row justify-content-between gap-2">
            <span>Prototype multipages KAKEMONO Events · Bootstrap, jQuery, SQLite</span>
            <span>Compte admin de démonstration : admin@kakemono.local / Admin123!</span>
        </div>
    </footer>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="/assets/js/app.js"></script>
    </body>
    </html>
    <?php
}
