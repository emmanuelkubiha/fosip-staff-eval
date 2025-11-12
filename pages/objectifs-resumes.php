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

// Vérification de la fiche
$sql = "SELECT * FROM objectifs WHERE id = $fiche_id AND user_id = $user_id";
$res = mysqli_query($conn, $sql);
$fiche = mysqli_fetch_assoc($res);

if (!$fiche) {
  echo "<div class='container mt-4'><div class='alert alert-danger'>Fiche introuvable ou accès refusé.</div></div>";
  include('../includes/footer.php');
  exit;
}

// Traitement du formulaire
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $reussite = $_POST['resume_reussite'];
  $amelioration = $_POST['resume_amelioration'];
  $problemes = $_POST['resume_problemes'];
  $dev = $_POST['resume_competence_a_developper'];
  $util = $_POST['resume_competence_a_utiliser'];
  $soutien = $_POST['resume_soutien'];

  $sql = "UPDATE objectifs SET
    resume_reussite = ?, resume_amelioration = ?, resume_problemes = ?,
    resume_competence_a_developper = ?, resume_competence_a_utiliser = ?, resume_soutien = ?
    WHERE id = ?";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, 'ssssssi', $reussite, $amelioration, $problemes, $dev, $util, $soutien, $fiche_id);
  mysqli_stmt_execute($stmt);
  $success = true;
}

// Suppression du résumé
if (isset($_GET['delete']) && $_GET['delete'] === '1') {
  $sql = "UPDATE objectifs SET
    resume_reussite = NULL, resume_amelioration = NULL, resume_problemes = NULL,
    resume_competence_a_developper = NULL, resume_competence_a_utiliser = NULL, resume_soutien = NULL
    WHERE id = $fiche_id";
  mysqli_query($conn, $sql);
  header("Location: objectifs-resume.php?id=$fiche_id");
  exit;
}
?>

<div class="container mt-4">
  <h4 class="mb-3 text-primary">Résumé des performances — <?= htmlspecialchars($fiche['periode']) ?></h4>
  <p class="text-muted">Décrivez votre performance globale pour cette période. Cette section est facultative mais fortement recommandée à la fin du mois.</p>

  <?php if ($success): ?>
    <div class="alert alert-success">Résumé enregistré avec succès.</div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-12">
      <label class="form-label">Qu'avez-vous bien fait ce mois ?</label>
      <textarea name="resume_reussite" class="form-control" rows="2"><?= htmlspecialchars($fiche['resume_reussite']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Que pourriez-vous améliorer ?</label>
      <textarea name="resume_amelioration" class="form-control" rows="2"><?= htmlspecialchars($fiche['resume_amelioration']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Quels problèmes avez-vous rencontrés ?</label>
      <textarea name="resume_problemes" class="form-control" rows="2"><?= htmlspecialchars($fiche['resume_problemes']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Compétence à développer</label>
      <textarea name="resume_competence_a_developper" class="form-control" rows="2"><?= htmlspecialchars($fiche['resume_competence_a_developper']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Compétence à mieux utiliser</label>
      <textarea name="resume_competence_a_utiliser" class="form-control" rows="2"><?= htmlspecialchars($fiche['resume_competence_a_utiliser']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Soutien souhaité</label>
      <textarea name="resume_soutien" class="form-control" rows="2"><?= htmlspecialchars($fiche['resume_soutien']) ?></textarea>
    </div>

    <div class="col-12 text-end">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Enregistrer
      </button>
      <a href="objectifs-resume.php?id=<?= $fiche_id ?>&delete=1" class="btn btn-outline-danger" onclick="return confirm('Supprimer le résumé ?')">
        <i class="bi bi-trash"></i> Supprimer
      </a>
    </div>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
