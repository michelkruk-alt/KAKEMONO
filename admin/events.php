<?php
require_once __DIR__ . '/../includes/layout.php';
require_roles([ROLE_ADMIN, ROLE_MODERATOR]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $planImage = null;
    try {
        $planImage = handle_image_upload('plan_image', 'plans');
    } catch (Throwable $throwable) {
        set_flash('danger', $throwable->getMessage());
        redirect_to('/admin/events.php');
    }
    if ($eventId > 0) {
        $currentStmt = $pdo->prepare('SELECT plan_image FROM events WHERE id = ?');
        $currentStmt->execute([$eventId]);
        $currentImage = $currentStmt->fetchColumn();
        $stmt = $pdo->prepare('UPDATE events SET title = ?, year_label = ?, location = ?, start_date = ?, end_date = ?, intro_text = ?, plan_image = ?, is_active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([trim($_POST['title'] ?? ''), trim($_POST['year_label'] ?? ''), trim($_POST['location'] ?? ''), $_POST['start_date'] ?? '', $_POST['end_date'] ?? '', trim($_POST['intro_text'] ?? ''), $planImage ?: $currentImage, isset($_POST['is_active']) ? 1 : 0, now(), $eventId]);
        set_flash('success', 'Événement mis à jour.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO events (title, year_label, location, start_date, end_date, intro_text, plan_image, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([trim($_POST['title'] ?? ''), trim($_POST['year_label'] ?? ''), trim($_POST['location'] ?? ''), $_POST['start_date'] ?? '', $_POST['end_date'] ?? '', trim($_POST['intro_text'] ?? ''), $planImage, isset($_POST['is_active']) ? 1 : 0, now(), now()]);
        set_flash('success', 'Événement créé.');
    }
    redirect_to('/admin/events.php');
}
$events = get_all_events($pdo);
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($events as $event) {
    if ((int) $event['id'] === $editId) {
        $editing = $event;
    }
}
render_header('Événements admin', 'events_admin');
?>
<div class="row g-4"><div class="col-lg-5"><div class="card rounded-4 bg-white"><div class="card-body p-4"><h1 class="h4 section-title mb-4"><?= $editing ? 'Modifier l’événement' : 'Créer un événement' ?></h1><form method="post" enctype="multipart/form-data" class="vstack gap-3"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) ($editing['id'] ?? 0) ?>"><div><label class="form-label">Titre</label><input name="title" class="form-control" value="<?= e($editing['title'] ?? '') ?>" required></div><div class="row g-3"><div class="col-md-4"><label class="form-label">Année</label><input name="year_label" class="form-control" value="<?= e($editing['year_label'] ?? '') ?>" required></div><div class="col-md-8"><label class="form-label">Lieu</label><input name="location" class="form-control" value="<?= e($editing['location'] ?? '') ?>" required></div></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Début</label><input type="date" name="start_date" class="form-control" value="<?= e($editing['start_date'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Fin</label><input type="date" name="end_date" class="form-control" value="<?= e($editing['end_date'] ?? '') ?>" required></div></div><div><label class="form-label">Texte d’introduction</label><textarea name="intro_text" class="form-control" rows="4" required><?= e($editing['intro_text'] ?? '') ?></textarea></div><div><label class="form-label">Plan PNG/JPG</label><input type="file" name="plan_image" accept="image/png,image/jpeg" class="form-control"></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="eventActive" <?= !isset($editing['is_active']) || (int) $editing['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="eventActive">Événement activé</label></div><button class="btn btn-primary">Enregistrer</button></form></div></div></div><div class="col-lg-7"><div class="card rounded-4 bg-white"><div class="card-body p-4"><h2 class="h4 section-title">Liste des événements</h2><div class="table-responsive mt-3"><table class="table align-middle"><thead><tr><th>Titre</th><th>Dates</th><th>État</th><th></th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= e($event['title']) ?></td><td><?= e($event['start_date']) ?> → <?= e($event['end_date']) ?></td><td><?= (int) $event['is_active'] ? 'Actif' : 'Inactif' ?></td><td class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="/admin/events.php?edit=<?= (int) $event['id'] ?>">Modifier</a><a class="btn btn-sm btn-outline-secondary" href="/admin/event_map.php?id=<?= (int) $event['id'] ?>">Plan & stands</a></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
<?php render_footer(); ?>
