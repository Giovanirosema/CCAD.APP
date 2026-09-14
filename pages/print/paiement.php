<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pdo = db();
$numero = $_GET['numero'] ?? '';
$stmt = $pdo->prepare("
    SELECT p.*, a.prenom, a.nom, pl.nom AS plan_nom
    FROM polices p JOIN assures a ON a.id=p.assure_id JOIN plans pl ON pl.id=p.plan_id
    WHERE p.numero=?
");
$stmt->execute([$numero]);
$d = $stmt->fetch();
if (!$d) { http_response_code(404); die('Police introuvable.'); }

$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$moisNoms = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$totalAnnuel = $d['prime'] * 12;

$doc_title = 'Fiche de paiement';
$doc_ref = $d['numero'];
require __DIR__ . '/../../includes/print_start.php';
?>
<div class="print-section-title">Assuré et police</div>
<dl class="print-grid print-grid--3">
  <div><dt>Assuré</dt><dd><?= h($d['prenom'] . ' ' . $d['nom']) ?></dd></div>
  <div><dt>Police</dt><dd><?= h($d['numero']) ?></dd></div>
  <div><dt>Plan</dt><dd><?= h($d['plan_nom']) ?></dd></div>
  <div><dt>Cotisation mensuelle</dt><dd><?= fmt_money($d['prime'], $devise) ?></dd></div>
  <div><dt>Mode habituel</dt><dd><?= h(ucfirst($d['mode_paiement'])) ?></dd></div>
  <div><dt>Échéance</dt><dd>Le <?= (int) $r['echeance_jour'] ?> de chaque mois</dd></div>
</dl>

<div class="print-section-title">Suivi annuel des cotisations — <?= date('Y') ?></div>
<table class="print-table">
  <thead><tr><th>Mois</th><th>Date</th><th>Reçu nº</th><th>Mode</th><th class="num">Montant</th><th>Signature</th></tr></thead>
  <tbody>
  <?php foreach ($moisNoms as $m): ?>
    <tr><td><?= $m ?></td><td></td><td></td><td></td><td class="num"></td><td></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<div class="print-note">Total annuel prévisionnel : <strong><?= fmt_money($totalAnnuel, $devise) ?></strong></div>

<?php require __DIR__ . '/../../includes/print_end.php'; ?>
