-- =====================================================================
-- CCAD — Confiance, Compagnie d'Assurance de Décès
-- Données de démonstration — Gestion interne CCAD
-- =====================================================================
SET NAMES utf8mb4;
USE ccad_gestion;

-- Départements
INSERT INTO departements (id, nom) VALUES
(1, 'Ouest'),
(2, 'Sud'),
(3, 'Nord'),
(4, 'Artibonite'),
(5, 'Centre'),
(6, 'Grand’Anse'),
(7, 'Nippes'),
(8, 'Nord-Est'),
(9, 'Nord-Ouest'),
(10, 'Sud-Est');

-- Communes
INSERT INTO communes (departement_id, nom) VALUES
(1, 'Port-au-Prince'),
(1, 'Delmas'),
(1, 'Carrefour'),
(1, 'Pétion-Ville'),
(1, 'Tabarre'),
(1, 'Cité Soleil'),
(1, 'Kenscoff'),
(1, 'Gressier'),
(1, 'Croix-des-Bouquets'),
(1, 'Thomazeau'),
(1, 'Ganthier'),
(1, 'Fonds-Verrettes'),
(1, 'Cornillon'),
(1, 'Cabaret'),
(1, 'Arcahaie'),
(1, 'Léogâne'),
(1, 'Petit-Goâve'),
(1, 'Grand-Goâve'),
(1, 'Anse-à-Galets'),
(1, 'Pointe-à-Raquette'),
(2, 'Les Cayes'),
(2, 'Camp-Perrin'),
(2, 'Chantal'),
(2, 'Maniche'),
(2, 'Torbeck'),
(2, 'Îlet-à-Vache'),
(2, 'Cavaillon'),
(2, 'Saint-Louis-du-Sud'),
(2, 'Aquin'),
(2, 'Fonds-des-Nègres'),
(2, 'Port-Salut'),
(2, 'Saint-Jean-du-Sud'),
(2, 'Arniquet'),
(2, 'Chardonnières'),
(2, 'Les Anglais'),
(2, 'Tiburon'),
(2, 'Port-à-Piment'),
(2, 'Roche-à-Bateau'),
(2, 'Côteaux'),
(3, 'Cap-Haïtien'),
(3, 'Limonade'),
(3, 'Quartier-Morin'),
(3, 'Acul-du-Nord'),
(3, 'Milot'),
(3, 'Plaine-du-Nord'),
(3, 'Grande-Rivière-du-Nord'),
(3, 'Bahon'),
(3, 'Dondon'),
(3, 'Saint-Raphaël'),
(3, 'Pignon'),
(3, 'La Victoire'),
(3, 'Ranquitte'),
(3, 'Borgne'),
(3, 'Port-Margot'),
(3, 'Pilate'),
(3, 'Plaisance'),
(3, 'Limbé'),
(3, 'Bas-Limbé'),
(4, 'Gonaïves'),
(4, 'Saint-Marc'),
(4, 'Dessalines'),
(4, 'Grande-Saline'),
(4, 'Desdunes'),
(4, 'Petite-Rivière-de-l’Artibonite'),
(4, 'Verrettes'),
(4, 'La Chapelle'),
(4, 'Liancourt'),
(4, 'Marmelade'),
(4, 'Saint-Michel-de-l’Attalaye'),
(4, 'Gros-Morne'),
(4, 'Terre-Neuve'),
(4, 'Anse-Rouge'),
(4, 'Ennery'),
(5, 'Hinche'),
(5, 'Cerca-Carvajal'),
(5, 'Maïssade'),
(5, 'Thomonde'),
(5, 'Mirebalais'),
(5, 'Boucan-Carré'),
(5, 'Saut-d’Eau'),
(5, 'Lascahobas'),
(5, 'Belladère'),
(5, 'Savanette'),
(5, 'Cerca-la-Source'),
(5, 'Thomassique'),
(6, 'Jérémie'),
(6, 'Abricots'),
(6, 'Bonbon'),
(6, 'Moron'),
(6, 'Chambellan'),
(6, 'Dame-Marie'),
(6, 'Anse-d’Hainault'),
(6, 'Les Irois'),
(6, 'Corail'),
(6, 'Roseaux'),
(6, 'Beaumont'),
(6, 'Pestel'),
(7, 'Miragoâne'),
(7, 'Fonds-des-Nègres'),
(7, 'Paillant'),
(7, 'Petite-Rivière-de-Nippes'),
(7, 'Anse-à-Veau'),
(7, 'Arnaud'),
(7, 'L’Asile'),
(7, 'Petit-Trou-de-Nippes'),
(7, 'Plaisance-du-Sud'),
(7, 'Baradères'),
(7, 'Grand-Boucan'),
(8, 'Fort-Liberté'),
(8, 'Ferrier'),
(8, 'Perches'),
(8, 'Ouanaminthe'),
(8, 'Capotille'),
(8, 'Mont-Organisé'),
(8, 'Trou-du-Nord'),
(8, 'Sainte-Suzanne'),
(8, 'Terrier-Rouge'),
(8, 'Caracol'),
(8, 'Vallières'),
(8, 'Carice'),
(8, 'Mombin-Crochu'),
(9, 'Port-de-Paix'),
(9, 'Bassin-Bleu'),
(9, 'Chansolme'),
(9, 'Tortue'),
(9, 'Saint-Louis-du-Nord'),
(9, 'Anse-à-Foleur'),
(9, 'Jean-Rabel'),
(9, 'Môle-Saint-Nicolas'),
(9, 'Bombardopolis'),
(9, 'Baie-de-Henne'),
(10, 'Jacmel'),
(10, 'Marigot'),
(10, 'Cayes-Jacmel'),
(10, 'La Vallée'),
(10, 'Bainet'),
(10, 'Côtes-de-Fer'),
(10, 'Belle-Anse'),
(10, 'Grand-Gosier'),
(10, 'Thiotte'),
(10, 'Anse-à-Pitre');

