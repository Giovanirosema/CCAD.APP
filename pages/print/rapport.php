<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];

$type = $_GET['type'] ?? 'synthese';
$periode = $_GET['periode'] ?? 'annee';
$planF = $_GET['plan'] ?? 'tous';
$deptF = $_GET['dept'] ?? 'tous';

$spans = [
    'mois' => ['libelle' => 'Mois en cours — ' . date('F Y'), 'from' => date('Y-m-01')],
    'trimestre' => ['libelle' => 'Trimestre en cours', 'from' => date('Y-m-01', strtotime('-2 months'))],
    'semestre' => ['libelle' => 'Semestre en cours', 'from' => date('Y-m-01', strtotime('-5 months'))],
    'annee' => ['libelle' => 'Exercice ' . date('Y'), 'from' => date('Y-01-01')],
];
$span = $spans[$periode] ?? $spans['annee'];

$planFilterSql = '';
$deptFilterSql = '';
$params = [$span['from']];
if ($planF !== 'tous') { $planFilterSql = ' AND pl.id = ?'; $params[] = $planF; }
if ($deptF !== 'tous') { $deptFilterSql = ' AND dep.nom = ?'; $params[] = $deptF; }

$planNomStmt = $pdo->prepare('SELECT nom FROM plans WHERE id=?');
if ($planF !== 'tous') { $planNomStmt->execute([$planF]); $planLabel = $planNomStmt->fetchColumn(); } else { $planLabel = 'Tous les plans'; }
$deptLabel = $deptF === 'tous' ? 'Tous les départements' : $deptF;

$titres = [
    'synthese' => 'Rapport de synthèse', 'portefeuille' => 'Rapport — portefeuille des assurés',
    'journal' => 'Rapport — journal des encaissements', 'arrieres' => 'Rapport — arriérés et suspensions',
    'sinistres' => 'Rapport — sinistres et capitaux versés', 'eligibles' => 'Rapport — assurés devenus éligibles',
];

$sqlBase = "FROM paiements pa JOIN polices p ON p.id=pa.police_id JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id
    LEFT JOIN departements dep ON dep.id=a.departement_id WHERE pa.statut='Encaissé' AND pa.date_paiement >= ? $planFilterSql $deptFilterSql";
$stmt = $pdo->prepare("SELECT COALESCE(SUM(pa.montant),0) total, COUNT(*) recus $sqlBase");
$stmt->execute($params);
$totaux = $stmt->fetch();

$adhStmt = $pdo->prepare("SELECT COUNT(*) FROM polices p JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id LEFT JOIN departements dep ON dep.id=a.departement_id WHERE p.date_adhesion >= ? $planFilterSql $deptFilterSql");
$adhStmt->execute($params);
$nbAdhesions = (int) $adhStmt->fetchColumn();

$sinistresStmt = $pdo->prepare("SELECT COALESCE(SUM(rc.capital),0) capitaux, COUNT(*) dossiers FROM reclamations rc JOIN polices p ON p.id=rc.police_id JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id LEFT JOIN departements dep ON dep.id=a.departement_id WHERE rc.statut='Encaissé' AND rc.date_declaration >= ? $planFilterSql $deptFilterSql");
$sinistresStmt->execute($params);
$sinistres = $sinistresStmt->fetch();

$retardStmt = $pdo->prepare("SELECT COUNT(*) FROM polices p JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id LEFT JOIN departements dep ON dep.id=a.departement_id WHERE p.statut='En retard' $planFilterSql $deptFilterSql");
$retardStmt->execute(array_slice($params, 1));
$nbRetard = (int) $retardStmt->fetchColumn();

$totalPolicesStmt = $pdo->prepare("SELECT COUNT(*) FROM polices p JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id LEFT JOIN departements dep ON dep.id=a.departement_id WHERE p.statut != 'Résiliée' $planFilterSql $deptFilterSql");
$totalPolicesStmt->execute(array_slice($params, 1));
$totalPolices = (int) $totalPolicesStmt->fetchColumn();
$taux = $totalPolices ? round((($totalPolices - $nbRetard) / $totalPolices) * 100, 1) : 0;

$doc_title = $titres[$type] ?? $titres['synthese'];
$doc_ref = strtoupper(substr($type, 0, 3));
require __DIR__ . '/../../includes/print_start.php';
?>
<div class="print-note">Portée : <?= h($span['libelle']) ?> · <?= h($planLabel) ?> · <?= h($deptLabel) ?>. Montants exprimés en <?= h($devise) ?>. Document interne établi sous la responsabilité de la direction.</div>

