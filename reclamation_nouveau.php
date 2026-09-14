<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('reclamations');

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$eligMois = (int) $r['eligibilite_mois'];

$polices = $pdo->query("
  SELECT p.id, p.numero, p.capital, p.assure_id, a.prenom, a.nom,
         TIMESTAMPDIFF(MONTH, p.date_adhesion, CURDATE()) AS mois
  FROM polices p JOIN assures a ON a.id=p.assure_id WHERE p.statut != 'Résiliée' ORDER BY a.nom
")->fetchAll();
$services = $pdo->query('SELECT * FROM services WHERE actif=1')->fetchAll();
$piecesType = [
    ["Acte de décès", "Délivré par l'officier d'état civil"],
    ["Certificat médical de décès", "Signé par le médecin ou l'hôpital"],
    ["Pièce d'identité du déclarant", "CIN ou passeport en cours de validité"],
    ["Pièces d'identité des bénéficiaires", "Une par bénéficiaire déclaré"],
    ["Reçu de la dernière cotisation", "Ou relevé de la police"],
    ["Attestation de lien de parenté", "Acte de naissance ou de mariage"],
];

$selectedNumero = $_GET['numero'] ?? ($polices[0]['numero'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    $policeId = (int) $p['police_id'];
    $dateDeces = $p['date_deces'] ?? '';
    if (!$policeId) $errors[] = 'L\'assuré décédé est obligatoire.';
    if ($dateDeces === '') $errors[] = 'La date du décès est obligatoire.';
    if (trim($p['declarant_nom'] ?? '') === '') $errors[] = 'Le nom du déclarant est obligatoire.';

    if (!$errors) {
        $pol = $pdo->prepare('SELECT p.*, TIMESTAMPDIFF(MONTH, p.date_adhesion, CURDATE()) mois FROM polices p WHERE id=?');
        $pol->execute([$policeId]);
        $pol = $pol->fetch();

        $cotisations = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE police_id=? AND statut='Encaissé'");
        $cotisations->execute([$policeId]);
        $cotisations = (float) $cotisations->fetchColumn();

        $arrieres = $pol['statut'] === 'En retard' ? $pol['prime'] * 2 : 0;
        $ref = next_reference('reclamations', 'reference', 'D', 4);

        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO reclamations (reference, police_id, date_deces, lieu_deces, cause, numero_acte_deces, medecin_declarant, date_declaration, declarant_nom, declarant_lien, declarant_telephone, declarant_cin, temoin1_nom, temoin1_telephone, temoin2_nom, temoin2_telephone, capital, arrieres, cotisations_versees, mois_cotises, statut) VALUES (?,?,?,?,?,?,?,CURDATE(),?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $ref, $policeId, $dateDeces, $p['lieu_deces'] ?: null, $p['cause'] ?: null, $p['numero_acte'] ?: null,
                $p['medecin'] ?: null, trim($p['declarant_nom']), $p['declarant_lien'] ?: null, $p['declarant_telephone'] ?: null,
                $p['declarant_cin'] ?: null, $p['temoin1_nom'] ?: null, $p['temoin1_telephone'] ?: null,
                $p['temoin2_nom'] ?: null, $p['temoin2_telephone'] ?: null, $pol['capital'], $arrieres, $cotisations,
                (int) $pol['mois'], 'En attente',
            ]);
        $reclId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO reclamation_pieces (reclamation_id, nom, detail, recu) VALUES (?,?,?,?)');
        foreach ($piecesType as $i => [$nom, $detail]) {
            $stmt->execute([$reclId, $nom, $detail, !empty($p['piece'][$i]) ? 1 : 0]);
        }
        if (!empty($p['service'])) {
            $stmt = $pdo->prepare('INSERT INTO reclamation_services (reclamation_id, service_id) VALUES (?,?)');
            foreach ((array) $p['service'] as $sid) { $stmt->execute([$reclId, (int) $sid]); }
        }
        $pdo->commit();

        log_audit('Réclamation', $ref, 'Dossier ouvert · pièces incomplètes');
        flash('success', 'Dossier ouvert', "Dossier $ref · à valider par l'administrateur.");
        redirect('reclamation_detail.php?ref=' . urlencode($ref));
    }
}

$page_title = 'Ouvrir un dossier de réclamation';
$active_nav = 'reclamations';
require __DIR__ . '/includes/layout_start.php';

$selPolice = null;
foreach ($polices as $pl) { if ($pl['numero'] === $selectedNumero) { $selPolice = $pl; break; } }
$selPolice = $selPolice ?: ($polices[0] ?? null);
$eligible = $selPolice && $selPolice['mois'] >= $eligMois;
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Réclamations</span><span>Nouveau dossier</span></div>
    <h1 class="page-header__title">Ouvrir un dossier de réclamation</h1>
    <div class="page-header__meta">Déclaration de décès · <?= date('d/m/Y') ?></div>
  </div>
  <div class="page-header__actions"><a class="btn btn--ghost" href="reclamations.php">Annuler</a></div>
</div>

<?php if ($errors): ?><div class="login-error"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

<form method="post">
<div class="form-row">
  <div class="form-col">
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Défunt et police</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Assuré décédé <span class="req">*</span></label>
          <select class="input" name="police_id" id="policeSelect" required onchange="ccadUpdateCapital()">
            <?php foreach ($polices as $pl): ?><option value="<?= $pl['id'] ?>" data-capital="<?= $pl['capital'] ?>" data-mois="<?= $pl['mois'] ?>" <?= $pl['numero']===$selectedNumero?'selected':'' ?>><?= h($pl['prenom'] . ' ' . $pl['nom']) ?> · <?= h($pl['numero']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label class="field__label">Date du décès <span class="req">*</span></label><input class="input" type="date" name="date_deces" required></div>
        <div class="field"><label class="field__label">Lieu du décès</label><input class="input" name="lieu_deces" placeholder="Hôpital, domicile, commune"></div>
        <div class="field"><label class="field__label">Cause déclarée</label><select class="input" name="cause">
          <option value="">Sélectionner</option><option value="maladie">Maladie</option><option value="accident">Accident</option>
          <option value="naturelle">Cause naturelle</option><option value="autre">Autre — à préciser</option>
        </select></div>
        <div class="field"><label class="field__label">Nº acte de décès</label><input class="input" name="numero_acte" placeholder="000-0000"></div>
        <div class="field"><label class="field__label">Médecin ou officier déclarant</label><input class="input" name="medecin" placeholder="Dr. …"></div>
      </div>
    </div>

    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Déclarant et témoins</div>
      <div class="form-grid">
        <div class="field"><label class="field__label">Déclarant — nom <span class="req">*</span></label><input class="input" name="declarant_nom" required></div>
        <div class="field"><label class="field__label">Lien avec le défunt</label><input class="input" name="declarant_lien" placeholder="Fille, époux…"></div>
        <div class="field"><label class="field__label">Déclarant — téléphone <span class="req">*</span></label><input class="input" name="declarant_telephone" required></div>
        <div class="field"><label class="field__label">Déclarant — CIN</label><input class="input" name="declarant_cin"></div>
        <div class="field"><label class="field__label">Témoin 1 — nom <span class="req">*</span></label><input class="input" name="temoin1_nom" required></div>
        <div class="field"><label class="field__label">Témoin 1 — téléphone</label><input class="input" name="temoin1_telephone"></div>
        <div class="field"><label class="field__label">Témoin 2 — nom <span class="req">*</span></label><input class="input" name="temoin2_nom" required></div>
        <div class="field"><label class="field__label">Témoin 2 — téléphone</label><input class="input" name="temoin2_telephone"></div>
      </div>
    </div>

    <div class="card">
      <div class="card__title" style="margin-bottom:4px">Services funéraires demandés</div>
      <div class="card__subtitle" style="margin-bottom:14px">Selon les services activés dans les paramètres</div>
      <div class="stack stack--sm">
        <?php foreach ($services as $s): ?>
        <label class="checkbox"><input type="checkbox" name="service[]" value="<?= $s['id'] ?>"><?= h($s['nom']) ?> <span class="text-faint">(<?= h($s['mode']) ?>)</span></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="form-col form-col--side">
    <div class="card card--brand">
      <div class="card__title" style="margin-bottom:10px">Capital à verser</div>
      <div id="capitalDisplay" style="font-family:var(--font-numeric);font-size:26px;font-weight:600;color:var(--navy-700)"><?= fmt_money($selPolice['capital'] ?? 0, $devise) ?></div>
      <span class="badge badge--<?= $eligible ? 'success' : 'warning' ?>" id="eligBadge"><?= icon('shield-check',12) ?> <?= $eligible ? 'Éligible' : 'Sous délai de carence' ?></span>
      <div class="text-muted" style="font-size:12px;margin-top:8px" id="eligNote"><?= $eligible ? "Délai de $eligMois mois atteint — capital versable aux bénéficiaires." : 'Décision de l\'administrateur requise (mois cotisés insuffisants).' ?></div>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:10px">Pièces du dossier</div>
      <div class="stack stack--sm">
        <?php foreach ($piecesType as $i => [$nom, $detail]): ?>
        <label class="checkbox"><input type="checkbox" name="piece[<?= $i ?>]" value="1"><?= h($nom) ?><span class="checkbox__desc"><?= h($detail) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:8px">Confidentialité</div>
      <div class="text-muted" style="font-size:12px">Données sensibles : cause du décès, certificat médical et pièces d'identité ne sont visibles que des rôles Administrateur et Agent de gestion. Toute consultation est journalisée.</div>
    </div>
  </div>
</div>

<div class="cluster" style="justify-content:flex-end;margin-top:8px">
  <a class="btn btn--ghost" href="reclamations.php">Annuler</a>
  <button class="btn btn--primary" type="submit"><?= icon('folder-open',16) ?> Ouvrir le dossier</button>
</div>
</form>

<script>
function ccadUpdateCapital() {
  var sel = document.getElementById('policeSelect');
  var opt = sel.options[sel.selectedIndex];
  var capital = Number(opt.dataset.capital || 0);
  var mois = Number(opt.dataset.mois || 0);
  document.getElementById('capitalDisplay').textContent = capital.toLocaleString('fr-FR') + ' <?= h($devise) ?>';
  var eligible = mois >= <?= $eligMois ?>;
  var badge = document.getElementById('eligBadge');
  badge.className = 'badge badge--' + (eligible ? 'success' : 'warning');
  document.getElementById('eligNote').textContent = eligible ? "Délai de <?= $eligMois ?> mois atteint — capital versable aux bénéficiaires." : "Décision de l'administrateur requise (mois cotisés insuffisants).";
}
</script>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
