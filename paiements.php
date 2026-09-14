<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('paiements');
$page_title = 'Paiements';
$active_nav = 'paiements';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';

$moisCourant = date('Y-m');
$encaisseMois = (float) $pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut='Encaissé' AND DATE_FORMAT(date_paiement,'%Y-%m')='$moisCourant'")->fetchColumn();
$aValider = (int) $pdo->query("SELECT COUNT(*) FROM paiements WHERE statut='À valider'")->fetchColumn();
$arrieres = (float) $pdo->query("SELECT COALESCE(SUM(prime),0) FROM polices WHERE statut='En retard'")->fetchColumn();
$nbRetard = (int) $pdo->query("SELECT COUNT(*) FROM polices WHERE statut='En retard'")->fetchColumn();

$journal = $pdo->query("
  SELECT pa.reference, a.prenom, a.nom, p.numero, pa.date_paiement, pa.montant, pa.mode, pa.statut
  FROM paiements pa JOIN polices p ON p.id=pa.police_id JOIN assures a ON a.id=p.assure_id
  ORDER BY pa.date_paiement DESC, pa.id DESC LIMIT 100
")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Paiements</span></div>
    <h1 class="page-header__title">Paiements</h1>
    <div class="page-header__meta"><?= date('F Y') ?> · <?= fmt_money($encaisseMois, $devise) ?> encaissés · <?= $nbRetard ?> polices en retard</div>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--secondary" href="collecte.php"><?= icon('calendar-clock',16) ?> Collecte du jour</a>
    <a class="btn btn--secondary" href="relances.php"><?= icon('bell',16) ?> Relances</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-card__label"><?= icon('credit-card',14) ?> Encaissé ce mois</div><div><span class="stat-card__value"><?= fmt_money($encaisseMois) ?></span><span class="stat-card__unit"><?= h($devise) ?></span></div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('calendar-clock',14) ?> À valider</div><div><span class="stat-card__value"><?= $aValider ?></span><span class="stat-card__unit">reçus</span></div></div>
  <div class="stat-card"><div class="stat-card__label"><?= icon('triangle-alert',14) ?> Arriérés cumulés</div><div><span class="stat-card__value"><?= fmt_money($arrieres) ?></span><span class="stat-card__unit"><?= h($devise) ?></span></div><div class="stat-card__delta stat-card__delta--down"><?= $nbRetard ?> polices</div></div>
</div>

<div class="card card--flush">
  <div class="card__head"><div><div class="card__title">Derniers encaissements</div><div class="card__subtitle">Tous modes de paiement</div></div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Reçu</th><th>Assuré</th><th>Date</th><th class="is-numeric">Montant</th><th>Mode</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($journal as $j): ?>
        <tr class="is-clickable" onclick="location.href='assure_fiche.php?numero=<?= urlencode($j['numero']) ?>&tab=paiements'">
          <td class="is-mono"><?= h($j['reference']) ?></td>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($j['prenom'] . ' ' . $j['nom']) ?></span><span class="person-cell__meta"><?= h($j['numero']) ?></span></div></td>
          <td class="is-mono"><?= fmt_date_fr($j['date_paiement']) ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($j['montant'], $devise) ?></td>
          <td><?= h(ucfirst($j['mode'])) ?></td>
          <td><?= badge_html($j['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$journal): ?><tr><td colspan="6"><div class="empty-state"><?= icon('credit-card',28) ?><div class="empty-state__title">Aucun paiement</div></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
