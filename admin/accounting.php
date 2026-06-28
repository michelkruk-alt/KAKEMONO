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
        ? ($currentReservation['invoice_number'] ?: generate_invoice_number($reservationId))
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
$activeTab = $_GET['tab'] ?? 'reservations';

$totalStands = (int) $pdo->query('SELECT COUNT(*) FROM event_stands')->fetchColumn();
$approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'approved'")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending_admin'")->fetchColumn();
$rejectedCount = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'rejected'")->fetchColumn();
$fillRate = $totalStands ? (int) round(($approvedCount / $totalStands) * 100) : 0;

$monthExpr = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
    ? 'SUBSTRING(COALESCE(r.approved_at, r.created_at), 1, 7)'
    : 'substr(COALESCE(r.approved_at, r.created_at), 1, 7)';
$chartRows = $pdo->query("
    SELECT {$monthExpr} AS label,
           COALESCE(SUM(ri.price_ttc * ri.quantity), 0) AS revenue
    FROM reservations r
    LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
    WHERE r.status = 'approved'
    GROUP BY label
    ORDER BY label
")->fetchAll();
$labels = array_map(fn($row) => $row['label'], $chartRows);
$values = array_map(fn($row) => round((float) $row['revenue'], 2), $chartRows);

// Financial totals
$totalApproved = (float) $pdo->query("SELECT COALESCE(SUM(ri.price_ttc * ri.quantity), 0) FROM reservation_items ri JOIN reservations r ON r.id = ri.reservation_id WHERE r.status = 'approved'")->fetchColumn();
$totalPending  = (float) $pdo->query("SELECT COALESCE(SUM(ri.price_ttc * ri.quantity), 0) FROM reservation_items ri JOIN reservations r ON r.id = ri.reservation_id WHERE r.status = 'pending_admin'")->fetchColumn();
$totalRejected = (float) $pdo->query("SELECT COALESCE(SUM(ri.price_ttc * ri.quantity), 0) FROM reservation_items ri JOIN reservations r ON r.id = ri.reservation_id WHERE r.status = 'rejected'")->fetchColumn();

// Invoices tab: approved reservations with invoice number
$invoicesStmt = $pdo->query("
    SELECT r.*, e.title AS event_title, s.label AS stand_label, u.first_name, u.last_name, u.email
    FROM reservations r
    JOIN events e ON e.id = r.event_id
    JOIN event_stands s ON s.id = r.stand_id
    JOIN users u ON u.id = r.user_id
    WHERE r.status = 'approved'
    ORDER BY r.approved_at DESC
");
$invoices = $invoicesStmt->fetchAll();

// Schedules tab: reservations with a payment schedule defined
$schedulesStmt = $pdo->query("
    SELECT r.*, e.title AS event_title, s.label AS stand_label, u.first_name, u.last_name, u.email
    FROM reservations r
    JOIN events e ON e.id = r.event_id
    JOIN event_stands s ON s.id = r.stand_id
    JOIN users u ON u.id = r.user_id
    WHERE r.payment_schedule IS NOT NULL AND r.payment_schedule != ''
    ORDER BY r.updated_at DESC
");
$schedules = $schedulesStmt->fetchAll();

render_header('Réservations & comptabilité', 'accounting');
?>
<div class="row g-4">
    <div class="col-lg-3">
        <div class="vstack gap-3">
            <div class="sidebar-stat"><div class="small text-uppercase">Taux de remplissage</div><div class="display-6 fw-bold"><?= $fillRate ?>%</div></div>
            <div class="sidebar-stat"><div class="small text-uppercase">Réservations en attente</div><div class="display-6 fw-bold"><a class="link-dark text-decoration-none" href="/admin/accounting.php?status=pending_admin"><?= $pendingCount ?></a></div></div>
            <div class="sidebar-stat"><div class="small text-uppercase">CA encaissé (validé)</div><div class="display-6 fw-bold text-success"><?= format_price($totalApproved) ?></div></div>
            <div class="sidebar-stat"><div class="small text-uppercase">CA en attente</div><div class="display-6 fw-bold text-warning"><?= format_price($totalPending) ?></div></div>
            <div class="sidebar-stat"><div class="small text-uppercase">CA refusé</div><div class="display-6 fw-bold text-danger"><?= format_price($totalRejected) ?></div></div>
            <div class="soft-panel">
                <h2 class="h5 section-title">Filtres</h2>
                <form method="get" class="vstack gap-3 mt-3">
                    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                    <select name="event_id" class="form-select"><option value="0">Tous les événements</option><?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>" <?= $eventFilter === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['title']) ?></option><?php endforeach; ?></select>
                    <select name="status" class="form-select"><option value="">Tous les statuts</option><?php foreach ([RES_CART, RES_PENDING, RES_APPROVED, RES_REJECTED] as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(reservation_status_label($status)) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-primary">Appliquer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card rounded-4 bg-white mb-4"><div class="card-body p-4"><h1 class="h3 section-title">Demandes et comptabilité</h1><canvas id="revenueChart" class="mt-3" data-labels='<?= e(json_encode($labels)) ?>' data-values='<?= e(json_encode($values)) ?>'></canvas></div></div>

        <div class="card rounded-4 bg-white">
            <div class="card-body p-0">
                <ul class="nav nav-tabs px-4 pt-3" id="accountingTabs">
                    <li class="nav-item"><a class="nav-link <?= $activeTab === 'reservations' ? 'active' : '' ?>" href="/admin/accounting.php?tab=reservations&event_id=<?= $eventFilter ?>&status=<?= e($statusFilter) ?>">Réservations <span class="badge text-bg-secondary"><?= count($reservations) ?></span></a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeTab === 'invoices' ? 'active' : '' ?>" href="/admin/accounting.php?tab=invoices&event_id=<?= $eventFilter ?>">Factures <span class="badge text-bg-success"><?= count($invoices) ?></span></a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeTab === 'schedules' ? 'active' : '' ?>" href="/admin/accounting.php?tab=schedules&event_id=<?= $eventFilter ?>">Échéanciers <span class="badge text-bg-warning text-dark"><?= count($schedules) ?></span></a></li>
                </ul>
                <div class="p-4">

                <?php if ($activeTab === 'reservations'): ?>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Exposant</th><th>Événement / stand</th><th>Statut</th><th>Total</th><th>Règlement</th><th>Action</th></tr></thead><tbody><?php foreach ($reservations as $reservation): $detail = get_reservation($pdo, (int) $reservation['id']); ?><tr><td><?= e($reservation['first_name'] . ' ' . $reservation['last_name']) ?><br><small><?= e($reservation['email']) ?></small></td><td><?= e($reservation['event_title']) ?><br><small>Stand <?= e($reservation['stand_label']) ?></small></td><td><?= e(reservation_status_label($reservation['status'])) ?></td><td><?= format_price($detail['total_ttc']) ?></td><td><?= e($reservation['payment_method'] ?: 'À définir') ?></td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#approve-<?= (int) $reservation['id'] ?>">Traiter</button></td></tr><tr class="collapse" id="approve-<?= (int) $reservation['id'] ?>"><td colspan="6"><form method="post" class="row g-3 p-3 bg-light-subtle rounded-4"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>"><div class="col-md-4"><label class="form-label">Statut</label><select name="status" class="form-select"><?php foreach ([RES_PENDING, RES_APPROVED, RES_REJECTED] as $status): ?><option value="<?= e($status) ?>" <?= $reservation['status'] === $status ? 'selected' : '' ?>><?= e(reservation_status_label($status)) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Mode de règlement</label><select name="payment_method" class="form-select"><?php foreach (['Virement', 'Espèces', 'Échelonné'] as $method): ?><option value="<?= e($method) ?>" <?= ($reservation['payment_method'] ?: '') === $method ? 'selected' : '' ?>><?= e($method) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Échéancier</label><input name="payment_schedule" class="form-control" value="<?= e($reservation['payment_schedule'] ?? '') ?>" placeholder="ex. 3 fois 120 €"></div><div class="col-12"><button class="btn btn-primary">Enregistrer</button> <a class="btn btn-outline-secondary" href="/invoice.php?id=<?= (int) $reservation['id'] ?>">Voir le dossier</a></div></form></td></tr><?php endforeach; ?><?php if (!$reservations): ?><tr><td colspan="6" class="text-muted text-center py-4">Aucune réservation pour ces filtres.</td></tr><?php endif; ?></tbody></table></div>

                <?php elseif ($activeTab === 'invoices'): ?>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>N° Facture</th><th>Exposant</th><th>Événement / stand</th><th>Total TTC</th><th>Mode de règlement</th><th>Date de validation</th><th>PDF</th></tr></thead><tbody><?php foreach ($invoices as $inv): $invDetail = get_reservation($pdo, (int) $inv['id']); ?><tr><td><strong><?= e($inv['invoice_number'] ?: generate_invoice_number((int) $inv['id'])) ?></strong></td><td><?= e($inv['first_name'] . ' ' . $inv['last_name']) ?><br><small><?= e($inv['email']) ?></small></td><td><?= e($inv['event_title']) ?><br><small>Stand <?= e($inv['stand_label']) ?></small></td><td><?= format_price($invDetail['total_ttc']) ?></td><td><?= e($inv['payment_method'] ?: 'Non précisé') ?></td><td><?= e($inv['approved_at'] ?? '-') ?></td><td><a class="btn btn-sm btn-outline-primary" href="/invoice_pdf.php?id=<?= (int) $inv['id'] ?>" target="_blank">PDF</a> <a class="btn btn-sm btn-outline-secondary" href="/invoice.php?id=<?= (int) $inv['id'] ?>">Dossier</a></td></tr><?php endforeach; ?><?php if (!$invoices): ?><tr><td colspan="7" class="text-muted text-center py-4">Aucune facture émise.</td></tr><?php endif; ?></tbody></table></div>
                <div class="text-end mt-3 fw-bold">Total facturé : <?= format_price($totalApproved) ?></div>

                <?php elseif ($activeTab === 'schedules'): ?>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Exposant</th><th>Événement / stand</th><th>Statut</th><th>Total TTC</th><th>Mode de règlement</th><th>Détail de l'échéancier</th><th>Dossier</th></tr></thead><tbody><?php foreach ($schedules as $sch): $schDetail = get_reservation($pdo, (int) $sch['id']); ?><tr><td><?= e($sch['first_name'] . ' ' . $sch['last_name']) ?><br><small><?= e($sch['email']) ?></small></td><td><?= e($sch['event_title']) ?><br><small>Stand <?= e($sch['stand_label']) ?></small></td><td><?= e(reservation_status_label($sch['status'])) ?></td><td><?= format_price($schDetail['total_ttc']) ?></td><td><?= e($sch['payment_method'] ?: 'Non précisé') ?></td><td><span class="badge text-bg-light text-dark border"><?= e($sch['payment_schedule']) ?></span></td><td><a class="btn btn-sm btn-outline-secondary" href="/invoice.php?id=<?= (int) $sch['id'] ?>">Dossier</a></td></tr><?php endforeach; ?><?php if (!$schedules): ?><tr><td colspan="7" class="text-muted text-center py-4">Aucun échéancier enregistré.</td></tr><?php endif; ?></tbody></table></div>

                <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
