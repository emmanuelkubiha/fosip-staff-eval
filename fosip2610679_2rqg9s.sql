-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : jeu. 22 jan. 2026 à 10:12
-- Version du serveur : 10.11.15-MariaDB-deb12
-- Version de PHP : 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `fosip2610679_2rqg9s`
--

-- --------------------------------------------------------

--
-- Structure de la table `actions_recommandations`
--

CREATE TABLE `actions_recommandations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL COMMENT 'Référence à objectifs.id',
  `superviseur_id` int(11) NOT NULL COMMENT 'Référence à users.id (superviseur)',
  `besoins_developpement` text DEFAULT NULL COMMENT 'Détaillez les besoins de développement identifiés',
  `necessite_developpement` text DEFAULT NULL COMMENT 'Expliquez la nécessité de ce développement',
  `comment_atteindre` text DEFAULT NULL COMMENT 'Comment cet objectif sera atteint',
  `quand_atteindre` varchar(255) DEFAULT NULL COMMENT 'Quand cet objectif sera atteint',
  `autres_actions` text DEFAULT NULL COMMENT 'Toute autre action ou suivi convenu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `auto_evaluation`
--

CREATE TABLE `auto_evaluation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note` enum('non_atteint','atteint','depasse') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competences_de_gestion`
--

CREATE TABLE `competences_de_gestion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `planification_organisation` varchar(50) NOT NULL,
  `communication_verbale` varchar(50) NOT NULL,
  `communication_ecrite` varchar(50) NOT NULL,
  `respect_procedures` varchar(50) NOT NULL,
  `respect_delai` varchar(50) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `auteur_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competences_individuelles`
--

CREATE TABLE `competences_individuelles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `perseverance` varchar(50) NOT NULL,
  `qualite_de_travail` varchar(50) NOT NULL,
  `gestion_de_temps` varchar(50) NOT NULL,
  `flexibilite` varchar(50) NOT NULL,
  `auto_developpement` varchar(50) NOT NULL,
  `ponctualite` varchar(50) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `auteur_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competence_evaluation`
--

CREATE TABLE `competence_evaluation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `superviseur_id` int(11) DEFAULT NULL,
  `supervise_id` int(11) DEFAULT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `categorie` enum('individuelle','gestion','leader','profil') NOT NULL,
  `competence` varchar(100) DEFAULT NULL,
  `point_avere` tinyint(1) DEFAULT 0,
  `point_fort` tinyint(1) DEFAULT 0,
  `point_a_developper` tinyint(1) DEFAULT 0,
  `non_applicable` tinyint(1) DEFAULT 0,
  `commentaire` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `competence_evaluation`
--

INSERT INTO `competence_evaluation` (`id`, `superviseur_id`, `supervise_id`, `cycle_id`, `categorie`, `competence`, `point_avere`, `point_fort`, `point_a_developper`, `non_applicable`, `commentaire`) VALUES
(39, 2, 3, NULL, 'individuelle', 'Persévérance', 1, 0, 0, 0, NULL),
(40, 2, 3, NULL, 'individuelle', 'Qualité de travail', 1, 0, 0, 0, NULL),
(41, 2, 3, NULL, 'individuelle', 'Gestion du temps', 1, 0, 0, 0, NULL),
(42, 2, 3, NULL, 'individuelle', 'Flexibilité', 0, 1, 0, 0, NULL),
(43, 2, 3, NULL, 'individuelle', 'Auto-développement', 1, 0, 0, 0, NULL),
(44, 2, 3, NULL, 'individuelle', 'Ponctualité', 0, 1, 0, 0, NULL),
(45, 2, 3, NULL, 'gestion', 'Planification & Organisation', 0, 1, 0, 0, NULL),
(46, 2, 3, NULL, 'gestion', 'Communication verbale', 1, 0, 0, 0, NULL),
(47, 2, 3, NULL, 'gestion', 'Communication écrite', 1, 0, 0, 0, NULL),
(48, 2, 3, NULL, 'gestion', 'Respect des procédures', 1, 0, 0, 0, NULL),
(49, 2, 3, NULL, 'gestion', 'Respect des délais', 0, 0, 1, 0, NULL),
(50, 2, 3, NULL, 'leader', 'Travail en équipe', 1, 0, 0, 0, NULL),
(51, 2, 3, NULL, 'leader', 'Capacité d\'écoute', 1, 0, 0, 0, NULL),
(52, 2, 3, NULL, 'leader', 'Compassion', 1, 0, 0, 0, NULL),
(53, 2, 3, NULL, 'leader', 'Accessible', 1, 0, 0, 0, NULL),
(54, 2, 3, NULL, 'leader', 'Qualités interpersonnelles', 1, 0, 0, 0, NULL),
(55, 2, 3, NULL, 'leader', 'Compréhension des autres', 1, 0, 0, 0, NULL),
(56, 2, 3, NULL, 'profil', 'Communication digitale', 1, 0, 0, 0, NULL),
(57, 2, 3, NULL, 'profil', 'Graphisme', 1, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `competence_profile_evaluations`
--

CREATE TABLE `competence_profile_evaluations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fiche_id` int(10) UNSIGNED NOT NULL,
  `competence_id` int(10) UNSIGNED NOT NULL,
  `auteur_id` int(10) UNSIGNED NOT NULL,
  `note` enum('non_atteint','atteint','depasse') NOT NULL,
  `superviseur_profile_commentaire` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competence_votre_profil`
