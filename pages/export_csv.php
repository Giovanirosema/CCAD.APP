<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();
$type = $_GET['type'] ?? '';

$exports = [
    'assures' => [
        'sql' => "SELECT a.reference, a.prenom, a.nom, p.numero, f.nom AS formule, pl.nom AS plan, p.prime, com.nom AS commune, p.date_adhesion, p.statut
                  FROM assures a JOIN polices p ON p.assure_id=a.id JOIN plans pl ON pl.id=p.plan_id JOIN formules f ON f.id=p.formule_id
                  LEFT JOIN communes com ON com.id=a.commune_id ORDER BY a.nom",
        'cols' => ['reference' => 'Référence', 'prenom' => 'Prénom', 'nom' => 'Nom', 'numero' => 'Police', 'formule' => 'Formule', 'plan' => 'Plan', 'prime' => 'Cotisation', 'commune' => 'Commune', 'date_adhesion' => 'Adhésion', 'statut' => 'Statut'],
    ],
    'paiements' => [
        'sql' => "SELECT pa.reference, a.prenom, a.nom, p.numero, pa.date_paiement, pa.montant, pa.mode, pa.statut
                  FROM paiements pa JOIN polices p ON p.id=pa.police_id JOIN assures a ON a.id=p.assure_id ORDER BY pa.date_paiement DESC",
        'cols' => ['reference' => 'Reçu', 'prenom' => 'Prénom', 'nom' => 'Nom', 'numero' => 'Police', 'date_paiement' => 'Date', 'montant' => 'Montant', 'mode' => 'Mode', 'statut' => 'Statut'],
    ],
    'polices' => [
        'sql' => "SELECT p.numero, a.prenom, a.nom, pl.nom AS plan, p.prime, p.statut, dep.nom AS departement
                  FROM polices p JOIN assures a ON a.id=p.assure_id JOIN plans pl ON pl.id=p.plan_id LEFT JOIN departements dep ON dep.id=a.departement_id
                  ORDER BY a.nom",
        'cols' => ['numero' => 'Police', 'prenom' => 'Prénom', 'nom' => 'Nom', 'plan' => 'Plan', 'prime' => 'Cotisation', 'statut' => 'Statut', 'departement' => 'Département'],
    ],
    'reclamations' => [
        'sql' => "SELECT rc.reference, a.prenom, a.nom, p.numero, rc.date_deces, rc.date_declaration, rc.capital, rc.arrieres, rc.statut
                  FROM reclamations rc JOIN polices p ON p.id=rc.police_id JOIN assures a ON a.id=p.assure_id ORDER BY rc.date_declaration DESC",
        'cols' => ['reference' => 'Dossier', 'prenom' => 'Prénom', 'nom' => 'Nom', 'numero' => 'Police', 'date_deces' => 'Décès', 'date_declaration' => 'Déclaration', 'capital' => 'Capital', 'arrieres' => 'Arriérés', 'statut' => 'Statut'],
    ],
    'audit' => [
        'sql' => "SELECT al.horodatage, CONCAT(u.prenom,' ',u.nom) AS utilisateur, al.zone, al.type_operation, al.cible, al.detail
                  FROM audit_log al LEFT JOIN utilisateurs u ON u.id=al.utilisateur_id ORDER BY al.horodatage DESC LIMIT 2000",
        'cols' => ['horodatage' => 'Horodatage', 'utilisateur' => 'Utilisateur', 'zone' => 'Zone', 'type_operation' => 'Opération', 'cible' => 'Objet', 'detail' => 'Détail'],
    ],
    'plans' => [
        'sql' => 'SELECT nom, prime, capital, actif FROM plans ORDER BY ordre',
        'cols' => ['nom' => 'Plan', 'prime' => 'Cotisation', 'capital' => 'Couverture', 'actif' => 'Disponible'],
    ],
    'utilisateurs' => [
        'sql' => "SELECT CONCAT(u.prenom,' ',u.nom) AS nom, u.login, r.nom AS role, d.nom AS departement, c.nom AS commune, u.zone_collecte, u.statut
                  FROM utilisateurs u JOIN roles r ON r.id=u.role_id LEFT JOIN departements d ON d.id=u.departement_id LEFT JOIN communes c ON c.id=u.commune_id ORDER BY u.nom",
        'cols' => ['nom' => 'Utilisateur', 'login' => 'Identifiant', 'role' => 'Rôle', 'departement' => 'Département', 'commune' => 'Commune', 'zone_collecte' => 'Zone', 'statut' => 'Statut'],
    ],
];

if (!isset($exports[$type])) {
    http_response_code(404);
    die('Type d\'export inconnu.');
}

$rows = $pdo->query($exports[$type]['sql'])->fetchAll();
$cols = $exports[$type]['cols'];

log_audit('Paramètres', 'Export', "Export CSV — $type · " . count($rows) . ' ligne(s)');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ccad-' . $type . '-' . date('dmY') . '.csv"');
echo "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel

$out = fopen('php://output', 'w');
fputcsv($out, array_values($cols), ';');
foreach ($rows as $row) {
    $line = [];
    foreach (array_keys($cols) as $key) {
        $line[] = $row[$key] ?? '';
    }
    fputcsv($out, $line, ';');
}
fclose($out);