-- Rôles
INSERT INTO roles (id, nom, description) VALUES
(1, 'Administrateur', 'Accès complet, y compris paramètres, plans et comptes utilisateurs.'),
(2, 'Administrateur départemental', 'Tous les modules, limités à son département d’affectation.'),
(3, 'Agent de gestion', 'Adhésions, fiches assurés et encaissements de sa zone.'),
(4, 'Caissier', 'Encaissements et reçus uniquement.'),
(5, 'Agent de collecte', 'Saisie des cotisations sur le terrain, sans modification de police.');

-- Permissions
INSERT INTO permissions (id, cle, nom, description) VALUES
(1, 'assures', 'Assurés', 'Consulter et enregistrer des adhésions'),
(2, 'polices', 'Polices', 'Modifier, suspendre ou résilier une police'),
(3, 'paiements', 'Paiements', 'Encaisser et éditer les reçus'),
(4, 'reclamations', 'Réclamations', 'Ouvrir et instruire les dossiers funéraires'),
(5, 'rapports', 'Rapports', 'Éditer les rapports de gestion'),
(6, 'parametres', 'Paramètres', 'Plans, règles, comptes utilisateurs');

-- Permissions par rôle
INSERT INTO role_permissions (role_id, permission_id) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(3, 1),
(3, 3),
(3, 4),
(4, 3),
(5, 3);

-- Formules
INSERT INTO formules (id, code, nom, description, actif) VALUES
(1, 'individuel', 'Police individuelle', 'Un seul assuré, cotisation mensuelle jusqu’à son décès.', 1),
(2, 'collectif', 'Plan collectif familial', 'Une police négociée pour tous les membres de la famille ; tout membre ajouté exige une nouvelle entente avec la compagnie.', 1),
(3, 'bien', 'Bien en échange conditionnel entre vif', 'Funérailles planifiées du vivant de l’intéressé en échange d’un bien.', 1),
(4, 'parent', 'CCAD, Parent analogique', 'La compagnie tient le rôle de parent pour une personne décédée non assurée, après négociation avec les proches.', 1);

-- Plans tarifaires
INSERT INTO plans (id, code, nom, prime, capital, actif, ordre) VALUES
(1, 'p1', 'Plan 1', 1000, 200000, 1, 1),
(2, 'p2', 'Plan 2', 1250, 250000, 1, 2),
(3, 'p3', 'Plan 3', 1500, 300000, 1, 3),
(4, 'p4', 'Plan 4', 1750, 325000, 1, 4),
(5, 'p5', 'Plan 5', 2000, 400000, 1, 5),
(6, 'p6', 'Plan 6', 2500, 500000, 1, 6),
(7, 'p7', 'Plan 7', 5000, 1000000, 1, 7);

