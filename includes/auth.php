<?php
function require_role($allowed_roles = []) {
  // Évite l'avertissement "Ignoring session_start()" si la session est déjà active.
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $role = $_SESSION['role'] ?? 'invité';
  if (!in_array($role, $allowed_roles)) {
    header('Location: ../unauthorized.php');
    exit;
  }
}
