<?php
/**
 * Authentification et contrôle d'accès par rôle (RBAC).
 */
require_once __DIR__ . '/../config/db.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function has_permission(string $cle): bool
{
    $user = current_user();
    return $user !== null && in_array($cle, $user['permissions'], true);
}

function require_permission(string $cle): void
{
    require_login();
    if (!has_permission($cle)) {
        http_response_code(403);
        require __DIR__ . '/../pages/403.php';
        exit;
    }
}

/**
 * Tente une connexion. Retourne true en cas de succès.
 */
function attempt_login(string $login, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT u.*, r.nom AS role_nom, d.nom AS departement_nom, c.nom AS commune_nom
         FROM utilisateurs u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN departements d ON d.id = u.departement_id
         LEFT JOIN communes c ON c.id = u.commune_id
         WHERE u.login = ? AND u.statut = "Active"'
    );
    $stmt->execute([$login]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['mot_de_passe_hash'])) {
        return false;
    }

    $perms = db()->prepare(
        'SELECT p.cle FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ?'
    );
    $perms->execute([$row['role_id']]);

    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'prenom' => $row['prenom'],
        'nom' => $row['nom'],
        'nom_complet' => trim($row['prenom'] . ' ' . $row['nom']),
        'login' => $row['login'],
        'role_id' => (int) $row['role_id'],
        'role' => $row['role_nom'],
        'departement' => $row['departement_nom'],
        'commune' => $row['commune_nom'],
        'zone' => $row['zone_collecte'],
        'permissions' => $perms->fetchAll(PDO::FETCH_COLUMN),
    ];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