--

CREATE TABLE `competence_votre_profil` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `competence` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `competence_votre_profil`
--

INSERT INTO `competence_votre_profil` (`id`, `user_id`, `competence`, `updated_at`, `created_at`) VALUES
(1, 3, 'Communication digitale', '2025-11-08 02:06:33', '2025-11-08 02:06:33'),
(15, 3, 'Graphisme', '2025-11-08 03:42:35', '2025-11-08 03:42:35');

-- --------------------------------------------------------

--
-- Structure de la table `coordination_commentaires`
--

CREATE TABLE `coordination_commentaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coord_id` int(11) DEFAULT NULL,
  `supervise_id` int(11) DEFAULT NULL,
  `fiche_id` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_commentaire` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cote_des_objectifs`
--

CREATE TABLE `cote_des_objectifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `superviseur_id` int(11) NOT NULL,
  `note` tinyint(3) UNSIGNED NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evaluation_cycles`
--

CREATE TABLE `evaluation_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mois` int(11) DEFAULT NULL,
  `annee` int(11) DEFAULT NULL,
  `date_creation` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `objectifs`
--

CREATE TABLE `objectifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nom_projet` varchar(255) DEFAULT NULL,
  `poste` varchar(255) DEFAULT NULL,
  `date_commencement` date DEFAULT NULL,
  `periode` varchar(7) DEFAULT NULL,
  `superviseur_id` int(11) DEFAULT NULL,
  `statut` enum('encours','attente','evalue','termine') DEFAULT 'encours',
  `resume_reussite` text DEFAULT NULL,
  `resume_amelioration` text DEFAULT NULL,
  `resume_problemes` text DEFAULT NULL,
  `resume_competence_a_developper` text DEFAULT NULL,
  `resume_competence_a_utiliser` text DEFAULT NULL,
  `resume_soutien` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `objectifs`
--

