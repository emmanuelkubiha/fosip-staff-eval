# 📊 FOSIP Performance Staff Suite

Système d'évaluation et de gestion des performances du personnel pour FOSIP (Formation pour la Solidarité et l'Innovation Populaire).

## 🎯 Description

Application web complète permettant la gestion des évaluations de performance des employés avec un workflow structuré : auto-évaluation → supervision → coordination finale. Le système inclut également un module complet de rapports et statistiques.

## ✨ Fonctionnalités principales

### 🔐 Authentification et gestion des utilisateurs
- Système de connexion sécurisé avec sessions PHP
- 4 rôles : Admin, Coordination, Superviseur, Agent/Staff
- Gestion complète des utilisateurs (CRUD)
- Hiérarchie superviseur-agent

### 📝 Gestion des objectifs et évaluations
- Création de fiches d'évaluation par période
- Définition d'objectifs avec indicateurs et cibles
- Auto-évaluation par les agents
- Évaluation par les superviseurs
- Commentaire final de la coordination

### 👥 Module de supervision
- Vue par agent
- Vue par période
- Filtres avancés (statut, période, recherche)
- Évaluation des auto-évaluations
- Suivi des performances

### 📊 Module Rapports (NOUVEAU)
- **Statistiques globales** : fiches totales, terminées, agents évalués, taux d'atteinte
- **Filtres avancés** : par période, superviseur, statut
- **Export PDF** : rapport professionnel avec mise en page optimisée
- **Export Excel** : données brutes pour analyse approfondie
- **Tableaux de bord** :
  - Performance par superviseur avec taux de complétion
  - Évolution par période (6 derniers mois)
- **Statistiques détaillées** : objectifs atteints, progression temporelle

