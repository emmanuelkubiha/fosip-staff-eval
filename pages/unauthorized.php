<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Accès non autorisé</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
  <div class="text-center">
    <i class="bi bi-shield-lock text-danger" style="font-size: 4rem;"></i>
    <h1 class="mt-3 text-danger">Accès refusé</h1>
    <p class="text-muted">Vous n'avez pas les autorisations nécessaires pour accéder à cette page.</p>
    <a href="dashboard.php" class="btn btn-primary mt-3">
      <i class="bi bi-arrow-left-circle me-1"></i> Retour à l'accueil
    </a>
  </div>
</body>
</html>
