<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('assures');

$pdo = db();
$type = ($_GET['type'] ?? 'individuel') === 'famille' ? 'famille' : 'individuel';
$org = organisation();
$devise = $org['devise'] ?? 'HTG';
$r = regles();

$plans = $pdo->query('SELECT * FROM plans WHERE actif=1 ORDER BY ordre')->fetchAll();
$formuleCode = $type === 'famille' ? 'collectif' : 'individuel';
$formule = $pdo->prepare('SELECT * FROM formules WHERE code=?');
$formule->execute([$formuleCode]);
$formule = $formule->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optional = ['sexe', 'date_naissance', 'lieu_naissance', 'etat_civil', 'nif', 'profession', 'personnes_charge',
        'telephone2', 'email', 'section_communale', 'adresse', 'temoin1_nom', 'temoin1_telephone', 'temoin2_nom',
        'temoin2_telephone', 'mode_paiement', 'moncash_numero', 'zone_collecte', 'chef_famille', 'situation_foyer',
        'personne_urgence', 'telephone_urgence', 'lieu_inhumation', 'caveau'];
    $p = array_merge(array_fill_keys($optional, ''), $_POST);
    $prenom = trim($p['prenom'] ?? '');
    $nom = trim($p['nom'] ?? '');
    $telephone = trim($p['telephone'] ?? '');
    $planId = (int) ($p['plan_id'] ?? 0);
    $deptNom = trim($p['departement'] ?? '');
    $communeNom = trim($p['commune'] ?? '');

    if ($prenom === '') $errors[] = 'Le prénom est obligatoire.';
    if ($nom === '') $errors[] = 'Le nom est obligatoire.';
    if ($telephone === '') $errors[] = 'Le téléphone est obligatoire.';
    if (!$planId) $errors[] = 'Le plan tarifaire est obligatoire.';
    if ($deptNom === '' || $communeNom === '') $errors[] = 'Le département et la commune sont obligatoires.';

    if (!$errors) {
        $plan = $pdo->prepare('SELECT * FROM plans WHERE id=?');
        $plan->execute([$planId]);
        $plan = $plan->fetch();

        $deptStmt = $pdo->prepare('SELECT id FROM departements WHERE nom=?');
        $deptStmt->execute([$deptNom]);
        $deptId = $deptStmt->fetchColumn() ?: null;
        $communeStmt = $pdo->prepare('SELECT id FROM communes WHERE nom=? AND departement_id=?');
        $communeStmt->execute([$communeNom, $deptId]);
        $communeId = $communeStmt->fetchColumn() ?: null;

        $photoPath = null;
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fname = 'a-' . uniqid() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/assets/uploads/assures/' . $fname);
            $photoPath = 'assets/uploads/assures/' . $fname;
        }

        $reference = next_reference('assures', 'reference', 'A', 4);

        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO assures (reference, prenom, nom, sexe, date_naissance, lieu_naissance, etat_civil, nif, profession, personnes_charge, telephone, telephone2, email, departement_id, commune_id, section_communale, adresse, temoin1_nom, temoin1_telephone, temoin2_nom, temoin2_telephone, photo_path, agent_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $reference, $prenom, $nom, $p['sexe'] ?: null, $p['date_naissance'] ?: null, $p['lieu_naissance'] ?: null,
                $p['etat_civil'] ?: null, $p['nif'] ?: null, $p['profession'] ?: null, (int) ($p['personnes_charge'] ?? 0),
                $telephone, $p['telephone2'] ?: null, $p['email'] ?: null, $deptId, $communeId, $p['section_communale'] ?: null,
                $p['adresse'] ?: null, $p['temoin1_nom'] ?: null, $p['temoin1_telephone'] ?: null, $p['temoin2_nom'] ?: null,
                $p['temoin2_telephone'] ?: null, $photoPath, current_user()['id'],
            ]);
        $assureId = (int) $pdo->lastInsertId();

        $numero = next_police_number();
        $pdo->prepare('INSERT INTO polices (numero, assure_id, formule_id, plan_id, prime, capital, frais_inscription, mode_paiement, moncash_numero, zone_collecte, date_adhesion, chef_famille, situation_foyer, personne_urgence, telephone_urgence, lieu_inhumation_prevu, caveau, statut) VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),?,?,?,?,?,?,?)')
            ->execute([
                $numero, $assureId, $formule['id'], $planId, $plan['prime'], $plan['capital'], $org['frais_inscription'] ?? 1000,
                $p['mode_paiement'] ?? 'moncash', $p['moncash_numero'] ?: null, $p['zone_collecte'] ?: null,
                $type === 'famille' ? trim($p['chef_famille'] ?? ($prenom . ' ' . $nom)) : null,
                $type === 'famille' ? ($p['situation_foyer'] ?? null) : null,
                $type === 'famille' ? ($p['personne_urgence'] ?? null) : null,
                $type === 'famille' ? ($p['telephone_urgence'] ?? null) : null,
                $type === 'famille' ? ($p['lieu_inhumation'] ?? null) : null,
                $type === 'famille' ? ($p['caveau'] ?? null) : null,
                'En attente',
            ]);
        $policeId = (int) $pdo->lastInsertId();

        if ($type === 'famille' && !empty($p['membre_nom'])) {
            $stmt = $pdo->prepare('INSERT INTO police_membres (police_id, nom, lien_parente, date_naissance, part_pct, statut) VALUES (?,?,?,?,?,?)');
            foreach ($p['membre_nom'] as $i => $mn) {
                if (trim($mn) === '') continue;
                $stmt->execute([$policeId, trim($mn), $p['membre_lien'][$i] ?? 'Autre parent', $p['membre_naissance'][$i] ?: null, (float) ($p['membre_part'][$i] ?? 0), 'Active']);
            }
        }

        if (!empty($p['benef_nom'])) {
            $stmt = $pdo->prepare('INSERT INTO police_beneficiaires (police_id, nom, lien_parente, date_naissance, telephone, part_pct) VALUES (?,?,?,?,?,?)');
            foreach ($p['benef_nom'] as $i => $bn) {
                if (trim($bn) === '') continue;
                $stmt->execute([$policeId, trim($bn), $p['benef_lien'][$i] ?? null, $p['benef_naissance'][$i] ?: null, $p['benef_tel'][$i] ?: null, (float) ($p['benef_part'][$i] ?? 0)]);
            }
        }

        $pieceTypes = ["Photo d'identité", "Acte de naissance", "Carte d'identification nationale", "NIF", "Preuve d'adresse", "Certificat médical"];
        $stmt = $pdo->prepare('INSERT INTO police_pieces (police_id, nom, recu) VALUES (?,?,?)');
        foreach ($pieceTypes as $i => $nom2) {
            $stmt->execute([$policeId, $nom2, !empty($p['piece'][$i]) ? 1 : 0]);
        }

        $pdo->commit();
        log_audit('Adhésion', $reference, ($type === 'famille' ? 'Plan collectif familial' : 'Plan individuel') . ' · police ' . $numero);
        flash('success', 'Assuré enregistré', "Référence $reference · police $numero prête.");
        redirect('assure_fiche.php?numero=' . urlencode($numero));
    }
}

