<?php
/** Page autonome affichée lorsqu'un utilisateur n'a pas la permission requise. */
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Accès refusé — CCAD</title>
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="login-shell">
  <div class="card" style="max-width:440px;text-align:center">
    <h2 style="margin-bottom:8px">Accès refusé</h2>
    <p class="text-muted">Votre rôle ne dispose pas de cette permission. Contactez un administrateur si vous pensez qu'il s'agit d'une erreur.</p>
    <p style="margin-top:16px"><a class="btn btn--primary" href="../dashboard.php">Retour au tableau de bord</a></p>
  </div>
</div>
</body>
</html>
