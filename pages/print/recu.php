<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pdo = db();
$ref = $_GET['ref'] ?? '';
$stmt = $pdo->prepare("
    SELECT pa.*, p.numero, pl.nom AS plan_nom, a.prenom, a.nom
    FROM paiements pa JOIN polices p ON p.id=pa.police_id JOIN plans pl ON pl.id=p.plan_id JOIN assures a ON a.id=p.assure_id
    WHERE pa.reference=?
");
$stmt->execute([$ref]);
$d = $stmt->fetch();
if (!$d) { http_response_code(404); die('Reçu introuvable.'); }

$devise = organisation()['devise'] ?? 'HTG';
$r = regles();
$prochaine = date('d/m/Y', strtotime($d['date_paiement'] . ' +1 month'));

$doc_title = "Reçu d'acquittement";
$doc_ref = $d['reference'];
$narrow = true;
require __DIR__ . '/../../includes/print_start.php';
?>
<div class="print-amount"><?= fmt_money($d['montant'], $devise) ?></div>
<dl class="print-grid" style="grid-template-columns:1fr">
  <div><dt>Assuré</dt><dd><?= h($d['prenom'] . ' ' . $d['nom']) ?></dd></div>
  <div><dt>Police</dt><dd><?= h($d['numero']) ?></dd></div>
  <div><dt>Plan</dt><dd><?= h($d['plan_nom']) ?></dd></div>
  <div><dt>Date de paiement</dt><dd><?= fmt_date_fr($d['date_paiement']) ?></dd></div>
  <div><dt>Mode de paiement</dt><dd><?= h(ucfirst($d['mode'])) ?></dd></div>
  <div><dt>Statut</dt><dd><?= h($d['statut']) ?></dd></div>
  <div><dt>Prochaine échéance</dt><dd><?= $prochaine ?></dd></div>
</dl>
<div class="print-note">Cotisation acquittée. La couverture reste active jusqu'à la prochaine échéance ; un retard de <?= (int) $r['retard_jours'] ?> jours place la police en retard.</div>
<div class="print-signatures" style="grid-template-columns:1fr">
  <div class="print-signature">Signature de l'agent encaisseur</div>
</div>

<?php require __DIR__ . '/../../includes/print_end.php'; ?>
