<?php
require_once __DIR__ . '/includes/layout.php';
$events = get_all_events($pdo);
render_header('Événements', 'events');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 section-title mb-1">Événements</h1>
        <p class="text-muted mb-0">Sélectionnez un événement actif pour réserver un stand ou explorer le plan.</p>
    </div>
</div>
<div class="row g-4">
    <?php foreach ($events as $event):
        $stands = get_stands_with_status($pdo, (int) $event['id']);
        $occupied = count(array_filter($stands, fn($stand) => $stand['status'] !== 'available'));
        $fillRate = count($stands) ? (int) round(($occupied / count($stands)) * 100) : 0;
    ?>
        <div class="col-lg-6">
            <div class="card rounded-4 bg-white h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <span class="badge <?= (int) $event['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?> mb-2"><?= (int) $event['is_active'] ? 'Actif' : 'Inactif' ?></span>
                            <h2 class="h4"><?= e($event['title']) ?></h2>
                            <p class="text-muted mb-2"><?= e($event['location']) ?> · <?= e($event['start_date']) ?> → <?= e($event['end_date']) ?></p>
                            <p><?= e($event['intro_text']) ?></p>
                        </div>
                        <div class="text-end">
                            <div class="display-6 fw-bold"><?= $fillRate ?>%</div>
                            <small class="text-muted">Taux de remplissage</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-muted"><?= count($stands) ?> stands · <?= $occupied ?> indisponibles</small>
                        <a class="btn btn-primary" href="/event.php?id=<?= (int) $event['id'] ?>">Voir le plan</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php render_footer(); ?>
