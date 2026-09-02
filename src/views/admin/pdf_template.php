<?php
/**
 * Vue / Template : Génération du Devis au format PDF (Moteur Dompdf)
 *
 * Ce fichier agit comme un "View Template" injecté dans le moteur Dompdf.
 *
 * CHOIX ARCHITECTURAL :
 * Le moteur de rendu de Dompdf est basé sur les standards CSS 2.1. Il ne supporte
 * ni Flexbox ni CSS Grid. Pour garantir une mise en page stricte et incassable lors
 * de l'export PDF (notamment pour l'alignement des montants financiers), l'utilisation
 * de tableaux HTML (<table>) est la solution technique la plus robuste et recommandée.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin/PDF
 * @author     Romain Remusat
 * @version    1.2.0
 *
 * @var array $devis       Données relationnelles du devis et du client (MySQL)
 * @var array $prestations Liste itérative des lignes commerciales (MySQL)
 */

// -----------------------------------------------------------------------------
// MOTEUR DE CALCUL FINANCIER (Règles Métier)
// -----------------------------------------------------------------------------
$totalHT = 0.0;

// Agrégation du Total Hors Taxes à partir du tableau des prestations
foreach ($prestations as $p) {
    $totalHT += (float)$p['montant_ht'];
}

// Application du taux de TVA standard français (20%)
$tvaRate = 0.20;
$totalTVA = $totalHT * $tvaRate;
$totalTTC = $totalHT + $totalTVA;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis - Innov'Events</title>
    <style>
        /*
         * INTEGRATION DE LA CHARTE GRAPHIQUE INNOV'EVENTS
         * Couleurs dominantes : Slate Dark (#0F172A) et Bleu Electrique (#3B82F6)
         */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .brand-logo {
            font-size: 22px;
            font-weight: bold;
            color: #0F172A; /* Slate Dark - Charte Graphique */
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .brand-sub {
            color: #3B82F6; /* Bleu Electrique - Charte Graphique */
            font-size: 12px;
            font-weight: normal;
        }
        .doc-title {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #0F172A;
        }
        .doc-ref {
            text-align: right;
            font-size: 11px;
            color: #64748B;
        }
        .info-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .info-card {
            width: 48%;
            vertical-align: top;
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 12px;
        }
        .info-card h4 {
            margin: 0 0 8px 0;
            color: #0F172A;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #3B82F6; /* Séparateur Bleu Electrique */
            padding-bottom: 4px;
        }
        .prestations-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .prestations-table th {
            background-color: #0F172A; /* En-tête Slate Dark */
            color: #FFFFFF;
            font-size: 11px;
            text-transform: uppercase;
            padding: 10px;
            text-align: left;
        }
        .prestations-table td {
            padding: 10px;
            border-bottom: 1px solid #E2E8F0;
        }
        .text-right {
            text-align: right;
        }
        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 10px;
        }
        .totals-table .total-row {
            background-color: #0F172A;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #94A3B8;
            border-top: 1px solid #E2E8F0;
            padding-top: 10px;
        }
    </style>
</head>
<body>

