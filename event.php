<?php
require_once __DIR__ . '/includes/layout.php';
$eventId = (int) ($_GET['id'] ?? 0);
$event = get_event($pdo, $eventId);
if (!$event) {
    http_response_code(404);
    exit('Événement introuvable.');
}
$stands = get_stands_with_status($pdo, $eventId);
$user = current_user();
render_header($event['title'], 'events');
?>
<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <h1 class="h2 section-title mb-1"><?= e($event['title']) ?></h1>
        <p class="text-muted mb-2"><?= e($event['location']) ?> · du <?= e($event['start_date']) ?> au <?= e($event['end_date']) ?></p>
        <p class="mb-0"><?= e($event['intro_text']) ?></p>
    </div>
    <div class="soft-panel py-3">
        <div><span class="legend-dot" style="background:#698f3f"></span> Disponible</div>
        <div><span class="legend-dot" style="background:#dd8b39"></span> En attente</div>
        <div><span class="legend-dot" style="background:#b94a48"></span> Réservé</div>
    </div>
</div>

<div class="card rounded-4 bg-white">
    <div class="card-body p-4">
        <div class="plan-board" id="mapClickBoard">
            <?php if (!empty($event['plan_image'])): ?><img src="<?= e($event['plan_image']) ?>" alt="Plan de l'événement"><?php endif; ?>
            <?php foreach ($stands as $stand):
                $payload = $stand;
                $payload['price_ttc_label'] = format_price((float) $stand['price_ttc']);
                foreach ($payload['options'] as &$option) {
                    $option['price_ttc_label'] = format_price((float) $option['price_ttc']);
                }
                unset($option);
            ?>
                <button
                    type="button"
                    class="stand-point <?= e($stand['status']) ?>"
                    style="left: <?= e((string) $stand['x_coord']) ?>%; top: <?= e((string) $stand['y_coord']) ?>%;"
                    title="<?= e($stand['label'] . ($stand['occupant'] ? ' · ' . $stand['occupant'] : '')) ?>"
                    <?= $stand['status'] === 'available' ? 'data-stand-json=\'' . json_attr($payload) . '\'' : '' ?>
                    <?= $stand['status'] !== 'available' ? 'disabled' : '' ?>
                ></button>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 small text-muted">Survol : l’exposant est indiqué pour les stands réservés. Cliquez sur un point vert pour préparer une réservation.</div>
    </div>
</div>

<div class="modal fade" id="standModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h2 class="modal-title h4" data-role="title">Stand</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/reserve.php?action=add" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="stand_id">
                    <input type="hidden" name="event_id">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <img data-role="image" class="img-fluid rounded-4 d-none mb-3" alt="Visuel type du stand">
                            <div class="soft-panel">
                                <div class="fw-semibold mb-2">Tarif TTC du stand</div>
                                <div class="display-6" data-role="price"></div>
                                <div class="small text-muted mt-2">Pré-réservation temporaire enregistrée dans votre panier pendant 15 minutes.</div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <p data-role="description"></p>
                            <p class="small text-muted" data-role="note"></p>
                            <div class="mb-3"><strong>Options proposées</strong><div data-role="options" class="mt-2"></div></div>
                            <div class="mb-3"><label class="form-label">Image PNG/JPG de présentation du stand</label><input type="file" name="stand_image" accept="image/png,image/jpeg" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Présentation courte du travail exposé</label><textarea name="presentation_text" rows="4" class="form-control" required></textarea></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="ai_sourced" id="aiSourced"><label class="form-check-label" for="aiSourced">Les origines ou inspirations sont sourcées de l’IA</label></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if (!$user): ?>
                        <a class="btn btn-outline-primary" href="/login.php">Se connecter pour réserver</a>
                    <?php else: ?>
                        <button class="btn btn-primary">Ajouter au panier</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
