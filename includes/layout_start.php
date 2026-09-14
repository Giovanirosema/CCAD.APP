<?php
/**
 * Ossature commune de toutes les pages authentifiées : <head>, barre latérale, barre supérieure.
 * Variables attendues avant inclusion : $page_title (string), $active_nav (string, valeur de navItems).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$page_title = $page_title ?? APP_NAME;
$active_nav = $active_nav ?? '';

$navBadgeAssures = (int) db()->query("SELECT COUNT(*) FROM polices WHERE statut='En attente'")->fetchColumn();
$navBadgePaiements = (int) db()->query("SELECT COUNT(*) FROM paiements WHERE statut='À valider'")->fetchColumn();

function nav_item(string $route, string $href, string $label, string $iconName, ?string $perm, string $active, ?int $badge = null): void
{
    if ($perm !== null && !has_permission($perm)) {
        return;
    }
    $cls = 'sidenav__item' . ($active === $route ? ' is-active' : '');
    echo '<li><a class="' . $cls . '" href="' . h($href) . '">' . icon($iconName) . '<span class="sidenav__item-label">' . h($label) . '</span>';
    if ($badge) {
        echo '<span class="sidenav__badge">' . (int) $badge . '</span>';
    }
    echo '</a></li>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> — CCAD</title>
<link rel="icon" href="assets/img/ccad-logo.jpg">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <nav class="sidenav" id="sidenav">
    <div class="sidenav__brand">
      <img src="assets/img/ccad-logo.jpg" alt="Sceau CCAD">
      <div>
        <div class="sidenav__brand-name"><?= h(APP_NAME) ?></div>
        <div class="sidenav__brand-sub"><?= h(APP_SUBTITLE) ?></div>
      </div>
    </div>

    <div class="sidenav__section">Gestion</div>
    <ul class="sidenav__list">
      <?php
        nav_item('dashboard', 'dashboard.php', 'Tableau de bord', 'chart-column', null, $active_nav);
        nav_item('assures', 'assures.php', 'Assurés', 'users', 'assures', $active_nav, $navBadgeAssures ?: null);
        nav_item('polices', 'polices.php', 'Polices', 'file-text', 'polices', $active_nav);
        nav_item('paiements', 'paiements.php', 'Paiements', 'credit-card', 'paiements', $active_nav, $navBadgePaiements ?: null);
        nav_item('collecte', 'collecte.php', 'Collecte du jour', 'calendar-clock', 'paiements', $active_nav);
        nav_item('relances', 'relances.php', 'Relances', 'bell', 'paiements', $active_nav);
        nav_item('reclamations', 'reclamations.php', 'Réclamations', 'folder-open', 'reclamations', $active_nav);
      ?>
    </ul>

    <div class="sidenav__section">Administration</div>
    <ul class="sidenav__list">
      <?php
        nav_item('rapports', 'rapports.php', 'Rapports', 'chart-column', 'rapports', $active_nav);
        nav_item('supervision', 'supervision.php', 'Supervision', 'chart-column', 'rapports', $active_nav);
        nav_item('audit', 'audit.php', 'Journal d’audit', 'shield-check', 'rapports', $active_nav);
        nav_item('parametres', 'parametres.php', 'Paramètres', 'settings', 'parametres', $active_nav);
      ?>
    </ul>

    <div class="sidenav__footer">
      <form action="logout.php" method="post">
        <button class="sidenav__logout" type="submit"><?= icon('log-out') ?> Se déconnecter</button>
      </form>
    </div>
  </nav>

  <div class="main">
    <header class="topbar no-print">
      <button class="icon-btn" style="display:none" id="navToggle" aria-label="Menu"><?= icon('chevron-right') ?></button>
      <form class="topbar__search" action="assures.php" method="get">
        <?= icon('search') ?>
        <input type="search" name="q" placeholder="Rechercher un assuré, une police, un reçu…" value="<?= h($_GET['q'] ?? '') ?>">
      </form>
      <div class="topbar__spacer"></div>
      <button class="icon-btn topbar__icon-btn" type="button" title="Notifications"><?= icon('bell') ?></button>
      <div class="topbar__user">
        <div class="topbar__avatar"><?= h(mb_substr($user['prenom'] ?? $user['nom'], 0, 1) . mb_substr($user['nom'], 0, 1)) ?></div>
        <div class="topbar__user-meta">
          <div class="topbar__user-name"><?= h($user['nom_complet']) ?></div>
          <div class="topbar__user-role"><?= h($user['role']) ?></div>
        </div>
      </div>
    </header>
    <div class="content">