<div class="print-section-title">Indicateurs clés</div>
<dl class="print-grid print-grid--3">
  <div><dt>Cotisations encaissées</dt><dd><?= fmt_money($totaux['total'], $devise) ?></dd></div>
  <div><dt>Reçus enregistrés</dt><dd><?= (int) $totaux['recus'] ?></dd></div>
  <div><dt>Nouvelles adhésions</dt><dd><?= $nbAdhesions ?></dd></div>
  <div><dt>Capitaux funéraires versés</dt><dd><?= fmt_money($sinistres['capitaux'], $devise) ?></dd></div>
  <div><dt>Polices en retard</dt><dd><?= $nbRetard ?></dd></div>
  <div><dt>Taux de recouvrement</dt><dd><?= $taux ?> %</dd></div>
</dl>

<?php if ($type === 'portefeuille'): $rows = $pdo->prepare("SELECT pl.nom, COUNT(*) n, p.statut FROM polices p JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id LEFT JOIN departements dep ON dep.id=a.departement_id WHERE p.statut != 'Résiliée' $planFilterSql $deptFilterSql GROUP BY pl.id, p.statut ORDER BY pl.nom"); $rows->execute(array_slice($params, 1)); $rows = $rows->fetchAll(); ?>
<div class="print-section-title">Portefeuille par plan et statut</div>
<table class="print-table"><thead><tr><th>Plan</th><th>Statut</th><th class="num">Effectif</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['nom']) ?></td><td><?= h($row['statut']) ?></td><td class="num"><?= (int) $row['n'] ?></td></tr><?php endforeach; ?></tbody></table>

<?php elseif ($type === 'journal'): $rows = $pdo->prepare("SELECT pa.reference, pa.date_paiement, pa.montant, pa.mode, a.prenom, a.nom $sqlBase ORDER BY pa.date_paiement"); $rows->execute($params); $rows = $rows->fetchAll(); ?>
<div class="print-section-title">Journal des encaissements</div>
<table class="print-table"><thead><tr><th>Reçu</th><th>Assuré</th><th>Date</th><th>Mode</th><th class="num">Montant</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['reference']) ?></td><td><?= h($row['prenom'].' '.$row['nom']) ?></td><td><?= fmt_date_fr($row['date_paiement']) ?></td><td><?= h(ucfirst($row['mode'])) ?></td><td class="num"><?= fmt_money($row['montant']) ?></td></tr><?php endforeach; ?></tbody></table>

<?php elseif ($type === 'arrieres'): $rows = $pdo->query("SELECT p.numero, a.prenom, a.nom, p.prime, p.statut FROM polices p JOIN assures a ON a.id=p.assure_id WHERE p.statut IN ('En retard','Suspendue') ORDER BY p.statut")->fetchAll(); ?>
<div class="print-section-title">Arriérés et suspensions</div>
<table class="print-table"><thead><tr><th>Police</th><th>Assuré</th><th class="num">Prime</th><th>Statut</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['numero']) ?></td><td><?= h($row['prenom'].' '.$row['nom']) ?></td><td class="num"><?= fmt_money($row['prime']) ?></td><td><?= h($row['statut']) ?></td></tr><?php endforeach; ?></tbody></table>

<?php elseif ($type === 'sinistres'): $rows = $pdo->query("SELECT rc.reference, a.prenom, a.nom, rc.capital, rc.statut, rc.date_declaration FROM reclamations rc JOIN polices p ON p.id=rc.police_id JOIN assures a ON a.id=p.assure_id ORDER BY rc.date_declaration DESC")->fetchAll(); ?>
<div class="print-section-title">Sinistres et capitaux</div>
<table class="print-table"><thead><tr><th>Dossier</th><th>Assuré décédé</th><th>Déclaration</th><th class="num">Capital</th><th>Statut</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['reference']) ?></td><td><?= h($row['prenom'].' '.$row['nom']) ?></td><td><?= fmt_date_fr($row['date_declaration']) ?></td><td class="num"><?= fmt_money($row['capital']) ?></td><td><?= h($row['statut']) ?></td></tr><?php endforeach; ?></tbody></table>

<?php elseif ($type === 'eligibles'): $rows = $pdo->query("SELECT p.numero, a.prenom, a.nom, TIMESTAMPDIFF(MONTH,p.date_adhesion,CURDATE()) mois FROM polices p JOIN assures a ON a.id=p.assure_id WHERE p.statut='Active' HAVING mois >= $eligMois ORDER BY mois DESC")->fetchAll(); ?>
<div class="print-section-title">Assurés devenus éligibles (<?= $eligMois ?> mois)</div>
<table class="print-table"><thead><tr><th>Police</th><th>Assuré</th><th class="num">Mois cotisés</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['numero']) ?></td><td><?= h($row['prenom'].' '.$row['nom']) ?></td><td class="num"><?= (int) $row['mois'] ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>

<div class="print-note">Règles appliquées : éligibilité à <?= $eligMois ?> mois, retard signalé après <?= (int) $r['retard_jours'] ?> jours, suspension à <?= (int) $r['suspension_jours'] ?> jours, échéance mensuelle le <?= (int) $r['echeance_jour'] ?>.</div>

<?php require __DIR__ . '/../../includes/print_end.php'; ?>
