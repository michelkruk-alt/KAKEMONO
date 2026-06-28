<?php
require_once __DIR__ . '/includes/layout.php';
$settings = get_settings($pdo);
render_header('Conditions générales', 'terms');
?>
<div class="card rounded-4 bg-white">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h2 section-title mb-1">Conditions générales de vente</h1>
                <p class="text-muted mb-0">Version PDF téléchargeable pour validation des demandes exposants.</p>
            </div>
            <a class="btn btn-primary" href="/cgv_pdf.php" target="_blank">Télécharger le PDF</a>
        </div>
        <ol class="vstack gap-3 ps-3">
            <li>Les demandes de réservation sont soumises à validation préalable par l’association.</li>
            <li>Les frais de dossier sont non remboursables et évoluent selon la date de dépôt : 50 € TTC dès le 1er janvier, 100 € TTC dès le 1er avril, 200 € TTC dès le 1er mai.</li>
            <li>La TVA est fixée à 0% pour l’activité associative.</li>
            <li>Le règlement peut être saisi par l’administration ou la comptabilité : virement, espèces, ou paiement échelonné avec échéancier.</li>
            <li>Les exposants doivent fournir une image PNG/JPG et une présentation de leur stand lors de la demande.</li>
            <li><?= e($settings['billing_note'] ?? '') ?></li>
        </ol>
    </div>
</div>
<?php render_footer(); ?>
