# 📋 WORKFLOW DES STATUTS DE FICHE D'ÉVALUATION

## Migration SQL Required
**IMPORTANT** : Exécutez le fichier `migration_statuts_objectifs.sql` dans phpMyAdmin avant d'utiliser le système.

## Cycle de Vie d'une Fiche

```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│ ENCOURS  │ --> │ ATTENTE  │ --> │  ÉVALUÉ  │ --> │ TERMINÉ  │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
    ↓                ↓                 ↓                 ↓
 Employé        Superviseur       Superviseur      Coordination
   crée            reçoit            évalue           valide
```

## Détails des Statuts

### 1️⃣ **ENCOURS** (Badge: Info/Bleu clair)
- **Qui** : Employé
- **Action** : Crée la fiche et remplit ses objectifs
- **Description** : Fiche en cours de création/modification par l'employé
- **Transition** : → ATTENTE (quand l'employé soumet la fiche)

### 2️⃣ **ATTENTE** (Badge: Warning/Jaune)
- **Qui** : Superviseur
- **Action** : Reçoit la notification qu'une fiche est prête à évaluer
- **Description** : Fiche soumise, en attente d'évaluation du superviseur
- **Transition** : → ÉVALUÉ (automatique après enregistrement de l'évaluation)

### 3️⃣ **ÉVALUÉ** (Badge: Primary/Bleu)
- **Qui** : Coordination
- **Action** : Voit que le superviseur a terminé son évaluation
- **Description** : Superviseur a évalué les compétences, noté les objectifs et défini le plan d'action
- **Changement automatique** : Se déclenche dans `supervision-evaluation-save.php`
- **Transition** : → TERMINÉ (automatique après commentaire de coordination)

### 4️⃣ **TERMINÉ** (Badge: Success/Vert)
- **Qui** : Tous
- **Action** : Consultation uniquement
- **Description** : Cycle complet - Coordination a validé et commenté
- **Changement automatique** : Se déclenche dans `coordination-save.php`
- **Transition** : Fin du workflow

## Fichiers Modifiés

### 1. Base de données
- **fosip_evaluation.sql** : ENUM mis à jour
- **migration_statuts_objectifs.sql** : Script de migration pour bases existantes

### 2. Backend
- **supervision-evaluation-save.php** : Met statut à "evalue" après enregistrement
- **coordination-save.php** : Met statut à "termine" après commentaire

### 3. Frontend
- **fiche-evaluation-complete.php** : Affichage statuts avec switch/case
- **imprimer-fiche-evaluation.php** : Affichage statuts avec switch/case

## Tests Recommandés

1. ✅ Créer une fiche (statut = encours)
2. ✅ Soumettre la fiche (statut = attente)
3. ✅ Évaluer en tant que superviseur → vérifier changement automatique à "evalue"
4. ✅ Commenter en tant que coordination → vérifier changement automatique à "termine"
5. ✅ Vérifier badges de couleur dans toutes les pages

## Code Colors

```php
// Switch statement pour affichage
switch($fiche['statut']) {
  case 'encours': 
    $class = 'info';    // Bleu clair
    $label = 'En cours';
    break;
  case 'attente': 
    $class = 'warning'; // Jaune
    $label = 'En attente';
    break;
  case 'evalue': 
    $class = 'primary'; // Bleu
    $label = 'Évalué';
    break;
  case 'termine': 
    $class = 'success'; // Vert
    $label = 'Terminé';
    break;
}
```

## Avantages

✅ **Traçabilité** : Chaque étape est visible
✅ **Automatique** : Changements de statut sans intervention manuelle
✅ **Sécurisé** : Inclus dans les transactions
✅ **Visuel** : Codes couleur clairs (Bootstrap badges)
✅ **Workflow clair** : Chacun sait où en est la fiche
