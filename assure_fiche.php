<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('assures');

$pdo = db();
$numero = $_GET['numero'] ?? '';

function load_police(PDO $pdo, string $numero): ?array
{
    $stmt = $pdo->prepare("
        SELECT p.*, a.reference, a.prenom, a.nom, a.date_naissance, a.nif, a.telephone, a.telephone2, a.email,
               a.profession, a.adresse, a.section_communale, a.sexe, a.etat_civil, a.id AS assure_id,
               com.nom AS ville, dep.nom AS departement, pl.nom AS plan_nom, pl.capital AS plan_capital, pl.id AS plan_id,
               f.nom AS formule_nom
        FROM polices p
        JOIN assures a ON a.id = p.assure_id
        JOIN plans pl ON pl.id = p.plan_id
        JOIN formules f ON f.id = p.formule_id
        LEFT JOIN communes com ON com.id = a.commune_id
        LEFT JOIN departements dep ON dep.id = a.departement_id
        WHERE p.numero = ?
    ");
    $stmt->execute([$numero]);
    return $stmt->fetch() ?: null;
}

$police = load_police($pdo, $numero);
if (!$police) {
    http_response_code(404);
    $page_title = 'Assuré introuvable';
    $active_nav = 'assures';
    require __DIR__ . '/includes/layout_start.php';
    echo '<div class="empty-state">' . icon('search', 32) . '<div class="empty-state__title">Police introuvable</div></div>';
    require __DIR__ . '/includes/layout_end.php';
    exit;
}

$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];

// ---- Actions POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'paiement' && has_permission('paiements')) {
        $montant = (float) str_replace(' ', '', $_POST['montant'] ?? '0');
        $mode = $_POST['mode'] ?? 'moncash';
        $date = $_POST['date'] ?? date('Y-m-d');
        $ref = next_reference('paiements', 'reference', 'R', 4);
        $pdo->prepare('INSERT INTO paiements (reference, police_id, montant, mode, date_paiement, statut, agent_id) VALUES (?,?,?,?,?,?,?)')
            ->execute([$ref, $police['id'], $montant, $mode, $date, 'Encaissé', current_user()['id']]);
        log_audit('Encaissement', $ref, fmt_money($montant, $devise) . ' · ' . $mode . ' · police ' . $police['numero']);
        flash('success', 'Paiement enregistré', "Reçu $ref · " . fmt_money($montant, $devise) . '.');
        redirect('pages/print/recu.php?ref=' . urlencode($ref));
    }
    if ($action === 'resilier' && has_permission('polices')) {
        $pdo->prepare("UPDATE polices SET statut='Résiliée', resilie_le=NOW() WHERE id=?")->execute([$police['id']]);
        log_audit('Modification', $police['numero'], 'Police résiliée — couverture terminée.');
        flash('danger', 'Police résiliée', 'Police ' . $police['numero'] . ' · couverture terminée.');
        redirect('assures.php');
    }
}

$page_title = $police['prenom'] . ' ' . $police['nom'];
$active_nav = 'assures';
require __DIR__ . '/includes/layout_start.php';

$tab = $_GET['tab'] ?? 'police';
$mois = mois_cotises($police['date_adhesion']);
$eligible = $mois >= $eligMois;

$paiements = $pdo->prepare('SELECT * FROM paiements WHERE police_id=? ORDER BY date_paiement DESC');
$paiements->execute([$police['id']]);
$paiements = $paiements->fetchAll();

$benefs = $pdo->prepare('SELECT * FROM police_beneficiaires WHERE police_id=?');
$benefs->execute([$police['id']]);
$benefs = $benefs->fetchAll();

$membres = $pdo->prepare('SELECT * FROM police_membres WHERE police_id=?');
$membres->execute([$police['id']]);
$membres = $membres->fetchAll();

$reclamations = $pdo->prepare('SELECT * FROM reclamations WHERE police_id=?');
$reclamations->execute([$police['id']]);
$reclamations = $reclamations->fetchAll();

