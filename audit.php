<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('rapports');
$page_title = "Journal d'audit";
$active_nav = 'audit';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$userF = $_GET['user'] ?? 'tous';
$typeF = $_GET['type'] ?? 'tous';
$periodeF = $_GET['periode'] ?? 'semaine';
$periodeJours = ['jour' => 1, 'semaine' => 7, 'mois' => 30][$periodeF] ?? 7;

$sql = "SELECT al.*, CONCAT(u.prenom,' ',u.nom) AS utilisateur FROM audit_log al LEFT JOIN utilisateurs u ON u.id=al.utilisateur_id WHERE al.horodatage >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params = [$periodeJours];
if ($userF !== 'tous') { $sql .= ' AND u.id = ?'; $params[] = $userF; }
if ($typeF !== 'tous') { $sql .= ' AND al.type_operation = ?'; $params[] = $typeF; }
$sql .= ' ORDER BY al.horodatage DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$users = $pdo->query("SELECT id, CONCAT(prenom,' ',nom) nom FROM utilisateurs ORDER BY nom")->fetchAll();
$types = ['Consultation','Adhésion','Encaissement','Modification','Suspension','Réclamation','Paramètres'];
$securite = $pdo->query('SELECT * FROM reglages_securite WHERE id=1')->fetch();
$qs = http_build_query($_GET);
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Journal d'audit</span></div>
    <h1 class="page-header__title">Journal d'audit</h1>
    <div class="page-header__meta"><?= count($rows) ?> opération(s) · conservation 24 mois · journal non modifiable</div>
  </div>
  <div class="page-header__actions"><a class="btn btn--secondary" href="pages/export_csv.php?type=audit&<?= $qs ?>"><?= icon('printer',16) ?> Exporter le journal</a></div>
</div>

<div class="card">
  <div class="card__subtitle" style="margin-bottom:14px"><?= ($securite['journal_actif'] ?? 1) ? "Journal actif — chaque consultation de dossier est tracée" : "Journal désactivé dans Paramètres › Utilisateurs" ?></div>
  <form class="filter-bar" method="get">
    <div class="field"><label class="field__label">Utilisateur</label><select class="input" name="user" onchange="this.form.submit()">
      <option value="tous">Tous les utilisateurs</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $userF==$u['id']?'selected':'' ?>><?= h($u['nom']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="field__label">Opération</label><select class="input" name="type" onchange="this.form.submit()">
      <option value="tous">Toutes les opérations</option><?php foreach ($types as $t): ?><option value="<?= h($t) ?>" <?= $typeF===$t?'selected':'' ?>><?= h($t) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="field__label">Période</label><select class="input" name="periode" onchange="this.form.submit()">
      <option value="jour" <?= $periodeF==='jour'?'selected':'' ?>>Aujourd'hui</option>
      <option value="semaine" <?= $periodeF==='semaine'?'selected':'' ?>>7 derniers jours</option>
      <option value="mois" <?= $periodeF==='mois'?'selected':'' ?>>30 derniers jours</option>
    </select></div>
  </form>
</div>

<div class="card card--flush">
  <div class="table-wrap">
    <table class="data-table is-dense">
      <thead><tr><th>Horodatage</th><th>Utilisateur</th><th>Opération</th><th>Objet</th><th>Détail</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="is-mono"><?= fmt_datetime_fr($row['horodatage']) ?></td>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($row['utilisateur'] ?: 'Traitement automatique') ?></span><span class="person-cell__meta"><?= h($row['zone'] ?? '') ?></span></div></td>
          <td><span class="tag"><?= h($row['type_operation']) ?></span></td>
          <td class="is-mono"><?= h($row['cible'] ?? '—') ?></td>
          <td><?= h($row['detail'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state"><?= icon('search',28) ?><div class="empty-state__title">Aucune opération</div><div class="empty-state__desc">Aucune trace pour ces filtres.</div></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
