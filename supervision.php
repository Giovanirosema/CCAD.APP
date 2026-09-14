<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('rapports');
$page_title = 'Supervision';
$active_nav = 'supervision';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';

$deptStats = $pdo->query("
  SELECT dep.nom AS dept, COUNT(DISTINCT p.id) assures, COALESCE(SUM(pa.montant),0) encaisse,
    COUNT(DISTINCT CASE WHEN p.statut='En retard' THEN p.id END) retard
  FROM departements dep
  LEFT JOIN assures a ON a.departement_id=dep.id
  LEFT JOIN polices p ON p.assure_id=a.id AND p.statut != 'Résiliée'
  LEFT JOIN paiements pa ON pa.police_id=p.id AND pa.statut='Encaissé'
  GROUP BY dep.id HAVING assures > 0 ORDER BY encaisse DESC
")->fetchAll();
$respStmt = $pdo->prepare("SELECT CONCAT(u.prenom,' ',u.nom) FROM utilisateurs u JOIN roles r ON r.id=u.role_id JOIN departements d ON d.id=u.departement_id WHERE d.nom=? AND r.nom LIKE 'Administrateur%' LIMIT 1");
foreach ($deptStats as &$d) {
    $respStmt->execute([$d['dept']]);
    $d['responsable'] = $respStmt->fetchColumn() ?: '—';
    $d['taux'] = $d['assures'] ? round((($d['assures'] - $d['retard']) / $d['assures']) * 100, 1) : 0;
}
unset($d);

$agentStats = $pdo->query("
  SELECT u.id, CONCAT(u.prenom,' ',u.nom) nom, u.zone_collecte AS zone, r.nom AS role, u.statut,
    (SELECT COUNT(*) FROM paiements pa WHERE pa.agent_id=u.id AND pa.statut='Encaissé') recus,
    (SELECT COALESCE(SUM(montant),0) FROM paiements pa WHERE pa.agent_id=u.id AND pa.statut='Encaissé') encaisse,
    (SELECT COUNT(*) FROM assures a WHERE a.agent_id=u.id) adhesions
  FROM utilisateurs u JOIN roles r ON r.id=u.role_id
  ORDER BY encaisse DESC
")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Supervision</span></div>
    <h1 class="page-header__title">Supervision par département</h1>
    <div class="page-header__meta"><?= count($deptStats) ?> départements · <?= count($agentStats) ?> comptes · exercice <?= date('Y') ?></div>
  </div>
</div>

<div class="card card--flush">
  <div class="card__head"><div class="card__title">Départements</div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Département</th><th>Responsable</th><th class="is-numeric">Assurés</th><th class="is-numeric">Encaissé (<?= h($devise) ?>)</th><th class="is-numeric">Recouvrement</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($deptStats as $d): ?>
        <tr>
          <td><?= h($d['dept']) ?></td><td><?= h($d['responsable']) ?></td>
          <td class="is-numeric is-mono"><?= (int) $d['assures'] ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($d['encaisse']) ?></td>
          <td class="is-numeric is-mono"><?= $d['taux'] ?>%</td>
          <td><span class="badge badge--<?= $d['taux'] >= 94 ? 'success' : 'warning' ?>"><?= $d['taux'] >= 94 ? 'Objectif tenu' : 'Sous objectif' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card card--flush">
  <div class="card__head"><div class="card__title">Agents et caissiers</div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Agent</th><th>Rôle</th><th class="is-numeric">Reçus</th><th class="is-numeric">Encaissé (<?= h($devise) ?>)</th><th class="is-numeric">Adhésions</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($agentStats as $ag): ?>
        <tr>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($ag['nom']) ?></span><span class="person-cell__meta"><?= h($ag['zone'] ?? '—') ?></span></div></td>
          <td><?= h($ag['role']) ?></td>
          <td class="is-numeric is-mono"><?= (int) $ag['recus'] ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($ag['encaisse']) ?></td>
          <td class="is-numeric is-mono"><?= (int) $ag['adhesions'] ?></td>
          <td><?= badge_html($ag['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
