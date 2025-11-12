
<?php
// pages/fiche-evaluation-save-resumes.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$fiche_id = intval($_POST['fiche_id'] ?? 0);

if ($fiche_id <= 0) {
  header('Location: fiches-evaluation-voir.php');
  exit;
}

// Vérifie que la fiche appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id FROM objectifs WHERE id = ? AND user_id = ?");
$stmt->execute([$fiche_id, $user_id]);
if (!$stmt->fetch()) {
  header('Location: fiches-evaluation-voir.php');
  exit;
}

// Récupère les champs
$fields = [
  'resume_reussite',
  'resume_amelioration',
  'resume_problemes',
  'resume_competence_a_developper',
  'resume_competence_a_utiliser',
  'resume_soutien'
];

$data = [];
foreach ($fields as $f) {
  $data[$f] = trim($_POST[$f] ?? '');
}

// Met à jour la fiche
$sql = "UPDATE objectifs SET ";
$sql .= implode(', ', array_map(fn($f) => "$f = :$f", $fields));
$sql .= " WHERE id = :id";

$data['id'] = $fiche_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($data);

// Redirection vers la fiche
header("Location: fiche-evaluation.php?id=$fiche_id");
exit;

// pages/fiche-evaluation.php
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
$fiche_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($fiche_id <= 0) {
  echo '<div class="alert alert-warning">Fiche non spécifiée. <a href="fiches-evaluation-voir.php">Retour à la liste</a></div>';
  include('../includes/footer.php');
  exit;
}

// Charger la fiche (vérifie l'appartenance)
$stmt = $pdo->prepare("
  SELECT o.*, u.nom AS sup_nom, u.post_nom AS sup_post_nom, u.fonction AS sup_fonction
  FROM objectifs o
  LEFT JOIN users u ON o.superviseur_id = u.id
  WHERE o.id = ? AND o.user_id = ?
  LIMIT 1
");
$stmt->execute([$fiche_id, $user_id]);
$fiche = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fiche) {
  echo '<div class="alert alert-danger">Fiche introuvable ou non autorisée.</div>';
  include('../includes/footer.php');
  exit;
}

// Vérifications verrouillage
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $fiche['periode']]);
$verrou_superviseur = $stmtEval->fetchColumn() > 0;

$stmtCom = $pdo->prepare("SELECT COUNT(*) FROM coordination_commentaires WHERE fiche_id = ?");
$stmtCom->execute([$fiche_id]);
$verrou_coordination = $stmtCom->fetchColumn() > 0;

$verrou_total = $verrou_superviseur || $verrou_coordination;

