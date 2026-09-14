<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Tableau de bord';
$active_nav = 'dashboard';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$r = regles();
$org = organisation();
$devise = $org['devise'] ?? 'HTG';
$eligMois = (int) $r['eligibilite_mois'];
$retardJours = (int) $r['retard_jours'];

$assuresActifs = (int) $pdo->query("SELECT COUNT(*) FROM polices WHERE statut IN ('Active','En retard','En attente')")->fetchColumn();
$moisCourant = date('Y-m');
$stmtEncaisse = $pdo->prepare('SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut="Encaissé" AND DATE_FORMAT(date_paiement,"%Y-%m")=?');
$stmtEncaisse->execute([$moisCourant]);
$encaisseMois = (float) $stmtEncaisse->fetchColumn();
$retardStmt = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(prime),0) arrieres FROM polices WHERE statut='En retard'");
$retardRow = $retardStmt->fetch();
$eligiblesStmt = $pdo->prepare("SELECT COUNT(*) FROM polices WHERE statut='Active' AND TIMESTAMPDIFF(MONTH, date_adhesion, CURDATE()) >= ?");
$eligiblesStmt->execute([$eligMois]);
$eligibles = (int) $eligiblesStmt->fetchColumn();

// Tendance des encaissements — 9 derniers mois
$trend = $pdo->query("
  SELECT DATE_FORMAT(date_paiement,'%Y-%m') ym, DATE_FORMAT(date_paiement,'%b') lbl, SUM(montant) total
  FROM paiements WHERE statut='Encaissé' AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 9 MONTH)
  GROUP BY ym ORDER BY ym
")->fetchAll();
$maxTrend = 1;
foreach ($trend as $t) { $maxTrend = max($maxTrend, (float) $t['total']); }

$moisFr = ['01'=>'Janv','02'=>'Févr','03'=>'Mars','04'=>'Avr','05'=>'Mai','06'=>'Juin','07'=>'Juil','08'=>'Août','09'=>'Sept','10'=>'Oct','11'=>'Nov','12'=>'Déc'];

