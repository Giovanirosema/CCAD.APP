<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pdo = db();
$numero = $_GET['numero'] ?? '';
$stmt = $pdo->prepare("
    SELECT p.*, a.*, p.id AS police_id, pl.nom AS plan_nom, f.nom AS formule_nom, com.nom AS ville, dep.nom AS departement
    FROM polices p JOIN assures a ON a.id=p.assure_id JOIN plans pl ON pl.id=p.plan_id JOIN formules f ON f.id=p.formule_id
    LEFT JOIN communes com ON com.id=a.commune_id LEFT JOIN departements dep ON dep.id=a.departement_id
    WHERE p.numero=?
");
$stmt->execute([$numero]);
$d = $stmt->fetch();
if (!$d) { http_response_code(404); die('Police introuvable.'); }

$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];

$membres = $pdo->prepare('SELECT * FROM police_membres WHERE police_id=?');
$membres->execute([$d['police_id']]);
$membres = $membres->fetchAll();

$doc_title = "Fiche d'inscription";
$doc_ref = $d['numero'];
require __DIR__ . '/../../includes/print_start.php';
?>
<div class="print-section-title">Identité</div>
<dl class="print-grid">
  <div><dt>Nom et prénom</dt><dd><?= h($d['prenom'] . ' ' . $d['nom']) ?></dd></div>
  <div><dt>Référence assuré</dt><dd><?= h($d['reference']) ?></dd></div>
  <div><dt>Sexe</dt><dd><?= $d['sexe'] === 'f' ? 'Féminin' : ($d['sexe'] === 'm' ? 'Masculin' : '—') ?></dd></div>
  <div><dt>État civil</dt><dd><?= h(ucfirst($d['etat_civil'] ?? '—')) ?></dd></div>
  <div><dt>Date de naissance</dt><dd><?= fmt_date_fr($d['date_naissance']) ?></dd></div>
  <div><dt>Lieu de naissance</dt><dd><?= h($d['lieu_naissance'] ?? '—') ?></dd></div>
  <div><dt>Âge</dt><dd><?= age_years($d['date_naissance']) ?? '—' ?> ans</dd></div>
  <div><dt>Profession</dt><dd><?= h($d['profession'] ?? '—') ?></dd></div>
  <div><dt>NIF</dt><dd><?= h($d['nif'] ?? '—') ?></dd></div>
  <div><dt>Téléphone</dt><dd><?= h($d['telephone'] ?? '—') ?></dd></div>
  <div><dt>Deuxième téléphone</dt><dd><?= h($d['telephone2'] ?? '—') ?></dd></div>
  <div><dt>Courriel</dt><dd><?= h($d['email'] ?? '—') ?></dd></div>
  <div><dt>Commune</dt><dd><?= h($d['ville'] ?? '—') ?> (<?= h($d['departement'] ?? '—') ?>)</dd></div>
  <div><dt>Section communale</dt><dd><?= h($d['section_communale'] ?? '—') ?></dd></div>
  <div style="grid-column:1/-1"><dt>Adresse</dt><dd><?= h($d['adresse'] ?? '—') ?></dd></div>
</dl>

<div class="print-section-title">Police</div>
<dl class="print-grid">
  <div><dt>Numéro de police</dt><dd><?= h($d['numero']) ?></dd></div>
  <div><dt>Formule</dt><dd><?= h($d['formule_nom']) ?></dd></div>
  <div><dt>Plan tarifaire</dt><dd><?= h($d['plan_nom']) ?></dd></div>
  <div><dt>Cotisation mensuelle</dt><dd><?= fmt_money($d['prime'], $devise) ?></dd></div>
  <div><dt>Couverture funéraire</dt><dd><?= fmt_money($d['capital'], $devise) ?></dd></div>
  <div><dt>Frais d'inscription</dt><dd><?= fmt_money($d['frais_inscription'], $devise) ?></dd></div>
  <div><dt>Numéro MonCash</dt><dd><?= h($d['moncash_numero'] ?? '—') ?></dd></div>
  <div><dt>Zone de collecte</dt><dd><?= h($d['zone_collecte'] ?? '—') ?></dd></div>
  <div><dt>Date d'adhésion</dt><dd><?= fmt_date_fr($d['date_adhesion']) ?></dd></div>
  <div><dt>Éligibilité (<?= $eligMois ?> mois)</dt><dd><?= date('d/m/Y', strtotime($d['date_adhesion'] . " +$eligMois months")) ?></dd></div>
</dl>

<?php if ($d['temoin1_nom'] || $d['temoin2_nom']): ?>
<div class="print-section-title">Témoins</div>
<dl class="print-grid">
  <?php if ($d['temoin1_nom']): ?><div><dt>Témoin 1</dt><dd><?= h($d['temoin1_nom'] . ' · ' . $d['temoin1_telephone']) ?></dd></div><?php endif; ?>
  <?php if ($d['temoin2_nom']): ?><div><dt>Témoin 2</dt><dd><?= h($d['temoin2_nom'] . ' · ' . $d['temoin2_telephone']) ?></dd></div><?php endif; ?>
</dl>
<?php endif; ?>

<?php if ($membres): ?>
<div class="print-section-title">Membres couverts (plan familial)</div>
<table class="print-table">
  <thead><tr><th>Membre</th><th>Lien</th><th>Naissance</th><th class="num">Part</th></tr></thead>
  <tbody><?php foreach ($membres as $m): ?><tr><td><?= h($m['nom']) ?></td><td><?= h($m['lien_parente']) ?></td><td><?= fmt_date_fr($m['date_naissance']) ?></td><td class="num"><?= rtrim(rtrim($m['part_pct'],'0'),'.') ?>%</td></tr><?php endforeach; ?></tbody>
</table>
<?php endif; ?>

<div class="print-signatures">
  <div class="print-signature">Signature de l'assuré</div>
  <div class="print-signature">Signature de l'agent</div>
  <div class="print-signature">Cachet CCAD</div>
</div>

<?php require __DIR__ . '/../../includes/print_end.php'; ?>
