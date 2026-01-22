<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Page introuvable</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .error-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      text-align: center;
    }
    .error-icon {
      font-size: 5rem;
      color: #dc3545;
    }
    .error-title {
      font-size: 2rem;
      color: #005DA4;
    }
    .error-message {
      color: #6c757d;
    }
  </style>
</head>
<body>
  <div class="error-container">
    <i class="bi bi-exclamation-triangle-fill error-icon"></i>
    <h1 class="error-title mt-3">Erreur 404 – Page introuvable</h1>
    <p class="error-message mb-4">La page que vous recherchez n'existe pas ou a été déplacée.</p>

    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="dashboard.php" class="btn btn-primary">
        <i class="bi bi-arrow-left-circle me-1"></i> Retour au tableau de bord
      </a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary">
        <i class="bi bi-arrow-left-circle me-1"></i> Retour à l’accueil
      </a>
    <?php endif; ?>
  </div>
</body>
</html>
