<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('parametres');

$pdo = db();
$data = [
    'version' => 'CCAD-2.4.1',
    'date' => date('d/m/Y H:i'),
    'organisation' => $pdo->query('SELECT * FROM reglages_organisation WHERE id=1')->fetch(),
    'regles' => $pdo->query('SELECT * FROM reglages_regles WHERE id=1')->fetch(),
    'notifications' => $pdo->query('SELECT * FROM reglages_notifications WHERE id=1')->fetch(),
    'securite' => $pdo->query('SELECT * FROM reglages_securite WHERE id=1')->fetch(),
    'plans' => $pdo->query('SELECT code, nom, prime, capital, actif FROM plans ORDER BY ordre')->fetchAll(),
    'formules' => $pdo->query('SELECT code, nom, description, actif FROM formules')->fetchAll(),
    'services' => $pdo->query('SELECT nom, mode, actif FROM services')->fetchAll(),
    'utilisateurs' => $pdo->query("SELECT u.prenom, u.nom, u.login, r.nom AS role, u.statut FROM utilisateurs u JOIN roles r ON r.id=u.role_id")->fetchAll(),
];

log_audit('Paramètres', 'Sauvegarde', 'Sauvegarde des paramètres téléchargée');

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="ccad-sauvegarde-' . date('dmY') . '.json"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
