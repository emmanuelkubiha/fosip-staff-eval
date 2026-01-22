<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Supprime toutes les variables de session
$_SESSION = [];
session_destroy();
?>

<!-- Script JS pour indiquer la déconnexion -->
<script>
// Stocke un indicateur temporaire dans sessionStorage
sessionStorage.setItem('logoutToast', '1');

// Redirige vers la page de connexion (chemin dynamique, compatible local/prod)
var base = window.location.pathname.split('/pages/')[0];
window.location.href = base + '/pages/login.php';
</script>
