# Changelog - FOSIP Staff Performance Suite

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

## [Non publié]

### Ajouté - 11 novembre 2025
- **Module Rapports et Statistiques** pour la coordination
  - Page `rapports.php` avec statistiques globales et filtres avancés
  - Export PDF avec mise en page professionnelle et logo FOSIP
  - Export Excel (CSV) avec encodage UTF-8 pour analyse de données
  - Tableau de performance par superviseur avec taux de complétion
  - Graphique d'évolution par période (6 derniers mois)
  - Filtres par période, superviseur et statut
  - Documentation complète (`RAPPORTS_README.md` et `GUIDE_RAPPORTS.md`)
  
- **Menu mobile responsive**
  - Bouton hamburger dans le header pour petits écrans
  - Offcanvas sidebar pour navigation mobile
  - Icône agrandie pour meilleure visibilité
  - Fermeture automatique après sélection d'un menu

### Modifié - 11 novembre 2025
- Correction du bouton hamburger dans `header.php` pour cibler le bon offcanvas (`#sidebarMobile`)
- Amélioration de la responsivité du bouton menu (visible jusqu'à `lg` breakpoint)

---

## Historique des versions précédentes

### Phase 7 - Sidebar et Navigation
- Menu sidebar avec sous-menus collapsibles
- Détection automatique de la page active
- Badges de notification avec compteurs dynamiques
- Support mobile avec offcanvas

### Phase 6 - Système de Coordination
- Page `coordination.php` pour commentaires finaux
- Table `coordination_commentaires` dans la base de données
- Workflow complet d'évaluation (Agent → Superviseur → Coordination)
- Statuts de fiche : `encours`, `attente`, `evalue`, `termine`

### Phase 5 - Supervision
- Module de supervision des évaluations
- Pages `supervision.php`, `supervision-agent.php`, `supervision-periode.php`
- Évaluation des auto-évaluations par les superviseurs
- Statistiques par agent et par période

### Phase 4 - Auto-évaluation
- Formulaires d'auto-évaluation des agents
- Table `auto_evaluation` avec statuts d'atteinte
- Suivi des objectifs atteints/dépassés/partiels/non atteints
- Interface de modification des auto-évaluations

### Phase 3 - Gestion des Objectifs
- CRUD complet pour les fiches d'objectifs
- Gestion des items d'objectifs (objectifs_items)
- Résumés de performance (objectifs_resumes)
- Système de périodes mensuelles

### Phase 2 - Authentification et Utilisateurs
- Système de login avec sessions PHP
- Gestion des utilisateurs (CRUD)
- Rôles : admin, coordination, superviseur, staff/agent
- Hiérarchie superviseur-agent

### Phase 1 - Base du Projet
- Structure initiale du projet
- Configuration de la base de données MySQL
- Thème FOSIP avec couleurs officielles (#3D74B9, #F5C7A5)
- Layout Bootstrap 5 responsive
- Header et footer réutilisables

---

## Format

Ce fichier suit le format [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

### Types de changements
- **Ajouté** pour les nouvelles fonctionnalités
- **Modifié** pour les changements aux fonctionnalités existantes
- **Déprécié** pour les fonctionnalités qui seront bientôt supprimées
- **Supprimé** pour les fonctionnalités maintenant supprimées
- **Corrigé** pour les corrections de bugs
- **Sécurité** en cas de vulnérabilités
