# CCAD — Gestion Interne

Application interne de gestion pour **CCAD** (Confiance, Compagnie d'Assurance de Décès), une compagnie d'assurance funéraire/mutuelle basée à Camp-Perrin, Haïti. Générée à partir de la maquette Claude Design « Gestion Interne CCAD ».

Modules : tableau de bord, assurés et polices (individuelles/familiales), paiements et collecte, relances, réclamations (dossiers funéraires), rapports, supervision par département, journal d'audit, et paramètres administratifs (plans, formules, règles, utilisateurs/rôles).

## Stack

- **PHP 8+** (aucun framework, PDO pour l'accès aux données)
- **MySQL 8 / MariaDB 10.4+**
- HTML/CSS servi côté serveur, JavaScript vanilla minimal (listes en cascade département/commune, tableaux répétables, bascule d'onglets)

## Installation

1. Créer la base de données et les tables :
   ```bash
   mysql -u root -p < sql/schema.sql
   mysql -u root -p < sql/seed.sql
   ```
2. Configurer la connexion (variables d'environnement, valeurs par défaut dans `config/config.php`) :
   ```bash
   export CCAD_DB_HOST=127.0.0.1
   export CCAD_DB_NAME=ccad_gestion
   export CCAD_DB_USER=ccad_app
   export CCAD_DB_PASS=change-me
   ```
3. Démarrer un serveur PHP (dev) :
   ```bash
   php -S 127.0.0.1:8000
   ```
4. Se connecter sur `http://127.0.0.1:8000/login.php`.

### Comptes de démonstration

Tous les comptes créés par `sql/seed.sql` utilisent le mot de passe **`CCAD-2026`**.

| Identifiant     | Rôle                          |
|-----------------|-------------------------------|
| j.deriphonse    | Administrateur                |
| e.baptichon     | Administrateur départemental  |
| n.charles       | Agent de gestion              |
| s.aubourg       | Caissier                      |
| w.toussaint     | Agent de collecte             |

**Changer ce mot de passe avant tout déploiement réel.**

## Structure

```
config/           Connexion PDO et configuration
includes/         Authentification, RBAC, helpers, ossature de page (sidebar/topbar)
assets/css/       Jetons de conception + feuilles de style (application et impression)
assets/js/        Comportements client (listes en cascade, tableaux répétables)
pages/print/      Documents imprimables (fiche d'inscription, reçu, dossier, rapport)
pages/            Export CSV, sauvegarde JSON, page 403
sql/              schema.sql (structure) et seed.sql (données de démonstration)
*.php (racine)    Une page par écran (dashboard, assures, polices, paiements, …)
```

## Sécurité et contrôle d'accès

- Authentification par session PHP, mots de passe hachés avec `password_hash()` (bcrypt).
- Permissions par rôle stockées en base (`roles`, `permissions`, `role_permissions`) et vérifiées via `has_permission()` / `require_permission()` avant chaque action sensible.
- Toute opération significative (adhésion, encaissement, réclamation, modification de paramètres) est tracée dans `audit_log`.
- Les requêtes utilisent systématiquement des requêtes préparées PDO.

Avant un déploiement en production : configurer HTTPS, revoir les identifiants de démonstration, définir des variables d'environnement dédiées pour les identifiants de base de données, et restreindre l'accès au dossier `assets/uploads/`.
