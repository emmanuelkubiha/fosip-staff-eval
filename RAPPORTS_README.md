# Module Rapports et Statistiques

## Vue d'ensemble
Le module **Rapports** permet à la coordination de consulter des statistiques détaillées sur les évaluations du personnel et de télécharger des rapports sous différents formats.

## Fonctionnalités

### 1. **Statistiques globales**
- **Fiches totales** : Nombre total de fiches d'évaluation créées
- **Fiches terminées** : Nombre de fiches avec commentaire coordination
- **Agents évalués** : Nombre d'agents ayant au moins une fiche
- **Taux d'atteinte** : Pourcentage global d'objectifs atteints/dépassés

### 2. **Filtres de rapport**
Les rapports peuvent être filtrés par :
- **Période début** : Date de début de la période (format mois/année)
- **Période fin** : Date de fin de la période (format mois/année)
- **Superviseur** : Sélection d'un superviseur spécifique
- **Statut** : Filtrage par statut (terminé, évalué, en cours, en attente)

### 3. **Exports disponibles**

#### Export PDF
- Rapport formaté et prêt à imprimer
- Inclut un en-tête avec logo FOSIP
- Tableau récapitulatif de toutes les fiches
- Statistiques résumées
- **Utilisation** : Cliquer sur "Télécharger PDF" pour générer le rapport
- **Note** : Le PDF s'ouvre dans le navigateur avec option d'impression/sauvegarde

#### Export Excel (CSV)
- Données brutes au format CSV (compatible Excel, LibreOffice, etc.)
- Encodage UTF-8 avec BOM pour compatibilité Excel
- Séparateur point-virgule (;) pour compatibilité française
- **Colonnes incluses** :
  - ID Fiche
  - Période
  - Informations agent (nom, prénom, email, fonction)
  - Projet et poste
  - Informations superviseur
  - Statistiques objectifs (atteints/total/pourcentage)
  - Statut
  - Dates et commentaires

### 4. **Tableaux de bord**

#### Performance par superviseur
Affiche pour chaque superviseur :
- Nombre d'agents supervisés
- Nombre total de fiches
- Répartition par statut (en cours, évaluées, terminées)
- Taux de complétion (pourcentage de fiches terminées)
- Badge coloré selon la performance :
  - 🟢 Vert : ≥ 75%
  - 🟡 Jaune : 50-74%
  - 🔴 Rouge : < 50%

#### Évolution par période
Affiche les 6 derniers mois avec :
- Nombre d'agents évalués par période
- Nombre de fiches créées
- Nombre de fiches terminées
- Barre de progression du taux de complétion

## Accès
- **Rôle requis** : Coordination uniquement
- **URL** : `/pages/rapports.php`
- **Accès sidebar** : Menu "Rapports" avec icône 📊

## Sécurité
- Protection CSRF sur tous les formulaires
- Vérification du rôle utilisateur
- Session requise pour accès

## Fichiers du module
- `pages/rapports.php` : Page principale avec statistiques et filtres
- `pages/rapports-export.php` : Gestionnaire d'exports (PDF et Excel)

## Améliorations futures possibles
1. **Export PDF avancé** : Intégration d'une librairie PDF (TCPDF, mPDF) pour graphiques avancés
2. **Graphiques interactifs** : Ajout de Chart.js pour visualisations dynamiques
3. **Rapport par agent** : Export détaillé d'un agent spécifique
4. **Planification d'exports** : Envoi automatique de rapports par email
5. **Comparaisons temporelles** : Graphiques d'évolution année sur année
6. **Export Word** : Génération de rapports au format DOCX

## Support
Pour toute question ou problème, contacter l'administrateur système FOSIP.
