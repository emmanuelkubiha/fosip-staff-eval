<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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

// Chargement des superviseurs
$superviseurs = [];
$sq = mysqli_query($conn, "SELECT id, nom, post_nom, fonction FROM users WHERE role IN ('superviseur', 'coordination') ORDER BY nom, post_nom");
while ($row = mysqli_fetch_assoc($sq)) $superviseurs[] = $row;

// Chargement des objectifs
$items = [];
$iq = mysqli_query($conn, "SELECT * FROM objectifs_items WHERE fiche_id = $fiche_id ORDER BY ordre ASC");
while ($row = mysqli_fetch_assoc($iq)) $items[] = $row;

// Traitement du formulaire
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom_projet = $_POST['nom_projet'];
  $poste = $_POST['poste'];
  $date_commencement = $_POST['date_commencement'];
  $periode = $_POST['periode'];
  $superviseur_id = $_POST['superviseur_id'];

  // Mise à jour de la fiche
  $sql = "UPDATE objectifs SET nom_projet=?, poste=?, date_commencement=?, periode=?, superviseur_id=? WHERE id=?";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, 'ssssii', $nom_projet, $poste, $date_commencement, $periode, $superviseur_id, $fiche_id);
  mysqli_stmt_execute($stmt);

  // Suppression des anciens objectifs
  mysqli_query($conn, "DELETE FROM objectifs_items WHERE fiche_id = $fiche_id");

  // Réinsertion des nouveaux objectifs
  $objectifs = $_POST['objectifs'] ?? [];
  foreach ($objectifs as $index => $obj) {
    $objSafe = mysqli_real_escape_string($conn, $obj);
    $ordre = $index + 1;
    mysqli_query($conn, "INSERT INTO objectifs_items (fiche_id, contenu, ordre) VALUES ($fiche_id, '$objSafe', $ordre)");
  }

  $success = true;
}
?>

<div class="container mt-4">
  <h4 class="mb-3 text-primary">Modifier la fiche d’objectifs — <?= htmlspecialchars($fiche['periode']) ?></h4>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill text-success"></i> Modifications enregistrées avec succès.
    </div>
    <script>
      setTimeout(() => {
        window.location.href = 'objectifs-liste.php';
      }, 2000);
    </script>
  <?php endif; ?>

  <form method="post" class="row g-3" onsubmit="return confirm('Confirmer les modifications de cette fiche ?')">
    <div class="col-md-6">
      <label class="form-label">Nom du Projet</label>
      <input type="text" name="nom_projet" class="form-control" value="<?= htmlspecialchars($fiche['nom_projet']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Titre du Poste</label>
      <input type="text" name="poste" class="form-control" value="<?= htmlspecialchars($fiche['poste']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Date de Commencement</label>
      <input type="date" name="date_commencement" class="form-control" value="<?= $fiche['date_commencement'] ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Période</label>
      <input type="month" name="periode" class="form-control" value="<?= $fiche['periode'] ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Superviseur</label>
      <select name="superviseur_id" class="form-select" required>
        <?php foreach ($superviseurs as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $fiche['superviseur_id'] == $s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nom'].' '.$s['post_nom'].' — '.$s['fonction']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <h5 class="text-primary">Objectifs</h5>
      <div id="objectifs-container">
        <?php foreach ($items as $i => $item): ?>
          <div class="objectif-item mb-2">
            <textarea name="objectifs[]" class="form-control" rows="2" placeholder="Objectif <?= $i+1 ?>"><?= htmlspecialchars($item['contenu']) ?></textarea>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="ajouterObjectif()">+ Ajouter un objectif</button>
    </div>

    <div class="col-12 text-end">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-pencil-square"></i> Enregistrer les modifications
      </button>
    </div>
  </form>
</div>

<script>
function ajouterObjectif() {
  const container = document.getElementById('objectifs-container');
  const index = container.querySelectorAll('.objectif-item').length + 1;
  const div = document.createElement('div');
  div.className = 'objectif-item mb-2';
  div.innerHTML = `<textarea name="objectifs[]" class="form-control" rows="2" placeholder="Objectif ${index}"></textarea>`;
  container.appendChild(div);
}
</script>

<?php include('../includes/footer.php'); ?>
