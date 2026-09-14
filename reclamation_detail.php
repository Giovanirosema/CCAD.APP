<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('reclamations');

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];
$ref = $_GET['ref'] ?? '';

function load_reclamation(PDO $pdo, string $ref): ?array
{
    $stmt = $pdo->prepare("
        SELECT rc.*, p.numero AS police_numero, p.capital AS police_capital, a.prenom, a.nom, a.id AS assure_id
        FROM reclamations rc JOIN polices p ON p.id=rc.police_id JOIN assures a ON a.id=p.assure_id
        WHERE rc.reference=?
    ");
    $stmt->execute([$ref]);
    return $stmt->fetch() ?: null;
}

$rec = load_reclamation($pdo, $ref);
if (!$rec) {
    http_response_code(404);
    $page_title = 'Dossier introuvable';
    $active_nav = 'reclamations';
    require __DIR__ . '/includes/layout_start.php';
    echo '<div class="empty-state">' . icon('search', 32) . '<div class="empty-state__title">Dossier introuvable</div></div>';
    require __DIR__ . '/includes/layout_end.php';
    exit;
}

// Initialise les versements depuis les bénéficiaires désignés sur la police, au premier affichage.
$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM reclamation_versements WHERE reclamation_id=?');
$stmtCount->execute([$rec['id']]);
if ((int) $stmtCount->fetchColumn() === 0) {
    $net = $rec['capital'] - $rec['arrieres'] - $rec['frais_dossier'];
    $benefs = $pdo->prepare('SELECT * FROM police_beneficiaires WHERE police_id=(SELECT police_id FROM reclamations WHERE id=?)');
    $benefs->execute([$rec['id']]);
    $benefs = $benefs->fetchAll();
    if ($benefs) {
        $stmt = $pdo->prepare('INSERT INTO reclamation_versements (reclamation_id, beneficiaire_nom, lien_parente, part_pct, montant, mode, statut) VALUES (?,?,?,?,?,?,?)');
        foreach ($benefs as $b) {
            $montant = round($net * $b['part_pct'] / 100);
            $stmt->execute([$rec['id'], $b['nom'], $b['lien_parente'], $b['part_pct'], $montant, 'MonCash', 'En attente']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'pieces') {
        $pieces = $pdo->prepare('SELECT id FROM reclamation_pieces WHERE reclamation_id=?');
        $pieces->execute([$rec['id']]);
        $ids = $pieces->fetchAll(PDO::FETCH_COLUMN);
        $upd = $pdo->prepare('UPDATE reclamation_pieces SET recu=? WHERE id=?');
        foreach ($ids as $pid) {
            $upd->execute([!empty($_POST['piece'][$pid]) ? 1 : 0, $pid]);
        }
        flash('info', 'Pièces mises à jour', null);
        redirect('reclamation_detail.php?ref=' . urlencode($ref));
    }
    if ($action === 'valider' && has_permission('reclamations')) {
        $stmtM = $pdo->prepare('SELECT COUNT(*) FROM reclamation_pieces WHERE reclamation_id=? AND recu=0');
        $stmtM->execute([$rec['id']]);
        $manquantes = (int) $stmtM->fetchColumn();
        if ($manquantes > 0) {
            flash('warning', 'Validation impossible', "$manquantes pièce(s) manquante(s) au dossier $ref.");
        } else {
            $pdo->prepare("UPDATE reclamations SET statut='Encaissé', valide_le=NOW() WHERE id=?")->execute([$rec['id']]);
            $pdo->prepare("UPDATE reclamation_versements SET statut='Versé' WHERE reclamation_id=?")->execute([$rec['id']]);
            log_audit('Réclamation', $ref, 'Versement validé — capital réparti entre les bénéficiaires.');
            flash('success', 'Versement validé', 'Capital réparti entre les bénéficiaires du dossier ' . $ref . '.');
        }
        redirect('reclamation_detail.php?ref=' . urlencode($ref));
    }
    if ($action === 'rejeter' && has_permission('reclamations')) {
        $pdo->prepare("UPDATE reclamations SET statut='Rejeté', decision_note=? WHERE id=?")->execute([$_POST['motif'] ?? null, $rec['id']]);
        log_audit('Réclamation', $ref, 'Dossier rejeté sans versement.');
        flash('danger', 'Dossier rejeté', "Dossier $ref clos sans versement.");
        redirect('reclamations.php');
    }
}

$page_title = 'Dossier ' . $rec['reference'];
$active_nav = 'reclamations';
require __DIR__ . '/includes/layout_start.php';

$pieces = $pdo->prepare('SELECT * FROM reclamation_pieces WHERE reclamation_id=?');
$pieces->execute([$rec['id']]);
$pieces = $pieces->fetchAll();
$manquantes = count(array_filter($pieces, fn($p) => !$p['recu']));

$versements = $pdo->prepare('SELECT * FROM reclamation_versements WHERE reclamation_id=?');
$versements->execute([$rec['id']]);
$versements = $versements->fetchAll();

$net = $rec['capital'] - $rec['arrieres'] - $rec['frais_dossier'];
$eligible = $rec['mois_cotises'] >= $eligMois;
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Réclamations</span><span><?= h($rec['reference']) ?></span></div>
    <h1 class="page-header__title">Dossier <?= h($rec['reference']) ?></h1>
    <div class="page-header__meta"><?= h($rec['prenom'] . ' ' . $rec['nom']) ?> · police <?= h($rec['police_numero']) ?> · déclaré le <?= fmt_date_fr($rec['date_declaration']) ?> · <?= $manquantes ?> pièce(s) manquante(s)</div>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--ghost" href="reclamations.php"><?= icon('folder-open',16) ?> Retour</a>
    <a class="btn btn--secondary" href="pages/print/reclamation.php?ref=<?= urlencode($ref) ?>" target="_blank"><?= icon('printer',16) ?> Imprimer le dossier</a>
  </div>
</div>

<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Assuré décédé</div>
      <div class="deflist" style="--cols:2">
        <div><div class="deflist__term">Assuré décédé</div><div class="deflist__value"><?= h($rec['prenom'] . ' ' . $rec['nom']) ?></div></div>
        <div><div class="deflist__term">Numéro de police</div><div class="deflist__value"><?= h($rec['police_numero']) ?></div></div>
        <div><div class="deflist__term">Date du décès</div><div class="deflist__value"><?= fmt_date_fr($rec['date_deces']) ?></div></div>
        <div><div class="deflist__term">Lieu du décès</div><div class="deflist__value"><?= h($rec['lieu_deces'] ?? '—') ?></div></div>
        <div><div class="deflist__term">Date de déclaration</div><div class="deflist__value"><?= fmt_date_fr($rec['date_declaration']) ?></div></div>
        <div><div class="deflist__term">Déclarant</div><div class="deflist__value"><?= h($rec['declarant_nom'] . ($rec['declarant_lien'] ? ' (' . $rec['declarant_lien'] . ')' : '')) ?></div></div>
        <div><div class="deflist__term">Mois cotisés</div><div class="deflist__value"><?= (int) $rec['mois_cotises'] ?> mois</div></div>
        <div><div class="deflist__term">Éligibilité</div><div class="deflist__value"><?= $eligible ? 'Acquise' : "Non acquise ($eligMois mois requis)" ?></div></div>
      </div>
    </div>

    <form method="post">
      <input type="hidden" name="action" value="pieces">
      <div class="card">
        <div class="card__title" style="margin-bottom:4px">Pièces du dossier</div>
        <div class="card__subtitle" style="margin-bottom:14px">Le versement reste bloqué tant qu'une pièce manque</div>
        <div class="stack stack--sm">
          <?php foreach ($pieces as $pc): ?>
          <label class="checkbox"><input type="checkbox" name="piece[<?= $pc['id'] ?>]" value="1" <?= $pc['recu'] ? 'checked' : '' ?> onchange="this.form.submit()"><?= h($pc['nom']) ?><span class="checkbox__desc"><?= h($pc['detail'] ?? '') ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    </form>

    <div class="card card--flush">
      <div class="card__head"><div><div class="card__title">Versements aux bénéficiaires</div><div class="card__subtitle">Capital réparti selon les parts déclarées à la souscription</div></div></div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Bénéficiaire</th><th class="is-numeric">Part</th><th class="is-numeric">Montant</th><th>Mode</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($versements as $v): ?>
            <tr><td><div class="person-cell"><span class="person-cell__name"><?= h($v['beneficiaire_nom']) ?></span><span class="person-cell__meta"><?= h($v['lien_parente'] ?? '') ?></span></div></td>
            <td class="is-numeric is-mono"><?= rtrim(rtrim($v['part_pct'],'0'),'.') ?>%</td>
            <td class="is-numeric is-mono"><?= fmt_money($v['montant'], $devise) ?></td><td><?= h($v['mode']) ?></td><td><?= badge_html($v['statut']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$versements): ?><tr><td colspan="5"><div class="empty-state"><?= icon('users',28) ?><div class="empty-state__title">Aucun bénéficiaire désigné sur cette police</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="form-col form-col--side">
    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:10px">Calcul du capital</div>
      <div class="kv-list">
        <div class="kv-list__row"><div class="kv-list__term">Couverture du plan</div><div class="kv-list__value"><?= fmt_money($rec['capital'], $devise) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Cotisations encaissées</div><div class="kv-list__value"><?= fmt_money($rec['cotisations_versees'], $devise) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Arriérés retenus</div><div class="kv-list__value">− <?= fmt_money($rec['arrieres'], $devise) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Frais de dossier</div><div class="kv-list__value">− <?= fmt_money($rec['frais_dossier'], $devise) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Net à verser</div><div class="kv-list__value"><?= fmt_money($net, $devise) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Décision</div><div class="kv-list__value"><?= $manquantes ? 'En attente' : 'Favorable' ?></div></div>
      </div>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:10px">Décision</div>
      <span class="badge badge--<?= $manquantes ? 'warning' : 'success' ?>"><?= $manquantes ? 'Instruction en cours' : 'Prêt au versement' ?></span>
      <div class="text-muted" style="font-size:12px;margin:8px 0 14px"><?= $manquantes ? "Le versement reste bloqué jusqu'à réception des pièces manquantes." : 'Toutes les pièces sont au dossier et l\'éligibilité est vérifiée.' ?></div>
      <?php if (has_permission('reclamations') && !in_array($rec['statut'], ['Encaissé','Rejeté'], true)): ?>
      <form method="post" class="stack stack--sm">
        <input type="hidden" name="action" value="valider">
        <button class="btn btn--primary btn--full" type="submit"><?= icon('credit-card',16) ?> Valider le versement</button>
      </form>
      <form method="post" style="margin-top:8px" onsubmit="return confirm('Rejeter ce dossier sans versement ?')">
        <input type="hidden" name="action" value="rejeter">
        <button class="btn btn--danger btn--full" type="submit"><?= icon('x',16) ?> Rejeter le dossier</button>
      </form>
      <?php else: ?>
      <div class="text-faint" style="font-size:12px">Dossier <?= mb_strtolower($rec['statut']) ?> — aucune action supplémentaire.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