INSERT INTO `objectifs` (`id`, `user_id`, `nom_projet`, `poste`, `date_commencement`, `periode`, `superviseur_id`, `statut`, `resume_reussite`, `resume_amelioration`, `resume_problemes`, `resume_competence_a_developper`, `resume_competence_a_utiliser`, `resume_soutien`, `created_at`, `updated_at`) VALUES
(6, 7, 'FOSIP', 'Admin et RH', '2026-01-01', '2026-01', 5, 'encours', '', '', '', '', '', '', '2026-01-15 10:47:23', '2026-01-15 10:49:27'),
(7, 8, 'fosip', 'chargé de la logistique ', '2026-01-06', '2026-01', 5, 'encours', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-21 12:15:46', '2026-01-21 12:15:46'),
(8, 13, 'FOSIP', 'STAGIAIRE', '2026-01-07', '2026-01', 9, 'encours', '', '', '', '', '', '', '2026-01-22 09:44:27', '2026-01-22 09:54:01'),
(9, 7, 'FOSIP', 'Admin et RH', '2026-01-06', '2026-01', 5, 'encours', 'Nous avons identifier les besoins en formation et développement du personnel, développer l\'esprit d\'écouter, la communication et la motivation, nous avons mis en place des actions pour maintenir la motivation des employés face au changement.', 'l\'identification de mouvement interne, \r\nles entretiens annuels', 'vu que c\'est le début de l\'année nous avions encore beaucoup de choses à clôturer ensemble, pour planifier certains domaines de l\'année', 'Un encadrement en matière informatique', 'La recherche et la rédaction', 'voir avec l\'IT', '2026-01-22 09:47:23', '2026-01-22 10:16:36'),
(10, 13, 'FOSIP', 'STAGIAIRE', '2026-01-07', '2026-01', 9, 'encours', 'J\'ai déjà une idée sur l\'organisation et sur l\'assistance humanitaire', 'appui par des formations', 'pas d\'impact', 'formation sur  la lutte contre les violences  basés sur les genres', 'la compétence sur la gestion financière', 'un appuis dans le domaine de protection et éducation', '2026-01-22 09:52:27', '2026-01-22 10:30:27');

-- --------------------------------------------------------

--
-- Structure de la table `objectifs_items`
--

CREATE TABLE `objectifs_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `ordre` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `objectifs_items`
--

INSERT INTO `objectifs_items` (`id`, `fiche_id`, `contenu`, `ordre`, `created_at`) VALUES
(11, 6, 'Assurer l\'administration', 1, '2026-01-15 10:47:23'),
(12, 6, 'assurer la gestion ressources humaines', 2, '2026-01-15 10:50:06'),
(14, 7, 'répondre aux besoins du bureau', 1, '2026-01-21 12:17:08'),
(15, 7, 'Faire preuve de bon esprit d\'équipe', 2, '2026-01-21 12:17:48'),
(16, 8, 'l\'objectif est d\'avoir une idée sur FOSIP ', 1, '2026-01-22 09:44:27'),
(17, 8, 's\'intégrer dans l\'organisation et développer une connaissance en matière d\'assistance humanitaire', 2, '2026-01-22 09:44:27'),
(18, 9, 'Pilotage de la performance et développement : Entretiens annuels, planification des compétences et préparer les potentiels mouvements internes', 1, '2026-01-22 09:47:24'),
(19, 9, 'Stratégie et anticipation : planification des recrutements, promotion des valeurs et un environnement de travail motivant et inclusif', 2, '2026-01-22 09:47:24'),
(20, 9, 'Relation avec les parties prenantes : écoute, communication et motivation.', 3, '2026-01-22 09:47:24'),
(21, 10, 'Avoir une idée sur l\'organisation', 1, '2026-01-22 09:52:27'),
(22, 10, 'Acquérir une connaissance sur l\'assistance humanitaire', 2, '2026-01-22 09:52:27');

-- --------------------------------------------------------

--
-- Structure de la table `objectifs_resumes`
--

CREATE TABLE `objectifs_resumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `reussite` text DEFAULT NULL,
  `amelioration` text DEFAULT NULL,
  `problemes` text DEFAULT NULL,
  `competence_a_developper` text DEFAULT NULL,
  `competence_a_utiliser` text DEFAULT NULL,
  `soutien` text DEFAULT NULL,
  `complet` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `qualites_de_leader`
--

CREATE TABLE `qualites_de_leader` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `travail_equipe` varchar(50) NOT NULL,
  `capacite_ecoute` varchar(50) NOT NULL,
  `compassion` varchar(50) NOT NULL,
  `abordable` varchar(50) NOT NULL,
  `qualites_interpersonnelles` varchar(50) NOT NULL,
  `comprendre_les_autres_facilement` varchar(50) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `auteur_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `suivi_actions`
--

CREATE TABLE `suivi_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `superviseur_id` int(11) DEFAULT NULL,
  `supervise_id` int(11) DEFAULT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `actions` text DEFAULT NULL,
  `recommandations` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `supervisions`
--

CREATE TABLE `supervisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `superviseur_id` int(11) NOT NULL,
  `periode` varchar(7) NOT NULL,
  `statut` enum('encours','complet') DEFAULT 'encours',
  `date_validation` date DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `post_nom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff','coordination','superviseur') NOT NULL,
  `fonction` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `superviseur_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `post_nom`, `email`, `mot_de_passe`, `role`, `fonction`, `photo`, `superviseur_id`) VALUES
(1, 'Administrateur', '-', 'admin@fosip-drc.org', '$2y$12$k/IxOMeizYqrqZMA6ne19eDChwjIkyviU1fasXQ2RAZQV29pMZCdO', 'admin', 'IT', NULL, 1),
(2, 'Espoir', 'Rusimwa', 'superviseur@fosip-drc.org', '$2y$10$8G.csLWe.1z.bSyC7.Gr5uz7uvaDSXghO2SrRmko9Y1hmekzK10sO', 'superviseur', 'LOGISTIQUE', NULL, NULL),
(3, 'EMMANUEL', 'KUBIHA', 'emmanuel.kubiha@fosip-drc.org', '$2y$10$y2tAS1ZFfdlD2.rvlrMnregqYAiAU9yrthJOauayqGy3g0/08GepK', 'staff', 'IT', 'user_3_1762772198.jpeg', 7),
(4, 'Administrateur', '-', 'admin@gmail.com', '$2y$10$y2tAS1ZFfdlD2.rvlrMnregqYAiAU9yrthJOauayqGy3g0/08GepK', 'admin', 'Admin', NULL, NULL),
(5, 'GLORIA', 'MWAGALWA', 'coordination@fosip-drc.org', '$2y$10$y2tAS1ZFfdlD2.rvlrMnregqYAiAU9yrthJOauayqGy3g0/08GepK', 'coordination', 'Coordonatrice', '69163e727fc9e_profile-pic.png', NULL),
(6, 'Merveil', 'Tshibuyi', 'merveil.tshibuyi@fosip-drc.org', '$2y$12$wPUhusZ/9Jac5LEn9.hgZun.PB/iN6bTM8TC95217yeTuv0fLZc6O', 'staff', 'Comptable', '69163dbeb2c14_IMG_3265.jpeg', 7),
(7, 'Edith', 'Kizito', 'edith.kizitho@fosip-drc.org', '$2y$12$2XT86tY/3yzmoZbtc30EkeN3T0Cplt2IWmjpZe.TUY7EYw9AgYDU6', 'superviseur', 'RH', '69163e02de540_profile-pic.png', 5),
(8, 'Espoir', 'Rusimwa', 'espoir.rusimwa@fosip-drc.org', '$2y$12$LvzZCjxxE4Dgx8NpSm/PaeeNwDsoufbUDhBISOD8HU0JqlyA6N0We', 'superviseur', 'Logistique', '69163ecee9df4_profile-pic.png', NULL),
(9, 'Daniel', 'Ziminika', 'daniel.ziminika@fosip-drc.org', '$2y$12$eQ9W88SgPQoTqptQkffEVuPWZ4QXVSAHs5pAoHwsUkQo3biJysznu', 'superviseur', 'Program M.', '69163f3170be0_profile-pic.png', 5),
(10, 'Esther', 'Mumba', 'esther.mumba@fosip-drc.org', '$2y$12$vFfEzBVCcPBqTMVFKyYYRu6ZqqTKI2dpJyipKcU13lmjdu1yaXg5m', 'staff', 'Caissière', '69163f83cd0b3_profile-pic.png', 7),
(11, 'Emmanuela', 'Zawadi', 'emmanuela.zawadi@fosip-drc.org', '$2y$12$.Sk2Ayh.PT3KcbLHoa6PD.9oiE.LKpJbnBd9MApyIcwNnvNyBNt8i', 'staff', 'Genre VBG', '69164076b89bc_IMG_3266.jpeg', 9),
(12, 'Elie', 'Kabala Ngoy', 'elie.ngoy@fosip-drc.org', '$2y$12$0pqSuNJqn3Yp90a3VMPJEOktibz7UakHYU.wB1Bvu30f/1dEqbRGS', 'staff', 'MEAL', '69164148e0171_profile-pic.png', 9),
(13, 'Marie Jeanne', 'Kulondwa', 'mariekulondwa0@gmail.com', '$2y$12$0LAi2h3XlBUOAM2faBIaZ.t/4q8Y3auDY5v0joPGVLkipcxT.Smme', 'staff', 'stagiaire', NULL, 9);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `actions_recommandations`
--
ALTER TABLE `actions_recommandations`
  ADD KEY `idx_fiche` (`fiche_id`),
  ADD KEY `idx_superviseur` (`superviseur_id`),
  ADD KEY `idx_fiche_superviseur` (`fiche_id`,`superviseur_id`);

--
-- Index pour la table `auto_evaluation`
--
ALTER TABLE `auto_evaluation`
  ADD KEY `fiche_id` (`fiche_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `competences_de_gestion`
--
ALTER TABLE `competences_de_gestion`
  ADD KEY `fiche_id` (`fiche_id`);

--
-- Index pour la table `competences_individuelles`
--
ALTER TABLE `competences_individuelles`
  ADD KEY `fiche_id` (`fiche_id`);

--
-- Index pour la table `competence_evaluation`
--
ALTER TABLE `competence_evaluation`
  ADD KEY `superviseur_id` (`superviseur_id`),
  ADD KEY `supervise_id` (`supervise_id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Index pour la table `competence_profile_evaluations`
--
ALTER TABLE `competence_profile_evaluations`
  ADD KEY `fiche_id` (`fiche_id`),
  ADD KEY `competence_id` (`competence_id`),
  ADD KEY `auteur_id` (`auteur_id`);

--
-- Index pour la table `competence_votre_profil`
--
ALTER TABLE `competence_votre_profil`
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `coordination_commentaires`
--
ALTER TABLE `coordination_commentaires`
  ADD KEY `coord_id` (`coord_id`),
  ADD KEY `supervise_id` (`supervise_id`),
  ADD KEY `fk_coordination_fiche` (`fiche_id`);

--
-- Index pour la table `cote_des_objectifs`
--
ALTER TABLE `cote_des_objectifs`
  ADD KEY `fiche_id` (`fiche_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `superviseur_id` (`superviseur_id`);

--
-- Index pour la table `evaluation_cycles`
--

--
-- Index pour la table `objectifs`
--
ALTER TABLE `objectifs`
  ADD KEY `user_id` (`user_id`),
  ADD KEY `superviseur_id` (`superviseur_id`);

--
-- Index pour la table `objectifs_items`
--
ALTER TABLE `objectifs_items`
  ADD KEY `fiche_id` (`fiche_id`);

--
-- Index pour la table `objectifs_resumes`
--
ALTER TABLE `objectifs_resumes`
  ADD KEY `fiche_id` (`fiche_id`);

--
-- Index pour la table `qualites_de_leader`
--
ALTER TABLE `qualites_de_leader`
  ADD KEY `fiche_id` (`fiche_id`);

--
-- Index pour la table `suivi_actions`
--
ALTER TABLE `suivi_actions`
  ADD KEY `superviseur_id` (`superviseur_id`),
  ADD KEY `supervise_id` (`supervise_id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Index pour la table `supervisions`
--
ALTER TABLE `supervisions`
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `superviseur_id` (`superviseur_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `superviseur_id` (`superviseur_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `actions_recommandations`
--
ALTER TABLE `actions_recommandations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `auto_evaluation`
--
ALTER TABLE `auto_evaluation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `competences_de_gestion`
--
ALTER TABLE `competences_de_gestion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `competences_individuelles`
--
ALTER TABLE `competences_individuelles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `competence_evaluation`
--
ALTER TABLE `competence_evaluation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT pour la table `competence_profile_evaluations`
--
ALTER TABLE `competence_profile_evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `competence_votre_profil`
--
ALTER TABLE `competence_votre_profil`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `coordination_commentaires`
--
ALTER TABLE `coordination_commentaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `cote_des_objectifs`
--
ALTER TABLE `cote_des_objectifs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `evaluation_cycles`
--
ALTER TABLE `evaluation_cycles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `objectifs`
--
ALTER TABLE `objectifs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `objectifs_items`
--
ALTER TABLE `objectifs_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `objectifs_resumes`
--
ALTER TABLE `objectifs_resumes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `qualites_de_leader`
--
ALTER TABLE `qualites_de_leader`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_actions`
--
ALTER TABLE `suivi_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `supervisions`
--
ALTER TABLE `supervisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `actions_recommandations`
--
ALTER TABLE `actions_recommandations`
  ADD CONSTRAINT `fk_actions_objectifs` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_actions_superviseur` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `auto_evaluation`
--
ALTER TABLE `auto_evaluation`
  ADD CONSTRAINT `fk_autoeval_item` FOREIGN KEY (`item_id`) REFERENCES `objectifs_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `competences_de_gestion`
--
ALTER TABLE `competences_de_gestion`
  ADD CONSTRAINT `fk_cg_fiche` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `competences_individuelles`
--
ALTER TABLE `competences_individuelles`
  ADD CONSTRAINT `fk_ci_fiche` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `competence_evaluation`
--
ALTER TABLE `competence_evaluation`
  ADD CONSTRAINT `competence_evaluation_ibfk_1` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `competence_evaluation_ibfk_2` FOREIGN KEY (`supervise_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `competence_evaluation_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`);

--
-- Contraintes pour la table `competence_profile_evaluations`
--
ALTER TABLE `competence_profile_evaluations`
  ADD CONSTRAINT `fk_comp_profile_comp` FOREIGN KEY (`competence_id`) REFERENCES `competence_votre_profil` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `coordination_commentaires`
--
ALTER TABLE `coordination_commentaires`
  ADD CONSTRAINT `coordination_commentaires_ibfk_1` FOREIGN KEY (`coord_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `coordination_commentaires_ibfk_2` FOREIGN KEY (`supervise_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_coordination_fiche` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `cote_des_objectifs`
--
ALTER TABLE `cote_des_objectifs`
  ADD CONSTRAINT `fk_cote_fiche` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cote_item` FOREIGN KEY (`item_id`) REFERENCES `objectifs_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cote_superviseur` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `objectifs`
--
ALTER TABLE `objectifs`
  ADD CONSTRAINT `objectifs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `objectifs_ibfk_2` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `objectifs_items`
--
ALTER TABLE `objectifs_items`
  ADD CONSTRAINT `objectifs_items_ibfk_1` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `objectifs_resumes`
--
ALTER TABLE `objectifs_resumes`
  ADD CONSTRAINT `objectifs_resumes_ibfk_1` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `qualites_de_leader`
--
ALTER TABLE `qualites_de_leader`
  ADD CONSTRAINT `fk_ql_fiche` FOREIGN KEY (`fiche_id`) REFERENCES `objectifs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `suivi_actions`
--
ALTER TABLE `suivi_actions`
  ADD CONSTRAINT `suivi_actions_ibfk_1` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `suivi_actions_ibfk_2` FOREIGN KEY (`supervise_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `suivi_actions_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`);

--
-- Contraintes pour la table `supervisions`
--
ALTER TABLE `supervisions`
  ADD CONSTRAINT `supervisions_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supervisions_ibfk_2` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
