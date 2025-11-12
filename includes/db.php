<?php
/**
 * Connexion à la base de données
 * Utilise config.php pour les identifiants (non versionné)
 */

// Charger la configuration depuis config.php (hors Git)
$configPath = __DIR__ . '/../config.php';

if (file_exists($configPath)) {
    // Environnement de production (config.php existe)
    $config = require $configPath;
    $host = $config['host'];
    $dbname = $config['dbname'];
    $username = $config['username'];
    $password = $config['password'];
} else {
    // Environnement de développement local (fallback)
    $host = 'localhost';
    $dbname = 'fosip_eval';
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
    
    // Définir le fuseau horaire (RDC = UTC+1 ou UTC+2 selon la région)
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
