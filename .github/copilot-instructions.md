# 🤖 Copilot Instructions for FOSIP Staff Evaluation Suite

## 🏗️ Architecture & Modules
- **MVC-like structure**: PHP pages in `pages/` (views/controllers), shared logic in `includes/`, assets in `assets/`.
- **Major modules**:
  - **Auto-évaluation**: Agents créent/modifient leurs fiches.
  - **Supervision**: Superviseurs évaluent les fiches soumises.
  - **Coordination**: Coordination valide et commente, clôturant le workflow.
  - **Rapports**: Statistiques, exports PDF/Excel, accessible uniquement à la coordination.
- **Data flow**: Fiche d'évaluation suit le cycle `encours → attente → evalue → termine` (voir `WORKFLOW_STATUTS.md`).

## ⚙️ Workflows & Conventions
- **Statuts de fiche**: Utiliser les valeurs `encours`, `attente`, `evalue`, `termine` (affichage via switch/case, Bootstrap badges).
- **Sécurité**: CSRF sur tous les formulaires, sessions PHP, contrôle d'accès par rôle (voir `auth.php`).
- **Connexion DB**: Modifier `includes/db.php` pour adapter les paramètres locaux.
- **Exports**: PDF via navigateur, Excel/CSV avec séparateur `;` et encodage UTF-8.
- **Rapports**: Filtres avancés (période, superviseur, statut), voir `rapports.php` et `rapports-export.php`.

## 🛠️ Développement
- **Technos**: PHP 8.x, MySQL/MariaDB, Bootstrap 5, JS ES6+.
- **Démarrage local**: Placer le dossier dans `htdocs/` (XAMPP), accéder via `http://localhost/fosip-eval/`.
- **Base de données**: Importer `fosip_evaluation.sql`.
- **Vérification module Rapports**: `php verify-rapports.php`.
- **Rôles par défaut**: Voir README pour logins initiaux (changez en prod !).

## 📚 Fichiers clés
- `includes/auth.php` : Authentification et contrôle d'accès
- `includes/db.php` : Connexion PDO
- `pages/rapports.php` : Statistiques et filtres
- `pages/rapports-export.php` : Exports PDF/Excel
- `WORKFLOW_STATUTS.md` : Cycle de vie des fiches
- `RAPPORTS_README.md` : Doc technique Rapports
- `GUIDE_RAPPORTS.md` : Guide utilisateur Rapports

## 🧩 Patterns spécifiques
- **Affichage statuts** : Utiliser switch/case PHP pour badges Bootstrap
- **Séparation des rôles** : Contrôler l'accès à chaque page selon le rôle
- **Exports** : Générer PDF côté navigateur, CSV côté serveur (UTF-8, `;`)
- **Sidebar dynamique** : `includes/sidebar.php` gère le menu selon le rôle

## 🚨 Points d'attention
- **Ne pas exposer les mots de passe par défaut en production**
- **Vérifier la migration SQL pour les statuts** (`migration_statuts_objectifs.sql`)
- **Respecter la structure des statuts pour automatisation du workflow**

---

Pour toute question, consultez les fichiers de documentation ou contactez l'administrateur FOSIP.