function tab_link(string $numero, string $tab, string $current, string $label, string $icon, ?int $count = null): void
{
    $cls = 'tabs__item' . ($tab === $current ? ' is-active' : '');
    echo '<a class="' . $cls . '" href="assure_fiche.php?numero=' . urlencode($numero) . '&tab=' . $tab . '">' . icon($icon, 16) . ' ' . h($label);
    if ($count !== null) echo '<span class="tabs__count">' . $count . '</span>';
    echo '</a>';
}
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Assurés</span><span><?= h($police['reference']) ?></span></div>
    <h1 class="page-header__title"><?= h($police['prenom'] . ' ' . $police['nom']) ?></h1>
    <div class="page-header__meta">Police <?= h($police['numero']) ?> · adhésion <?= fmt_date_fr($police['date_adhesion']) ?> · <?= h($police['ville'] ?? '—') ?></div>
  </div>
  <div class="page-header__actions">
    <a class="btn btn--ghost" href="assures.php"><?= icon('users',16) ?> Retour</a>
    <a class="btn btn--secondary" href="pages/print/inscription.php?numero=<?= urlencode($police['numero']) ?>" target="_blank"><?= icon('printer',16) ?> Fiche d'inscription</a>
    <a class="btn btn--secondary" href="pages/print/paiement.php?numero=<?= urlencode($police['numero']) ?>" target="_blank"><?= icon('printer',16) ?> Fiche de paiement</a>
    <?php if (has_permission('paiements')): ?>
    <button class="btn btn--primary" type="button" onclick="document.getElementById('payModal').style.display='flex'"><?= icon('credit-card',16) ?> Enregistrer un paiement</button>
    <?php endif; ?>
  </div>
</div>