-- Services funéraires
INSERT INTO services (id, nom, mode, actif) VALUES
(1, 'Cercueil', 'Vente sur commande', 1),
(2, 'Gerbe', 'Vente sur commande', 1),
(3, 'Couronne', 'Vente sur commande', 1),
(4, 'Chaise', 'Location', 1),
(5, 'Table', 'Location', 1);

-- Informations de l'organisation
INSERT INTO reglages_organisation (id, nom, nif, adresse, telephone, email, site, slogan, frais_inscription, devise) VALUES
(1, 'CCAD — Confiance, Compagnie d’Assurance de Décès', '001-234-567-8', '7, Bouette, Camp-Perrin, Les Cayes', '+509 3714 6868 / 3262 5720', 'ccad.haiti@gmail.com', 'www.ccad.ht', 'Peye antèman w kounye a pou w ka antere ak diyité', 1000, 'HTG');

-- Règles métier
INSERT INTO reglages_regles (id, eligibilite_mois, retard_jours, suspension_jours, echeance_jour) VALUES (1, 24, 30, 60, 1);

-- Notifications administrateur
INSERT INTO reglages_notifications (id, adhesion, retard, eligibilite, reclamation) VALUES (1, 1, 1, 1, 0);

-- Sécurité
INSERT INTO reglages_securite (id, double_auth, journal_actif, acces_public) VALUES (1, 1, 1, 0);

-- Utilisateurs (mot de passe initial pour tous les comptes de démonstration : CCAD-2026)
INSERT INTO utilisateurs (id, prenom, nom, login, mot_de_passe_hash, role_id, departement_id, commune_id, zone_collecte, statut) VALUES
(1, 'Nadège', 'Charles', 'n.charles', '$2y$12$Im/X9FoDVPlIYoZHugzjOe78ErBsqKD4.B6rxtF/XtbORdZ8IAE2i', 3, 2, 22, 'Zone 2 — Mersan', 'Active'),
(2, 'Jocelyn', 'Dériphonse', 'j.deriphonse', '$2y$12$Im/X9FoDVPlIYoZHugzjOe78ErBsqKD4.B6rxtF/XtbORdZ8IAE2i', 1, 2, 21, 'Siège', 'Active'),
(3, 'Sherline', 'Aubourg', 's.aubourg', '$2y$12$Im/X9FoDVPlIYoZHugzjOe78ErBsqKD4.B6rxtF/XtbORdZ8IAE2i', 4, 2, 21, 'Zone 3 — Les Cayes', 'Active'),
(4, 'Elionor', 'Baptichon', 'e.baptichon', '$2y$12$Im/X9FoDVPlIYoZHugzjOe78ErBsqKD4.B6rxtF/XtbORdZ8IAE2i', 2, 1, 1, 'Zone 5 — Delmas', 'Active'),
(5, 'Wisly', 'Toussaint', 'w.toussaint', '$2y$12$Im/X9FoDVPlIYoZHugzjOe78ErBsqKD4.B6rxtF/XtbORdZ8IAE2i', 5, 7, 98, 'Zone 8 — Miragoâne', 'Active'),
(6, 'Rodrigue', 'Pétion', 'r.petion', '$2y$12$Im/X9FoDVPlIYoZHugzjOe78ErBsqKD4.B6rxtF/XtbORdZ8IAE2i', 3, 6, 86, 'Zone 9 — Jérémie', 'Archivé');

