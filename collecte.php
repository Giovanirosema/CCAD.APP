<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('paiements');

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['numeros'])) {
    $numeros = (array) $_POST['numeros'];
    $mode = $_POST['mode'] ?? 'especes';
    $count = 0;
    $total = 0;
    foreach ($numeros as $numero) {
        $stmt = $pdo->prepare("SELECT id, prime FROM polices WHERE numero=? AND statut != 'Résiliée'");
        $stmt->execute([$numero]);
        $pol = $stmt->fetch();
        if (!$pol) continue;
        $ref = next_reference('paiements', 'reference', 'R', 4);
        $pdo->prepare('INSERT INTO paiements (reference, police_id, montant, mode, date_paiement, statut, agent_id) VALUES (?,?,?,?,CURDATE(),?,?)')
            ->execute([$ref, $pol['id'], $pol['prime'], $mode, 'Encaissé', $user['id']]);
        $total += (float) $pol['prime'];
        $count++;
    }
    log_audit('Encaissement', 'Collecte', "Encaissement en lot · $count reçu(s) · " . fmt_money($total, $devise) . ' · ' . ($user['zone'] ?? ''));
    flash('success', 'Encaissement en lot enregistré', "$count reçu(s) émis · " . fmt_money($total, $devise) . '.');
    redirect('collecte.php');
}

$page_title = 'Collecte du jour';
$active_nav = 'collecte';
require __DIR__ . '/includes/layout_start.php';

$rows = $pdo->query("
  SELECT p.numero, a.prenom, a.nom, p.prime, p.zone_collecte, p.statut
  FROM polices p JOIN assures a ON a.id=p.assure_id
  WHERE p.statut IN ('Active','En attente') ORDER BY a.nom LIMIT 40
")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Collecte du jour</span></div>
    <h1 class="page-header__title">Collecte du jour</h1>
    <div class="page-header__meta"><?= count($rows) ?> polices à l'échéance · <?= h($user['zone'] ?? 'Toutes zones') ?></div>
  </div>
</div>

<form method="post">
<div class="card card--flush">
  <div class="card__head">
    <div class="card__title">Feuille de collecte</div>
    <div class="card__actions">
      <select class="input input--sm" name="mode" style="width:auto">
        <option value="especes">Espèces</option><option value="moncash">MonCash</option><option value="virement">Virement</option><option value="cheque">Chèque</option>
      </select>
      <button class="btn btn--ghost btn--sm" type="button" onclick="document.querySelectorAll('.colChk').forEach(c=>c.checked=!c.checked)">Tout sélectionner</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table is-dense">
      <thead><tr><th></th><th>Assuré</th><th>Police</th><th class="is-numeric">Montant</th><th>Zone</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><input class="colChk" type="checkbox" name="numeros[]" value="<?= h($row['numero']) ?>"></td>
          <td><div class="person-cell"><span class="person-cell__name"><?= h($row['prenom'] . ' ' . $row['nom']) ?></span></div></td>
          <td class="is-mono"><?= h($row['numero']) ?></td>
          <td class="is-numeric is-mono"><?= fmt_money($row['prime'], $devise) ?></td>
          <td><?= h($row['zone_collecte'] ?? '—') ?></td>
          <td><?= badge_html($row['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><?= icon('search',28) ?><div class="empty-state__title">Aucune police à collecter</div></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="cluster" style="justify-content:flex-end;margin-top:14px">
  <button class="btn btn--primary" type="submit"><?= icon('credit-card',16) ?> Encaisser la sélection</button>
</div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
