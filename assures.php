<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('assures');
$page_title = 'Assurés';
$active_nav = 'assures';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$q = trim($_GET['q'] ?? '');

$sql = "SELECT a.id, a.reference, a.prenom, a.nom, com.nom AS ville, pl.nom AS plan, p.prime, p.numero, p.date_adhesion, p.statut,
               TIMESTAMPDIFF(MONTH, p.date_adhesion, CURDATE()) AS mois
        FROM assures a
        JOIN polices p ON p.assure_id = a.id
        JOIN plans pl ON pl.id = p.plan_id
        LEFT JOIN communes com ON com.id = a.commune_id
        WHERE p.statut != 'Résiliée'";
$params = [];
if ($q !== '') {
    $sql .= " AND (a.nom LIKE ? OR a.prenom LIKE ? OR p.numero LIKE ? OR com.nom LIKE ?)";
    $like = "%$q%";
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY a.nom, a.prenom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$total = (int) $pdo->query("SELECT COUNT(*) FROM polices WHERE statut != 'Résiliée'")->fetchColumn();
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];
$devise = organisation()['devise'] ?? 'HTG';
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Assurés</span></div>
    <h1 class="page-header__title">Assurés</h1>
    <div class="page-header__meta"><?= count($rows) ?> dossier(s) affiché(s) sur <?= $total ?></div>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--secondary" href="assure_nouveau.php?type=famille"><?= icon('users',16) ?> Plan familial</a>
    <a class="btn btn--primary" href="assure_nouveau.php?type=individuel"><?= icon('user-plus',16) ?> Plan individuel</a>
  </div>
</div>

<div class="card">
  <form class="form-grid" method="get">
    <div class="field" style="grid-column:1/-1;max-width:420px">
      <label class="field__label">Recherche</label>
      <input class="input" type="search" name="q" value="<?= h($q) ?>" placeholder="Nom, numéro de police ou commune">
    </div>
  </form>
</div>

<div class="card card--flush">
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Assuré</th><th>Ville</th><th>Plan</th><th class="is-numeric">Prime mensuelle</th>
        <th class="is-numeric">Mois cotisés</th><th>Éligibilité</th><th>Fiches</th><th>Statut</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r2): $eligible = $r2['mois'] >= $eligMois; ?>
        <tr class="is-clickable" onclick="location.href='assure_fiche.php?numero=<?= urlencode($r2['numero']) ?>'">
          <td><div class="person-cell"><span class="person-cell__name"><?= h($r2['prenom'] . ' ' . $r2['nom']) ?></span><span class="person-cell__meta"><?= h($r2['numero']) ?></span></div></td>
          <td><?= h($r2['ville'] ?? '—') ?></td>
          <td><?= h($r2['plan']) ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($r2['prime'], $devise) ?></td>
          <td class="is-numeric is-mono"><?= (int) $r2['mois'] ?></td>
          <td><span class="badge badge--<?= $eligible ? 'success' : 'neutral' ?>"><?= $eligible ? 'Éligible' : max(0, $eligMois - $r2['mois']) . ' mois' ?></span></td>
          <td onclick="event.stopPropagation()">
            <a class="icon-btn" href="pages/print/inscription.php?numero=<?= urlencode($r2['numero']) ?>" target="_blank" title="Fiche d'inscription"><?= icon('printer',14) ?></a>
          </td>
          <td><?= badge_html($r2['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8"><div class="empty-state"><?= icon('search',28) ?><div class="empty-state__title">Aucun résultat</div><div class="empty-state__desc">Aucun dossier ne correspond à « <?= h($q) ?> ».</div></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
