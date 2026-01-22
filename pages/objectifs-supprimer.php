<?php
session_start();
require_once('../includes/db.php');
include('../includes/header.php');
include('../includes/sidebar.php');

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$fiche_id = intval($_GET['id'] ?? 0);
$confirm = isset($_POST['confirm']) ? true : false;
$deleted = false;

// Vérification de la fiche
$sql = "SELECT o.*, u.nom AS sup_nom, u.post_nom AS sup_post_nom, u.fonction AS sup_fonction
        FROM objectifs o
        LEFT JOIN users u ON o.superviseur_id = u.id
        WHERE o.id = $fiche_id AND o.user_id = $user_id";
$res = mysqli_query($conn, $sql);
$fiche = mysqli_fetch_assoc($res);

if (!$fiche) {
  echo "<div class='container mt-4'><div class='alert alert-danger'>Fiche introuvable ou accès refusé.</div></div>";
  include('../includes/footer.php');
  exit;
}

// Suppression confirmée
if ($confirm) {
  $del = mysqli_query($conn, "DELETE FROM objectifs WHERE id = $fiche_id");
  $deleted = $del ? true : false;
}
?>

<div class="container mt-4">
  <?php if ($deleted): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill text-success"></i> La fiche a été supprimée avec succès.
    </div>
    <script>
      setTimeout(() => {
        window.location.href = 'objectifs-liste.php';
      }, 2000);
    </script>
  <?php elseif (!$confirm): ?>
    <h4 class="mb-3 text-danger">Confirmer la suppression</h4>
    <p class="text-muted">Vous êtes sur le point de supprimer définitivement cette fiche d’objectifs. Cette action est irréversible.</p>

    <div class="card mb-3">
      <div class="card-body">
        <p><strong>Période :</strong> <?= htmlspecialchars($fiche['periode']) ?></p>
        <p><strong>Projet :</strong> <?= htmlspecialchars($fiche['nom_projet']) ?></p>
        <p><strong>Superviseur :</strong> <?= htmlspecialchars($fiche['sup_nom'].' '.$fiche['sup_post_nom']) ?> — <?= htmlspecialchars($fiche['sup_fonction']) ?></p>
        <p><strong>Statut :</strong> <span class="badge bg-secondary"><?= htmlspecialchars($fiche['statut']) ?></span></p>
      </div>
    </div>

    <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette fiche ?')">
      <input type="hidden" name="confirm" value="1">
      <button type="submit" class="btn btn-danger">
        <i class="bi bi-trash-fill"></i> Supprimer définitivement
      </button>
      <a href="objectifs-liste.php" class="btn btn-outline-secondary ms-2">
        <i class="bi bi-arrow-left-circle"></i> Annuler
      </a>
    </form>
  <?php else: ?>
    <div class="alert alert-danger">
      <i class="bi bi-x-circle-fill text-danger"></i> Une erreur est survenue lors de la suppression.
    </div>
  <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>
