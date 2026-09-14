<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('polices');
$page_title = 'Polices';
$active_nav = 'polices';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$r = regles();

$statut = $_GET['statut'] ?? 'tous';
$planF = $_GET['plan'] ?? 'tous';
$deptF = $_GET['dept'] ?? 'tous';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT p.numero, a.prenom, a.nom, pl.nom AS plan, p.prime, p.statut, p.jour_echeance, dep.nom AS dept
        FROM polices p JOIN assures a ON a.id=p.assure_id JOIN plans pl ON pl.id=p.plan_id
        LEFT JOIN departements dep ON dep.id=a.departement_id WHERE 1=1";
$params = [];
if ($statut !== 'tous') { $sql .= " AND p.statut=?"; $params[] = $statut; }
if ($planF !== 'tous') { $sql .= " AND pl.id=?"; $params[] = $planF; }
if ($deptF !== 'tous') { $sql .= " AND dep.nom=?"; $params[] = $deptF; }
if ($q !== '') { $sql .= " AND (a.nom LIKE ? OR a.prenom LIKE ? OR p.numero LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like); }
$sql .= " ORDER BY a.nom LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = $pdo->query("SELECT statut, COUNT(*) n, COALESCE(SUM(prime),0) montant FROM polices GROUP BY statut")->fetchAll(PDO::FETCH_ASSOC);
$byStatut = [];
foreach ($counts as $c) { $byStatut[$c['statut']] = $c; }
$totalPolices = array_sum(array_column($counts, 'n'));

$plans = $pdo->query('SELECT * FROM plans ORDER BY ordre')->fetchAll();
$depts = $pdo->query('SELECT nom FROM departements ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

$historique = $pdo->query("
  SELECT al.*, u.prenom up, u.nom un FROM audit_log al LEFT JOIN utilisateurs u ON u.id=al.utilisateur_id
  WHERE al.type_operation IN ('Modification','Suspension') ORDER BY al.horodatage DESC LIMIT 6
")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Polices</span></div>
    <h1 class="page-header__title">Polices</h1>
    <div class="page-header__meta"><?= $totalPolices ?> polices · <?= $byStatut['En retard']['n'] ?? 0 ?> en retard de plus de <?= (int) $r['retard_jours'] ?> jours · <?= $byStatut['Suspendue']['n'] ?? 0 ?> suspendues</div>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card stat-card--brand"><div class="stat-card__label"><?= icon('file-text',14) ?> Polices en vigueur</div><div><span class="stat-card__value"><?= $byStatut['Active']['n'] ?? 0 ?></span><span class="stat-card__unit">polices</span></div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('triangle-alert',14) ?> En retard (plus de <?= (int) $r['retard_jours'] ?> jours)</div><div><span class="stat-card__value"><?= $byStatut['En retard']['n'] ?? 0 ?></span><span class="stat-card__unit">polices</span></div><div class="stat-card__delta stat-card__delta--down"><?= fmt_money($byStatut['En retard']['montant'] ?? 0, $devise) ?> d'arriérés</div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('calendar-clock',14) ?> Suspendues</div><div><span class="stat-card__value"><?= $byStatut['Suspendue']['n'] ?? 0 ?></span><span class="stat-card__unit">polices</span></div><div class="stat-card__delta stat-card__delta--down">Au-delà de <?= (int) $r['suspension_jours'] ?> jours</div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('folder-open',14) ?> Résiliées</div><div><span class="stat-card__value"><?= $byStatut['Résiliée']['n'] ?? 0 ?></span><span class="stat-card__unit">polices</span></div></div>
</div>

<div class="card">
  <div class="card__title" style="margin-bottom:14px">Filtres</div>
  <form class="filter-bar" method="get">
    <div class="field"><label class="field__label">Statut</label><select class="input" name="statut" onchange="this.form.submit()">
      <option value="tous">Tous les statuts</option>
      <?php foreach (['Active','En retard','En attente','Suspendue','Résiliée'] as $s): ?><option value="<?= h($s) ?>" <?= $statut===$s?'selected':'' ?>><?= h($s) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="field__label">Plan tarifaire</label><select class="input" name="plan" onchange="this.form.submit()">
      <option value="tous">Tous les plans</option>
      <?php foreach ($plans as $pl): ?><option value="<?= $pl['id'] ?>" <?= $planF==$pl['id']?'selected':'' ?>><?= h($pl['nom']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="field__label">Département</label><select class="input" name="dept" onchange="this.form.submit()">
      <option value="tous">Tous les départements</option>
      <?php foreach ($depts as $d): ?><option value="<?= h($d) ?>" <?= $deptF===$d?'selected':'' ?>><?= h($d) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="field__label">Recherche</label><input class="input" name="q" value="<?= h($q) ?>" placeholder="Nom ou nº de police"></div>
  </form>
</div>

<div class="card card--flush">
  <div class="card__head"><div><div class="card__title">Toutes les polices</div><div class="card__subtitle"><?= count($rows) ?> police(s) affichée(s) · statut recalculé à chaque échéance</div></div>
    <div class="card__actions"><a class="btn btn--ghost btn--sm" href="pages/export_csv.php?type=polices&<?= http_build_query($_GET) ?>"><?= icon('printer',14) ?> Exporter CSV</a></div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Nº de police</th><th>Assuré</th><th>Plan</th><th class="is-numeric">Prime</th><th>Échéance</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr class="is-clickable" onclick="location.href='assure_fiche.php?numero=<?= urlencode($row['numero']) ?>'">
          <td class="is-mono"><?= h($row['numero']) ?></td>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($row['prenom'] . ' ' . $row['nom']) ?></span></div></td>
          <td><?= h($row['plan']) ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($row['prime'], $devise) ?></td>
          <td class="is-mono">Le <?= (int) $row['jour_echeance'] ?> du mois</td>
          <td><?= badge_html($row['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><?= icon('search',28) ?><div class="empty-state__title">Aucune police</div><div class="empty-state__desc">Aucun contrat ne correspond à ces filtres.</div></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card__title" style="margin-bottom:14px">Derniers changements de statut</div>
  <div class="stack stack--sm">
    <?php foreach ($historique as $h): ?>
    <div class="cluster" style="justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--border-subtle)">
      <div class="mono text-faint" style="font-size:11px;flex:0 0 auto"><?= fmt_date_fr($h['horodatage']) ?></div>
      <div style="flex:1 1 220px"><div style="font-size:12px;font-weight:500;color:var(--grey-800)"><?= h($h['detail']) ?></div>
        <div style="font-size:11px;color:var(--text-faint)"><?= h($h['up'] ? $h['up'] . ' ' . $h['un'] : 'Traitement automatique') ?></div></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$historique): ?><div class="text-faint" style="font-size:13px">Aucun changement récent.</div><?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