// Charger items
$stmtItems = $pdo->prepare("SELECT * FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC");
$stmtItems->execute([$fiche_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Résumés fiche (6 champs)
$resume_fields = [
  'resume_reussite','resume_amelioration','resume_problemes',
  'resume_competence_a_developper','resume_competence_a_utiliser','resume_soutien'
];
$resume_count = 0;
foreach ($resume_fields as $f) if (!empty($fiche[$f])) $resume_count++;
$resume_total = count($resume_fields);
?>

<div class="d-flex align-items-start gap-3 mb-3">
  <div>
    <h4 style="color:#3D74B9;"><i class="bi bi-file-earmark-text me-2"></i> Fiche d'évaluation</h4>
    <div class="text-muted">Bienvenue — voici la synthèse de vos objectifs pour la période <strong><?= htmlspecialchars($fiche['periode']) ?></strong>. Vous pouvez compléter ou modifier les résumés si la fiche n'est pas verrouillée par une évaluation ou un commentaire de coordination.</div>
  </div>
  <div class="ms-auto">
    <a href="fiches-evaluation-voir.php" class="btn btn-sm btn-outline-secondary">Voir vos fiches précédentes</a>
  </div>
</div>

<div class="card mb-3 shadow-sm">
  <div class="card-body">
    <div class="row">
      <div class="col-md-8">
        <p><strong>Projet :</strong> <?= htmlspecialchars($fiche['nom_projet']) ?></p>
        <p><strong>Poste :</strong> <?= htmlspecialchars($fiche['poste']) ?></p>
        <p><strong>Date commencement :</strong> <?= htmlspecialchars($fiche['date_commencement']) ?></p>
        <p><strong>Superviseur :</strong> <?= htmlspecialchars($fiche['sup_nom'].' '.$fiche['sup_post_nom']) ?> — <?= htmlspecialchars($fiche['sup_fonction']) ?></p>
      </div>
      <div class="col-md-4 text-end">
        <?php $badge = $fiche['statut'] === 'complet' ? 'success' : ($fiche['statut'] === 'attente' ? 'warning' : 'secondary'); ?>
        <div class="mb-2"><span class="badge bg-<?= $badge ?> p-2"><i class="bi bi-circle-fill me-1"></i><?= ucfirst($fiche['statut']) ?></span></div>
        <div class="small text-muted">Résumés complétés: <strong><?= $resume_count ?>/<?= $resume_total ?></strong></div>
        <?php if ($verrou_total): ?>
          <div class="alert alert-danger mt-2"><i class="bi bi-lock-fill me-1"></i> Verrouillée (évaluation ou commentaire présent)</div>
        <?php else: ?>
          <div class="text-end mt-2">
            <a href="objectifs-modifier.php?id=<?= $fiche_id ?>" class="btn btn-sm btn-warning me-1"><i class="bi bi-pencil-square"></i> Modifier fiche</a>
            <a href="objectifs-supprimer.php?id=<?= $fiche_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette fiche ?')"><i class="bi bi-trash"></i> Supprimer</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-8">
    <h5 style="color:#3D74B9;"><i class="bi bi-list-check me-2"></i> Objectifs</h5>

    <?php if (count($items) === 0): ?>
      <div class="alert alert-warning">Aucun objectif défini sur cette fiche.</div>
    <?php else: ?>
      <div class="list-group mb-3">
        <?php foreach ($items as $idx => $it):
          $resume_item = $it['resume'] ?? '';
          $incomplete = empty($resume_item);

          // Si tu as évaluations/coord commentaires par item, tu peux les récupérer ici (exemples)
          // $stmtItemEval = $pdo->prepare("SELECT COUNT(*) FROM evaluations_items WHERE item_id = ?");
          // $stmtItemEval->execute([$it['id']]); $itemEval = $stmtItemEval->fetchColumn() > 0;
        ?>
        <div class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <div><strong><?= ($idx+1) . '. ' . htmlspecialchars($it['contenu']) ?></strong></div>
            <?php if (!$incomplete): ?>
              <div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($resume_item)) ?></div>
            <?php else: ?>
              <div class="small text-muted mt-1">Aucun résumé pour cet objectif.</div>
            <?php endif; ?>
          </div>

          <div class="text-end">
            <?php if ($incomplete): ?>
              <span class="badge bg-secondary blinking d-block mb-2">Incomplet</span>
            <?php else: ?>
              <span class="badge bg-success d-block mb-2">Complété</span>
            <?php endif; ?>

            <?php if (!$verrou_total): ?>
              <a href="objectif-item-modifier.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary mb-1"><i class="bi bi-pencil"></i> Modifier</a>
              <a href="objectif-item-supprimer.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cet objectif ?')"><i class="bi bi-trash"></i></a>
            <?php else: ?>
              <div class="small text-muted">Action verrouillée</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-md-4">
    <h5 style="color:#3D74B9;"><i class="bi bi-card-text me-2"></i> Résumés de la fiche</h5>

    <form method="post" action="fiche-evaluation-save-resumes.php">
      <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">
      <?php foreach ($resume_fields as $field):
        $label = str_replace('resume_','', $field);
        $placeholder = "Remplissez: " . ucwords(str_replace('_',' ',$label));
        $value = htmlspecialchars($fiche[$field] ?? '');
      ?>
        <div class="mb-2">
          <label class="form-label small text-muted"><?= ucwords(str_replace('_',' ', $label)) ?></label>
          <textarea name="<?= $field ?>" class="form-control form-control-sm" rows="2" <?= $verrou_total ? 'readonly' : '' ?> placeholder="<?= $placeholder ?>"><?= $value ?></textarea>
        </div>
      <?php endforeach; ?>

      <div class="d-grid gap-2 mt-2">
        <?php if ($verrou_total): ?>
          <button class="btn btn-secondary" disabled>Modification bloquée</button>
        <?php else: ?>
          <button class="btn btn-fosip" type="submit">Enregistrer les résumés</button>
        <?php endif; ?>
      </div>
    </form>

    <hr>
    <div class="small text-muted mt-2">
      <strong>Statut évaluation</strong><br>
      <?= $verrou_superviseur ? '<span class="badge bg-success">Superviseur a évalué</span>' : '<span class="badge bg-secondary">Pas encore évalué</span>' ?><br>
      <?= $verrou_coordination ? '<span class="badge bg-info text-dark mt-1">Coordination a commenté</span>' : '<span class="badge bg-light text-muted mt-1">Pas de commentaire coordination</span>' ?>
    </div>
  </div>
</div>

<style>
.blinking { animation: blink 1s infinite; }
@keyframes blink { 0%{opacity:1}50%{opacity:.3}100%{opacity:1} }
.btn-fosip { background:#3D74B9; color:#fff; border-radius:6px; }
.btn-fosip:hover { background:#2f5f98; }
</style>

<script>
document.querySelectorAll('.blinking').forEach(el=> el.style.animation='blink 1s infinite');
</script>

    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
