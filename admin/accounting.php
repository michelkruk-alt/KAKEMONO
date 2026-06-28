<?php
require_once __DIR__ . '/../includes/layout.php';
$current = require_roles([ROLE_ADMIN, ROLE_ACCOUNTING, ROLE_MODERATOR]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $status = $_POST['status'] ?? RES_PENDING;
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentSchedule = trim($_POST['payment_schedule'] ?? '');
    $currentReservation = get_reservation($pdo, $reservationId);
    $invoiceNumber = $currentReservation && $status === RES_APPROVED
        ? ($currentReservation['invoice_number'] ?: 'KAK-' . date('Y') . '-' . str_pad((string) $reservationId, 4, '0', STR_PAD_LEFT))
        : ($currentReservation['invoice_number'] ?? null);
    $stmt = $pdo->prepare('UPDATE reservations SET status = ?, payment_method = ?, payment_schedule = ?, invoice_number = ?, approved_by = ?, approved_at = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$status, $paymentMethod, $paymentSchedule, $invoiceNumber, (int) $current['id'], $status === RES_APPROVED ? now() : null, now(), $reservationId]);
    $reservation = get_reservation($pdo, $reservationId);
    if ($reservation) {
        queue_mail($pdo, $reservation['email'], 'Mise à jour de votre demande KAKEMONO', 'Votre dossier a été mis à jour. Facture : ' . ($invoiceNumber ?: 'à venir') . '. Consultez votre espace pour récupérer le PDF.');
    }
    set_flash('success', 'Réservation mise à jour.');
    redirect_to('/admin/accounting.php');
}
$eventFilter = (int) ($_GET['event_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$query = <<<SQL
    SELECT r.*, e.title AS event_title, s.label AS stand_label, u.first_name, u.last_name, u.email
    FROM reservations r
    JOIN events e ON e.id = r.event_id
    JOIN event_stands s ON s.id = r.stand_id
    JOIN users u ON u.id = r.user_id
    WHERE 1 = 1
SQL;
$params = [];
if ($eventFilter > 0) {
    $query .= ' AND r.event_id = ?';
    $params[] = $eventFilter;
}
if ($statusFilter !== '') {
    $query .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
$query .= ' ORDER BY r.created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll();
$events = get_all_events($pdo);
$totalStands = (int) $pdo->query('SELECT COUNT(*) FROM event_stands')->fetchColumn();
$approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'approved'")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending_admin'")->fetchColumn();
$fillRate = $totalStands ? (int) round(($approvedCount / $totalStands) * 100) : 0;
$chartRows = $pdo->query("SELECT substr(COALESCE(approved_at, created_at), 1, 7) AS label, COALESCE(SUM((SELECT COALESCE(SUM(price_ttc * quantity), 0) FROM reservation_items ri WHERE ri.reservation_id = r.id)), 0) AS revenue FROM reservations r WHERE status = 'approved' GROUP BY label ORDER BY label")->fetchAll();
$labels = array_map(fn($row) => $row['label'], $chartRows);
$values = array_map(fn($row) => round((float) $row['revenue'], 2), $chartRows);
render_header('Réservations & comptabilité', 'accounting');
?>
<div class="row g-4">
    <div class="col-lg-3">
        <div class="vstack gap-3">
            <div class="sidebar-stat"><div class="small text-uppercase">Taux de remplissage</div><div class="display-6 fw-bold"><?= $fillRate ?>%</div></div>
            <div class="sidebar-stat"><div class="small text-uppercase">Réservations en attente</div><div class="display-6 fw-bold"><a class="link-dark text-decoration-none" href="/admin/accounting.php?status=pending_admin"><?= $pendingCount ?></a></div></div>
            <div class="sidebar-stat"><div class="small text-uppercase">CA validé</div><div class="display-6 fw-bold"><?= format_price(array_sum($values)) ?></div></div>
            <div class="soft-panel">
                <h2 class="h5 section-title">Filtres</h2>
                <form method="get" class="vstack gap-3 mt-3">
                    <select name="event_id" class="form-select"><option value="0">Tous les événements</option><?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>" <?= $eventFilter === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['title']) ?></option><?php endforeach; ?></select>
                    <select name="status" class="form-select"><option value="">Tous les statuts</option><?php foreach ([RES_CART, RES_PENDING, RES_APPROVED, RES_REJECTED] as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(reservation_status_label($status)) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-primary">Appliquer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card rounded-4 bg-white mb-4"><div class="card-body p-4"><h1 class="h3 section-title">Demandes et factures</h1><canvas id="revenueChart" class="mt-3" data-labels='<?= e(json_encode($labels)) ?>' data-values='<?= e(json_encode($values)) ?>'></canvas></div></div>
        <div class="card rounded-4 bg-white"><div class="card-body p-4"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Exposant</th><th>Événement / stand</th><th>Statut</th><th>Total</th><th>Règlement</th><th>Validation</th></tr></thead><tbody><?php foreach ($reservations as $reservation): $detail = get_reservation($pdo, (int) $reservation['id']); ?><tr><td><?= e($reservation['first_name'] . ' ' . $reservation['last_name']) ?><br><small><?= e($reservation['email']) ?></small></td><td><?= e($reservation['event_title']) ?><br><small>Stand <?= e($reservation['stand_label']) ?></small></td><td><?= e(reservation_status_label($reservation['status'])) ?></td><td><?= format_price($detail['total_ttc']) ?></td><td><?= e($reservation['payment_method'] ?: 'À définir') ?></td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#approve-<?= (int) $reservation['id'] ?>">Traiter</button></td></tr><tr class="collapse" id="approve-<?= (int) $reservation['id'] ?>"><td colspan="6"><form method="post" class="row g-3 p-3 bg-light-subtle rounded-4"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>"><div class="col-md-4"><label class="form-label">Statut</label><select name="status" class="form-select"><?php foreach ([RES_PENDING, RES_APPROVED, RES_REJECTED] as $status): ?><option value="<?= e($status) ?>" <?= $reservation['status'] === $status ? 'selected' : '' ?>><?= e(reservation_status_label($status)) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Mode de règlement</label><select name="payment_method" class="form-select"><?php foreach (['Virement', 'Espèces', 'Échelonné'] as $method): ?><option value="<?= e($method) ?>" <?= ($reservation['payment_method'] ?: '') === $method ? 'selected' : '' ?>><?= e($method) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Échéancier</label><input name="payment_schedule" class="form-control" value="<?= e($reservation['payment_schedule'] ?? '') ?>" placeholder="ex. 3 fois 120 €"></div><div class="col-12"><button class="btn btn-primary">Enregistrer</button> <a class="btn btn-outline-secondary" href="/invoice.php?id=<?= (int) $reservation['id'] ?>">Voir le dossier</a></div></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
</div>
<?php render_footer(); ?>
