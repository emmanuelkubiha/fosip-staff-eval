# Explication temporaire : Mise en page desktop (padding gauche)

## Objectif
Pour éviter le chevauchement entre la barre de navigation latérale (sidebar) et le contenu principal sur grand écran, un padding-left important a été ajouté sur plusieurs pages. Cette modification améliore la lisibilité sur desktop, mais peut causer des problèmes d'affichage sur mobile (large espace vide à gauche).

## Fichiers modifiés
- pages/supervision.php
- pages/supervision-agent.php
- pages/supervision-periode.php
- pages/supervision-evaluer.php
- pages/fiche-evaluation-modifier.php

## Détail des changements
- Ajout ou augmentation du `padding-left` (6rem à 7.5rem) sur la colonne principale (`col-md-9` ou `col-lg-9`) pour laisser la place à la sidebar.
- But : garantir que le contenu ne se superpose jamais à la barre de navigation sur desktop.
- À surveiller : sur mobile, ce padding peut provoquer un décalage ou un espace vide important. Un ajustement CSS (media queries) sera nécessaire pour corriger cela plus tard.

## À faire plus tard
- Revoir le responsive/mobile pour réduire ou supprimer ce padding sur petits écrans.
- Tester sur plusieurs tailles d'écran.

---
Fichier temporaire, à supprimer ou adapter après refonte responsive.
