<?php
require_once __DIR__ . '/includes/layout.php';

$events = get_active_events($pdo);
$featuredEvent = $events[0] ?? null;
$stats = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'reservations' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN ('pending_admin', 'approved')")->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
];

render_header('Accueil', 'home');
?>
<div class="row g-4 align-items-stretch mb-4">
    <div class="col-lg-8">
        <div class="hero-card p-4 p-lg-5 rounded-4 bg-white">
            <span class="badge badge-status mb-3">Plateforme exposants & administration</span>
            <h1 class="display-5 fw-bold section-title">Gestion des stands, réservations, facturation et accueil visiteurs</h1>
            <p class="lead">Prototype multipages HTML, SQL, jQuery et Bootstrap pour l’association KAKEMONO Events, avec identité chaleureuse brune et outils dédiés aux exposants, à l’administration, à la comptabilité et au public.</p>
            <div class="d-flex gap-3 flex-wrap">
                <a class="btn btn-primary btn-lg" href="/events.php">Voir les événements</a>
                <a class="btn btn-outline-primary btn-lg" href="/touch.php">Mode tactile visiteurs</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="soft-panel h-100 kakemono-card">
            <h2 class="h4">Fonctions clés</h2>
            <ul class="mb-0 ps-3">
                <li>Validation des comptes par administrateur</li>
                <li>Plan interactif avec stands verts / orange / rouges</li>
                <li>Panier, CGV, pré-réservation et frais de dossier évolutifs</li>
                <li>Validation comptable, échéanciers et facture PDF</li>
                <li>Messagerie interne, livre d’or et espace visiteurs tactile</li>
            </ul>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="sidebar-stat"><div class="small text-uppercase">Comptes</div><div class="display-6 fw-bold"><?= $stats['users'] ?></div></div></div>
    <div class="col-md-4"><div class="sidebar-stat"><div class="small text-uppercase">Réservations traitées</div><div class="display-6 fw-bold"><?= $stats['reservations'] ?></div></div></div>
    <div class="col-md-4"><div class="sidebar-stat"><div class="small text-uppercase">Messages</div><div class="display-6 fw-bold"><?= $stats['messages'] ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4">
                <h2 class="h3 section-title">Parcours exposant</h2>
                <div class="row g-3 mt-1">
                    <div class="col-md-6"><div class="soft-panel h-100"><strong>1. Créer un compte</strong><p class="mb-0 text-muted">Inscription complète avec coordonnées de facturation, case société/association, validation par administrateur et confirmation par mail.</p></div></div>
                    <div class="col-md-6"><div class="soft-panel h-100"><strong>2. Choisir un événement</strong><p class="mb-0 text-muted">Visualisation du plan, options disponibles, téléchargement de visuel de stand et présentation courte.</p></div></div>
                    <div class="col-md-6"><div class="soft-panel h-100"><strong>3. Confirmer la demande</strong><p class="mb-0 text-muted">Panier, acceptation des CGV PDF, frais de dossier non remboursables calculés à la date du dépôt.</p></div></div>
                    <div class="col-md-6"><div class="soft-panel h-100"><strong>4. Suivre la facturation</strong><p class="mb-0 text-muted">Validation par admin/modération/comptabilité, échéancier si besoin, facture PDF et messagerie intégrée.</p></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card rounded-4 bg-white h-100">
            <div class="card-body p-4">
                <h2 class="h3 section-title">Événement mis en avant</h2>
                <?php if ($featuredEvent): ?>
                    <h3 class="h4"><?= e($featuredEvent['title']) ?></h3>
                    <p class="text-muted mb-2"><?= e($featuredEvent['location']) ?> · du <?= e($featuredEvent['start_date']) ?> au <?= e($featuredEvent['end_date']) ?></p>
                    <p><?= e($featuredEvent['intro_text']) ?></p>
                    <a class="btn btn-primary" href="/event.php?id=<?= (int) $featuredEvent['id'] ?>">Accéder au plan</a>
                <?php else: ?>
                    <p class="mb-0 text-muted">Aucun événement actif pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
