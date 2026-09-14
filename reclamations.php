<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('reclamations');
$page_title = 'Réclamations';
$active_nav = 'reclamations';
require __DIR__ . '/includes/layout_start.php';

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';

$rows = $pdo->query("
  SELECT rc.reference, rc.date_declaration, rc.capital, rc.statut,
         (SELECT beneficiaire_nom FROM reclamation_versements WHERE reclamation_id=rc.id ORDER BY id LIMIT 1) AS benef_principal,
         (SELECT COUNT(*) FROM reclamation_versements WHERE reclamation_id=rc.id) AS nb_benef,
         a.prenom, a.nom, p.numero
  FROM reclamations rc JOIN polices p ON p.id=rc.police_id JOIN assures a ON a.id=p.assure_id
  ORDER BY rc.date_declaration DESC
")->fetchAll();

$enAttentePieces = (int) $pdo->query("SELECT COUNT(DISTINCT reclamation_id) FROM reclamation_pieces WHERE recu=0")->fetchColumn();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Réclamations</span></div>
    <h1 class="page-header__title">Réclamations</h1>
    <div class="page-header__meta"><?= count($rows) ?> dossiers ouverts · <?= $enAttentePieces ?> en attente de pièces</div>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--primary" href="reclamation_nouveau.php"><?= icon('folder-open',16) ?> Ouvrir un dossier</a>
  </div>
</div>

<div class="card card--flush">
  <div class="card__head"><div><div class="card__title">Dossiers en cours</div><div class="card__subtitle">Capital décès à verser aux bénéficiaires</div></div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Dossier</th><th>Assuré décédé</th><th>Déclaration</th><th class="is-numeric">Capital</th><th>Bénéficiaire principal</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr class="is-clickable" onclick="location.href='reclamation_detail.php?ref=<?= urlencode($row['reference']) ?>'">
          <td class="is-mono"><?= h($row['reference']) ?></td>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($row['prenom'] . ' ' . $row['nom']) ?></span><span class="person-cell__meta"><?= h($row['numero']) ?></span></div></td>
          <td class="is-mono"><?= fmt_date_fr($row['date_declaration']) ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($row['capital'], $devise) ?></td>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($row['benef_principal'] ?? '—') ?></span><span class="person-cell__meta"><?= (int) $row['nb_benef'] ?> bénéficiaire(s)</span></div></td>
          <td><?= badge_html($row['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><?= icon('folder-open',28) ?><div class="empty-state__title">Aucun dossier</div></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