$page_title = $type === 'famille' ? 'Adhésion — plan familial' : 'Adhésion — plan individuel';
$active_nav = 'assures';
require __DIR__ . '/includes/layout_start.php';

$haiti = haiti_communes_map();
$pieceTypes = ["Photo d'identité", "Acte de naissance", "Carte d'identification nationale", "NIF", "Preuve d'adresse", "Certificat médical"];
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Assurés</span><span><?= $type === 'famille' ? 'Plan familial' : 'Plan individuel' ?></span></div>
    <h1 class="page-header__title"><?= h($page_title) ?></h1>
    <div class="page-header__meta"><?= $type === 'famille' ? 'Une police, plusieurs membres couverts · prime unique' : 'Une police, un seul assuré · déclaration de santé requise' ?></div>
  </div>
</div>

<?php if ($errors): ?>
<div class="login-error"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__title" style="margin-bottom:14px"><?= $type === 'famille' ? "Identité du chef de famille" : "Identité de l'assuré" ?></div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Prénom <span class="req">*</span></label><input class="input" name="prenom" placeholder="Roseline" required></div>
        <div class="field"><label class="field__label">Nom <span class="req">*</span></label><input class="input" name="nom" placeholder="Pierre-Louis" required></div>
        <div class="field"><label class="field__label">Date de naissance</label><input class="input" type="date" name="date_naissance"><div class="field__hint">L'âge est calculé automatiquement</div></div>
        <div class="field"><label class="field__label">NIF</label><input class="input" name="nif" placeholder="000-000-000-0000"><div class="field__hint">Au moins 14 chiffres</div></div>
        <div class="field"><label class="field__label">Sexe</label><select class="input" name="sexe"><option value="">Sélectionner</option><option value="f">Féminin</option><option value="m">Masculin</option></select></div>
        <div class="field"><label class="field__label">État civil</label><select class="input" name="etat_civil">
          <option value="">Sélectionner</option><option value="celibataire">Célibataire</option><option value="marie">Marié(e)</option>
          <option value="placage">En plaçage</option><option value="veuf">Veuf / veuve</option><option value="divorce">Divorcé(e)</option>
        </select></div>
        <div class="field"><label class="field__label">Lieu de naissance</label><input class="input" name="lieu_naissance" placeholder="Camp-Perrin"></div>
        <div class="field"><label class="field__label">Témoin 1 — nom <span class="req">*</span></label><input class="input" name="temoin1_nom" placeholder="Jean-Claude Pierre-Louis" required></div>
        <div class="field"><label class="field__label">Témoin 1 — téléphone <span class="req">*</span></label><input class="input" name="temoin1_telephone" placeholder="+509 0000 0000" required></div>
        <div class="field"><label class="field__label">Témoin 2 — nom <span class="req">*</span></label><input class="input" name="temoin2_nom" placeholder="Micheline Dorvil" required></div>
        <div class="field"><label class="field__label">Témoin 2 — téléphone <span class="req">*</span></label><input class="input" name="temoin2_telephone" placeholder="+509 0000 0000" required></div>
        <div class="field"><label class="field__label">Profession</label><input class="input" name="profession" placeholder="Commerçante"></div>
        <div class="field"><label class="field__label">Personnes à charge</label><input class="input" type="number" min="0" name="personnes_charge" value="0"></div>
        <div class="field"><label class="field__label">Téléphone <span class="req">*</span></label><input class="input" name="telephone" placeholder="+509 0000 0000" required></div>
        <div class="field"><label class="field__label">Deuxième téléphone</label><input class="input" name="telephone2" placeholder="+509 0000 0000"></div>
        <div class="field"><label class="field__label">Courriel</label><input class="input" type="email" name="email" placeholder="nom@exemple.ht"></div>
        <div class="field"><label class="field__label">Département <span class="req">*</span></label>
          <select class="input" id="dept" name="departement" required><option value="">Sélectionner</option><?php foreach (array_keys($haiti) as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label class="field__label">Commune <span class="req">*</span></label><select class="input" id="commune" name="commune" required><option value="">Sélectionner</option></select></div>
        <div class="field"><label class="field__label">Section communale</label><input class="input" name="section_communale" placeholder="4e section Mersan"></div>
        <div class="field"><label class="field__label">Adresse</label><input class="input" name="adresse" placeholder="12, rue Ferdinand"></div>
      </div>
    </div>

    <?php if ($type === 'famille'): ?>
    <div class="card">
      <div class="card__title" style="margin-bottom:4px">Situation familiale</div>
      <div class="card__subtitle" style="margin-bottom:14px">Renseignements propres au plan collectif familial</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Chef de famille <span class="req">*</span></label><input class="input" name="chef_famille" placeholder="Antoine Pierre-Louis" required></div>
        <div class="field"><label class="field__label">Situation du foyer</label><select class="input" name="situation_foyer">
          <option value="Marié(e)">Marié(e)</option><option value="Célibataire">Célibataire</option><option value="En plaçage">En plaçage</option><option value="Veuf / veuve">Veuf / veuve</option><option value="Divorcé(e)">Divorcé(e)</option>
        </select></div>
        <div class="field"><label class="field__label">Personne à contacter</label><input class="input" name="personne_urgence" placeholder="Kettelie Pierre-Louis"></div>
        <div class="field"><label class="field__label">Téléphone du contact</label><input class="input" name="telephone_urgence" placeholder="+509 0000 0000"></div>
        <div class="field"><label class="field__label">Lieu d'inhumation prévu</label><input class="input" name="lieu_inhumation" placeholder="Cimetière de Camp-Perrin"></div>
        <div class="field"><label class="field__label">Caveau</label><input class="input" name="caveau"></div>
      </div>

      <div style="margin-top:18px">
        <div class="card__title" style="margin-bottom:10px;font-size:14px">Membres couverts</div>
        <div class="table-wrap"><table class="data-table is-dense">
          <thead><tr><th>Membre</th><th>Lien de parenté</th><th>Naissance</th><th class="is-numeric">Part (%)</th><th></th></tr></thead>
          <tbody id="membresBody">
            <tr>
              <td><input class="input input--sm" name="membre_nom[]" placeholder="Nom du membre"></td>
              <td><select class="input input--sm" name="membre_lien[]"><?php foreach (['Souscripteur','Souscriptrice','Époux','Épouse','Conjoint','Enfant','Fils','Fille','Père','Mère','Frère','Sœur','Neveu','Nièce','Autre parent'] as $l): ?><option><?= h($l) ?></option><?php endforeach; ?></select></td>
              <td><input class="input input--sm" type="date" name="membre_naissance[]"></td>
              <td><input class="input input--sm" type="number" step="0.01" name="membre_part[]" value="0"></td>
              <td><button class="icon-btn" type="button" data-remove-row><?= icon('trash-2',14) ?></button></td>
            </tr>
          </tbody>
        </table></div>
        <button class="btn btn--secondary btn--sm" style="margin-top:10px" type="button" id="addMembre"><?= icon('user-plus',16) ?> Ajouter un membre</button>
        <div class="field__hint" style="margin-top:6px">Tout membre ajouté après la souscription exige une nouvelle entente avec la compagnie. Huit membres au maximum par police.</div>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card__title" style="margin-bottom:4px">Déclaration de santé</div>
      <div class="card__subtitle" style="margin-bottom:14px">Obligatoire sur une police individuelle</div>
      <div class="stack stack--sm">
        <label class="radio"><input type="radio" name="sante" value="aucune" checked> Aucune affection déclarée</label>
        <label class="radio"><input type="radio" name="sante" value="affection"> Affection déclarée — examen médical requis</label>
        <div class="field" style="margin-top:6px"><label class="field__label">Médecin ou institution</label><input class="input" name="medecin" placeholder="Hôpital Immaculée Conception"></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Police souscrite</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Formule de police</label><input class="input" value="<?= h($formule['nom']) ?>" disabled></div>
        <div class="field"><label class="field__label">Plan tarifaire <span class="req">*</span></label>
          <select class="input" id="planSelect" name="plan_id" required onchange="ccadUpdatePlan()">
            <option value="">Sélectionner</option>
            <?php foreach ($plans as $pl): ?><option value="<?= $pl['id'] ?>" data-prime="<?= $pl['prime'] ?>" data-capital="<?= $pl['capital'] ?>"><?= h($pl['nom']) ?> · <?= fmt_money($pl['prime'], $devise) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label class="field__label">Cotisation mensuelle</label><input class="input" id="primeDisplay" value="" disabled></div>
        <div class="field"><label class="field__label">Couverture funéraire</label><input class="input" id="capitalDisplay" value="" disabled></div>
        <div class="field"><label class="field__label">Frais d'inscription</label><input class="input" value="<?= fmt_money($org['frais_inscription'] ?? 1000, $devise) ?>" disabled></div>
        <div class="field"><label class="field__label">Date d'adhésion</label><input class="input" value="<?= date('d/m/Y') ?>" disabled></div>
        <div class="field"><label class="field__label">Mode de paiement habituel</label><select class="input" name="mode_paiement">
          <option value="moncash">MonCash</option><option value="especes">Espèces</option><option value="virement">Virement bancaire</option><option value="cheque">Chèque</option>
        </select></div>
        <div class="field"><label class="field__label">Numéro MonCash</label><input class="input" name="moncash_numero" placeholder="+509 0000 0000"></div>
        <div class="field"><label class="field__label">Zone de collecte</label><input class="input" name="zone_collecte" placeholder="Zone 2 — Mersan"></div>
      </div>
    </div>

    <div class="card card--flush">
      <div class="card__head"><div class="card__title">Bénéficiaires</div><div class="card__subtitle">Répartition de la couverture funéraire — total 100 %</div></div>
      <div class="table-wrap"><table class="data-table is-dense">
        <thead><tr><th>Bénéficiaire</th><th>Lien</th><th>Naissance</th><th>Téléphone</th><th class="is-numeric">Part (%)</th><th></th></tr></thead>
        <tbody id="benefBody">
          <tr>
            <td><input class="input input--sm" name="benef_nom[]" placeholder="Nom du bénéficiaire"></td>
            <td><input class="input input--sm" name="benef_lien[]" placeholder="Fille, époux…"></td>
            <td><input class="input input--sm" type="date" name="benef_naissance[]"></td>
            <td><input class="input input--sm" name="benef_tel[]" placeholder="+509 0000 0000"></td>
            <td><input class="input input--sm" type="number" step="0.01" name="benef_part[]" value="100"></td>
            <td><button class="icon-btn" type="button" data-remove-row><?= icon('trash-2',14) ?></button></td>
          </tr>
        </tbody>
      </table></div>
      <div style="padding:12px 20px"><button class="btn btn--secondary btn--sm" type="button" id="addBenef"><?= icon('user-plus',16) ?> Ajouter</button></div>
    </div>
  </div>

  <div class="form-col form-col--side">
    <div class="card">
      <div class="card__title" style="margin-bottom:4px">Photo d'identité</div>
      <div class="card__subtitle" style="margin-bottom:14px">Obligatoire pour enregistrer le dossier</div>
      <label class="upload-label"><input type="file" name="photo" accept="image/*" style="display:none" onchange="this.nextElementSibling.textContent=this.files[0]?this.files[0].name:'Téléverser une photo'">Téléverser une photo</label>
      <div class="field__hint" style="margin-top:6px">Portrait neutre, fond clair, sans filtre.</div>
    </div>

    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:10px">Récapitulatif de la police</div>
      <div class="kv-list">
        <div class="kv-list__row"><div class="kv-list__term">Formule</div><div class="kv-list__value"><?= h($formule['nom']) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Frais d'inscription</div><div class="kv-list__value"><?= fmt_money($org['frais_inscription'] ?? 1000, $devise) ?></div></div>
        <div class="kv-list__row"><div class="kv-list__term">Éligibilité</div><div class="kv-list__value">Après <?= (int) $r['eligibilite_mois'] ?> mois</div></div>
      </div>
    </div>

    <div class="card">
      <div class="card__title" style="margin-bottom:10px">Pièces exigées</div>
      <div class="stack stack--sm">
        <?php foreach ($pieceTypes as $i => $nom2): ?>
        <label class="checkbox"><input type="checkbox" name="piece[<?= $i ?>]" value="1"><?= h($nom2) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="cluster" style="justify-content:flex-end;margin-top:8px">
  <a class="btn btn--ghost" href="assures.php">Annuler</a>
  <button class="btn btn--primary" type="submit"><?= icon('user-plus',16) ?> Enregistrer l'adhésion</button>
</div>
</form>

<script src="assets/js/ccad.js"></script>
<script>
var HAITI = <?= json_encode($haiti, JSON_UNESCAPED_UNICODE) ?>;
ccadWireDeptCommune('dept', 'commune', HAITI);
ccadWireRepeater('membresBody', 'addMembre',
  '<tr><td><input class="input input--sm" name="membre_nom[]" placeholder="Nom du membre"></td>' +
  '<td><select class="input input--sm" name="membre_lien[]"><?php foreach (['Souscripteur','Souscriptrice','Époux','Épouse','Conjoint','Enfant','Fils','Fille','Père','Mère','Frère','Sœur','Neveu','Nièce','Autre parent'] as $l) { echo '<option>' . h($l) . '</option>'; } ?></select></td>' +
  '<td><input class="input input--sm" type="date" name="membre_naissance[]"></td>' +
  '<td><input class="input input--sm" type="number" step="0.01" name="membre_part[]" value="0"></td>' +
  '<td><button class="icon-btn" type="button" data-remove-row><?= icon("trash-2",14) ?></button></td></tr>', 8);
ccadWireRepeater('benefBody', 'addBenef',
  '<tr><td><input class="input input--sm" name="benef_nom[]" placeholder="Nom du bénéficiaire"></td>' +
  '<td><input class="input input--sm" name="benef_lien[]" placeholder="Fille, époux…"></td>' +
  '<td><input class="input input--sm" type="date" name="benef_naissance[]"></td>' +
  '<td><input class="input input--sm" name="benef_tel[]" placeholder="+509 0000 0000"></td>' +
  '<td><input class="input input--sm" type="number" step="0.01" name="benef_part[]" value="0"></td>' +
  '<td><button class="icon-btn" type="button" data-remove-row><?= icon("trash-2",14) ?></button></td></tr>', 8);
function ccadUpdatePlan() {
  var sel = document.getElementById('planSelect');
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('primeDisplay').value = opt.dataset.prime ? Number(opt.dataset.prime).toLocaleString('fr-FR') + ' <?= h($devise) ?>' : '';
  document.getElementById('capitalDisplay').value = opt.dataset.capital ? Number(opt.dataset.capital).toLocaleString('fr-FR') + ' <?= h($devise) ?>' : '';
}
</script>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
