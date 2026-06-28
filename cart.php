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
$standOptionsStmt = $pdo->prepare(<<<'SQL'
    SELECT p.*
    FROM stand_options so
    JOIN products p ON p.id = so.option_product_id
    WHERE so.stand_id = ?
    ORDER BY p.name ASC
SQL);
$selectedOptionsStmt = $pdo->prepare('SELECT product_id FROM reservation_items WHERE reservation_id = ? AND item_type = ? AND product_id IS NOT NULL');
$applicableFee = dossier_fee_for_date(today());
render_header('Panier');
?>
<h1 class="h2 section-title mb-4">Panier & pré-réservations</h1>
<?php if (!$cartReservations): ?>
    <div class="card rounded-4 bg-white"><div class="card-body p-4"><p class="mb-0 text-muted">Votre panier est vide.</p></div></div>
<?php else: ?>
    <div class="vstack gap-4">
        <?php foreach ($cartReservations as $reservation):
            $detail = get_reservation($pdo, (int) $reservation['id']);
            $standOptionsStmt->execute([(int) $reservation['stand_id']]);
            $standOptions = $standOptionsStmt->fetchAll();
            $selectedOptionsStmt->execute([(int) $reservation['id'], 'option']);
            $selectedOptionIds = array_map('intval', $selectedOptionsStmt->fetchAll(PDO::FETCH_COLUMN));
            $selectedOptions = array_values(array_filter($detail['items'], static fn (array $item): bool => $item['item_type'] === 'option'));
            $feePreview = $applicableFee;
            $subtotalWithFee = (float) $detail['total_ttc'] + $feePreview;
        ?>
            <div class="card rounded-4 bg-white">
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="h4 mb-1"><?= e($reservation['event_title']) ?> · Stand <?= e($reservation['stand_label']) ?></h2>
                                    <div class="text-muted small">Expire le <?= e($reservation['hold_expires_at']) ?></div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-2">
                                    <span class="badge text-bg-warning">Pré-réservation</span>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-reservation-<?= (int) $reservation['id'] ?>" aria-expanded="false" aria-controls="edit-reservation-<?= (int) $reservation['id'] ?>">Modifier l’article</button>
                                </div>
                            </div>
                            <ul class="list-group mb-3">
                                <?php foreach ($detail['items'] as $item): ?>
                                    <?php if ($item['item_type'] === 'option' || $item['item_type'] === 'fee') { continue; } ?>
                                    <li class="list-group-item d-flex justify-content-between"><span><?= e($item['label']) ?></span><span><?= format_price((float) $item['price_ttc'] * (int) $item['quantity']) ?></span></li>
                                <?php endforeach; ?>
                                <li class="list-group-item d-flex justify-content-between"><span>Frais de dossier applicables</span><span><?= format_price($feePreview) ?></span></li>
                            </ul>
                            <div class="soft-panel mb-3">
                                <div class="fw-semibold mb-2">Options complémentaires souscrites</div>
                                <?php if ($selectedOptions): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($selectedOptions as $item): ?>
                                            <li class="list-group-item px-0 d-flex justify-content-between"><span><?= e($item['label']) ?></span><span><?= format_price((float) $item['price_ttc'] * (int) $item['quantity']) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="mb-0 text-muted">Aucune option complémentaire sélectionnée.</p>
                                <?php endif; ?>
                            </div>
                            <p class="mb-2"><strong>Présentation :</strong> <?= e($reservation['presentation_text']) ?></p>
                            <p class="mb-0"><strong>IA sourcée :</strong> <?= (int) $reservation['ai_sourced'] ? 'Oui' : 'Non' ?></p>
                            <div class="collapse mt-4" id="edit-reservation-<?= (int) $reservation['id'] ?>">
                                <div class="border rounded-4 p-4 bg-light-subtle">
                                    <h3 class="h5 mb-3">Modifier l’article</h3>
                                    <form method="post" action="/reserve.php?action=update" enctype="multipart/form-data" class="vstack gap-3">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                        <div>
                                            <label class="form-label">Options complémentaires</label>
                                            <div class="vstack gap-2">
                                                <?php if ($standOptions): ?>
                                                    <?php foreach ($standOptions as $option): ?>
                                                        <label class="form-check border rounded-3 p-3 bg-white">
                                                            <input class="form-check-input" type="checkbox" name="option_ids[]" value="<?= (int) $option['id'] ?>" <?= in_array((int) $option['id'], $selectedOptionIds, true) ? 'checked' : '' ?>>
                                                            <span class="form-check-label ms-1">
                                                                <strong><?= e($option['name']) ?></strong>
                                                                <?php if (!empty($option['description'])): ?><br><small class="text-muted"><?= e($option['description']) ?></small><?php endif; ?>
                                                                <br><span class="badge text-bg-secondary mt-1"><?= format_price((float) $option['price_ttc']) ?></span>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="mb-0 text-muted">Aucune option complémentaire disponible pour ce stand.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label">Remplacer l’image du stand</label>
                                            <input type="file" name="stand_image" accept="image/png,image/jpeg" class="form-control">
                                            <div class="form-text">Laissez vide pour conserver l’image actuelle.</div>
                                        </div>
                                        <div>
                                            <label class="form-label">Présentation courte du travail exposé</label>
                                            <textarea name="presentation_text" rows="4" class="form-control" required><?= e($reservation['presentation_text']) ?></textarea>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ai_sourced" id="edit-ai-<?= (int) $reservation['id'] ?>" <?= (int) $reservation['ai_sourced'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit-ai-<?= (int) $reservation['id'] ?>">Les origines ou inspirations sont sourcées de l’IA</label>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button class="btn btn-primary">Enregistrer les modifications</button>
                                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-reservation-<?= (int) $reservation['id'] ?>">Annuler</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="soft-panel h-100">
                                <div class="mb-2">Frais de dossier applicables aujourd’hui : <strong><?= format_price($feePreview) ?></strong></div>
                                <div class="mb-3 small text-muted">Non remboursables dans tous les cas.</div>
                                <form method="post" action="/reserve.php?action=confirm" class="vstack gap-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="terms_accepted" id="terms-<?= (int) $reservation['id'] ?>">
                                        <label class="form-check-label" for="terms-<?= (int) $reservation['id'] ?>">J’accepte les <a href="/cgv.php" target="_blank">conditions générales de vente</a>.</label>
                                    </div>
                                    <button class="btn btn-primary">Confirmer ma demande</button>
                                    <div class="fw-semibold">Sous-total actuel : <?= format_price($subtotalWithFee) ?></div>
                                </form>
                                <form method="post" action="/reserve.php?action=remove" class="mt-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                    <button class="btn btn-outline-danger w-100" onclick="return confirm('Supprimer cet article du panier et libérer la pré-réservation ?');">Supprimer l’article</button>
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
