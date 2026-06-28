<?php
require_once __DIR__ . '/includes/layout.php';
$events = get_active_events($pdo);
$eventId = (int) ($_GET['id'] ?? ($events[0]['id'] ?? 0));
$event = $eventId ? get_event($pdo, $eventId) : null;
$stands = $event ? get_stands_with_status($pdo, $eventId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $stmt = $pdo->prepare('INSERT INTO visitor_feedback (event_id, stand_id, visitor_name, visitor_email, appreciation, comment, is_private, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int) ($_POST['event_id'] ?? 0),
        $_POST['stand_id'] !== '' ? (int) $_POST['stand_id'] : null,
        trim($_POST['visitor_name'] ?? 'Visiteur'),
        trim($_POST['visitor_email'] ?? '') ?: null,
        $_POST['appreciation'] !== '' ? (int) $_POST['appreciation'] : null,
        trim($_POST['comment'] ?? ''),
        isset($_POST['is_private']) ? 1 : 0,
        now(),
    ]);
    set_flash('success', 'Merci pour votre message.');
    redirect_to('/touch.php?id=' . (int) ($_POST['event_id'] ?? 0));
}

$approvedReservations = [];
if ($event) {
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT r.*, s.label, u.first_name, u.last_name
        FROM reservations r
        JOIN event_stands s ON s.id = r.stand_id
        JOIN users u ON u.id = r.user_id
        WHERE r.event_id = ? AND r.status = 'approved'
    SQL);
    $stmt->execute([$eventId]);
    $approvedReservations = $stmt->fetchAll();
}
$guestbook = $event ? $pdo->prepare('SELECT * FROM visitor_feedback WHERE event_id = ? AND is_private = 0 ORDER BY created_at DESC LIMIT 10') : null;
$guestbook?->execute([$eventId]);
$guestEntries = $guestbook ? $guestbook->fetchAll() : [];
render_header('Plan visiteurs', 'touch');
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="h2 section-title mb-1">Plan visiteurs tactile</h1>
                        <p class="text-muted mb-0">Cliquez sur un stand rouge pour découvrir l’exposant et son activité.</p>
                    </div>
                    <form method="get"><select name="id" class="form-select" onchange="this.form.submit()"><?php foreach ($events as $item): ?><option value="<?= (int) $item['id'] ?>" <?= $eventId === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['title']) ?></option><?php endforeach; ?></select></form>
                </div>
                <div class="plan-board">
                    <?php if ($event && $event['plan_image']): ?><img src="<?= e($event['plan_image']) ?>" alt="Plan visiteurs"><?php endif; ?>
                    <?php foreach ($stands as $stand):
                        if ($stand['status'] !== 'reserved') {
                            continue;
                        }
                        $reservation = array_values(array_filter($approvedReservations, fn($entry) => (int) $entry['stand_id'] === (int) $stand['id']))[0] ?? null;
                        $payload = [
                            'label' => $stand['label'],
                            'occupant' => $stand['occupant'],
                            'presentation_text' => $reservation['presentation_text'] ?? '',
                            'stand_image' => $reservation['stand_image'] ?? '',
                        ];
                    ?>
                        <button type="button" class="stand-point reserved" style="left: <?= e((string) $stand['x_coord']) ?>%; top: <?= e((string) $stand['y_coord']) ?>%;" data-bs-toggle="modal" data-bs-target="#visitorModal" data-visitor='<?= json_attr($payload) ?>'></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="vstack gap-4">
            <div class="soft-panel kakemono-card">
                <h2 class="h4">Jeux Chasse aux Kakeminions</h2>
                <p class="mb-0">Encart visible pour annoncer la chasse et préparer l’affichage temps réel des membres de l’association dans une future version.</p>
            </div>
            <div class="soft-panel">
                <h2 class="h4">Planning & activités</h2>
                <ul class="mb-0 ps-3"><li>10h : ouverture visiteurs</li><li>14h : démonstration live</li><li>16h : animations familles</li><li>18h : remise des goodies</li></ul>
            </div>
            <div class="soft-panel">
                <h2 class="h4">Livre d’or & commentaire privé</h2>
                <form method="post" class="vstack gap-3 mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                    <input type="hidden" name="stand_id" value="">
                    <div><input name="visitor_name" class="form-control" placeholder="Votre nom"></div>
                    <div><input name="visitor_email" type="email" class="form-control" placeholder="Votre e-mail pour recevoir un tag / goodie"></div>
                    <div><select name="appreciation" class="form-select"><option value="">Appréciation</option><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select></div>
                    <div><textarea name="comment" rows="4" class="form-control" placeholder="Votre commentaire" required></textarea></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_private" id="privateFeedback"><label class="form-check-label" for="privateFeedback">Commentaire privé sur l’événement</label></div>
                    <button class="btn btn-primary">Publier</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-8">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4">
                <h2 class="h4 section-title">Livre d’or public</h2>
                <div class="vstack gap-3 mt-3">
                    <?php foreach ($guestEntries as $entry): ?>
                        <div class="soft-panel"><strong><?= e($entry['visitor_name']) ?></strong><span class="ms-2 text-muted small"><?= e($entry['created_at']) ?></span><p class="mb-0 mt-2"><?= e($entry['comment']) ?></p></div>
                    <?php endforeach; ?>
                    <?php if (!$guestEntries): ?><p class="text-muted mb-0">Aucun message public pour le moment.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="visitorModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content rounded-4"><div class="modal-header"><h2 class="modal-title h4">Exposant</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><img id="visitorImage" class="img-fluid rounded-4 mb-3 d-none" alt="Stand"><h3 id="visitorTitle" class="h4"></h3><p id="visitorText" class="mb-0"></p></div></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-visitor]').forEach(function (button) {
        button.addEventListener('click', function () {
            const payload = JSON.parse(this.getAttribute('data-visitor'));
            document.getElementById('visitorTitle').textContent = payload.label + ' · ' + payload.occupant;
            document.getElementById('visitorText').textContent = payload.presentation_text || 'Présentation à venir.';
            const image = document.getElementById('visitorImage');
            if (payload.stand_image) {
                image.src = payload.stand_image;
                image.classList.remove('d-none');
            } else {
                image.classList.add('d-none');
            }
        });
    });
});
</script>
<?php render_footer(); ?>
