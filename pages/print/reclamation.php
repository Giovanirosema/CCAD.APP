<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pdo = db();
$ref = $_GET['ref'] ?? '';
$stmt = $pdo->prepare("
    SELECT rc.*, p.numero AS police_numero, p.capital AS police_capital, pl.nom AS plan_nom, a.prenom, a.nom
    FROM reclamations rc JOIN polices p ON p.id=rc.police_id JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id
    WHERE rc.reference=?
");
$stmt->execute([$ref]);
$d = $stmt->fetch();
if (!$d) { http_response_code(404); die('Dossier introuvable.'); }

$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];
$net = $d['capital'] - $d['arrieres'] - $d['frais_dossier'];

$versements = $pdo->prepare('SELECT * FROM reclamation_versements WHERE reclamation_id=?');
$versements->execute([$d['id']]);
$versements = $versements->fetchAll();

$doc_title = 'Dossier de réclamation';
$doc_ref = $d['reference'];
require __DIR__ . '/../../includes/print_start.php';
?>
<div class="print-section-title">Assuré décédé et police</div>
<dl class="print-grid">
  <div><dt>Assuré décédé</dt><dd><?= h($d['prenom'] . ' ' . $d['nom']) ?></dd></div>
  <div><dt>Police</dt><dd><?= h($d['police_numero']) ?></dd></div>
  <div><dt>Plan / couverture</dt><dd><?= h($d['plan_nom']) ?> · <?= fmt_money($d['police_capital'], $devise) ?></dd></div>
  <div><dt>Mois cotisés</dt><dd><?= (int) $d['mois_cotises'] ?> / <?= $eligMois ?></dd></div>
  <div><dt>Date du décès</dt><dd><?= fmt_date_fr($d['date_deces']) ?></dd></div>
  <div><dt>Lieu du décès</dt><dd><?= h($d['lieu_deces'] ?? '—') ?></dd></div>
  <div><dt>Cause déclarée</dt><dd><?= h(ucfirst($d['cause'] ?? '—')) ?></dd></div>
  <div><dt>Nº acte de décès</dt><dd><?= h($d['numero_acte_deces'] ?? '—') ?></dd></div>
  <div><dt>Déclarant</dt><dd><?= h($d['declarant_nom'] . ($d['declarant_lien'] ? ' · ' . $d['declarant_lien'] : '')) ?></dd></div>
  <div><dt>Téléphone du déclarant</dt><dd><?= h($d['declarant_telephone'] ?? '—') ?></dd></div>
  <div><dt>Témoin 1</dt><dd><?= h(trim(($d['temoin1_nom'] ?? '') . ' · ' . ($d['temoin1_telephone'] ?? ''), ' ·')) ?: '—' ?></dd></div>
  <div><dt>Témoin 2</dt><dd><?= h(trim(($d['temoin2_nom'] ?? '') . ' · ' . ($d['temoin2_telephone'] ?? ''), ' ·')) ?: '—' ?></dd></div>
  <div><dt>Statut du dossier</dt><dd><?= h($d['statut']) ?></dd></div>
</dl>

<div class="print-section-title">Calcul du capital</div>
<dl class="print-grid print-grid--3">
  <div><dt>Couverture du plan</dt><dd><?= fmt_money($d['capital'], $devise) ?></dd></div>
  <div><dt>Arriérés retenus</dt><dd>− <?= fmt_money($d['arrieres'], $devise) ?></dd></div>
  <div><dt>Frais de dossier</dt><dd>− <?= fmt_money($d['frais_dossier'], $devise) ?></dd></div>
</dl>
<div class="print-amount"><?= fmt_money($net, $devise) ?></div>

<div class="print-section-title">Répartition aux bénéficiaires</div>
<table class="print-table">
  <thead><tr><th>Bénéficiaire</th><th>Lien</th><th class="num">Part</th><th class="num">Montant</th><th>Mode</th></tr></thead>
  <tbody><?php foreach ($versements as $v): ?><tr><td><?= h($v['beneficiaire_nom']) ?></td><td><?= h($v['lien_parente']) ?></td><td class="num"><?= rtrim(rtrim($v['part_pct'],'0'),'.') ?>%</td><td class="num"><?= fmt_money($v['montant'], $devise) ?></td><td><?= h($v['mode']) ?></td></tr><?php endforeach; ?></tbody>
</table>

<div class="print-signatures">
  <div class="print-signature">Déclarant</div>
  <div class="print-signature">Agent CCAD</div>
  <div class="print-signature">Direction — visa de validation</div>
</div>

<?php require __DIR__ . '/../../includes/print_end.php'; ?>