-- Assurés (portefeuille actif)
INSERT INTO assures (id, reference, prenom, nom, sexe, date_naissance, lieu_naissance, etat_civil, nif, profession, personnes_charge, telephone, telephone2, email, departement_id, commune_id, section_communale, adresse, temoin1_nom, temoin1_telephone, temoin2_nom, temoin2_telephone, agent_id) VALUES
(1, 'A-1041', 'Marie-Lourdes', 'Jean-Baptiste', 'f', '1979-03-17', 'Camp-Perrin', 'marie', '003-456-789-0', 'Commerçante', 3, '+509 3712 4408', '+509 4408 1122', 'ml.jeanbaptiste@exemple.ht', 2, 22, '4e section Mersan', '12, rue Ferdinand, Camp-Perrin', 'Jean-Claude Pierre-Louis', '+509 3712 0088', 'Micheline Dorvil', '+509 4412 7730', 1),
(2, 'A-1088', 'Wilfrid', 'Étienne', 'm', '1975-11-02', 'Les Cayes', 'marie', '004-112-233-5', 'Chauffeur', 2, '+509 3655 9042', NULL, NULL, 2, 21, NULL, 'Rue Geffrard, Les Cayes', NULL, NULL, NULL, NULL, 3),
(3, 'A-1102', 'Roseline', 'Pierre-Louis', 'f', '1979-03-17', 'Camp-Perrin', 'marie', '003-456-100-2', 'Commerçante', 2, '+509 3712 4408', NULL, 'r.pierrelouis@exemple.ht', 2, 22, '4e section Mersan', '12, rue Ferdinand, Camp-Perrin', 'Jean-Claude Pierre-Louis', '+509 3712 0088', 'Micheline Dorvil', '+509 4412 7730', 1),
(4, 'A-1147', 'Jean-Robert', 'Casimir', 'm', '1968-06-14', 'Les Cayes', 'marie', '005-778-991-0', 'Cultivateur', 4, '+509 4822 1190', NULL, NULL, 2, 21, NULL, 'Bergeaud, Les Cayes', NULL, NULL, NULL, NULL, 3),
(5, 'A-1163', 'Yolette', 'Dorvilier', 'f', '1985-09-30', 'Maniche', 'celibataire', '006-221-440-7', 'Couturière', 1, '+509 3410 7761', NULL, NULL, 2, 24, NULL, 'Bourg de Maniche', NULL, NULL, NULL, NULL, 1),
(6, 'A-1190', 'Fritzner', 'Saint-Juste', 'm', '1972-01-08', 'Camp-Perrin', 'celibataire', '007-556-330-9', 'Maçon', 0, '+509 3714 2200', NULL, NULL, 2, 22, NULL, 'Bourg de Camp-Perrin', NULL, NULL, NULL, NULL, 1),
(7, 'A-1215', 'Mirlande', 'Toussaint', 'f', '1981-04-22', 'Cavaillon', 'marie', '008-664-221-3', 'Commerçante', 2, '+509 3655 1144', NULL, NULL, 2, 27, NULL, 'Bourg de Cavaillon', NULL, NULL, NULL, NULL, 4),
(8, 'A-1240', 'Gérard', 'Nazaire', 'm', '1965-12-05', 'Les Cayes', 'veuf', '009-887-112-4', 'Agriculteur', 3, '+509 3712 9081', NULL, NULL, 2, 21, NULL, 'Ravine du Sud, Les Cayes', NULL, NULL, NULL, NULL, 3);

-- Polices (individuelles / familiales) du portefeuille actif
INSERT INTO polices (id, numero, assure_id, formule_id, plan_id, prime, capital, frais_inscription, mode_paiement, moncash_numero, zone_collecte, date_adhesion, jour_echeance, chef_famille, situation_foyer, statut) VALUES
(1, 'P-2024-1041', 1, 2, 6, 2500, 500000, 1000, 'moncash', '+509 3712 4408', 'Zone 2 — Mersan', '2024-08-04', 1, 'Marie-Lourdes Jean-Baptiste', 'Marié(e)', 'Active'),
(2, 'P-2024-1088', 2, 1, 3, 1500, 300000, 1000, 'moncash', '+509 3655 9042', 'Zone 3 — Les Cayes', '2024-09-19', 1, NULL, NULL, 'En retard'),
(3, 'P-2024-1102', 3, 2, 5, 2000, 400000, 1000, 'moncash', '+509 3712 4408', 'Zone 2 — Mersan', '2024-10-02', 1, 'Roseline Pierre-Louis', 'Marié(e)', 'Active'),
(4, 'P-2025-1147', 4, 3, 7, 5000, 1000000, 1000, 'moncash', '+509 4822 1190', 'Zone 3 — Les Cayes', '2025-01-11', 1, NULL, NULL, 'En attente'),
(5, 'P-2025-1163', 5, 1, 1, 1000, 200000, 1000, 'moncash', '+509 3410 7761', 'Zone 2 — Mersan', '2025-02-27', 1, NULL, NULL, 'Active'),
(6, 'P-2025-1190', 6, 1, 4, 1750, 325000, 1000, 'moncash', '+509 3714 2200', 'Zone 2 — Mersan', '2025-04-15', 1, NULL, NULL, 'Suspendue'),
(7, 'P-2025-1215', 7, 2, 6, 2500, 500000, 1000, 'moncash', '+509 3655 1144', 'Zone 5 — Delmas', '2025-06-06', 1, 'Mirlande Toussaint', 'Marié(e)', 'Active'),
(8, 'P-2025-1240', 8, 4, 2, 1250, 250000, 1000, 'moncash', '+509 3712 9081', 'Zone 3 — Les Cayes', '2025-07-21', 1, NULL, NULL, 'En retard');

