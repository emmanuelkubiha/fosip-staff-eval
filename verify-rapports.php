<?php
/**
 * Script de vérification du module Rapports
 * À exécuter une fois pour vérifier que tout est en place
 */

echo "=== Vérification du module Rapports ===\n\n";

// 1. Vérifier les fichiers
$files = [
    'pages/rapports.php',
    'pages/rapports-export.php',
    'RAPPORTS_README.md',
    'GUIDE_RAPPORTS.md',
    'CHANGELOG.md'
];

echo "1. Vérification des fichiers...\n";
$allFilesExist = true;
foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $exists = file_exists($fullPath);
    $status = $exists ? '✓' : '✗';
    echo "   $status $file\n";
    if (!$exists) $allFilesExist = false;
}
echo "\n";

// 2. Vérifier la connexion à la base de données
echo "2. Vérification de la connexion à la base de données...\n";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "   ✓ Connexion à la base de données OK\n";
} catch (Exception $e) {
    echo "   ✗ Erreur de connexion : " . $e->getMessage() . "\n";
    $allFilesExist = false;
}
echo "\n";

// 3. Vérifier les tables nécessaires
echo "3. Vérification des tables de la base de données...\n";
$tables = [
    'objectifs',
    'objectifs_items',
    'auto_evaluation',
    'users',
    'coordination_commentaires'
];

try {
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = (bool)$stmt->fetchColumn();
        $status = $exists ? '✓' : '✗';
        echo "   $status Table '$table'\n";
        if (!$exists) $allFilesExist = false;
    }
} catch (Exception $e) {
    echo "   ✗ Erreur lors de la vérification des tables : " . $e->getMessage() . "\n";
    $allFilesExist = false;
}
echo "\n";

// 4. Vérifier l'utilisateur coordination
echo "4. Vérification de l'utilisateur coordination...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'coordination'");
    $count = (int)$stmt->fetchColumn();
    if ($count > 0) {
        echo "   ✓ $count utilisateur(s) avec le rôle 'coordination'\n";
    } else {
        echo "   ⚠ Aucun utilisateur avec le rôle 'coordination'\n";
        echo "   → Vous devez créer un compte coordination pour accéder au module\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erreur : " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Vérifier les données de test
echo "5. Statistiques actuelles...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM objectifs");
    $nbFiches = (int)$stmt->fetchColumn();
    echo "   • $nbFiches fiche(s) d'évaluation\n";
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM objectifs");
    $nbAgents = (int)$stmt->fetchColumn();
    echo "   • $nbAgents agent(s) évalué(s)\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut = 'termine'");
    $nbTerminees = (int)$stmt->fetchColumn();
    echo "   • $nbTerminees fiche(s) terminée(s)\n";
    
    if ($nbFiches === 0) {
        echo "   ⚠ Aucune donnée disponible pour les rapports\n";
        echo "   → Créez des fiches d'évaluation pour tester le module\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erreur : " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Vérifier les permissions d'écriture
echo "6. Vérification des permissions...\n";
$testFile = __DIR__ . '/test_write_' . time() . '.tmp';
if (@file_put_contents($testFile, 'test')) {
    echo "   ✓ Permissions d'écriture OK\n";
    @unlink($testFile);
} else {
    echo "   ✗ Pas de permission d'écriture dans le dossier\n";
    $allFilesExist = false;
}
echo "\n";

// Conclusion
echo "=== Résultat ===\n";
if ($allFilesExist) {
    echo "✓ Le module Rapports est prêt à être utilisé !\n\n";
    echo "Pour accéder au module :\n";
    echo "1. Connectez-vous avec un compte 'coordination'\n";
    echo "2. Allez dans le menu 'Rapports'\n";
    echo "3. Consultez les statistiques et téléchargez vos rapports\n";
} else {
    echo "✗ Des problèmes ont été détectés. Veuillez les corriger avant d'utiliser le module.\n";
}

echo "\n=== Documentation ===\n";
echo "• RAPPORTS_README.md : Documentation technique\n";
echo "• GUIDE_RAPPORTS.md : Guide d'utilisation pour les utilisateurs\n";
echo "• CHANGELOG.md : Historique des modifications\n";
?>
