<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('rapports');
$page_title = 'Rapports';
$active_nav = 'rapports';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$r = regles();

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

$sqlBase = "FROM paiements pa
    JOIN polices p ON p.id=pa.police_id JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id
    LEFT JOIN departements dep ON dep.id=a.departement_id
    WHERE pa.statut='Encaissé' AND pa.date_paiement >= ? $planFilterSql $deptFilterSql";

$stmt = $pdo->prepare("SELECT COALESCE(SUM(pa.montant),0) total, COUNT(*) recus $sqlBase");
$stmt->execute($params);
$totaux = $stmt->fetch();

$stmtMois = $pdo->prepare("SELECT DATE_FORMAT(pa.date_paiement,'%Y-%m') ym, DATE_FORMAT(pa.date_paiement,'%b') lbl, COUNT(*) recus, SUM(pa.montant) montant $sqlBase GROUP BY ym ORDER BY ym");
$stmtMois->execute($params);
$mois = $stmtMois->fetchAll();
$maxMois = 1;
foreach ($mois as $m) { $maxMois = max($maxMois, (float) $m['montant']); }

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
$tauxRecouvrement = $totalPolices ? round((($totalPolices - $nbRetard) / $totalPolices) * 100, 1) : 0;

$planStats = $pdo->prepare("
  SELECT pl.nom, COUNT(DISTINCT p.id) assures, COALESCE(SUM(pa.montant),0) encaisse
  FROM plans pl LEFT JOIN polices p ON p.plan_id=pl.id AND p.statut != 'Résiliée'
  LEFT JOIN paiements pa ON pa.police_id=p.id AND pa.statut='Encaissé' AND pa.date_paiement >= ?
  WHERE pl.actif=1 GROUP BY pl.id ORDER BY encaisse DESC
");
$planStats->execute([$span['from']]);
$planStats = $planStats->fetchAll();

$deptStats = $pdo->prepare("
  SELECT dep.nom AS dept, COUNT(DISTINCT p.id) assures, COALESCE(SUM(pa.montant),0) encaisse
  FROM departements dep LEFT JOIN assures a ON a.departement_id=dep.id
  LEFT JOIN polices p ON p.assure_id=a.id AND p.statut != 'Résiliée'
  LEFT JOIN paiements pa ON pa.police_id=p.id AND pa.statut='Encaissé' AND pa.date_paiement >= ?
  GROUP BY dep.id HAVING assures > 0 ORDER BY encaisse DESC
");
$deptStats->execute([$span['from']]);
$deptStats = $deptStats->fetchAll();

$plans = $pdo->query('SELECT * FROM plans ORDER BY ordre')->fetchAll();
$depts = $pdo->query('SELECT nom FROM departements ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$eligMois = (int) $r['eligibilite_mois'];

$rapportsDispo = [
    ['icon' => 'users', 'nom' => 'Portefeuille des assurés', 'detail' => 'Effectifs par plan, département et statut.', 'type' => 'portefeuille'],
    ['icon' => 'credit-card', 'nom' => 'Journal des encaissements', 'detail' => 'Tous les reçus de la période retenue.', 'type' => 'journal'],
    ['icon' => 'triangle-alert', 'nom' => 'Arriérés et suspensions', 'detail' => 'Retards au-delà de ' . (int) $r['retard_jours'] . ' jours.', 'type' => 'arrieres'],
    ['icon' => 'folder-open', 'nom' => 'Sinistres et capitaux versés', 'detail' => 'Dossiers funéraires clos et en cours.', 'type' => 'sinistres'],
    ['icon' => 'shield-check', 'nom' => 'Assurés devenus éligibles', 'detail' => "Délai de $eligMois mois atteint.", 'type' => 'eligibles'],
];
$qs = http_build_query(['periode' => $periode, 'plan' => $planF, 'dept' => $deptF]);
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Rapports</span></div>
    <h1 class="page-header__title">Rapports</h1>
    <div class="page-header__meta"><?= h($span['libelle']) ?> · <?= count($mois) ?> mois analysés</div>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--primary" href="pages/print/rapport.php?type=synthese&<?= $qs ?>" target="_blank"><?= icon('printer',16) ?> Éditer le rapport</a>
  </div>
</div>

<div class="card">
  <div class="card__title" style="margin-bottom:14px">Paramètres du rapport</div>
  <form class="filter-bar" method="get">
    <div class="field"><label class="field__label">Période</label><select class="input" name="periode" onchange="this.form.submit()">
      <option value="mois" <?= $periode==='mois'?'selected':'' ?>>Mois en cours</option>
      <option value="trimestre" <?= $periode==='trimestre'?'selected':'' ?>>Trimestre en cours</option>
      <option value="semestre" <?= $periode==='semestre'?'selected':'' ?>>Semestre en cours</option>
      <option value="annee" <?= $periode==='annee'?'selected':'' ?>>Exercice <?= date('Y') ?></option>
    </select></div>
    <div class="field"><label class="field__label">Plan</label><select class="input" name="plan" onchange="this.form.submit()">
      <option value="tous">Tous les plans</option><?php foreach ($plans as $pl): ?><option value="<?= $pl['id'] ?>" <?= $planF==$pl['id']?'selected':'' ?>><?= h($pl['nom']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="field__label">Département</label><select class="input" name="dept" onchange="this.form.submit()">
      <option value="tous">Tous les départements</option><?php foreach ($depts as $d): ?><option value="<?= h($d) ?>" <?= $deptF===$d?'selected':'' ?>><?= h($d) ?></option><?php endforeach; ?>
    </select></div>
  </form>
</div>

<div class="stat-grid">
  <div class="stat-card stat-card--brand"><div class="stat-card__label"><?= icon('credit-card',14) ?> Cotisations encaissées</div><div><span class="stat-card__value"><?= fmt_money($totaux['total']) ?></span><span class="stat-card__unit"><?= h($devise) ?></span></div><div class="stat-card__delta stat-card__delta--up"><?= (int) $totaux['recus'] ?> reçus enregistrés</div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('user-plus',14) ?> Nouvelles adhésions</div><div><span class="stat-card__value"><?= $nbAdhesions ?></span><span class="stat-card__unit">assurés</span></div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('folder-open',14) ?> Capitaux funéraires versés</div><div><span class="stat-card__value"><?= fmt_money($sinistres['capitaux']) ?></span><span class="stat-card__unit"><?= h($devise) ?></span></div><div class="stat-card__delta stat-card__delta--neutral"><?= (int) $sinistres['dossiers'] ?> dossiers clos</div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('chart-column',14) ?> Taux de recouvrement</div><div><span class="stat-card__value"><?= $tauxRecouvrement ?></span><span class="stat-card__unit">%</span></div><div class="stat-card__delta stat-card__delta--down"><?= $nbRetard ?> polices en retard</div></div>
</div>

<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__title" style="margin-bottom:4px">Encaissements mensuels</div>
      <div class="card__subtitle" style="margin-bottom:8px">Montants en <?= h($devise) ?> · <?= h($span['libelle']) ?></div>
      <div class="bar-chart">
        <?php foreach ($mois as $m): $hh = max(8, round(((float)$m['montant'] / $maxMois) * 100)); ?>
        <div class="bar-chart__col"><div class="bar-chart__stack">
          <div class="bar-chart__n"><?= fmt_money($m['montant']) ?></div>
          <div class="bar-chart__bar" style="height:<?= $hh ?>%" title="<?= h($m['lbl'] . ' — ' . fmt_money($m['montant'], $devise)) ?>"></div>
        </div><div class="bar-chart__label"><?= h($m['lbl']) ?></div></div>
        <?php endforeach; ?>
        <?php if (!$mois): ?><div class="text-faint" style="font-size:13px">Aucun encaissement sur la période.</div><?php endif; ?>
      </div>
    </div>
    <div class="card card--flush">
      <div class="card__head"><div class="card__title">Détail mensuel</div></div>
      <div class="table-wrap"><table class="data-table is-dense">
        <thead><tr><th>Mois</th><th class="is-numeric">Reçus</th><th class="is-numeric">Encaissé (<?= h($devise) ?>)</th></tr></thead>
        <tbody><?php foreach ($mois as $m): ?><tr><td><?= h($m['lbl']) ?></td><td class="is-numeric is-mono"><?= (int) $m['recus'] ?></td><td class="is-numeric is-mono"><?= fmt_money($m['montant']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </div>
    <div class="form-row">
      <div style="flex:1 1 100%">
        <div class="card card--flush">
          <div class="card__head"><div class="card__title">Répartition par plan</div></div>
          <div class="table-wrap"><table class="data-table is-dense">
            <thead><tr><th>Plan</th><th class="is-numeric">Assurés</th><th class="is-numeric">Encaissé</th></tr></thead>
            <tbody><?php foreach ($planStats as $p2): ?><tr><td><?= h($p2['nom']) ?></td><td class="is-numeric is-mono"><?= (int) $p2['assures'] ?></td><td class="is-numeric is-mono"><?= fmt_money($p2['encaisse']) ?></td></tr><?php endforeach; ?></tbody>
          </table></div>
        </div>
      </div>
      <div style="flex:1 1 100%">
        <div class="card card--flush">
          <div class="card__head"><div class="card__title">Répartition géographique</div></div>
          <div class="table-wrap"><table class="data-table is-dense">
            <thead><tr><th>Département</th><th class="is-numeric">Assurés</th><th class="is-numeric">Encaissé</th></tr></thead>
            <tbody><?php foreach ($deptStats as $d2): ?><tr><td><?= h($d2['dept']) ?></td><td class="is-numeric is-mono"><?= (int) $d2['assures'] ?></td><td class="is-numeric is-mono"><?= fmt_money($d2['encaisse']) ?></td></tr><?php endforeach; ?></tbody>
          </table></div>
        </div>
      </div>
    </div>
  </div>
  <div class="form-col form-col--side">
    <div class="card">
      <div class="card__title" style="margin-bottom:12px">Rapports disponibles</div>
      <div class="stack stack--sm">
        <?php foreach ($rapportsDispo as $rp): ?>
        <div class="cluster" style="justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-subtle)">
          <div class="cluster" style="gap:10px"><span style="color:var(--navy-600)"><?= icon($rp['icon'],16) ?></span>
            <div><div style="font-size:13px;font-weight:500;color:var(--grey-800)"><?= h($rp['nom']) ?></div><div style="font-size:12px;color:var(--text-muted)"><?= h($rp['detail']) ?></div></div>
          </div>
          <a class="btn btn--ghost btn--sm" href="pages/print/rapport.php?type=<?= $rp['type'] ?>&<?= $qs ?>" target="_blank"><?= icon('printer',14) ?> Générer</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
