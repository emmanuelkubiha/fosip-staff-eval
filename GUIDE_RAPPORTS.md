# Guide d'utilisation - Module Rapports

## 📋 Accès au module

1. **Connectez-vous** avec un compte de type "Coordination"
2. Dans le menu latéral gauche, cliquez sur **"Rapports"** (icône 📊)
3. Vous arrivez sur la page des statistiques et rapports

---

## 📊 Vue d'ensemble de la page

La page Rapports se compose de 5 sections principales :

### 1. Statistiques globales (4 cartes en haut)
```
┌─────────────┬─────────────┬─────────────┬─────────────┐
│  📄 Total   │  ✅ Termi-  │  👥 Agents  │  🎯 Taux    │
│   Fiches    │    nées     │   évalués   │  d'atteinte │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

### 2. Filtres de rapport
Permet de personnaliser les données affichées :
- **Période début** : Ex. `2024-01`
- **Période fin** : Ex. `2024-12`
- **Superviseur** : Choisir un superviseur spécifique ou "Tous"
- **Statut** : Terminé / Évalué / En cours / En attente

**👉 Astuce** : Laissez les champs vides pour voir toutes les données

### 3. Téléchargement des rapports
Deux options disponibles :

#### 🔴 Rapport PDF
- **Quand l'utiliser ?** 
  - Pour présenter en réunion
  - Pour archiver officiellement
  - Pour impression papier
  
- **Contenu** :
  - En-tête professionnel avec logo FOSIP
  - Tableau récapitulatif complet
  - Mise en page optimisée pour l'impression

- **Comment ?** 
  1. Cliquez sur "Télécharger PDF"
  2. Une nouvelle fenêtre s'ouvre avec le rapport
  3. Utilisez `Ctrl+P` ou le bouton "🖨️ Imprimer / Sauver PDF"
  4. Choisissez "Enregistrer au format PDF" comme imprimante

#### 🟢 Rapport Excel (CSV)
- **Quand l'utiliser ?**
  - Pour analyse approfondie des données
  - Pour créer vos propres graphiques
  - Pour importer dans un autre système
  
- **Contenu** :
  - Toutes les colonnes de données
  - Format compatible Excel/LibreOffice
  - Encodage UTF-8 (accents préservés)

- **Comment ?**
  1. Cliquez sur "Télécharger Excel"
  2. Le fichier `.csv` se télécharge automatiquement
  3. Ouvrez avec Excel, LibreOffice Calc, ou Google Sheets
  4. Les données sont séparées par `;` (point-virgule)

### 4. Performance par superviseur
Tableau comparatif montrant :
- Nombre d'agents par superviseur
- Répartition des fiches par statut
- Taux de complétion avec code couleur :
  - 🟢 **≥75%** : Excellent
  - 🟡 **50-74%** : Satisfaisant
  - 🔴 **<50%** : À améliorer

### 5. Évolution par période
Historique des 6 derniers mois avec :
- Nombre de fiches créées
- Nombre de fiches terminées
- Barre de progression visuelle

---

## 🎯 Cas d'usage courants

### Scénario 1 : Rapport mensuel pour la direction
```
1. Sélectionnez le mois concerné (ex: 2024-11)
2. Laissez "Superviseur" et "Statut" vides
3. Cliquez sur "Télécharger PDF"
4. Imprimez ou envoyez par email
```

### Scénario 2 : Analyse d'un superviseur spécifique
```
1. Sélectionnez le superviseur dans la liste
2. Choisissez une période si nécessaire
3. Cliquez sur "Télécharger Excel"
4. Analysez dans Excel avec tableaux croisés dynamiques
```

### Scénario 3 : Bilan annuel
```
1. Période début : 2024-01
2. Période fin : 2024-12
3. Tous superviseurs
4. Téléchargez PDF ET Excel pour documentation complète
```

### Scénario 4 : Suivi des fiches en attente
```
1. Statut : "Évalué" (fiches qui attendent commentaire coordination)
2. Consultez la liste directement dans les tableaux
3. Téléchargez Excel pour suivi dans un tableur
```

---

## 💡 Astuces et bonnes pratiques

### Pour les exports PDF
- ✅ Le PDF s'ouvre dans le navigateur (pas de logiciel externe nécessaire)
- ✅ Utilisez Chrome ou Edge pour meilleur rendu
- ✅ Réglez l'échelle d'impression à 100% pour éviter les coupures
- ✅ Le bouton "Imprimer" en haut à droite lance le dialogue d'impression

### Pour les exports Excel
- ✅ Le fichier CSV utilise `;` comme séparateur
- ✅ Si Excel n'ouvre pas correctement :
  - Ouvrir Excel vide
  - Fichier > Ouvrir > Choisir le CSV
  - Ou bien : Données > Importer depuis CSV
- ✅ Les accents et caractères spéciaux sont préservés (UTF-8)

### Filtrage optimal
- ✅ **Ne filtrez pas trop** : Commencez large puis affinez
- ✅ **Testez sans filtre** : Voyez d'abord toutes les données
- ✅ **Un filtre à la fois** : Facilitez le diagnostic en cas de résultats vides

---

## ❗ Dépannage

### "Aucune donnée disponible"
- Vérifiez que des fiches existent dans la période choisie
- Réinitialisez les filtres avec le bouton "Réinitialiser"
- Vérifiez que le superviseur sélectionné a bien des fiches

### Le PDF ne s'affiche pas
- Vérifiez que les popups ne sont pas bloquées
- Essayez avec un autre navigateur
- Vérifiez votre connexion internet

### Le fichier CSV est mal formaté dans Excel
- Utilisez l'import depuis le menu Données
- Choisissez "Délimité" puis "Point-virgule"
- Sélectionnez l'encodage "UTF-8"

### "CSRF validation failed"
- Votre session a expiré, reconnectez-vous
- Ne pas utiliser le bouton "Précédent" du navigateur

---

## 📞 Support

Pour toute question sur le module Rapports :
- Contactez l'administrateur système FOSIP
- Email : coordination@fosip-drc.org
- Consultez la documentation technique : `RAPPORTS_README.md`

---

**Version** : 1.0  
**Date** : 11 novembre 2025  
**Auteur** : FOSIP IT Team
