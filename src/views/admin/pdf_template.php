<?php
/**
 * Template de génération de Devis PDF (View)
 *
 * Ce fichier définit la structure visuelle du document PDF généré via DomPDF.
 * Il exploite les données du modèle 'prospect' injectées par PdfController.
 *
 * Points d'attention pour les développeurs :
 * - Sécurisation XSS : Chaque donnée est traitée par `htmlspecialchars()`.
 * - Gestion défensive : Utilisation de l'opérateur de coalescence (??) pour
 * gérer les valeurs `NULL` en base de données et éviter les erreurs de type.
 * - Sémantique : Le CSS est intégré inline pour assurer la compatibilité
 * avec le moteur de rendu de DomPDF.
 *
 * @var array $prospect Données du prospect (Company, contact, budget, etc.)
 */

$p = $prospect ?? [];

// Ventilation automatique pour répondre à l'exigence des prestations HT / TVA / TTC
$budgetGlobal = (float)($p['budget'] ?? 0);
$prestations = [
    [
        'libelle' => 'Prestation Logistique & Coordination - ' . ($p['event_type'] ?? 'Événement'),
        'ht' => $budgetGlobal * 0.60
    ],
    [
        'libelle' => 'Service Traiteur & Restauration d\'exception',
        'ht' => $budgetGlobal * 0.40
    ]
];

$totalHT = 0;
foreach ($prestations as $pres) {
    $totalHT += $pres['ht'];
}

$tvaRate = 0.20; // TVA à 20%
$montantTVA = $totalHT * $tvaRate;
$totalTTC = $totalHT + $montantTVA;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis <?= htmlspecialchars($p['company_name'] ?? 'Inconnu') ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; line-height: 1.5; color: #333; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table th { background-color: #f8f9fa; width: 30%; text-align: left; }
        .info-table th, .info-table td { padding: 8px; border: 1px solid #ddd; }

        /* Table des prestations */
        .prest-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .prest-table th { background-color: #0f172a; color: #ffffff; text-align: left; padding: 10px; }
        .prest-table td { padding: 10px; border: 1px solid #ddd; }
        .text-right { text-align: right; }

        .total-container { width: 50%; margin-left: auto; margin-top: 20px; border-collapse: collapse; }
        .total-container td { padding: 6px 10px; border: 1px solid #ddd; }
        .total-ttc { font-weight: bold; background-color: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body>

<div class="header">
    <h1 style="color: #0f172a; margin: 0;">DEVIS OFFICIEL INNOV'EVENTS</h1>
    <p style="margin: 5px 0 0 0;">Document généré le <?= date('d/m/Y') ?></p>
</div>

<h3>Informations Client</h3>
<table class="info-table">
    <tr><th>Entreprise</th><td><?= htmlspecialchars($p['company_name'] ?? 'Non renseigné') ?></td></tr>
    <tr><th>Contact référent</th><td><?= htmlspecialchars($p['contact_name'] ?? 'Non renseigné') ?></td></tr>
    <tr><th>Email</th><td><?= htmlspecialchars($p['email'] ?? 'Non renseigné') ?></td></tr>
    <tr><th>Téléphone</th><td><?= htmlspecialchars($p['phone'] ?? 'Non renseigné') ?></td></tr>
</table>

<h3>Détail des Prestations</h3>
<table class="prest-table">
    <thead>
        <tr>
            <th>Description de la prestation</th>
            <th class="text-right" style="width: 25%;">Montant HT</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($prestations as $pres): ?>
            <tr>
                <td><?= htmlspecialchars($pres['libelle']) ?></td>
                <td class="text-right"><?= number_format($pres['ht'], 2, ',', ' ') ?> €</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="total-container">
    <tr>
        <td>Total Hors Taxes (HT)</td>
        <td class="text-right"><?= number_format($totalHT, 2, ',', ' ') ?> €</td>
    </tr>
    <tr>
        <td>TVA (20%)</td>
        <td class="text-right"><?= number_format($montantTVA, 2, ',', ' ') ?> €</td>
    </tr>
    <tr class="total-ttc">
        <td>Total TTC</td>
        <td class="text-right"><?= number_format($totalTTC, 2, ',', ' ') ?> €</td>
    </tr>
</table>

<p style="margin-top: 30px;"><strong>Description du projet :</strong></p>
<div style="border-left: 3px solid #0f172a; padding-left: 10px; background: #fafafa; font-style: italic;">
    <?= nl2br(htmlspecialchars($p['description'] ?? 'Aucune description fournie.', ENT_QUOTES, 'UTF-8')) ?>
</div>

</body>
</html>