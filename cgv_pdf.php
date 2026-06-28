<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pdf.php';
$settings = get_settings($pdo);
$lines = [
    'Conditions générales de vente KAKEMONO Events',
    '1. Toute réservation est soumise à validation préalable par l’association.',
    '2. Frais de dossier non remboursables : 50 EUR dès le 1er janvier, 100 EUR dès le 1er avril, 200 EUR dès le 1er mai.',
    '3. TVA à 0% pour l’activité associative.',
    '4. Paiements autorisés : virement, espèces, ou échéancier saisi par l’administration.',
    '5. Le visuel PNG/JPG et la présentation du stand sont obligatoires.',
    '6. Mention facturation : ' . ($settings['billing_note'] ?? ''),
];
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="cgv-kakemono.pdf"');
echo simple_pdf('CGV KAKEMONO', $lines);
