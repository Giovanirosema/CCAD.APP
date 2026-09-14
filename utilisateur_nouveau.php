<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('parametres');

$pdo = db();
$roles = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$perms = $pdo->query('SELECT * FROM permissions')->fetchAll();
$rolePermsAll = $pdo->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll();
$rolePermsMap = [];
foreach ($rolePermsAll as $rp) { $rolePermsMap[$rp['role_id']][] = $rp['permission_id']; }

function slugify(string $s): string
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','î'=>'i','ï'=>'i','ô'=>'o','û'=>'u','ù'=>'u','ç'=>'c','’'=>'','\''=>'']);
    return preg_replace('/[^a-z]/', '', $s);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optional = ['telephone', 'email', 'nif', 'login', 'departement', 'commune', 'zone', 'mot_de_passe'];
    $p = array_merge(array_fill_keys($optional, ''), $_POST);
    $prenom = trim($p['prenom'] ?? '');
    $nom = trim($p['nom'] ?? '');
    $roleId = (int) ($p['role_id'] ?? 0);
    if ($prenom === '' && $nom === '') $errors[] = 'Le nom est obligatoire.';
    if (!$roleId) $errors[] = 'Le rôle est obligatoire.';

    $login = trim($p['login'] ?? '') ?: (($prenom !== '' ? substr(slugify($prenom), 0, 1) . '.' : '') . slugify($nom));
    if ($login === '') $errors[] = 'Impossible de déterminer un identifiant de connexion.';
    if (!$errors) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE login=?');
        $exists->execute([$login]);
        if ($exists->fetchColumn() > 0) $errors[] = "L'identifiant $login est déjà utilisé.";
    }

    if (!$errors) {
        $deptId = null; $communeId = null;
        if (!empty($p['departement'])) {
            $d = $pdo->prepare('SELECT id FROM departements WHERE nom=?'); $d->execute([$p['departement']]); $deptId = $d->fetchColumn() ?: null;
            if ($deptId && !empty($p['commune'])) {
                $c = $pdo->prepare('SELECT id FROM communes WHERE nom=? AND departement_id=?'); $c->execute([$p['commune'], $deptId]); $communeId = $c->fetchColumn() ?: null;
            }
        }
        $hash = password_hash($p['mot_de_passe'] ?: 'CCAD-2026', PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO utilisateurs (prenom, nom, login, mot_de_passe_hash, telephone, email, nif, role_id, departement_id, commune_id, zone_collecte, double_auth, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$prenom, $nom, $login, $hash, $p['telephone'] ?: null, $p['email'] ?: null, $p['nif'] ?: null, $roleId, $deptId, $communeId, $p['zone'] ?: null, !empty($p['double_auth']) ? 1 : 0, 'Active']);
        $nom_complet = trim("$prenom $nom");
        log_audit('Paramètres', 'Utilisateurs', "Compte $login créé — $nom_complet · " . ($roles[array_search($roleId, array_column($roles, 'id'))]['nom'] ?? ''));
        flash('success', 'Utilisateur enregistré', "$nom_complet · identifiant $login. Identifiants transmis par SMS.");
        redirect('parametres.php?tab=users');
    }
}

$page_title = 'Nouvel utilisateur';
$active_nav = 'parametres';
require __DIR__ . '/includes/layout_start.php';

$haiti = haiti_communes_map();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Paramètres</span><span>Nouvel utilisateur</span></div>
    <h1 class="page-header__title">Nouvel utilisateur</h1>
  </div>
  <div class="page-header__actions"><a class="btn btn--ghost" href="parametres.php?tab=users">Annuler</a></div>
</div>

<?php if ($errors): ?><div class="login-error"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

<form method="post">
<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Identité</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Prénom</label><input class="input" name="prenom"></div>
        <div class="field"><label class="field__label">Nom <span class="req">*</span></label><input class="input" name="nom" required></div>
        <div class="field"><label class="field__label">Téléphone</label><input class="input" name="telephone" placeholder="+509 0000 0000"></div>
        <div class="field"><label class="field__label">Courriel</label><input class="input" type="email" name="email"></div>
        <div class="field"><label class="field__label">NIF</label><input class="input" name="nif"></div>
        <div class="field"><label class="field__label">Identifiant de connexion</label><input class="input" name="login" placeholder="Proposé automatiquement"></div>
      </div>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Affectation</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Département</label><select class="input" id="dept" name="departement"><option value="">Sélectionner</option><?php foreach (array_keys($haiti) as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="field__label">Commune</label><select class="input" id="commune" name="commune"><option value="">Sélectionner</option></select></div>
        <div class="field"><label class="field__label">Zone de collecte</label><input class="input" name="zone" placeholder="Zone 2 — Mersan"></div>
        <div class="field"><label class="field__label">Rôle <span class="req">*</span></label>
          <select class="input" id="roleSelect" name="role_id" required onchange="ccadUpdatePerms()">
            <option value="">Sélectionner</option>
            <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" data-hint="<?= h($r['description']) ?>"><?= h($r['nom']) ?></option><?php endforeach; ?>
          </select>
          <div class="field__hint" id="roleHint"></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Sécurité</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Mot de passe initial</label><input class="input" name="mot_de_passe" value="CCAD-2026"></div>
      </div>
      <label class="checkbox" style="margin-top:12px"><input type="checkbox" name="double_auth" value="1" checked> Authentification à deux facteurs</label>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:4px">Permissions</div>
      <div class="card__subtitle" style="margin-bottom:14px">Pré-remplies selon le rôle — ajustables au besoin</div>
      <div class="stack stack--sm" id="permsList">
        <?php foreach ($perms as $pm): ?>
        <label class="checkbox"><input type="checkbox" class="permChk" data-key="<?= $pm['id'] ?>" name="perm_display[]" disabled><?= h($pm['nom']) ?><span class="checkbox__desc"><?= h($pm['description']) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field__hint" style="margin-top:8px">Les permissions sont déterminées par le rôle choisi. Pour un accès personnalisé, créez un rôle dédié.</div>
    </div>
  </div>
  <div class="form-col form-col--side">
    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:10px">Récapitulatif</div>
      <div class="kv-list" id="recapList">
        <div class="kv-list__row"><div class="kv-list__term">Identifiant</div><div class="kv-list__value">—</div></div>
      </div>
    </div>
  </div>
</div>
<div class="cluster" style="justify-content:flex-end;margin-top:8px">
  <a class="btn btn--ghost" href="parametres.php?tab=users">Annuler</a>
  <button class="btn btn--primary" type="submit"><?= icon('user-plus',16) ?> Créer le compte</button>
</div>
</form>

<script src="assets/js/ccad.js"></script>
<script>
var HAITI = <?= json_encode($haiti, JSON_UNESCAPED_UNICODE) ?>;
ccadWireDeptCommune('dept', 'commune', HAITI);
var ROLE_PERMS = <?= json_encode($rolePermsMap) ?>;
function ccadUpdatePerms() {
  var sel = document.getElementById('roleSelect');
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('roleHint').textContent = opt.dataset.hint || '';
  var allowed = ROLE_PERMS[sel.value] || [];
  document.querySelectorAll('.permChk').forEach(function (c) {
    c.checked = allowed.indexOf(Number(c.dataset.key)) >= 0;
  });
}
</script>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
