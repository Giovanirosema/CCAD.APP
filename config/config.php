<?php
/**
 * Configuration de l'application — Gestion interne CCAD.
 * Copier ce fichier en dehors du dépôt de code en production et
 * renseigner les identifiants réels via variables d'environnement.
 *
 * Sur un poste sans accès facile aux variables d'environnement (ex. XAMPP/WampServer),
 * copier config/local.php.example vers config/local.php et y renseigner les valeurs :
 * ce fichier n'est jamais suivi par git et prend le pas sur les valeurs par défaut ci-dessous.
 */

if (file_exists(__DIR__ . '/local.php')) {
    require __DIR__ . '/local.php';
}

define('DB_HOST', getenv('CCAD_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('CCAD_DB_NAME') ?: 'ccad_gestion');
define('DB_USER', getenv('CCAD_DB_USER') ?: 'root');
define('DB_PASS', getenv('CCAD_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CCAD');
define('APP_SUBTITLE', 'Gestion interne');
define('APP_BASE_PATH', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'));

// Fuseau horaire — Haïti
date_default_timezone_set('America/Port-au-Prince');

session_name('ccad_session');
session_start();
