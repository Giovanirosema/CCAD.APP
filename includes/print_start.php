<?php
/**
 * Ossature commune des documents imprimables (fiches, reçus, rapports).
 * Variables attendues : $doc_title, $doc_ref, $narrow (bool, optionnel).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();
$org = organisation();
$narrow = $narrow ?? false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($doc_title ?? 'Document') ?> — CCAD</title>
<link rel="icon" href="../../assets/img/ccad-logo.jpg">
<link rel="stylesheet" href="../../assets/css/tokens.css">
<link rel="stylesheet" href="../../assets/css/print.css">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
  <a class="btn btn--secondary btn--sm" href="javascript:history.back()">Fermer</a>
  <button class="btn btn--primary btn--sm" type="button" onclick="window.print()">Imprimer</button>
</div>
<div class="print-sheet<?= $narrow ? ' print-sheet--narrow' : '' ?>">
  <div class="print-head">
    <img src="../../assets/img/ccad-logo.jpg" alt="Sceau CCAD">
    <div>
      <div class="print-head__org"><?= h($org['nom'] ?? 'CCAD') ?></div>
      <div class="print-head__sub"><?= h($org['adresse'] ?? '') ?><?php if (!empty($org['telephone'])): ?> · <?= h($org['telephone']) ?><?php endif; ?></div>
    </div>
    <div class="print-head__doc">
      <div class="print-head__doc-title"><?= h($doc_title ?? '') ?></div>
      <?php if (!empty($doc_ref)): ?><div class="print-head__doc-ref"><?= h($doc_ref) ?> · <?= date('d/m/Y') ?></div><?php endif; ?>
    </div>
  </div>
