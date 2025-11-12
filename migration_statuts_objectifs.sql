-- Migration pour ajouter les statuts 'evalue' et 'termine' à la table objectifs
-- À exécuter dans phpMyAdmin ou via ligne de commande MySQL

-- Modifier la colonne statut pour ajouter les nouveaux statuts
ALTER TABLE `objectifs` 
MODIFY `statut` ENUM('encours', 'attente', 'evalue', 'termine') DEFAULT 'encours';

-- Workflow des statuts :
-- 'encours'  : Fiche créée, employé remplit ses objectifs
-- 'attente'  : Fiche soumise, en attente d'évaluation du superviseur
-- 'evalue'   : Superviseur a évalué (compétences + notes + actions)
-- 'termine'  : Coordination a commenté/validé, cycle complet
