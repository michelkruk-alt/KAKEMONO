<?php
require_once __DIR__ . '/../includes/layout.php';
require_roles([ROLE_ADMIN, ROLE_MODERATOR]);
$eventId = (int) ($_GET['id'] ?? 0);
$event = get_event($pdo, $eventId);
if (!$event) {
    http_response_code(404);
    exit('Événement introuvable.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $standId = (int) ($_POST['stand_id'] ?? 0);
    $action   = $_POST['action'] ?? 'save';

    if ($action === 'delete' && $standId > 0) {
        // Block deletion if stand has non-cart reservations (preserve history)
        $resCount = (int) $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE stand_id = ? AND status != 'cart'")->execute([$standId]) ? 0 : 0;
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE stand_id = ? AND status != 'cart'");
        $checkStmt->execute([$standId]);
        $resCount = (int) $checkStmt->fetchColumn();
        if ($resCount > 0) {
            set_flash('danger', 'Ce stand ne peut pas être supprimé car il est lié à des réservations en cours ou validées.');
        } else {
            $pdo->prepare('DELETE FROM event_stands WHERE id = ? AND event_id = ?')->execute([$standId, $eventId]);
            set_flash('success', 'Stand supprimé.');
        }
        redirect_to('/admin/event_map.php?id=' . $eventId);
    }

    $optionIds = array_map('intval', $_POST['option_ids'] ?? []);
    if ($standId > 0) {
        $stmt = $pdo->prepare('UPDATE event_stands SET label = ?, product_id = ?, x_coord = ?, y_coord = ?, note = ?, updated_at = ? WHERE id = ? AND event_id = ?');
        $stmt->execute([trim($_POST['label'] ?? ''), (int) ($_POST['product_id'] ?? 0), (float) ($_POST['x_coord'] ?? 0), (float) ($_POST['y_coord'] ?? 0), trim($_POST['note'] ?? ''), now(), $standId, $eventId]);
        $pdo->prepare('DELETE FROM stand_options WHERE stand_id = ?')->execute([$standId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO event_stands (event_id, product_id, label, x_coord, y_coord, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$eventId, (int) ($_POST['product_id'] ?? 0), trim($_POST['label'] ?? ''), (float) ($_POST['x_coord'] ?? 0), (float) ($_POST['y_coord'] ?? 0), trim($_POST['note'] ?? ''), now(), now()]);
        $standId = (int) $pdo->lastInsertId();
    }
    $insert = $pdo->prepare('INSERT INTO stand_options (stand_id, option_product_id) VALUES (?, ?)');
    foreach ($optionIds as $optionId) {
        $insert->execute([$standId, $optionId]);
    }
    set_flash('success', 'Stand enregistré.');
    redirect_to('/admin/event_map.php?id=' . $eventId);
}
$spaceProducts = $pdo->query("SELECT * FROM products WHERE product_type = 'space' AND active = 1 ORDER BY name")->fetchAll();
$optionProducts = $pdo->query("SELECT * FROM products WHERE product_type = 'option' AND active = 1 ORDER BY name")->fetchAll();
$stands = get_stands_with_status($pdo, $eventId);
$optionMapStmt = $pdo->prepare('SELECT option_product_id FROM stand_options WHERE stand_id = ?');
render_header('Plan & stands', 'events_admin');
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 section-title mb-1">Plan & stands · <?= e($event['title']) ?></h1><p class="text-muted mb-0">Cliquez sur le plan pour placer un stand ou utilisez le bouton d’un point existant pour le modifier.</p></div><a class="btn btn-outline-primary" href="/admin/events.php">Retour aux événements</a></div>
<div class="card rounded-4 bg-white"><div class="card-body p-4"><div class="plan-board" id="mapClickBoard" data-editable="1"><?php if ($event['plan_image']): ?><img src="<?= e($event['plan_image']) ?>" alt="Plan événement"><?php endif; ?><?php foreach ($stands as $stand): $optionMapStmt->execute([(int) $stand['id']]); $optionIds = $optionMapStmt->fetchAll(PDO::FETCH_COLUMN); $payload = ['id' => (int) $stand['id'], 'label' => $stand['label'], 'product_id' => (int) $stand['product_id'], 'x_coord' => (float) $stand['x_coord'], 'y_coord' => (float) $stand['y_coord'], 'note' => $stand['note'], 'option_ids' => array_map('intval', $optionIds)]; ?><button type="button" class="stand-point <?= e($stand['status']) ?>" style="left: <?= e((string) $stand['x_coord']) ?>%; top: <?= e((string) $stand['y_coord']) ?>%;" data-edit-stand='<?= json_attr($payload) ?>' title="<?= e($stand['label']) ?>"></button><?php endforeach; ?></div><div class="small text-muted mt-3" id="coordHelp">Cliquez sur le plan pour choisir les coordonnées.</div></div></div>
<div class="modal fade" id="standEditorModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content rounded-4"><div class="modal-header"><h2 class="modal-title h4">Stand</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="post" id="standEditorForm"><div class="modal-body"><?= csrf_field() ?><input type="hidden" name="stand_id" value="0"><div class="row g-3"><div class="col-md-6"><label class="form-label">Libellé</label><input name="label" class="form-control" required></div><div class="col-md-6"><label class="form-label">Type de stand</label><select name="product_id" class="form-select"><?php foreach ($spaceProducts as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">X (%)</label><input name="x_coord" class="form-control" required></div><div class="col-md-6"><label class="form-label">Y (%)</label><input name="y_coord" class="form-control" required></div><div class="col-12"><label class="form-label">Note</label><textarea name="note" rows="3" class="form-control"></textarea></div><div class="col-12"><label class="form-label">Options autorisées</label><div class="row g-2"><?php foreach ($optionProducts as $option): ?><div class="col-md-6"><div class="form-check border rounded-3 p-2"><input class="form-check-input" type="checkbox" name="option_ids[]" value="<?= (int) $option['id'] ?>" id="stand-option-<?= (int) $option['id'] ?>"><label class="form-check-label" for="stand-option-<?= (int) $option['id'] ?>"><?= e($option['name']) ?></label></div></div><?php endforeach; ?></div></div></div></div><div class="modal-footer d-flex justify-content-between align-items-center"><div id="standDeleteWrapper" class="d-none"><button type="button" class="btn btn-outline-danger" id="standDeleteBtn">Supprimer ce stand</button></div><div class="d-flex gap-2"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer le stand</button></div></div></form></div></div></div>
<form method="post" id="standDeleteForm" class="d-none">
<?= csrf_field() ?>
<input type="hidden" name="stand_id" id="deleteStandId" value="0">
<input type="hidden" name="action" value="delete">
</form>
<?php render_footer(); ?>