$echeances = $pdo->query("
  SELECT a.nom, a.prenom, p.numero, p.prime, p.statut, p.zone_collecte
  FROM polices p JOIN assures a ON a.id=p.assure_id
  WHERE p.statut != 'Résiliée' ORDER BY p.id LIMIT 6
")->fetchAll();

$recentes = $pdo->query("
  SELECT a.nom, a.prenom, p.numero, pl.nom AS plan, p.prime, p.date_adhesion, p.statut
  FROM polices p JOIN assures a ON a.id=p.assure_id JOIN plans pl ON pl.id=p.plan_id
  ORDER BY p.date_adhesion DESC, p.id DESC LIMIT 7
")->fetchAll();

$statutParts = $pdo->query("SELECT statut, COUNT(*) n FROM polices GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalPolices = array_sum($statutParts);
$statutColors = ['Active'=>'var(--green-600)','En retard'=>'var(--red-600)','En attente'=>'var(--amber-600)','Suspendue'=>'var(--grey-500)','Résiliée'=>'var(--grey-400)'];

$planRepartition = $pdo->query("
  SELECT pl.nom, pl.prime, COALESCE(SUM(pa.montant),0) total
  FROM plans pl LEFT JOIN polices p ON p.plan_id=pl.id LEFT JOIN paiements pa ON pa.police_id=p.id AND pa.statut='Encaissé'
  GROUP BY pl.id ORDER BY total DESC LIMIT 4
")->fetchAll();
$maxPlan = 1;
foreach ($planRepartition as $p) { $maxPlan = max($maxPlan, (float) $p['total']); }

$journal = $pdo->query("
  SELECT al.*, u.prenom up, u.nom un FROM audit_log al LEFT JOIN utilisateurs u ON u.id=al.utilisateur_id
  ORDER BY al.horodatage DESC LIMIT 5
")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-header__eyebrow">Vue d'ensemble</div>
    <h1 class="page-header__title">Tableau de bord</h1>
    <div class="page-header__meta">Données au <?= date('d/m/Y \à H:i') ?> · exercice <?= date('Y') ?> · échéance mensuelle le <?= (int) $r['echeance_jour'] ?></div>
  </div>
  <div class="page-header__actions">
    <?php if (has_permission('assures')): ?><a class="btn btn--secondary btn--sm" href="assure_nouveau.php?type=individuel"><?= icon('user-plus',16) ?> Nouvelle adhésion</a><?php endif; ?>
    <?php if (has_permission('paiements')): ?><a class="btn btn--secondary btn--sm" href="collecte.php"><?= icon('credit-card',16) ?> Encaisser</a><?php endif; ?>
    <?php if (has_permission('reclamations')): ?><a class="btn btn--secondary btn--sm" href="reclamation_nouveau.php"><?= icon('folder-open',16) ?> Ouvrir un dossier</a><?php endif; ?>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card stat-card--brand">
    <div class="stat-card__label"><?= icon('users',14) ?> Assurés actifs</div>
    <div><span class="stat-card__value"><?= fmt_money($assuresActifs) ?></span><span class="stat-card__unit">assurés</span></div>
    <div class="stat-card__delta stat-card__delta--up">Portefeuille total</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label"><?= icon('credit-card',14) ?> Cotisations encaissées — <?= $moisFr[date('m')] ?></div>
    <div><span class="stat-card__value"><?= fmt_money($encaisseMois) ?></span><span class="stat-card__unit"><?= h($devise) ?></span></div>
    <div class="stat-card__delta stat-card__delta--up">Mois en cours</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label"><?= icon('triangle-alert',14) ?> En retard (plus de <?= $retardJours ?> jours)</div>
    <div><span class="stat-card__value"><?= (int) $retardRow['n'] ?></span><span class="stat-card__unit">polices</span></div>
    <div class="stat-card__delta stat-card__delta--down"><?= fmt_money($retardRow['arrieres'], $devise) ?> d'arriérés</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label"><?= icon('shield-check',14) ?> Éligibles ce mois</div>
    <div><span class="stat-card__value"><?= $eligibles ?></span><span class="stat-card__unit">assurés</span></div>
    <div class="stat-card__delta stat-card__delta--neutral"><?= $eligMois ?> mois de cotisation atteints</div>
  </div>
</div>

<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__head">
        <div><div class="card__title">Tendance des encaissements</div><div class="card__subtitle">Neuf derniers mois · <?= h($devise) ?></div></div>
        <div class="card__actions"><a class="btn btn--ghost btn--sm" href="rapports.php">Rapports</a></div>
      </div>
      <div class="card__body">
        <div class="bar-chart">
          <?php foreach ($trend as $t): $h = max(8, round(((float)$t['total'] / $maxTrend) * 100)); ?>
          <div class="bar-chart__col">
            <div class="bar-chart__stack">
              <div class="bar-chart__n"><?= fmt_money($t['total']) ?></div>
              <div class="bar-chart__bar" style="height:<?= $h ?>%" title="<?= h($t['lbl'] . ' — ' . fmt_money($t['total'], $devise)) ?>"></div>
            </div>
            <div class="bar-chart__label"><?= h($t['lbl']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card card--flush">
      <div class="card__head">
        <div><div class="card__title">Échéances du jour</div><div class="card__subtitle">Échéance du <?= (int) $r['echeance_jour'] ?> · polices à encaisser</div></div>
        <div class="card__actions"><a class="btn btn--ghost btn--sm" href="paiements.php">Paiements</a></div>
      </div>
      <div class="table-wrap">
        <table class="data-table is-dense">
          <thead><tr><th>Assuré</th><th class="is-numeric">Montant dû</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($echeances as $e): ?>
            <tr>
              <td><div class="person-cell"><span class="person-cell__name"><?= h($e['prenom'] . ' ' . $e['nom']) ?></span><span class="person-cell__meta"><?= h($e['numero']) ?></span></div></td>
              <td class="is-numeric is-mono"><?= fmt_money($e['prime'], $devise) ?></td>
              <td><?= badge_html($e['statut']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$echeances): ?><tr><td colspan="3"><div class="empty-state"><?= icon('search',28) ?><div class="empty-state__title">Aucune échéance</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card card--flush">
      <div class="card__head">
        <div><div class="card__title">Adhésions récentes</div><div class="card__subtitle">Derniers dossiers enregistrés</div></div>
        <div class="card__actions"><a class="btn btn--ghost btn--sm" href="assures.php">Voir tous</a></div>
      </div>
      <div class="table-wrap">
        <table class="data-table is-dense">
          <thead><tr><th>Assuré</th><th>Plan</th><th class="is-numeric">Prime</th><th>Adhésion</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($recentes as $r2): ?>
            <tr class="is-clickable" onclick="location.href='assure_fiche.php?numero=<?= urlencode($r2['numero']) ?>'">
              <td><div class="person-cell"><span class="person-cell__name"><?= h($r2['prenom'] . ' ' . $r2['nom']) ?></span><span class="person-cell__meta"><?= h($r2['numero']) ?></span></div></td>
              <td><?= h($r2['plan']) ?></td>
              <td class="is-numeric is-mono"><?= fmt_money($r2['prime'], $devise) ?></td>
              <td class="is-mono"><?= fmt_date_fr($r2['date_adhesion']) ?></td>
              <td><?= badge_html($r2['statut']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="form-col form-col--side">
    <div class="card">
      <div class="card__title" style="margin-bottom:12px">Interventions requises</div>
      <div class="alert-list">
        <div class="alert-item"><div class="alert-item__icon" style="color:var(--red-600)"><?= icon('triangle-alert',18) ?></div><div><div class="alert-item__title"><?= (int)$retardRow['n'] ?> polices en retard de paiement</div><div class="alert-item__detail">Seuil de <?= $retardJours ?> jours dépassé — suspension automatique à <?= (int)$r['suspension_jours'] ?> jours.</div></div></div>
        <div class="alert-item"><div class="alert-item__icon" style="color:var(--green-600)"><?= icon('shield-check',18) ?></div><div><div class="alert-item__title"><?= $eligibles ?> assurés éligibles</div><div class="alert-item__detail">Délai de <?= $eligMois ?> mois de cotisation atteint.</div></div></div>
        <div class="alert-item"><div class="alert-item__icon" style="color:var(--navy-600)"><?= icon('user-plus',18) ?></div><div><div class="alert-item__title">Adhésions à valider</div><div class="alert-item__detail">Vérifier les pièces d'identité manquantes sur les nouveaux dossiers.</div></div></div>
      </div>
    </div>

    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:12px">Encaissements par plan</div>
      <div class="split-list">
        <?php foreach ($planRepartition as $p): $pct = round(((float)$p['total'] / $maxPlan) * 100); ?>
        <div class="split-row">
          <div class="split-row__label"><?= h($p['nom']) ?></div>
          <div class="split-row__bar"><div class="split-row__fill" style="width:<?= $pct ?>%;background:var(--navy-600)"></div></div>
          <div class="split-row__value"><?= fmt_money($p['total']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__title" style="margin-bottom:12px">Portefeuille par statut</div>
      <div class="split-list">
        <?php foreach ($statutParts as $statut => $n): $pct = $totalPolices ? round(($n / $totalPolices) * 100) : 0; ?>
        <div class="split-row">
          <div class="split-row__label"><?= h($statut) ?></div>
          <div class="split-row__bar"><div class="split-row__fill" style="width:<?= $pct ?>%;background:<?= $statutColors[$statut] ?? 'var(--grey-400)' ?>"></div></div>
          <div class="split-row__value"><?= $n ?> (<?= $pct ?>%)</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__title" style="margin-bottom:12px">Journal d'activité</div>
      <div class="stack stack--sm">
        <?php foreach ($journal as $j): ?>
        <div style="padding-bottom:10px;border-bottom:1px solid var(--border-subtle)">
          <div style="font-size:12px;font-weight:500;color:var(--grey-800)"><?= h($j['detail']) ?></div>
          <div style="font-size:11px;color:var(--text-faint)"><?= h(date('H:i', strtotime($j['horodatage']))) ?> · <?= h($j['up'] ? $j['up'] . ' ' . $j['un'] : 'Traitement automatique') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