<div class="form-row">
  <div class="form-col">
    <div class="tabs">
      <?php
        tab_link($police['numero'], 'police', $tab, 'Police', 'file-text');
        tab_link($police['numero'], 'paiements', $tab, 'Paiements', 'credit-card', count($paiements));
        tab_link($police['numero'], 'benef', $tab, 'Bénéficiaires', 'users', count($benefs));
        tab_link($police['numero'], 'recl', $tab, 'Réclamations', 'folder-open', count($reclamations));
      ?>
    </div>

    <?php if ($tab === 'police'): ?>
      <div class="card">
        <div class="card__title" style="margin-bottom:14px">Identité</div>
        <div class="deflist" style="--cols:2">
          <div><div class="deflist__term">Nom complet</div><div class="deflist__value"><?= h($police['prenom'] . ' ' . $police['nom']) ?></div></div>
          <div><div class="deflist__term">Référence assuré</div><div class="deflist__value"><?= h($police['reference']) ?></div></div>
          <div><div class="deflist__term">Date de naissance</div><div class="deflist__value"><?= fmt_date_fr($police['date_naissance']) ?></div></div>
          <div><div class="deflist__term">Âge</div><div class="deflist__value"><?= age_years($police['date_naissance']) ?? '—' ?> ans</div></div>
          <div><div class="deflist__term">NIF</div><div class="deflist__value"><?= h($police['nif'] ?? '—') ?></div></div>
          <div><div class="deflist__term">Téléphone</div><div class="deflist__value"><?= h($police['telephone'] ?? '—') ?></div></div>
          <div><div class="deflist__term">Ville</div><div class="deflist__value"><?= h($police['ville'] ?? '—') ?></div></div>
          <div><div class="deflist__term">Adresse</div><div class="deflist__value"><?= h($police['adresse'] ?? '—') ?></div></div>
        </div>
      </div>
      <div class="card">
        <div class="card__title" style="margin-bottom:14px">Police</div>
        <div class="deflist" style="--cols:2">
          <div><div class="deflist__term">Numéro de police</div><div class="deflist__value"><?= h($police['numero']) ?></div></div>
          <div><div class="deflist__term">Formule</div><div class="deflist__value"><?= h($police['formule_nom']) ?></div></div>
          <div><div class="deflist__term">Plan tarifaire</div><div class="deflist__value"><?= h($police['plan_nom']) ?> · <?= fmt_money($police['prime'], $devise) ?>/mois</div></div>
          <div><div class="deflist__term">Prime mensuelle</div><div class="deflist__value"><?= fmt_money($police['prime'], $devise) ?></div></div>
          <div><div class="deflist__term">Date d'adhésion</div><div class="deflist__value"><?= fmt_date_fr($police['date_adhesion']) ?></div></div>
          <div><div class="deflist__term">Couverture funéraire</div><div class="deflist__value"><?= fmt_money($police['capital'], $devise) ?></div></div>
          <div><div class="deflist__term">Mois cotisés</div><div class="deflist__value"><?= $mois ?> / <?= $eligMois ?></div></div>
          <div><div class="deflist__term">Prochaine échéance</div><div class="deflist__value">Le <?= (int) $r['echeance_jour'] ?> du mois</div></div>
          <div><div class="deflist__term">Statut</div><div class="deflist__value"><?= badge_html($police['statut']) ?></div></div>
        </div>
      </div>
      <?php if ($membres): ?>
      <div class="card card--flush">
        <div class="card__head"><div class="card__title">Membres couverts</div></div>
        <div class="table-wrap"><table class="data-table is-dense">
          <thead><tr><th>Membre</th><th>Naissance</th><th class="is-numeric">Part</th><th>Statut</th></tr></thead>
          <tbody><?php foreach ($membres as $m): ?>
            <tr><td><div class="person-cell"><span class="person-cell__name"><?= h($m['nom']) ?></span><span class="person-cell__meta"><?= h($m['lien_parente']) ?></span></div></td>
            <td class="is-mono"><?= fmt_date_fr($m['date_naissance']) ?></td><td class="is-numeric is-mono"><?= rtrim(rtrim($m['part_pct'],'0'),'.') ?>%</td><td><?= badge_html($m['statut']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'paiements'): ?>
      <div class="card card--flush">
        <div class="card__head"><div><div class="card__title">Historique des paiements</div><div class="card__subtitle"><?= count($paiements) ?> versement(s) depuis l'adhésion</div></div></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Reçu</th><th>Date</th><th class="is-numeric">Montant</th><th>Mode</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($paiements as $pay): ?>
            <tr><td class="is-mono"><?= h($pay['reference']) ?></td><td class="is-mono"><?= fmt_date_fr($pay['date_paiement']) ?></td>
              <td class="is-numeric is-mono"><?= fmt_money($pay['montant'], $devise) ?></td><td><?= h(ucfirst($pay['mode'])) ?></td><td><?= badge_html($pay['statut']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$paiements): ?><tr><td colspan="5"><div class="empty-state"><?= icon('credit-card',28) ?><div class="empty-state__title">Aucun paiement enregistré</div></div></td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'benef'): ?>
      <div class="card card--flush">
        <div class="card__head"><div><div class="card__title">Bénéficiaires</div><div class="card__subtitle">Répartition du capital décès</div></div></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Bénéficiaire</th><th>Naissance</th><th class="is-numeric">Part</th><th>Téléphone</th></tr></thead>
          <tbody>
          <?php foreach ($benefs as $b): ?>
            <tr><td><div class="person-cell"><span class="person-cell__name"><?= h($b['nom']) ?></span><span class="person-cell__meta"><?= h($b['lien_parente']) ?></span></div></td>
            <td class="is-mono"><?= fmt_date_fr($b['date_naissance']) ?></td><td class="is-numeric is-mono"><?= rtrim(rtrim($b['part_pct'],'0'),'.') ?>%</td><td class="is-mono"><?= h($b['telephone'] ?? '—') ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$benefs): ?><tr><td colspan="4"><div class="empty-state"><?= icon('users',28) ?><div class="empty-state__title">Aucun bénéficiaire désigné</div></div></td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'recl'): ?>
      <div class="card card--flush">
        <div class="card__head"><div class="card__title">Réclamations liées à cette police</div>
          <?php if (has_permission('reclamations')): ?><div class="card__actions"><a class="btn btn--secondary btn--sm" href="reclamation_nouveau.php?numero=<?= urlencode($police['numero']) ?>">Ouvrir un dossier</a></div><?php endif; ?>
        </div>
        <?php if ($reclamations): ?>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Dossier</th><th>Déclaration</th><th class="is-numeric">Capital</th><th>Statut</th></tr></thead>
          <tbody><?php foreach ($reclamations as $rc): ?>
            <tr class="is-clickable" onclick="location.href='reclamation_detail.php?ref=<?= urlencode($rc['reference']) ?>'">
              <td class="is-mono"><?= h($rc['reference']) ?></td><td class="is-mono"><?= fmt_date_fr($rc['date_declaration']) ?></td>
              <td class="is-numeric is-mono"><?= fmt_money($rc['capital'], $devise) ?></td><td><?= badge_html($rc['statut']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php else: ?>
        <div class="empty-state"><?= icon('folder-open',28) ?><div class="empty-state__title">Aucune réclamation</div><div class="empty-state__desc">Aucun dossier ouvert pour cet assuré.</div></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="form-col form-col--side">
    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:10px">Éligibilité (<?= $eligMois ?> mois)</div>
      <div style="font-family:var(--font-numeric);font-size:26px;font-weight:600;color:var(--navy-700)"><?= $mois ?> <span style="font-size:14px;font-weight:500;color:var(--text-muted)">/ <?= $eligMois ?> mois cotisés</span></div>
      <div class="split-row__bar" style="margin:10px 0"><div class="split-row__fill" style="width:<?= min(100, round($mois/$eligMois*100)) ?>%;background:var(--navy-600)"></div></div>
      <span class="badge badge--<?= $eligible ? 'success' : 'warning' ?>"><?= icon('shield-check',12) ?> <?= $eligible ? 'Éligible' : 'En cours' ?></span>
      <div class="text-muted" style="font-size:12px;margin-top:8px"><?= $eligible ? "Délai de $eligMois mois atteint — capital décès mobilisable." : 'Encore ' . ($eligMois - $mois) . ' mois de cotisation avant l\'éligibilité.' ?></div>
    </div>
    <?php if (has_permission('polices')): ?>
    <div class="card">
      <div class="card__title" style="margin-bottom:10px">Actions</div>
      <div class="stack stack--sm">
        <?php if (has_permission('paiements')): ?><button class="btn btn--primary btn--full" type="button" onclick="document.getElementById('payModal').style.display='flex'"><?= icon('credit-card',16) ?> Enregistrer un paiement</button><?php endif; ?>
        <?php if ($police['statut'] !== 'Résiliée'): ?><button class="btn btn--danger btn--full" type="button" onclick="document.getElementById('resilModal').style.display='flex'"><?= icon('triangle-alert',16) ?> Résilier la police</button><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal-scrim no-print" id="payModal" style="display:none">
  <div class="modal">
    <div class="modal__head"><div class="modal__title">Enregistrer un paiement</div><div class="modal__desc">Assuré <?= h($police['prenom'] . ' ' . $police['nom']) ?> · police <?= h($police['numero']) ?></div></div>
    <form method="post">
      <input type="hidden" name="action" value="paiement">
      <div class="modal__body">
        <div class="field"><label class="field__label">Montant (<?= h($devise) ?>)</label><input class="input" type="text" name="montant" value="<?= (int) $police['prime'] ?>" required></div>
        <div class="field"><label class="field__label">Mode de paiement</label>
          <select class="input" name="mode">
            <option value="moncash">MonCash</option><option value="especes">Espèces</option>
            <option value="virement">Virement bancaire</option><option value="cheque">Chèque</option>
          </select>
        </div>
        <div class="field"><label class="field__label">Date</label><input class="input" type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
      </div>
      <div class="modal__footer">
        <button class="btn btn--ghost" type="button" onclick="document.getElementById('payModal').style.display='none'">Annuler</button>
        <button class="btn btn--primary" type="submit"><?= icon('printer',16) ?> Enregistrer et imprimer le reçu</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-scrim no-print" id="resilModal" style="display:none">
  <div class="modal">
    <div class="modal__head"><div class="modal__title">Résilier la police</div><div class="modal__desc">Cette action est définitive et termine la couverture funéraire.</div></div>
    <form method="post">
      <input type="hidden" name="action" value="resilier">
      <div class="modal__footer">
        <button class="btn btn--ghost" type="button" onclick="document.getElementById('resilModal').style.display='none'">Annuler</button>
        <button class="btn btn--danger" type="submit">Résilier</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
