<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pdf.php';
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
$lines = [
    'Facture : ' . ($reservation['invoice_number'] ?: 'Non attribuée'),
    'Événement : ' . $reservation['event_title'],
    'Stand : ' . $reservation['stand_label'],
    'Exposant : ' . $reservation['first_name'] . ' ' . $reservation['last_name'],
    'Adresse : ' . $reservation['address'] . ', ' . $reservation['postal_code'] . ' ' . $reservation['city'],
    'Email : ' . $reservation['email'],
    'Statut : ' . reservation_status_label($reservation['status']),
    'Mode de règlement : ' . ($reservation['payment_method'] ?: 'À définir'),
    'Échéancier : ' . ($reservation['payment_schedule'] ?: 'Aucun'),
    '--- Details ---',
];
foreach ($reservation['items'] as $item) {
    $lines[] = $item['label'] . ' x' . $item['quantity'] . ' : ' . number_format((float) $item['price_ttc'] * (int) $item['quantity'], 2, '.', ' ') . ' EUR';
}
$lines[] = 'Total TTC : ' . number_format($reservation['total_ttc'], 2, '.', ' ') . ' EUR';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="facture-' . (int) $reservation['id'] . '.pdf"');
echo simple_pdf('Facture KAKEMONO', $lines);
