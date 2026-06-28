<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$resStmt = $pdo->prepare(<<<'SQL'
    SELECT r.*, e.title AS event_title, s.label AS stand_label
    FROM reservations r
    JOIN events e ON e.id = r.event_id
    JOIN event_stands s ON s.id = r.stand_id
    WHERE r.user_id = ? AND r.status = 'cart'
    ORDER BY r.created_at DESC
SQL);
$resStmt->execute([(int) $user['id']]);
$cartReservations = $resStmt->fetchAll();
render_header('Panier');
?>
<h1 class="h2 section-title mb-4">Panier & pré-réservations</h1>
<?php if (!$cartReservations): ?>
    <div class="card rounded-4 bg-white"><div class="card-body p-4"><p class="mb-0 text-muted">Votre panier est vide.</p></div></div>
<?php else: ?>
    <div class="vstack gap-4">
        <?php foreach ($cartReservations as $reservation): $detail = get_reservation($pdo, (int) $reservation['id']); ?>
            <div class="card rounded-4 bg-white">
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="h4 mb-1"><?= e($reservation['event_title']) ?> · Stand <?= e($reservation['stand_label']) ?></h2>
                                    <div class="text-muted small">Expire le <?= e($reservation['hold_expires_at']) ?></div>
                                </div>
                                <span class="badge text-bg-warning">Pré-réservation</span>
                            </div>
                            <ul class="list-group mb-3">
                                <?php foreach ($detail['items'] as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between"><span><?= e($item['label']) ?></span><span><?= format_price((float) $item['price_ttc'] * (int) $item['quantity']) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="mb-2"><strong>Présentation :</strong> <?= e($reservation['presentation_text']) ?></p>
                            <p class="mb-0"><strong>IA sourcée :</strong> <?= (int) $reservation['ai_sourced'] ? 'Oui' : 'Non' ?></p>
                        </div>
                        <div class="col-lg-4">
                            <div class="soft-panel h-100">
                                <div class="mb-2">Frais de dossier applicables aujourd’hui : <strong><?= format_price(dossier_fee_for_date(today())) ?></strong></div>
                                <div class="mb-3 small text-muted">Non remboursables dans tous les cas.</div>
                                <form method="post" action="/reserve.php?action=confirm" class="vstack gap-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="terms_accepted" id="terms-<?= (int) $reservation['id'] ?>">
                                        <label class="form-check-label" for="terms-<?= (int) $reservation['id'] ?>">J’accepte les <a href="/cgv.php" target="_blank">conditions générales de vente</a>.</label>
                                    </div>
                                    <button class="btn btn-primary">Confirmer ma demande</button>
                                    <div class="fw-semibold">Sous-total actuel : <?= format_price($detail['total_ttc']) ?></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