-- Membres couverts de la police familiale P-2024-1102
INSERT INTO police_membres (police_id, nom, lien_parente, date_naissance, part_pct, statut) VALUES
(3, 'Roseline Pierre-Louis', 'Souscriptrice', '1979-03-17', 40, 'Active'),
(3, 'Antoine Pierre-Louis', 'Époux', '1975-02-02', 30, 'Active'),
(3, 'Kettelie Pierre-Louis', 'Fille', '2002-12-08', 30, 'En attente');

-- Bénéficiaires désignés — police P-2024-1041
INSERT INTO police_beneficiaires (police_id, nom, lien_parente, date_naissance, telephone, part_pct) VALUES
(1, 'Kettelie Jean-Baptiste', 'Fille', '2002-12-08', '+509 3410 7761', 50),
(1, 'Samuel Jean-Baptiste', 'Fils', '2006-05-23', '+509 4822 1190', 30),
(1, 'Antoine Jean-Baptiste', 'Époux', '1975-02-02', '+509 3655 9042', 20);

-- Pièces exigées au dossier — jeu type appliqué à chaque police
INSERT INTO police_pieces (police_id, nom, recu) VALUES
(1, 'Photo d’identité', 1),
(1, 'Acte de naissance', 1),
(1, 'Carte d’identification nationale', 1),
(1, 'NIF', 1),
(1, 'Preuve d’adresse', 1),
(1, 'Certificat médical', 1),
(2, 'Photo d’identité', 1),
(2, 'Acte de naissance', 1),
(2, 'Carte d’identification nationale', 1),
(2, 'NIF', 1),
(2, 'Preuve d’adresse', 1),
(2, 'Certificat médical', 1),
(3, 'Photo d’identité', 1),
(3, 'Acte de naissance', 1),
(3, 'Carte d’identification nationale', 1),
(3, 'NIF', 1),
(3, 'Preuve d’adresse', 1),
(3, 'Certificat médical', 1),
(4, 'Photo d’identité', 1),
(4, 'Acte de naissance', 1),
(4, 'Carte d’identification nationale', 1),
(4, 'NIF', 0),
(4, 'Preuve d’adresse', 0),
(4, 'Certificat médical', 0),
(5, 'Photo d’identité', 1),
(5, 'Acte de naissance', 1),
(5, 'Carte d’identification nationale', 1),
(5, 'NIF', 1),
(5, 'Preuve d’adresse', 1),
(5, 'Certificat médical', 1),
(6, 'Photo d’identité', 1),
(6, 'Acte de naissance', 1),
(6, 'Carte d’identification nationale', 1),
(6, 'NIF', 1),
(6, 'Preuve d’adresse', 1),
(6, 'Certificat médical', 1),
(7, 'Photo d’identité', 1),
(7, 'Acte de naissance', 1),
(7, 'Carte d’identification nationale', 1),
(7, 'NIF', 1),
(7, 'Preuve d’adresse', 1),
(7, 'Certificat médical', 1),
(8, 'Photo d’identité', 1),
(8, 'Acte de naissance', 1),
(8, 'Carte d’identification nationale', 1),
(8, 'NIF', 1),
(8, 'Preuve d’adresse', 1),
(8, 'Certificat médical', 1);

