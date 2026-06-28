<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$reservationId = (int) ($_GET['id'] ?? 0);
$reservation = get_reservation($pdo, $reservationId);
if (!$reservation) {
    http_response_code(404);
    exit('Réservation introuvable.');
}
if ((int) $reservation['user_id'] !== (int) $user['id'] && !is_privileged($user) && (int) $user['role_level'] !== ROLE_ADMIN) {
    http_response_code(403);
    exit('Accès refusé.');
}
render_header('Dossier de réservation');
?>
<div class="card rounded-4 bg-white">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h2 section-title mb-1">Dossier de réservation <?= e($reservation['invoice_number'] ?: '#' . $reservation['id']) ?></h1>
                <p class="text-muted mb-0"><?= e($reservation['event_title']) ?> · Stand <?= e($reservation['stand_label']) ?></p>
            </div>
            <?php if ($reservation['status'] === RES_APPROVED): ?><a class="btn btn-primary" target="_blank" href="/invoice_pdf.php?id=<?= (int) $reservation['id'] ?>">Télécharger la facture PDF</a><?php endif; ?>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-6"><div class="soft-panel"><strong>Exposant</strong><p class="mb-0 mt-2"><?= e($reservation['first_name'] . ' ' . $reservation['last_name']) ?><br><?= e($reservation['address']) ?><br><?= e($reservation['postal_code'] . ' ' . $reservation['city']) ?><br><?= e($reservation['email']) ?></p></div></div>
            <div class="col-md-6"><div class="soft-panel"><strong>Statut</strong><p class="mb-0 mt-2"><?= e(reservation_status_label($reservation['status'])) ?><br>Règlement : <?= e($reservation['payment_method'] ?: 'À définir') ?><br>Échéancier : <?= e($reservation['payment_schedule'] ?: 'Aucun') ?></p></div></div>
        </div>
        <table class="table align-middle">
            <thead><tr><th>Désignation</th><th>Qté</th><th>Montant TTC</th></tr></thead>
            <tbody><?php foreach ($reservation['items'] as $item): ?><tr><td><?= e($item['label']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= format_price((float) $item['price_ttc'] * (int) $item['quantity']) ?></td></tr><?php endforeach; ?></tbody>
            <tfoot><tr><th colspan="2">Total TTC</th><th><?= format_price($reservation['total_ttc']) ?></th></tr></tfoot>
        </table>
    </div>
</div>
<?php render_footer(); ?>
