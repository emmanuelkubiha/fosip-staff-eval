<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
  $destination = 'pages/dashboard.php';
  $message = "Bienvenue dans le système d’évaluation du personnel FOSIP.";
} else {
  $destination = 'pages/login.php';
  $message = "Veuillez patienter, redirection vers la page de connexion...";
}

// Redirection après 2 secondes
header("Refresh: 2; URL=$destination");

// Affiche la page de chargement avec le message
include("loading.php");
exit;