-- Paiements (journal des encaissements)
INSERT INTO paiements (reference, police_id, montant, mode, date_paiement, statut, agent_id) VALUES
('R-3391', 3, 2000, 'moncash', '2026-09-12', 'À valider', 1),
('R-3390', 1, 2500, 'moncash', '2026-09-01', 'Encaissé', 1),
('R-3389', 7, 4000, 'virement', '2026-09-01', 'Encaissé', 4),
('R-3388', 5, 1500, 'especes', '2026-08-31', 'Encaissé', 1),
('R-3387', 8, 1000, 'moncash', '2026-08-30', 'À valider', 3),
('R-3384', 6, 1500, 'especes', '2026-08-28', 'Encaissé', 1),
('R-3301', 1, 2500, 'especes', '2026-08-02', 'Encaissé', 1),
('R-3218', 1, 2500, 'moncash', '2026-07-03', 'Encaissé', 1),
('R-3140', 1, 2500, 'virement', '2026-06-01', 'Encaissé', 1),
('R-3062', 1, 2500, 'moncash', '2026-05-04', 'À valider', 1);

-- Assurés décédés (dossiers de réclamation historiques)
INSERT INTO assures (id, reference, prenom, nom, sexe, departement_id, commune_id, agent_id) VALUES
(9, 'A-0912', 'Michelet', 'Dorsainvil', 'm', 2, 22, 2),
(10, 'A-0771', 'Anite', 'Belizaire', 'f', 2, 21, 2),
(11, 'A-0688', 'Prosper', 'Lafontant', 'm', 2, 27, 2),
(12, 'A-0533', 'Carmelle', 'Vixamar', 'f', 1, 1, 2);

-- Polices résiliées (capital décès versé ou en cours de versement)
INSERT INTO polices (id, numero, assure_id, formule_id, plan_id, prime, capital, frais_inscription, mode_paiement, zone_collecte, date_adhesion, jour_echeance, statut, resilie_le) VALUES
(9, 'P-2023-0912', 9, 1, 3, 1500, 300000, 1000, 'moncash', 'Zone 3 — Les Cayes', '2024-05-02', 1, 'Résiliée', '2026-08-29 00:00:00'),
(10, 'P-2022-0771', 10, 1, 6, 2500, 500000, 1000, 'moncash', 'Zone 3 — Les Cayes', '2023-02-28', 1, 'Résiliée', '2026-08-25 00:00:00'),
(11, 'P-2023-0688', 11, 1, 3, 1500, 300000, 1000, 'moncash', 'Zone 3 — Les Cayes', '2024-08-21', 1, 'Résiliée', '2026-08-18 00:00:00'),
(12, 'P-2021-0533', 12, 1, 7, 5000, 700000, 1000, 'moncash', 'Zone 3 — Les Cayes', '2022-01-14', 1, 'Résiliée', '2026-08-10 00:00:00');

-- Dossiers de réclamation
INSERT INTO reclamations (reference, police_id, date_deces, lieu_deces, date_declaration, declarant_nom, declarant_lien, declarant_telephone, capital, arrieres, cotisations_versees, mois_cotises, statut) VALUES
('D-0416', 9, '2026-08-29', 'Camp-Perrin, Sud', '2026-09-02', 'Lucie Dorsainvil', 'Épouse', '+509 3700 1122', 300000, 0, 84000, 28, 'À valider'),
('D-0415', 10, '2026-08-25', 'Les Cayes, Sud', '2026-08-28', 'Jean Belizaire', 'Fils', '+509 3811 4477', 500000, 5000, 126000, 42, 'En attente'),
('D-0414', 11, '2026-08-18', 'Cavaillon, Sud', '2026-08-21', 'Marise Lafontant', 'Épouse', '+509 3922 6633', 300000, 2500, 72000, 24, 'Encaissé'),
('D-0413', 12, '2026-08-10', 'Port-au-Prince, Ouest', '2026-08-14', 'Esther Vixamar', 'Sœur', '+509 4433 8899', 700000, 0, 198000, 55, 'Encaissé');

-- Bénéficiaires et versements par dossier
INSERT INTO reclamation_versements (reclamation_id, beneficiaire_nom, lien_parente, part_pct, montant, mode, statut) VALUES
(1, 'Lucie Dorsainvil', 'Épouse', 50, 149250, 'MonCash', 'À valider'),
(1, 'Gaspard Dorsainvil', 'Fils', 30, 89550, 'MonCash', 'À valider'),
(1, 'Edwine Dorsainvil', 'Fille', 20, 59700, 'Espèces', 'À valider'),
(2, 'Jean Belizaire', 'Fils', 60, 296100, 'Virement', 'En attente'),
(2, 'Marise Belizaire', 'Fille', 40, 197400, 'MonCash', 'En attente'),
(3, 'Marise Lafontant', 'Épouse', 100, 296000, 'MonCash', 'Versé'),
(4, 'Esther Vixamar', 'Sœur', 70, 488950, 'Virement', 'Versé'),
(4, 'Gérald Vixamar', 'Frère', 30, 209550, 'MonCash', 'Versé');