### 📱 Interface responsive
- Design moderne avec Bootstrap 5
- Couleurs officielles FOSIP (#3D74B9, #F5C7A5)
- Menu mobile avec hamburger
- Sidebar collapsible avec sous-menus
- Notifications en temps réel (toasts)

## 🛠️ Technologies utilisées

- **Backend** : PHP 8.x
- **Base de données** : MySQL / MariaDB
- **Frontend** : 
  - Bootstrap 5.3.2
  - Bootstrap Icons
  - JavaScript ES6+
- **Architecture** : MVC-like avec séparation des concerns
- **Sécurité** : Protection CSRF, sessions sécurisées, PDO préparé

## 📁 Structure du projet

```
fosip-eval/
├── assets/
│   ├── css/
│   │   ├── sidebar.css
│   │   └── style.css
│   ├── img/
│   │   ├── profiles/          # Photos de profil
│   │   └── logocircular.png   # Logo FOSIP
│   └── js/
│       └── main.js
├── includes/
│   ├── auth.php               # Vérification authentification
│   ├── db.php                 # Connexion base de données
│   ├── header.php             # En-tête avec menu mobile
│   ├── footer.php             # Pied de page
│   ├── sidebar.php            # Menu latéral dynamique
│   └── version.php            # Versioning
├── pages/
│   ├── dashboard.php          # Tableau de bord
│   ├── login.php              # Page de connexion
│   ├── profile.php            # Profil utilisateur
│   ├── users.php              # Gestion utilisateurs (admin)
│   ├── coordination.php       # Module coordination
│   ├── supervision.php        # Module supervision
│   ├── rapports.php           # 📊 Module rapports (NOUVEAU)
│   ├── rapports-export.php    # 📊 Exports PDF/Excel (NOUVEAU)
│   └── ...                    # Autres pages
├── fosip_evaluation.sql       # Structure de la base de données
├── CHANGELOG.md               # Historique des modifications
├── RAPPORTS_README.md         # Documentation technique rapports
├── GUIDE_RAPPORTS.md          # Guide utilisateur rapports
├── verify-rapports.php        # Script de vérification
└── test-rapports.sql          # Données de test

```

## 🚀 Installation

### Prérequis
- PHP 8.0 ou supérieur
- MySQL 5.7+ ou MariaDB 10.3+
- Serveur web (Apache, Nginx) ou XAMPP/WAMP/MAMP
- Extension PHP PDO_MySQL activée

### Étapes d'installation

1. **Cloner ou télécharger le projet**
   ```bash
   git clone [url-du-projet]
   cd fosip-eval
   ```

2. **Configurer la base de données**
   ```bash
   # Créer la base de données
   mysql -u root -p
   CREATE DATABASE fosip_evaluation CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   exit;
   
   # Importer la structure
   mysql -u root -p fosip_evaluation < fosip_evaluation.sql
   ```

3. **Configurer la connexion DB**
   Éditer `includes/db.php` avec vos paramètres :
   ```php
   $host = 'localhost';
   $dbname = 'fosip_evaluation';
   $username = 'root';
   $password = 'votre_mot_de_passe';
   ```

4. **Configurer le serveur web**
   - **XAMPP** : Placer le dossier dans `htdocs/`
   - **Accès** : `http://localhost/fosip-eval/`

5. **Vérifier l'installation du module Rapports**
   ```bash
   php verify-rapports.php
   ```

6. **Connexion par défaut**
   - **Admin** : `admin@fosip-drc.org` / `admin123`
   - **Coordination** : `coordination@fosip-drc.org` / `coord123`
   - **Superviseur** : `superviseur@fosip-drc.org` / `super123`

   ⚠️ **Changez ces mots de passe immédiatement en production !**

## 📖 Documentation

- **[CHANGELOG.md](CHANGELOG.md)** : Historique complet des modifications
- **[RAPPORTS_README.md](RAPPORTS_README.md)** : Documentation technique du module Rapports
- **[GUIDE_RAPPORTS.md](GUIDE_RAPPORTS.md)** : Guide utilisateur pour les rapports
- **[WORKFLOW_STATUTS.md](WORKFLOW_STATUTS.md)** : Workflow des statuts d'évaluation

## 🎨 Thème et couleurs

### Couleurs FOSIP officielles
- **Bleu primaire** : `#3D74B9`
- **Jaune secondaire** : `#F5C7A5`
- **Gris neutre** : `#6c757d`

### Classes CSS réutilisables
```css
.btn-fosip         /* Bouton aux couleurs FOSIP */
.card-fosip        /* Carte avec bordure FOSIP */
.badge-fosip       /* Badge coloré FOSIP */
.navbar-fosip      /* Navbar avec gradient FOSIP */
```

## 🔒 Sécurité

- ✅ Protection CSRF sur tous les formulaires
- ✅ Requêtes préparées PDO (prévention SQL injection)
- ✅ Validation des données côté serveur
- ✅ Sessions sécurisées avec timeout
- ✅ Contrôle d'accès basé sur les rôles (RBAC)
- ✅ Hashage des mots de passe (bcrypt)

## 🐛 Dépannage

### Problème de connexion à la base de données
- Vérifier les paramètres dans `includes/db.php`
- Vérifier que MySQL est démarré
- Vérifier les permissions de l'utilisateur MySQL

### Erreur "Headers already sent"
- Vérifier qu'il n'y a pas d'espaces avant `<?php`
- Vérifier l'encodage des fichiers (UTF-8 sans BOM)

### Les rapports ne s'affichent pas
- Vérifier que l'utilisateur a le rôle `coordination`
- Vérifier qu'il y a des données dans la base
- Exécuter `php verify-rapports.php` pour diagnostiquer

### Export PDF vide
- Vérifier que les popups ne sont pas bloquées
- Essayer avec un autre navigateur
- Vérifier la console JavaScript pour les erreurs

## 📝 Workflow d'évaluation

```
1. Agent crée une fiche d'objectifs
   ↓ (statut: encours)
2. Agent remplit l'auto-évaluation
   ↓ (statut: attente)
3. Superviseur évalue
   ↓ (statut: evalue)
4. Coordination ajoute commentaire final
   ↓ (statut: termine)
5. Fiche archivée et consultable dans les rapports
```

## 🤝 Contribution

Pour contribuer au projet :
1. Créer une branche pour votre fonctionnalité
2. Commiter vos changements
3. Tester localement
4. Documenter dans CHANGELOG.md
5. Créer une pull request

## 📧 Support

- **Email** : support@fosip-drc.org
- **Documentation** : Consulter les fichiers MD du projet
- **Issues** : Reporter les bugs via le système de tickets

## 📄 Licence

© 2024 FOSIP - Formation pour la Solidarité et l'Innovation Populaire
Tous droits réservés.

---

**Version actuelle** : 1.0.0  
**Dernière mise à jour** : 11 novembre 2025  
**Développé pour** : FOSIP DRC

---

# FOSIP Staff Performance Suite

Ce système est réservé exclusivement à Emmanuel Kubiha.  
**Toute utilisation, modification ou diffusion sans autorisation est interdite.**  
Pour toute demande ou usage, contactez : **emmanuelkubiha@gmail.com**

## Dossiers principaux inclus

- `pages/` : Toutes les pages PHP du système
- `bd/` : Dossier contenant la base de données SQL (structure et exports)
- `assets/` : Fichiers statiques (images, CSS, JS)
- `includes/` : Fichiers d'inclusion PHP (header, footer, sidebar, etc.)

## Sécurité

- Les fichiers sensibles (`config.php`, photos uploadées, etc.) sont protégés par `.gitignore`
- L'accès au système est strictement réservé à l'administrateur désigné

## Contact

Pour toute question, demande d'accès ou support :
- Email : **emmanuelkubiha@gmail.com**

## Attention

Ce système est la propriété exclusive d'Emmanuel Kubiha.  
Toute utilisation non autorisée expose à des poursuites.  
Contactez-moi avant toute modification ou déploiement.

---
