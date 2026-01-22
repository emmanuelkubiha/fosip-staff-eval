<?php
/**
 * Connexion à la base de données
 * Utilise includes/config.php pour les identifiants (non versionné)
 */

// Nouveau chemin pour config.php
$configPath = __DIR__ . '/config.php';

if (file_exists($configPath)) {
    // Utiliser la config personnalisée (production ou local)
    $config = require $configPath;
    $host = $config['host'] ?? 'localhost';
    $dbname = $config['dbname'] ?? 'fosip_evaluation';
    $username = $config['username'] ?? 'root';
    $password = $config['password'] ?? '';
} else {
    // Fallback : paramètres par défaut pour localhost
    $host = 'localhost';
    $dbname = 'fosip_evaluation';
    $username = 'root';
    $password = '';
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    date_default_timezone_set('Africa/Kinshasa');
} catch (PDOException $e) {
    // En production : ne pas afficher les détails de l'erreur
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        error_log("Erreur DB: " . $e->getMessage());
        die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
    } else {
        // En dev local : afficher l'erreur
        die("Erreur de connexion : " . $e->getMessage());
    }
}
?>
