-- =====================================================================
-- CCAD — Confiance, Compagnie d'Assurance de Décès
-- Schéma de base de données — Gestion interne CCAD
-- =====================================================================
-- Moteur : MySQL 8.0+ / MariaDB 10.4+
-- Jeu de caractères : utf8mb4 (accents et caractères créoles/français)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS ccad_gestion
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ccad_gestion;

-- ---------------------------------------------------------------------
-- Référentiel géographique (départements / communes d'Haïti)
-- ---------------------------------------------------------------------
CREATE TABLE departements (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE communes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  departement_id  INT UNSIGNED NOT NULL,
  nom             VARCHAR(80) NOT NULL,
  FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_commune (departement_id, nom)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Rôles et permissions (contrôle d'accès)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(60) NOT NULL UNIQUE,
  description   VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cle           VARCHAR(40) NOT NULL UNIQUE,
  nom           VARCHAR(80) NOT NULL,
  description   VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id        INT UNSIGNED NOT NULL,
  permission_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Utilisateurs (agents, caissiers, administrateurs)
-- ---------------------------------------------------------------------
CREATE TABLE utilisateurs (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom              VARCHAR(80) DEFAULT NULL,
  nom                 VARCHAR(120) NOT NULL,
  login               VARCHAR(60) NOT NULL UNIQUE,
  mot_de_passe_hash   VARCHAR(255) NOT NULL,
  telephone           VARCHAR(30) DEFAULT NULL,
  email               VARCHAR(150) DEFAULT NULL,
  nif                 VARCHAR(30) DEFAULT NULL,
  role_id             INT UNSIGNED NOT NULL,
  departement_id      INT UNSIGNED DEFAULT NULL,
  commune_id          INT UNSIGNED DEFAULT NULL,
  zone_collecte       VARCHAR(80) DEFAULT NULL,
  portee_nationale    TINYINT(1) NOT NULL DEFAULT 0,
  double_auth         TINYINT(1) NOT NULL DEFAULT 1,
  statut              ENUM('Active','Archivé') NOT NULL DEFAULT 'Active',
  cree_le             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (departement_id) REFERENCES departements(id),
  FOREIGN KEY (commune_id) REFERENCES communes(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Catalogue produits : formules, plans tarifaires, services funéraires
-- ---------------------------------------------------------------------
CREATE TABLE formules (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(20) NOT NULL UNIQUE,
  nom           VARCHAR(120) NOT NULL,
  description   TEXT,
  actif         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE plans (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(20) NOT NULL UNIQUE,
  nom           VARCHAR(60) NOT NULL,
  prime         DECIMAL(12,2) NOT NULL,
  capital       DECIMAL(14,2) NOT NULL,
  actif         TINYINT(1) NOT NULL DEFAULT 1,
  ordre         INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE services (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(80) NOT NULL,
  mode          ENUM('Vente sur commande','Location') NOT NULL DEFAULT 'Vente sur commande',
  actif         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Assurés
-- ---------------------------------------------------------------------
CREATE TABLE assures (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference           VARCHAR(20) NOT NULL UNIQUE,
  prenom              VARCHAR(80) NOT NULL,
  nom                 VARCHAR(120) NOT NULL,
  sexe                ENUM('f','m') DEFAULT NULL,
  date_naissance      DATE DEFAULT NULL,
  lieu_naissance      VARCHAR(120) DEFAULT NULL,
  etat_civil          ENUM('celibataire','marie','placage','veuf','divorce') DEFAULT NULL,
  nif                 VARCHAR(30) DEFAULT NULL,
  profession          VARCHAR(120) DEFAULT NULL,
  personnes_charge    TINYINT UNSIGNED DEFAULT 0,
  telephone           VARCHAR(30) DEFAULT NULL,
  telephone2          VARCHAR(30) DEFAULT NULL,
  email               VARCHAR(150) DEFAULT NULL,
  departement_id      INT UNSIGNED DEFAULT NULL,
  commune_id          INT UNSIGNED DEFAULT NULL,
  section_communale   VARCHAR(120) DEFAULT NULL,
  adresse             VARCHAR(255) DEFAULT NULL,
  temoin1_nom         VARCHAR(120) DEFAULT NULL,
  temoin1_telephone   VARCHAR(30) DEFAULT NULL,
  temoin2_nom         VARCHAR(120) DEFAULT NULL,
  temoin2_telephone   VARCHAR(30) DEFAULT NULL,
  photo_path          VARCHAR(255) DEFAULT NULL,
  agent_id            INT UNSIGNED DEFAULT NULL,
  cree_le             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (departement_id) REFERENCES departements(id),
  FOREIGN KEY (commune_id) REFERENCES communes(id),
  FOREIGN KEY (agent_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Polices (individuelles ou familiales)
-- ---------------------------------------------------------------------
CREATE TABLE polices (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero                VARCHAR(20) NOT NULL UNIQUE,
  assure_id             INT UNSIGNED NOT NULL,
  formule_id            INT UNSIGNED NOT NULL,
  plan_id               INT UNSIGNED NOT NULL,
  prime                 DECIMAL(12,2) NOT NULL,
  capital               DECIMAL(14,2) NOT NULL,
  devise                ENUM('HTG','USD') NOT NULL DEFAULT 'HTG',
  frais_inscription     DECIMAL(12,2) NOT NULL DEFAULT 0,
  mode_paiement         ENUM('moncash','especes','virement','cheque') NOT NULL DEFAULT 'moncash',
  moncash_numero        VARCHAR(30) DEFAULT NULL,
  zone_collecte         VARCHAR(80) DEFAULT NULL,
  date_adhesion         DATE NOT NULL,
  jour_echeance         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  -- Champs propres à une police familiale
  chef_famille          VARCHAR(150) DEFAULT NULL,
  situation_foyer       VARCHAR(40) DEFAULT NULL,
  personne_urgence      VARCHAR(150) DEFAULT NULL,
  telephone_urgence     VARCHAR(30) DEFAULT NULL,
  lieu_inhumation_prevu VARCHAR(150) DEFAULT NULL,
  caveau                VARCHAR(150) DEFAULT NULL,
  statut                ENUM('Active','En retard','En attente','Suspendue','Résiliée') NOT NULL DEFAULT 'En attente',
  cree_le               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resilie_le            DATETIME DEFAULT NULL,
  FOREIGN KEY (assure_id) REFERENCES assures(id),
  FOREIGN KEY (formule_id) REFERENCES formules(id),
  FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB;

-- Membres couverts par une police familiale (vies couvertes)
CREATE TABLE police_membres (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  police_id       INT UNSIGNED NOT NULL,
  nom             VARCHAR(150) NOT NULL,
  lien_parente    VARCHAR(40) NOT NULL,
  date_naissance  DATE DEFAULT NULL,
  part_pct        DECIMAL(5,2) NOT NULL DEFAULT 0,
  statut          ENUM('Active','En attente') NOT NULL DEFAULT 'Active',
  FOREIGN KEY (police_id) REFERENCES polices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Bénéficiaires désignés pour le versement du capital décès
CREATE TABLE police_beneficiaires (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  police_id       INT UNSIGNED NOT NULL,
  nom             VARCHAR(150) NOT NULL,
  lien_parente    VARCHAR(60) DEFAULT NULL,
  date_naissance  DATE DEFAULT NULL,
  telephone       VARCHAR(30) DEFAULT NULL,
  part_pct        DECIMAL(5,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (police_id) REFERENCES polices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pièces exigées au dossier d'adhésion
CREATE TABLE police_pieces (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  police_id       INT UNSIGNED NOT NULL,
  nom             VARCHAR(150) NOT NULL,
  recu            TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (police_id) REFERENCES polices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Paiements (cotisations encaissées)
-- ---------------------------------------------------------------------
CREATE TABLE paiements (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference       VARCHAR(20) NOT NULL UNIQUE,
  police_id       INT UNSIGNED NOT NULL,
  montant         DECIMAL(12,2) NOT NULL,
  mode            ENUM('moncash','especes','virement','cheque') NOT NULL,
  date_paiement   DATE NOT NULL,
  statut          ENUM('Encaissé','À valider') NOT NULL DEFAULT 'À valider',
  agent_id        INT UNSIGNED DEFAULT NULL,
  cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (police_id) REFERENCES polices(id),
  FOREIGN KEY (agent_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Relances (rappels d'échéance envoyés)
-- ---------------------------------------------------------------------
CREATE TABLE relances (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  police_id       INT UNSIGNED NOT NULL,
  canal           ENUM('SMS','MonCash','Appel') NOT NULL DEFAULT 'SMS',
  modele          VARCHAR(60) NOT NULL,
  envoye_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  agent_id        INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (police_id) REFERENCES polices(id),
  FOREIGN KEY (agent_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Réclamations (dossiers funéraires — déclaration de décès)
-- ---------------------------------------------------------------------
CREATE TABLE reclamations (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference             VARCHAR(20) NOT NULL UNIQUE,
  police_id             INT UNSIGNED NOT NULL,
  date_deces            DATE NOT NULL,
  lieu_deces            VARCHAR(150) DEFAULT NULL,
  cause                 ENUM('maladie','accident','naturelle','autre') DEFAULT NULL,
  numero_acte_deces     VARCHAR(40) DEFAULT NULL,
  medecin_declarant     VARCHAR(150) DEFAULT NULL,
  date_declaration      DATE NOT NULL,
  declarant_nom         VARCHAR(150) DEFAULT NULL,
  declarant_lien        VARCHAR(60) DEFAULT NULL,
  declarant_telephone   VARCHAR(30) DEFAULT NULL,
  declarant_cin         VARCHAR(40) DEFAULT NULL,
  temoin1_nom           VARCHAR(150) DEFAULT NULL,
  temoin1_telephone     VARCHAR(30) DEFAULT NULL,
  temoin2_nom           VARCHAR(150) DEFAULT NULL,
  temoin2_telephone     VARCHAR(30) DEFAULT NULL,
  capital               DECIMAL(14,2) NOT NULL DEFAULT 0,
  arrieres              DECIMAL(12,2) NOT NULL DEFAULT 0,
  frais_dossier         DECIMAL(12,2) NOT NULL DEFAULT 1500,
  cotisations_versees   DECIMAL(14,2) NOT NULL DEFAULT 0,
  mois_cotises          INT UNSIGNED NOT NULL DEFAULT 0,
  statut                ENUM('En attente','À valider','Encaissé','Rejeté') NOT NULL DEFAULT 'En attente',
  decision_note         VARCHAR(255) DEFAULT NULL,
  cree_le               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  valide_le             DATETIME DEFAULT NULL,
  FOREIGN KEY (police_id) REFERENCES polices(id)
) ENGINE=InnoDB;

CREATE TABLE reclamation_pieces (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reclamation_id    INT UNSIGNED NOT NULL,
  nom               VARCHAR(150) NOT NULL,
  detail            VARCHAR(255) DEFAULT NULL,
  recu              TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (reclamation_id) REFERENCES reclamations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reclamation_versements (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reclamation_id    INT UNSIGNED NOT NULL,
  beneficiaire_nom  VARCHAR(150) NOT NULL,
  lien_parente      VARCHAR(60) DEFAULT NULL,
  part_pct          DECIMAL(5,2) NOT NULL DEFAULT 0,
  montant           DECIMAL(14,2) NOT NULL DEFAULT 0,
  mode              ENUM('MonCash','Espèces','Virement','Chèque') NOT NULL DEFAULT 'MonCash',
  statut            ENUM('En attente','À valider','Versé') NOT NULL DEFAULT 'En attente',
  FOREIGN KEY (reclamation_id) REFERENCES reclamations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reclamation_services (
  reclamation_id  INT UNSIGNED NOT NULL,
  service_id      INT UNSIGNED NOT NULL,
  PRIMARY KEY (reclamation_id, service_id),
  FOREIGN KEY (reclamation_id) REFERENCES reclamations(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Réglages de l'organisation (ligne unique — administration)
-- ---------------------------------------------------------------------
CREATE TABLE reglages_organisation (
  id              TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  nom             VARCHAR(200) NOT NULL,
  nif             VARCHAR(30) DEFAULT NULL,
  adresse         VARCHAR(255) DEFAULT NULL,
  telephone       VARCHAR(80) DEFAULT NULL,
  email           VARCHAR(150) DEFAULT NULL,
  site            VARCHAR(150) DEFAULT NULL,
  slogan          VARCHAR(255) DEFAULT NULL,
  frais_inscription DECIMAL(12,2) NOT NULL DEFAULT 1000,
  devise          ENUM('HTG','USD') NOT NULL DEFAULT 'HTG',
  CONSTRAINT chk_org_single_row CHECK (id = 1)
) ENGINE=InnoDB;

CREATE TABLE reglages_regles (
  id                  TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  eligibilite_mois    SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  retard_jours        SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  suspension_jours    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  echeance_jour       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT chk_regles_single_row CHECK (id = 1)
) ENGINE=InnoDB;

CREATE TABLE reglages_notifications (
  id            TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  adhesion      TINYINT(1) NOT NULL DEFAULT 1,
  retard        TINYINT(1) NOT NULL DEFAULT 1,
  eligibilite   TINYINT(1) NOT NULL DEFAULT 1,
  reclamation   TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT chk_notif_single_row CHECK (id = 1)
) ENGINE=InnoDB;

CREATE TABLE reglages_securite (
  id                TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  double_auth       TINYINT(1) NOT NULL DEFAULT 1,
  journal_actif     TINYINT(1) NOT NULL DEFAULT 1,
  acces_public      TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT chk_sec_single_row CHECK (id = 1)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Journal d'audit (traçabilité — non modifiable depuis l'application)
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  horodatage      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  utilisateur_id  INT UNSIGNED DEFAULT NULL,
  zone            VARCHAR(80) DEFAULT NULL,
  type_operation  ENUM('Consultation','Adhésion','Encaissement','Modification','Suspension','Réclamation','Paramètres') NOT NULL,
  cible           VARCHAR(60) DEFAULT NULL,
  detail          VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

CREATE INDEX idx_assures_nom ON assures (nom, prenom);
CREATE INDEX idx_polices_statut ON polices (statut);
CREATE INDEX idx_paiements_date ON paiements (date_paiement);
CREATE INDEX idx_reclamations_statut ON reclamations (statut);
CREATE INDEX idx_audit_horodatage ON audit_log (horodatage);

SET FOREIGN_KEY_CHECKS = 1;