<!-- =================================================================== -->
<!-- EN-TÊTE DU DOCUMENT (Header)                                        -->
<!-- =================================================================== -->
<table class="header-table">
    <tr>
        <td style="width: 50%;">
            <div class="brand-logo">Innov'Events <span class="brand-sub">Manager</span></div>
            <div style="font-size: 11px; color: #64748B;">
                15 Rue de l'Innovation, 75000 Paris<br>
                Siret: 890 123 456 00012 — contact@innovevents.fr
            </div>
        </td>
        <td style="width: 50%;">
            <div class="doc-title">PROPOSITION DE DEVIS</div>
            <div class="doc-ref">Référence : <?= htmlspecialchars($devis['reference_pdf'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="doc-ref">Date : <?= date('d/m/Y') ?></div>
        </td>
    </tr>
</table>

<!-- =================================================================== -->
<!-- COORDONNÉES CROISÉES (Émetteur / Destinataire)                      -->
<!-- =================================================================== -->
<table class="info-box">
    <tr>
        <td class="info-card">
            <h4>Émetteur</h4>
            <strong>Innov'Events Agency</strong><br>
            Chargée de projet : Chloé (Direction Commerciale)<br>
            Tél : 01 23 45 67 89<br>
            Email : chloe@innovevents.fr
        </td>
        <td style="width: 4%;"></td> <!-- Espaceur structurel pour Dompdf -->
        <td class="info-card">
            <h4>Destinataire (Client)</h4>
            <strong><?= htmlspecialchars($devis['company_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
            Contact : <?= htmlspecialchars($devis['contact_name'], ENT_QUOTES, 'UTF-8') ?><br>
            Email : <?= htmlspecialchars($devis['email'], ENT_QUOTES, 'UTF-8') ?><br>
            Tél : <?= htmlspecialchars($devis['phone'], ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
</table>

<!-- =================================================================== -->
<!-- SYNTHÈSE DU CAHIER DES CHARGES (Data issue de la conversion)        -->
<!-- =================================================================== -->
<div style="background-color: #EFF6FF; border-left: 4px solid #3B82F6; padding: 10px; margin-bottom: 20px; font-size: 12px;">
    <strong>Événement prévu :</strong> <?= htmlspecialchars($devis['event_type'], ENT_QUOTES, 'UTF-8') ?>
    <?php if (!empty($devis['event_date'])): ?>
        — <strong>Date souhaitée :</strong> <?= date('d/m/Y', strtotime($devis['event_date'])) ?>
    <?php endif; ?>
</div>

<!-- =================================================================== -->
<!-- DÉTAIL COMMERCIAL (Itération sur la table Prestations)              -->
<!-- =================================================================== -->
<table class="prestations-table">
    <thead>
    <tr>
        <th style="width: 70%;">Description de la prestation</th>
        <th style="width: 30%;" class="text-right">Montant HT (€)</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($prestations)): ?>
        <tr>
            <td colspan="2" style="text-align: center; color: #94A3B8; padding: 20px;">
                Aucune prestation détaillée n'a été enregistrée pour ce devis.
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($prestations as $p): ?>
            <tr>
                <!-- Protection XSS stricte lors de l'injection dans le PDF -->
                <td><?= htmlspecialchars($p['libelle'], ENT_QUOTES, 'UTF-8') ?></td>
                <!-- Formatage comptable français exigé (Espaces pour milliers, Virgule pour décimales) -->
                <td class="text-right"><?= number_format($p['montant_ht'], 2, ',', ' ') ?> €</td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<!-- =================================================================== -->
<!-- ENCART FINANCIER (Totaux et TVA)                                    -->
<!-- =================================================================== -->
<table class="totals-table">
    <tr>
        <td class="text-right"><strong>Total HT :</strong></td>
        <td class="text-right"><?= number_format($totalHT, 2, ',', ' ') ?> €</td>
    </tr>
    <tr>
        <td class="text-right"><strong>TVA (20%) :</strong></td>
        <td class="text-right"><?= number_format($totalTVA, 2, ',', ' ') ?> €</td>
    </tr>
    <tr class="total-row">
        <td class="text-right"><strong>Total TTC :</strong></td>
        <td class="text-right"><?= number_format($totalTTC, 2, ',', ' ') ?> €</td>
    </tr>
</table>

<!-- =================================================================== -->
<!-- PIED DE PAGE (Mentions Légales & Conformité RGPD - AT1)             -->
<!-- =================================================================== -->
<div class="footer">
    Innov'Events Manager — SARL au capital de 50 000 € — N° TVA Intracommunautaire : FR 12 890123456<br>
    Conditions de paiement : Acompte de 30% à la commande, solde à la livraison de l'événement.<br>
    Conformément au <strong>RGPD</strong>, vous disposez d'un droit d'accès, de rectification et d'effacement de vos données personnelles.
</div>

</body>
</html>