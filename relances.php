<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('paiements');

$pdo = db();
$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$org = organisation();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['numeros'])) {
    $numeros = (array) $_POST['numeros'];
    $canal = $_POST['canal'] ?? 'SMS';
    $modele = $_POST['modele'] ?? 'Premier rappel';
    $count = 0;
    foreach ($numeros as $numero) {
        $stmt = $pdo->prepare('SELECT id FROM polices WHERE numero=?');
        $stmt->execute([$numero]);
        $polId = $stmt->fetchColumn();
        if (!$polId) continue;
        $pdo->prepare('INSERT INTO relances (police_id, canal, modele, agent_id) VALUES (?,?,?,?)')->execute([$polId, $canal, $modele, $user['id']]);
        $count++;
    }
    log_audit('Modification', 'Relances', "$count relance(s) $canal envoyée(s) · modèle « $modele »");
    flash('success', 'Relances envoyées', "$count message(s) $canal émis · modèle « $modele ».");
    redirect('relances.php');
}

$page_title = 'Relances';
$active_nav = 'relances';
require __DIR__ . '/includes/layout_start.php';

$rows = $pdo->query("
  SELECT p.numero, a.prenom, a.nom, a.telephone, p.prime, p.statut,
         GREATEST(1, DATEDIFF(CURDATE(), COALESCE((SELECT MAX(date_paiement) FROM paiements WHERE police_id=p.id), p.date_adhesion)) - 30) AS jours
  FROM polices p JOIN assures a ON a.id=p.assure_id
  WHERE p.statut IN ('En retard','Suspendue') ORDER BY jours DESC
")->fetchAll();
$totalDu = 0;
foreach ($rows as $row) { $totalDu += (float) $row['prime'] * max(1, intdiv((int) $row['jours'], 30) + 1); }

$modeles = [
    'Premier rappel' => "CCAD — Bonjour [Nom]. Votre cotisation de [Montant] {$devise} pour la police [Police] est en attente depuis [Retard] jours. Vous pouvez régler par MonCash au " . ($org['telephone'] ?? '') . " ou auprès de votre agent de zone.",
    'Rappel ferme' => "CCAD — [Nom], la police [Police] accuse [Retard] jours de retard. Sans règlement de [Montant] {$devise} avant le " . (int) $r['echeance_jour'] . " du mois prochain, la couverture sera signalée en retard au dossier.",
    'Avis de suspension' => "CCAD — [Nom], faute de règlement, la police [Police] sera suspendue après " . (int) $r['suspension_jours'] . " jours de retard. Régularisez [Montant] {$devise} pour maintenir la couverture funéraire.",
];

$historique = $pdo->query("
  SELECT re.*, u.prenom up, u.nom un, p.numero FROM relances re LEFT JOIN utilisateurs u ON u.id=re.agent_id JOIN polices p ON p.id=re.police_id
  ORDER BY re.envoye_le DESC LIMIT 8
")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-header__breadcrumbs"><span>CCAD</span><span>Relances</span></div>
    <h1 class="page-header__title">Relances</h1>
    <div class="page-header__meta"><?= count($rows) ?> polices en retard · seuil <?= (int) $r['retard_jours'] ?> jours · suspension <?= (int) $r['suspension_jours'] ?> jours</div>
  </div>
</div>

<form method="post">
<div class="form-row">
  <div class="form-col">
    <div class="card card--flush">
      <div class="card__head">
        <div class="card__title">Polices concernées</div>
        <div class="card__actions"><button class="btn btn--ghost btn--sm" type="button" onclick="document.querySelectorAll('.relChk').forEach(c=>c.checked=!c.checked)">Tout sélectionner</button></div>
      </div>
      <div class="table-wrap">
        <table class="data-table is-dense">
          <thead><tr><th></th><th>Assuré</th><th>Téléphone</th><th class="is-numeric">Retard</th><th class="is-numeric">Montant dû</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $row): $du = (float) $row['prime'] * max(1, intdiv((int) $row['jours'], 30) + 1); ?>
            <tr>
              <td><input class="relChk" type="checkbox" name="numeros[]" value="<?= h($row['numero']) ?>"></td>
              <td><div class="person-cell"><span class="person-cell__name"><?= h($row['prenom'] . ' ' . $row['nom']) ?></span><span class="person-cell__meta"><?= h($row['numero']) ?></span></div></td>
              <td class="is-mono"><?= h($row['telephone'] ?? '—') ?></td>
              <td class="is-numeric is-mono"><?= (int) $row['jours'] ?> j</td>
              <td class="is-numeric is-mono"><?= fmt_money($du, $devise) ?></td>
              <td><?= badge_html($row['statut']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><?= icon('bell',28) ?><div class="empty-state__title">Aucune police en retard</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Historique des relances</div>
      <div class="stack stack--sm">
        <?php foreach ($historique as $h2): ?>
        <div class="cluster" style="justify-content:space-between;padding-bottom:10px;border-bottom:1px solid var(--border-subtle)">
          <div style="font-size:12px"><span class="mono text-faint"><?= fmt_date_fr($h2['envoye_le']) ?></span> — <?= h($h2['modele']) ?> · <?= h($h2['numero']) ?></div>
          <div class="text-faint" style="font-size:11px"><?= h($h2['canal']) ?> · <?= h($h2['up'] ? $h2['up'].' '.$h2['un'] : '—') ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$historique): ?><div class="text-faint" style="font-size:13px">Aucune relance envoyée pour le moment.</div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="form-col form-col--side">
    <div class="card">
      <div class="card__title" style="margin-bottom:14px">Message de relance</div>
      <div class="field"><label class="field__label">Canal</label>
        <select class="input" name="canal">
          <option value="SMS">SMS</option><option value="MonCash">Notification MonCash</option><option value="Appel">Appel de l'agent</option>
        </select>
      </div>
      <div class="field" style="margin-top:12px"><label class="field__label">Modèle</label>
        <select class="input" name="modele" id="modeleSelect" onchange="document.getElementById('apercu').textContent=window.MODELES[this.value]">
          <?php foreach (array_keys($modeles) as $m): ?><option><?= h($m) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="text-muted" id="apercu" style="font-size:12px;margin-top:10px;padding:10px;background:var(--surface-sunken);border-radius:var(--radius-sm)"><?= h($modeles['Premier rappel']) ?></div>
      <button class="btn btn--primary btn--full" style="margin-top:14px" type="submit"><?= icon('bell',16) ?> Envoyer les relances</button>
    </div>
    <div class="card">
      <div class="card__title" style="margin-bottom:10px">Règles de relance</div>
      <div class="stack stack--sm" style="font-size:12px;color:var(--text-muted)">
        <div class="cluster"><?= icon('calendar-clock',14) ?> Premier rappel automatique <?= (int) $r['retard_jours'] ?> jours après l'échéance.</div>
        <div class="cluster"><?= icon('triangle-alert',14) ?> Rappel ferme à <?= round(((int) $r['retard_jours'] + (int) $r['suspension_jours']) / 2) ?> jours, avis de suspension à <?= (int) $r['suspension_jours'] ?> jours.</div>
      </div>
    </div>
  </div>
</div>
</form>

<script>window.MODELES = <?= json_encode($modeles, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
