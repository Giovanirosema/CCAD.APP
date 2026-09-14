<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if ($login !== '' && $password !== '' && attempt_login($login, $password)) {
        log_audit('Consultation', current_user()['login'], 'Connexion à l\'application');
        redirect('dashboard.php');
    }
    $error = 'Identifiant ou mot de passe incorrect.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — CCAD</title>
<link rel="icon" href="assets/img/ccad-logo.jpg">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="login-card__brand">
      <img src="assets/img/ccad-logo.jpg" alt="Sceau CCAD">
      <div>
        <h1 style="font-size:20px">CCAD</h1>
        <div class="text-muted" style="font-size:12px">Confiance, Compagnie d'Assurance de Décès</div>
      </div>
      <div class="login-card__slogan">« Peye antèman w kounye a pou w ka antere ak diyite »</div>
    </div>
    <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <div class="field">
        <label class="field__label" for="login">Identifiant</label>
        <input class="input" type="text" id="login" name="login" placeholder="n.charles" required autofocus>
      </div>
      <div class="field">
        <label class="field__label" for="password">Mot de passe</label>
        <input class="input" type="password" id="password" name="password" required>
      </div>
      <button class="btn btn--primary btn--full btn--lg" type="submit">Se connecter</button>
    </form>
    <p class="text-faint" style="font-size:11px;margin-top:18px;text-align:center">Accès réservé au personnel autorisé de CCAD. Toute connexion est journalisée.</p>
  </div>
</div>
</body>
</html>
