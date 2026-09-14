<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('parametres');

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';

// ---- Actions POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'org') {
        $pdo->prepare('UPDATE reglages_organisation SET nom=?, nif=?, adresse=?, telephone=?, email=?, site=?, slogan=?, frais_inscription=?, devise=? WHERE id=1')
            ->execute([$_POST['nom'], $_POST['nif'], $_POST['adresse'], $_POST['telephone'], $_POST['email'], $_POST['site'], $_POST['slogan'], (float) $_POST['frais_inscription'], $_POST['devise']]);
        log_audit('Paramètres', 'Organisation', 'Informations administratives mises à jour');
        flash('success', 'Informations enregistrées', null);
        redirect('parametres.php?tab=org');
    }

    if ($action === 'regles') {
        $pdo->prepare('UPDATE reglages_regles SET eligibilite_mois=?, retard_jours=?, suspension_jours=?, echeance_jour=? WHERE id=1')
            ->execute([(int) $_POST['eligibilite'], (int) $_POST['retard'], (int) $_POST['suspension'], (int) $_POST['echeance']]);
        $pdo->prepare('UPDATE reglages_notifications SET adhesion=?, retard=?, eligibilite=?, reclamation=? WHERE id=1')
            ->execute([!empty($_POST['notif_adhesion']) ? 1 : 0, !empty($_POST['notif_retard']) ? 1 : 0, !empty($_POST['notif_eligibilite']) ? 1 : 0, !empty($_POST['notif_reclamation']) ? 1 : 0]);
        log_audit('Paramètres', 'Règles', 'Règles et alertes mises à jour');
        flash('success', 'Paramètres appliqués', null);
        redirect('parametres.php?tab=regles');
    }

    if ($action === 'regles_reset') {
        $pdo->exec("UPDATE reglages_regles SET eligibilite_mois=24, retard_jours=30, suspension_jours=60, echeance_jour=1 WHERE id=1");
        flash('info', 'Règles réinitialisées', 'Valeurs par défaut restaurées.');
        redirect('parametres.php?tab=regles');
    }

    if ($action === 'securite') {
        $pdo->prepare('UPDATE reglages_securite SET double_auth=?, journal_actif=?, acces_public=? WHERE id=1')
            ->execute([!empty($_POST['double_auth']) ? 1 : 0, !empty($_POST['journal_actif']) ? 1 : 0, !empty($_POST['acces_public']) ? 1 : 0]);
        log_audit('Paramètres', 'Sécurité', 'Paramètres de sécurité mis à jour');
        flash('success', 'Sécurité mise à jour', null);
        redirect('parametres.php?tab=users');
    }

    if ($action === 'plan_add') {
        $code = 'plan-' . (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn() + 1;
        $pdo->prepare('INSERT INTO plans (code, nom, prime, capital, actif, ordre) VALUES (?,?,?,?,0,?)')
            ->execute([$code . '-' . uniqid(), 'Nouveau plan', 2000, 120000, (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn() + 1]);
        flash('success', 'Plan ajouté', 'Renseigner la prime puis activer le plan.');
        redirect('parametres.php?tab=plans');
    }
    if ($action === 'plan_update') {
        $pdo->prepare('UPDATE plans SET nom=?, prime=?, capital=?, actif=? WHERE id=?')
            ->execute([$_POST['nom'], (float) str_replace(' ', '', $_POST['prime']), (float) str_replace(' ', '', $_POST['capital']), !empty($_POST['actif']) ? 1 : 0, (int) $_POST['id']]);
        flash('success', 'Plan mis à jour', null);
        redirect('parametres.php?tab=plans');
    }
    if ($action === 'plan_delete') {
        $pdo->prepare('DELETE FROM plans WHERE id=?')->execute([(int) $_POST['id']]);
        log_audit('Paramètres', 'Plans', 'Plan retiré des offres proposées');
        flash('danger', 'Plan retiré', "Le plan n'est plus proposé à l'adhésion.");
        redirect('parametres.php?tab=plans');
    }

    if ($action === 'formule_toggle') {
        $pdo->prepare('UPDATE formules SET actif=? WHERE id=?')->execute([!empty($_POST['actif']) ? 1 : 0, (int) $_POST['id']]);
        redirect('parametres.php?tab=formules');
    }
    if ($action === 'service_toggle') {
        $pdo->prepare('UPDATE services SET actif=? WHERE id=?')->execute([!empty($_POST['actif']) ? 1 : 0, (int) $_POST['id']]);
        redirect('parametres.php?tab=formules');
    }
}

$page_title = 'Paramètres';
$active_nav = 'parametres';
require __DIR__ . '/includes/layout_start.php';

$tab = $_GET['tab'] ?? 'plans';
$plans = $pdo->query('SELECT * FROM plans ORDER BY ordre')->fetchAll();
$formules = $pdo->query('SELECT * FROM formules')->fetchAll();
$services = $pdo->query('SELECT * FROM services')->fetchAll();
$org = $pdo->query('SELECT * FROM reglages_organisation WHERE id=1')->fetch();
$regles = $pdo->query('SELECT * FROM reglages_regles WHERE id=1')->fetch();
$notif = $pdo->query('SELECT * FROM reglages_notifications WHERE id=1')->fetch();
$securite = $pdo->query('SELECT * FROM reglages_securite WHERE id=1')->fetch();
$users = $pdo->query("SELECT u.*, r.nom AS role_nom, d.nom AS dept_nom, c.nom AS commune_nom FROM utilisateurs u JOIN roles r ON r.id=u.role_id LEFT JOIN departements d ON d.id=u.departement_id LEFT JOIN communes c ON c.id=u.commune_id ORDER BY u.nom")->fetchAll();
$activePlans = array_filter($plans, fn($p) => $p['actif']);
$notifCount = (int) $notif['adhesion'] + (int) $notif['retard'] + (int) $notif['eligibilite'] + (int) $notif['reclamation'];

$couvStmt = $pdo->query("
  SELECT d.nom AS dept, COUNT(*) agents, GROUP_CONCAT(DISTINCT u.zone_collecte SEPARATOR ' · ') zones,
    (SELECT CONCAT(u2.prenom,' ',u2.nom) FROM utilisateurs u2 JOIN roles r2 ON r2.id=u2.role_id WHERE u2.departement_id=d.id AND r2.nom LIKE 'Administrateur%' LIMIT 1) responsable
  FROM utilisateurs u JOIN departements d ON d.id=u.departement_id GROUP BY d.id
")->fetchAll();

function ptab(string $value, string $current, string $label, string $iconName, ?int $count = null): void
{
    $cls = 'tabs__item' . ($current === $value ? ' is-active' : '');
    echo '<a class="' . $cls . '" href="parametres.php?tab=' . $value . '">' . icon($iconName, 16) . ' ' . h($label);
    if ($count !== null) echo '<span class="tabs__count">' . $count . '</span>';
    echo '</a>';
}
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Paramètres</span></div>
    <h1 class="page-header__title">Paramètres</h1>
    <div class="page-header__meta"><?= count($activePlans) ?> plans actifs · éligibilité à <?= (int) $regles['eligibilite_mois'] ?> mois · suspension à <?= (int) $regles['suspension_jours'] ?> jours</div>
  </div>
</div>

<div class="tabs">
  <?php
    ptab('plans', $tab, 'Plans de police', 'file-text', count($plans));
    ptab('formules', $tab, 'Formules et services', 'folder-open', count($formules));
    ptab('org', $tab, 'Informations admin', 'shield-check');
    ptab('regles', $tab, 'Règles et alertes', 'calendar-clock');
    ptab('users', $tab, 'Utilisateurs', 'users', count($users));
    ptab('donnees', $tab, 'Données et sauvegarde', 'printer');
  ?>
</div>

<?php if ($tab === 'plans'): ?>
<div class="card card--flush">
  <div class="card__head"><div class="card__title">Plans de police</div><div class="card__actions">
    <form method="post"><input type="hidden" name="action" value="plan_add"><button class="btn btn--secondary btn--sm" type="submit"><?= icon('file-text',16) ?> Ajouter un plan</button></form>
  </div></div>
  <?php foreach ($plans as $pl): ?>
  <form method="post" id="planForm<?= $pl['id'] ?>"><input type="hidden" name="action" value="plan_update"><input type="hidden" name="id" value="<?= $pl['id'] ?>"></form>
  <form method="post" id="planDelete<?= $pl['id'] ?>" onsubmit="return confirm('Retirer ce plan ?')"><input type="hidden" name="action" value="plan_delete"><input type="hidden" name="id" value="<?= $pl['id'] ?>"></form>
  <?php endforeach; ?>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Plan</th><th class="is-numeric">Cotisation</th><th class="is-numeric">Couverture</th><th>Disponible</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($plans as $pl): ?>
    <tr>
        <td><input class="input input--sm" form="planForm<?= $pl['id'] ?>" name="nom" value="<?= h($pl['nom']) ?>"></td>
        <td class="is-numeric"><input class="input input--sm text-right" form="planForm<?= $pl['id'] ?>" name="prime" value="<?= (int) $pl['prime'] ?>"></td>
        <td class="is-numeric"><input class="input input--sm text-right" form="planForm<?= $pl['id'] ?>" name="capital" value="<?= (int) $pl['capital'] ?>"></td>
        <td><label class="checkbox"><input type="checkbox" form="planForm<?= $pl['id'] ?>" name="actif" value="1" <?= $pl['actif']?'checked':'' ?>></label></td>
        <td class="cluster">
          <button class="btn btn--ghost btn--sm" type="submit" form="planForm<?= $pl['id'] ?>">Enregistrer</button>
          <button class="icon-btn" type="submit" form="planDelete<?= $pl['id'] ?>"><?= icon('trash-2',14) ?></button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="text-muted" style="font-size:12px;padding:14px 20px">
    <?php if ($activePlans): ?>Plans proposés à l'adhésion : <?php foreach ($activePlans as $i => $p2) { echo ($i?' · ':'') . h($p2['nom']) . ' (' . fmt_money($p2['prime'], $devise) . ')'; } ?>.
    <?php else: ?>Aucun plan actif : les formulaires d'adhésion ne proposent aucune police.<?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'formules'): ?>
<div class="card card--flush">
  <div class="card__head"><div class="card__title">Formules</div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Formule</th><th>Description</th><th>Actif</th></tr></thead><tbody>
  <?php foreach ($formules as $f): ?>
  <tr><td><?= h($f['nom']) ?></td><td class="text-muted" style="font-size:12px"><?= h($f['description']) ?></td>
    <td><form method="post"><input type="hidden" name="action" value="formule_toggle"><input type="hidden" name="id" value="<?= $f['id'] ?>">
      <label class="checkbox"><input type="checkbox" name="actif" value="1" <?= $f['actif']?'checked':'' ?> onchange="this.form.submit()"></label></form></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<div class="card card--flush">
  <div class="card__head"><div class="card__title">Services funéraires</div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Service</th><th>Mode</th><th>Actif</th></tr></thead><tbody>
  <?php foreach ($services as $s): ?>
  <tr><td><?= h($s['nom']) ?></td><td><?= h($s['mode']) ?></td>
    <td><form method="post"><input type="hidden" name="action" value="service_toggle"><input type="hidden" name="id" value="<?= $s['id'] ?>">
      <label class="checkbox"><input type="checkbox" name="actif" value="1" <?= $s['actif']?'checked':'' ?> onchange="this.form.submit()"></label></form></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>

<?php elseif ($tab === 'org'): ?>
<form method="post"><input type="hidden" name="action" value="org">
<div class="card">
  <div class="card__title" style="margin-bottom:14px">Informations administratives</div>
  <div class="form-grid">
    <div class="field" style="grid-column:1/-1"><label class="field__label">Nom de l'organisation</label><input class="input" name="nom" value="<?= h($org['nom']) ?>"></div>
    <div class="field"><label class="field__label">NIF</label><input class="input" name="nif" value="<?= h($org['nif']) ?>"></div>
    <div class="field"><label class="field__label">Téléphone</label><input class="input" name="telephone" value="<?= h($org['telephone']) ?>"></div>
    <div class="field" style="grid-column:1/-1"><label class="field__label">Adresse</label><input class="input" name="adresse" value="<?= h($org['adresse']) ?>"></div>
    <div class="field"><label class="field__label">Courriel</label><input class="input" name="email" value="<?= h($org['email']) ?>"></div>
    <div class="field"><label class="field__label">Site</label><input class="input" name="site" value="<?= h($org['site']) ?>"></div>
    <div class="field" style="grid-column:1/-1"><label class="field__label">Slogan</label><input class="input" name="slogan" value="<?= h($org['slogan']) ?>"></div>
    <div class="field"><label class="field__label">Frais d'inscription</label><input class="input" name="frais_inscription" value="<?= (int) $org['frais_inscription'] ?>"></div>
    <div class="field"><label class="field__label">Devise</label><select class="input" name="devise">
      <option value="HTG" <?= $org['devise']==='HTG'?'selected':'' ?>>HTG — gourde haïtienne</option>
      <option value="USD" <?= $org['devise']==='USD'?'selected':'' ?>>USD — dollar américain</option>
    </select></div>
  </div>
  <div class="cluster" style="justify-content:flex-end;margin-top:16px"><button class="btn btn--primary" type="submit">Enregistrer</button></div>
</div>
</form>

<?php elseif ($tab === 'regles'): ?>
<form method="post"><input type="hidden" name="action" value="regles">
<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Règles métier</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Éligibilité (mois)</label><input class="input" type="number" name="eligibilite" value="<?= (int) $regles['eligibilite_mois'] ?>"></div>
        <div class="field"><label class="field__label">Retard (jours)</label><input class="input" type="number" name="retard" value="<?= (int) $regles['retard_jours'] ?>"></div>
        <div class="field"><label class="field__label">Suspension (jours)</label><input class="input" type="number" name="suspension" value="<?= (int) $regles['suspension_jours'] ?>"></div>
        <div class="field"><label class="field__label">Échéance (jour du mois)</label><input class="input" type="number" min="1" max="28" name="echeance" value="<?= (int) $regles['echeance_jour'] ?>"></div>
      </div>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Notifications administrateur</div>
      <div class="stack stack--sm">
        <label class="checkbox"><input type="checkbox" name="notif_adhesion" value="1" <?= $notif['adhesion']?'checked':'' ?>> Nouvelle adhésion</label>
        <label class="checkbox"><input type="checkbox" name="notif_retard" value="1" <?= $notif['retard']?'checked':'' ?>> Retard de paiement</label>
        <label class="checkbox"><input type="checkbox" name="notif_eligibilite" value="1" <?= $notif['eligibilite']?'checked':'' ?>> Éligibilité atteinte</label>
        <label class="checkbox"><input type="checkbox" name="notif_reclamation" value="1" <?= $notif['reclamation']?'checked':'' ?>> Nouvelle réclamation</label>
      </div>
    </div>
  </div>
  <div class="form-col form-col--side">
    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:10px">Effets des règles</div>
      <div class="stack stack--sm" style="font-size:12px;color:var(--text-muted)">
        <div class="cluster"><?= icon('shield-check',14) ?> Éligible après <?= (int) $regles['eligibilite_mois'] ?> mois de cotisation ininterrompue.</div>
        <div class="cluster"><?= icon('triangle-alert',14) ?> En retard à <?= (int) $regles['retard_jours'] ?> jours, suspendue à <?= (int) $regles['suspension_jours'] ?> jours.</div>
        <div class="cluster"><?= icon('calendar-clock',14) ?> Échéance mensuelle le <?= (int) $regles['echeance_jour'] ?>.</div>
        <div class="cluster"><?= icon('bell',14) ?> <?= $notifCount ?> notification(s) active(s) sur 4.</div>
      </div>
    </div>
  </div>
</div>
<div class="cluster" style="justify-content:flex-end;margin-top:8px">
  <button class="btn btn--ghost" type="submit" form="reglesReset">Réinitialiser les règles</button>
  <button class="btn btn--primary" type="submit"><?= icon('shield-check',16) ?> Appliquer</button>
</div>
</form>
<form id="reglesReset" method="post" style="display:none"><input type="hidden" name="action" value="regles_reset"></form>

<?php elseif ($tab === 'users'): ?>
<div class="card card--flush">
  <div class="card__head"><div><div class="card__title">Utilisateurs</div><div class="card__subtitle"><?= count($users) ?> comptes</div></div>
    <div class="card__actions"><a class="btn btn--primary btn--sm" href="utilisateur_nouveau.php"><?= icon('user-plus',16) ?> Nouvel utilisateur</a></div>
  </div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Département</th><th>Zone</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><div class="person-cell"><span class="person-cell__name"><?= h($u['prenom'] . ' ' . $u['nom']) ?></span><span class="person-cell__meta"><?= h($u['login']) ?></span></div></td>
      <td><span class="badge badge--<?= $u['role_nom']==='Administrateur'?'brand':'neutral' ?>"><?= h($u['role_nom']) ?></span></td>
      <td><?= h($u['dept_nom'] ?? '—') ?></td>
      <td><?= h($u['zone_collecte'] ?? '—') ?></td>
      <td><?= badge_html($u['statut']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card card--flush">
  <div class="card__head"><div class="card__title">Couverture territoriale</div></div>
  <div class="table-wrap"><table class="data-table is-dense"><thead><tr><th>Département</th><th>Responsable</th><th>Zones de collecte</th><th class="is-numeric">Agents</th></tr></thead><tbody>
    <?php foreach ($couvStmt as $c): ?><tr><td><?= h($c['dept']) ?></td><td><?= h($c['responsable'] ?? '—') ?></td><td><?= h($c['zones'] ?? '—') ?></td><td class="is-numeric is-mono"><?= (int) $c['agents'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<div class="card">
  <div class="card__title" style="margin-bottom:14px">Sécurité</div>
  <form method="post"><input type="hidden" name="action" value="securite">
    <div class="stack stack--sm">
      <label class="checkbox"><input type="checkbox" name="double_auth" value="1" <?= $securite['double_auth']?'checked':'' ?> onchange="this.form.submit()"> Authentification à deux facteurs pour les administrateurs</label>
      <label class="checkbox"><input type="checkbox" name="journal_actif" value="1" <?= $securite['journal_actif']?'checked':'' ?> onchange="this.form.submit()"> Journal d'audit actif</label>
      <label class="checkbox"><input type="checkbox" name="acces_public" value="1" <?= $securite['acces_public']?'checked':'' ?> onchange="this.form.submit()"> Autoriser l'accès public en lecture seule</label>
    </div>
  </form>
</div>

<?php elseif ($tab === 'donnees'): ?>
<div class="card">
  <div class="card__title" style="margin-bottom:10px">Sauvegarde</div>
  <div class="text-muted" style="font-size:13px;margin-bottom:14px"><?= count($plans) ?> plans · <?= count($formules) ?> formules · <?= count($users) ?> comptes · règles et informations administratives</div>
  <a class="btn btn--primary btn--sm" href="pages/backup_download.php"><?= icon('download',16) ?> Télécharger la sauvegarde</a>
</div>
<div class="card">
  <div class="card__title" style="margin-bottom:14px">Exports disponibles</div>
  <div class="stack stack--sm">
    <?php
    $exports = [
      ['icon' => 'users', 'nom' => 'Assurés et polices', 'type' => 'assures'],
      ['icon' => 'credit-card', 'nom' => 'Journal des encaissements', 'type' => 'paiements'],
      ['icon' => 'folder-open', 'nom' => 'Dossiers funéraires', 'type' => 'reclamations'],
      ['icon' => 'shield-check', 'nom' => "Journal d'audit", 'type' => 'audit'],
      ['icon' => 'file-text', 'nom' => 'Plans et formules', 'type' => 'plans'],
      ['icon' => 'settings', 'nom' => 'Comptes utilisateurs', 'type' => 'utilisateurs'],
    ];
    foreach ($exports as $ex): ?>
    <div class="cluster" style="justify-content:space-between;padding-bottom:10px;border-bottom:1px solid var(--border-subtle)">
      <div class="cluster"><?= icon($ex['icon'],16) ?> <?= h($ex['nom']) ?></div>
      <a class="btn btn--ghost btn--sm" href="pages/export_csv.php?type=<?= $ex['type'] ?>"><?= icon('download',14) ?> Exporter</a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<div class="card">
  <div class="card__title" style="margin-bottom:10px">Conservation des données</div>
  <div class="stack stack--sm" style="font-size:12px;color:var(--text-muted)">
    <div class="cluster"><?= icon('calendar-clock',14) ?> Sauvegarde automatique chaque nuit, conservée 90 jours.</div>
    <div class="cluster"><?= icon('shield-check',14) ?> Journal d'audit conservé 24 mois, non modifiable.</div>
    <div class="cluster"><?= icon('folder-open',14) ?> Dossiers funéraires archivés 10 ans après le versement du capital.</div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