-- Pièces du dossier (jeu type)
INSERT INTO reclamation_pieces (reclamation_id, nom, detail, recu) VALUES
(1, 'Acte de décès', 'Délivré par l’officier d’état civil', 1),
(1, 'Certificat médical de décès', 'Signé par le médecin ou l’hôpital', 1),
(1, 'Pièce d’identité du déclarant', 'CIN ou passeport en cours de validité', 1),
(1, 'Pièces d’identité des bénéficiaires', 'Une par bénéficiaire déclaré', 0),
(1, 'Reçu de la dernière cotisation', 'Ou relevé de la police', 1),
(1, 'Attestation de lien de parenté', 'Acte de naissance ou de mariage', 0),
(2, 'Acte de décès', 'Délivré par l’officier d’état civil', 1),
(2, 'Certificat médical de décès', 'Signé par le médecin ou l’hôpital', 1),
(2, 'Pièce d’identité du déclarant', 'CIN ou passeport en cours de validité', 1),
(2, 'Pièces d’identité des bénéficiaires', 'Une par bénéficiaire déclaré', 0),
(2, 'Reçu de la dernière cotisation', 'Ou relevé de la police', 1),
(2, 'Attestation de lien de parenté', 'Acte de naissance ou de mariage', 0),
(3, 'Acte de décès', 'Délivré par l’officier d’état civil', 1),
(3, 'Certificat médical de décès', 'Signé par le médecin ou l’hôpital', 1),
(3, 'Pièce d’identité du déclarant', 'CIN ou passeport en cours de validité', 1),
(3, 'Pièces d’identité des bénéficiaires', 'Une par bénéficiaire déclaré', 1),
(3, 'Reçu de la dernière cotisation', 'Ou relevé de la police', 1),
(3, 'Attestation de lien de parenté', 'Acte de naissance ou de mariage', 1),
(4, 'Acte de décès', 'Délivré par l’officier d’état civil', 1),
(4, 'Certificat médical de décès', 'Signé par le médecin ou l’hôpital', 1),
(4, 'Pièce d’identité du déclarant', 'CIN ou passeport en cours de validité', 1),
(4, 'Pièces d’identité des bénéficiaires', 'Une par bénéficiaire déclaré', 1),
(4, 'Reçu de la dernière cotisation', 'Ou relevé de la police', 1),
(4, 'Attestation de lien de parenté', 'Acte de naissance ou de mariage', 1);

-- Journal d'audit
INSERT INTO audit_log (horodatage, utilisateur_id, zone, type_operation, cible, detail) VALUES
('2026-09-12 08:32:00', 3, 'Zone 3 — Les Cayes', 'Encaissement', 'R-3391', '2 500 HTG · MonCash · police P-2024-1102'),
('2026-09-12 08:19:00', 1, 'Zone 2 — Mersan', 'Consultation', 'A-1041', 'Fiche assuré ouverte'),
('2026-09-12 08:14:00', 1, 'Zone 2 — Mersan', 'Adhésion', 'A-1263', 'Plan collectif familial · 3 membres'),
('2026-09-12 07:58:00', 2, 'Siège', 'Réclamation', 'D-0416', 'Dossier ouvert · pièces incomplètes'),
('2026-09-12 07:41:00', NULL, 'Système', 'Suspension', 'P-2025-1190', '60 jours sans versement'),
('2026-09-11 16:22:00', 2, 'Siège', 'Paramètres', 'Plan 5', 'Couverture portée à 500 000 HTG'),
('2026-09-11 15:10:00', 4, 'Zone 5 — Delmas', 'Modification', 'A-1147', 'Numéro MonCash corrigé'),
('2026-09-11 11:47:00', 5, 'Zone 8 — Miragoâne', 'Encaissement', 'R-3384', '1 500 HTG · espèces'),
('2026-09-11 09:05:00', 4, 'Zone 5 — Delmas', 'Consultation', 'A-1215', 'Historique des paiements'),
('2026-09-10 17:31:00', 2, 'Siège', 'Paramètres', 'Utilisateurs', 'Compte w.toussaint créé');

