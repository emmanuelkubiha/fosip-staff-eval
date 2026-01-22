<?php
session_start();
require_once('../includes/db.php');
$current_page = 'fiches-evaluation-voir.php';
include('../includes/header.php');
?>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container mt-4">

<?php
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];

// Récupère la fiche la plus récente
$stmt = $pdo->prepare("
  SELECT o.*, u.nom AS sup_nom, u.post_nom AS sup_post_nom, u.fonction AS sup_fonction
  FROM objectifs o
  LEFT JOIN users u ON o.superviseur_id = u.id
  WHERE o.user_id = ?
  ORDER BY o.periode DESC
  LIMIT 1
");
$stmt->execute([$user_id]);
$fiche = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fiche):
?>
  <div class="text-center mt-5">
    <i class="bi bi-clipboard-x text-secondary" style="font-size: 3rem;"></i>
    <h4 class="text-muted mt-3">Aucune fiche trouvée</h4>
    <p class="text-secondary">Vous n’avez pas encore défini d’objectifs. Cliquez ci-dessous pour commencer.</p>
    <a href="objectifs-ajouter.php" class="btn btn-primary mt-3">
      <i class="bi bi-plus-circle me-1"></i> Ajouter des objectifs
    </a>
  </div>
</div></div></div>
<?php include('../includes/footer.php'); exit; endif; ?>

<?php
$fiche_id = $fiche['id'];

// Vérifie si le superviseur a évalué
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $fiche['periode']]);
$verrou_superviseur = $stmtEval->fetchColumn() > 0;

// Vérifie si la coordination a commenté
$stmtCom = $pdo->prepare("SELECT COUNT(*) FROM coordination_commentaires WHERE fiche_id = ?");
$stmtCom->execute([$fiche_id]);
$verrou_coordination = $stmtCom->fetchColumn() > 0;

$verrou_total = $verrou_superviseur || $verrou_coordination;

// Objectifs
$stmtItems = $pdo->prepare("SELECT * FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC");
$stmtItems->execute([$fiche_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>

<h4 class="mb-3 text-primary">
  <i class="bi bi-clipboard-check me-2"></i> Fiche d’objectifs — <?= htmlspecialchars($fiche['periode']) ?>
</h4>

<div class="card mb-3 shadow-sm">
  <div class="card-body">
    <p><strong><i class="bi bi-diagram-3 me-1 text-primary"></i> Projet :</strong> <?= htmlspecialchars($fiche['nom_projet']) ?></p>
    <p><strong><i class="bi bi-person-workspace me-1 text-primary"></i> Poste :</strong> <?= htmlspecialchars($fiche['poste']) ?></p>
    <p><strong><i class="bi bi-calendar-event me-1 text-primary"></i> Date de commencement :</strong> <?= htmlspecialchars($fiche['date_commencement']) ?></p>
    <p><strong><i class="bi bi-person-lines-fill me-1 text-primary"></i> Superviseur :</strong> <?= htmlspecialchars($fiche['sup_nom'].' '.$fiche['sup_post_nom']) ?> — <?= htmlspecialchars($fiche['sup_fonction']) ?></p>
    <p><strong><i class="bi bi-info-circle me-1 text-primary"></i> Statut :</strong>
      <?php
        $color = $fiche['statut'] === 'complet' ? 'success' : ($fiche['statut'] === 'attente' ? 'warning' : 'secondary');
        echo "<span class='badge bg-$color'><i class='bi bi-circle-fill me-1'></i>" . ucfirst($fiche['statut']) . "</span>";
      ?>
    </p>

    <?php if ($verrou_total): ?>
      <div class="alert alert-danger mt-3">
        <i class="bi bi-lock-fill me-1"></i> Cette fiche est verrouillée car une évaluation ou un commentaire a déjà été enregistré. Pour modifier ou supprimer, demandez au superviseur ou à la coordination de retirer leurs entrées.
      </div>
    <?php else: ?>
      <div class="text-end">
        <a href="objectifs-modifier.php?id=<?= $fiche_id ?>" class="btn btn-sm btn-warning me-2">
          <i class="bi bi-pencil-square me-1"></i> Modifier la fiche
        </a>
        <a href="objectifs-supprimer.php?id=<?= $fiche_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer toute cette fiche d’objectifs ?')">
          <i class="bi bi-trash me-1"></i> Supprimer la fiche
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<h5 class="text-primary"><i class="bi bi-list-check me-2"></i> Objectifs du mois</h5>
<?php if (count($items) > 0): ?>
  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Objectif</th>
        <th>Résumé</th>
        <th>Évaluation</th>
        <th>Commentaire</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $index => $item): ?>
        <?php
          $resume = $item['resume'] ?? '';
          $eval = $item['eval_statut'] ?? null; // à adapter selon ta table
          $commentaire = $verrou_coordination; // booléen basé sur la fiche
        ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td><?= htmlspecialchars($item['contenu']) ?></td>
          <td>
            <?= empty($resume) ? '<span class="badge bg-secondary blinking">Incomplet</span>' : '<span class="badge bg-success">Complété</span>' ?>
          </td>
          <td>
            <?= $verrou_superviseur ? '<span class="badge bg-success">Évalué</span>' : '<span class="badge bg-secondary">Non évalué</span>' ?>
          </td>
          <td>
            <?= $commentaire ? '<span class="badge bg-info text-dark">Commenté</span>' : '<span class="badge bg-light text-muted">Non disponible</span>' ?>
          </td>
          <td>
            <?php if (!$verrou_total): ?>
              <a href="objectif-item-modifier.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="objectif-item-supprimer.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cet objectif ?')">
                <i class="bi bi-trash"></i>
              </a>
            <?php else: ?>
              <span class="text-muted"><i class="bi bi-lock-fill me-1"></i> Verrouillé</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-circle-fill me-1"></i> Aucun objectif n’a été défini pour cette fiche.
  </div>
<?php endif; ?>

<style>
.blinking {
  animation: blink 1s infinite;
}
@keyframes blink {
  0% { opacity: 1; }
  50% { opacity: 0.3; }
  100% { opacity: 1; }
}
</style>

<script>
  // Clignotement pour les résumés incomplets
  document.querySelectorAll('.blinking').forEach(el => {
    el.style.animation = 'blink 1s infinite';
  });

  // Fonction pour filtrer les objectifs (à compléter si tu veux ajouter des filtres dynamiques)
  function filtrerObjectifs() {
    alert("Fonction de filtrage à implémenter selon les périodes ou mots-clés.");
  }

  // Fonction pour charger plus d’objectifs (si pagination)
  function chargerPlus() {
    alert("Fonction de chargement supplémentaire à implémenter.");
  }

  // Fonction pour ajouter un résumé (à relier à une modale ou page)
  function ajouterResume(id) {
    alert("Ajouter un résumé pour l’objectif #" + id + " — à implémenter.");
  }
</script>

</div> <!-- fin container -->
</div> <!-- fin col-md-9 -->
</div> <!-- fin row -->

<?php include('../includes/footer.php'); ?>
