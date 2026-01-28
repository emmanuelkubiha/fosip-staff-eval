<?php
// pages/fiche-evaluation-modifier.php
// Modifier une fiche d'évaluation (GET affiche, POST traite la sauvegarde)
// Affichage du résultat via toast Bootstrap (info-bulle JS).


if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php'); // $pdo attendu
$current_page = 'mes-objectifs.php';

// ---------- helpers ----------
function tableExists(PDO $pdo, string $table): bool {
  try { $st = $pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch (Throwable $e) { return false; }
}
function hasLockEntry(PDO $pdo, string $table, int $fiche_id, string $col = 'fiche_id'): bool {
  if (!tableExists($pdo, $table)) return false;
  try { $st = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?"); $st->execute([$fiche_id]); return (int)$st->fetchColumn() > 0; }
  catch (Throwable $e) { return false; }
}
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
  return $_SESSION['csrf_token'];
}
function csrf_check($token): bool {
  return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------- auth ----------
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php'); exit;
}
$user_id = (int)$_SESSION['user_id'];

/* ---------- POST : sauvegarde ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fiche_id = intval($_POST['fiche_id'] ?? 0);
  $nom_projet = trim((string)($_POST['nom_projet'] ?? ''));
  $poste = trim((string)($_POST['poste'] ?? ''));
  $date_commencement = trim((string)($_POST['date_commencement'] ?? ''));
  $superviseur_id = intval($_POST['superviseur_id'] ?? 0);
  $resume_reussite = trim((string)($_POST['resume_reussite'] ?? ''));
  $resume_amelioration = trim((string)($_POST['resume_amelioration'] ?? ''));
  $resume_problemes = trim((string)($_POST['resume_problemes'] ?? ''));
  $resume_competence_a_developper = trim((string)($_POST['resume_competence_a_developper'] ?? ''));
  $resume_competence_a_utiliser = trim((string)($_POST['resume_competence_a_utiliser'] ?? ''));
  $resume_soutien = trim((string)($_POST['resume_soutien'] ?? ''));
  $token = $_POST['csrf_token'] ?? '';

  if ($fiche_id <= 0) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fiche invalide.'];
    header('Location: fiches-evaluation-voir.php'); exit;
  }
  if (!csrf_check($token)) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Jeton de sécurité invalide. Rechargez et réessayez.'];
    header("Location: fiche-evaluation-modifier.php?id={$fiche_id}"); exit;
  }

  // Charger fiche et vérifier propriété
  $st = $pdo->prepare("SELECT id, user_id, periode FROM objectifs WHERE id = ? LIMIT 1");
  $st->execute([$fiche_id]);
  $fiche = $st->fetch(PDO::FETCH_ASSOC);
  if (!$fiche || (int)$fiche['user_id'] !== $user_id) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fiche introuvable ou non autorisée.'];
    header('Location: fiches-evaluation-voir.php'); exit;
  }

  // Vérification verrous
  $stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
  $stmtEval->execute([$user_id, $fiche['periode']]);
  $verrou_superviseur = $stmtEval->fetchColumn() > 0;
  $verrou_coord = hasLockEntry($pdo, 'coordination_commentaires', $fiche_id, 'fiche_id');
  $verrou_ci = hasLockEntry($pdo, 'competences_individuelles', $fiche_id, 'fiche_id');
  $verrou_cg = hasLockEntry($pdo, 'competences_de_gestion', $fiche_id, 'fiche_id');
  $verrou_ql = hasLockEntry($pdo, 'qualites_de_leader', $fiche_id, 'fiche_id');

  if ($verrou_superviseur || $verrou_coord || $verrou_ci || $verrou_cg || $verrou_ql) {
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'Modification impossible : la fiche est verrouillée par une évaluation ou des commentaires.'];
    header("Location: fiche-evaluation.php?id={$fiche_id}&msg=verrouille"); exit;
  }

  // Préparer update sans updated_at si colonne absente
  $colUpdated = false;
  try {
    $chk = $pdo->prepare("SHOW COLUMNS FROM `objectifs` LIKE 'updated_at'"); $chk->execute(); $colUpdated = (bool)$chk->fetch();
  } catch (Throwable $e) { $colUpdated = false; }

  if ($colUpdated) {
    $sql = "UPDATE objectifs SET nom_projet = ?, poste = ?, date_commencement = ?, superviseur_id = ?, resume_reussite = ?, resume_amelioration = ?, resume_problemes = ?, resume_competence_a_developper = ?, resume_competence_a_utiliser = ?, resume_soutien = ?, updated_at = NOW() WHERE id = ?";
    $params = [$nom_projet, $poste, $date_commencement, $superviseur_id, $resume_reussite, $resume_amelioration, $resume_problemes, $resume_competence_a_developper, $resume_competence_a_utiliser, $resume_soutien, $fiche_id];
  } else {
    $sql = "UPDATE objectifs SET nom_projet = ?, poste = ?, date_commencement = ?, superviseur_id = ?, resume_reussite = ?, resume_amelioration = ?, resume_problemes = ?, resume_competence_a_developper = ?, resume_competence_a_utiliser = ?, resume_soutien = ? WHERE id = ?";
    $params = [$nom_projet, $poste, $date_commencement, $superviseur_id, $resume_reussite, $resume_amelioration, $resume_problemes, $resume_competence_a_developper, $resume_competence_a_utiliser, $resume_soutien, $fiche_id];
  }

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Fiche mise à jour avec succès.'];
    header("Location: fiche-evaluation.php?id={$fiche_id}"); exit;
  } catch (Throwable $e) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Erreur lors de la sauvegarde. ' . $e->getMessage()];
    header("Location: fiche-evaluation-modifier.php?id={$fiche_id}"); exit;
  }
}

/* ---------- GET : afficher formulaire prérempli ---------- */
$fiche_id = intval($_GET['id'] ?? 0);
if ($fiche_id <= 0) {
  echo '<div class="alert alert-warning">Fiche non spécifiée. <a href="fiches-evaluation-voir.php">Retour</a></div>';
  include('../includes/footer.php'); exit;
}

// Inclure le header UNIQUEMENT après toutes les redirections
include('../includes/header.php'); // doit inclure Bootstrap CSS/JS bundle + Bootstrap Icons

// Charger fiche
$st = $pdo->prepare("SELECT o.*, u.nom AS sup_nom, u.post_nom AS sup_post_nom FROM objectifs o LEFT JOIN users u ON o.superviseur_id = u.id WHERE o.id = ? AND o.user_id = ? LIMIT 1");
$st->execute([$fiche_id, $user_id]);
$fiche = $st->fetch(PDO::FETCH_ASSOC);
if (!$fiche) {
  echo '<div class="alert alert-danger">Fiche introuvable ou non autorisée. <a href=\"fiches-evaluation-voir.php\">Retour</a></div>';
  include('../includes/footer.php'); exit;
}

// Verrous visibles dans l'interface
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $fiche['periode']]);
$verrou_superviseur = $stmtEval->fetchColumn() > 0;
$verrou_coord = hasLockEntry($pdo, 'coordination_commentaires', $fiche_id, 'fiche_id');
$verrou_ci = hasLockEntry($pdo, 'competences_individuelles', $fiche_id, 'fiche_id');
$verrou_cg = hasLockEntry($pdo, 'competences_de_gestion', $fiche_id, 'fiche_id');
$verrou_ql = hasLockEntry($pdo, 'qualites_de_leader', $fiche_id, 'fiche_id');
$verrou_total = $verrou_superviseur || $verrou_coord || $verrou_ci || $verrou_cg || $verrou_ql;

// Récupérer superviseurs : filtrer par role = 'superviseur' ou 'coordination'
$superviseurs = [];
try {
  $sstmt = $pdo->prepare("SELECT id, nom, post_nom, fonction FROM users WHERE role IN ('superviseur', 'coordination') ORDER BY nom, post_nom");
  $sstmt->execute();
  $superviseurs = $sstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // fallback : récupérer tout le monde si la colonne role n'existe pas
  $sstmt = $pdo->query("SELECT id, nom, post_nom, fonction FROM users ORDER BY nom, post_nom LIMIT 200");
  $superviseurs = $sstmt->fetchAll(PDO::FETCH_ASSOC);
}

$token = csrf_token();
?>

<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-lg-9" style="padding-left:7.5rem;">
    <div class="container mt-3">
      <h3><i class="bi bi-pencil-square me-1"></i> Modifier la fiche d'évaluation</h3>

      <!-- Toast container (Bootstrap) -->
      <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>
      </div>

      <?php
        // We use session['toast'] to pass a message after redirect; displayed by JS below
        if (!empty($_SESSION['flash_error'])) { echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['flash_error']).'</div>'; unset($_SESSION['flash_error']); }
        if (!empty($_SESSION['flash_success'])) { echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['flash_success']).'</div>'; unset($_SESSION['flash_success']); }
      ?>

      <?php if ($verrou_total): ?>
        <div class="alert alert-warning">La fiche est verrouillée (supervision/coordination/compétences) ; les modifications sont désactivées.</div>
      <?php endif; ?>

      <form method="post" action="fiche-evaluation-modifier.php" class="row g-3 needs-validation" novalidate>
        <input type="hidden" name="fiche_id" value="<?= htmlspecialchars($fiche_id) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

        <div class="col-md-8">
          <label class="form-label"><strong>Nom du projet</strong></label>
          <input name="nom_projet" class="form-control" value="<?= htmlspecialchars($fiche['nom_projet'] ?? '') ?>" <?= $verrou_total ? 'readonly' : '' ?> required>

          <label class="form-label mt-2"><strong>Poste</strong></label>
          <input name="poste" class="form-control" value="<?= htmlspecialchars($fiche['poste'] ?? '') ?>" <?= $verrou_total ? 'readonly' : '' ?> required>

          <label class="form-label mt-2"><strong>Date de commencement</strong></label>
          <input type="date" name="date_commencement" class="form-control" value="<?= htmlspecialchars($fiche['date_commencement'] ?? '') ?>" <?= $verrou_total ? 'readonly' : '' ?>>

          <label class="form-label mt-2"><strong>Superviseur</strong></label>
          <select name="superviseur_id" class="form-select" <?= $verrou_total ? 'disabled' : '' ?>>
            <option value="0">-- non défini --</option>
            <?php foreach ($superviseurs as $s): ?>
              <option value="<?= $s['id'] ?>" <?= ($s['id'] == $fiche['superviseur_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['nom'].' '.$s['post_nom'].' — '.$s['fonction']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <hr><h5>Résumés (synthèse)</h5>

          <div class="mb-2">
            <label class="form-label"><strong>Réussite</strong></label>
            <textarea name="resume_reussite" class="form-control" rows="3" <?= $verrou_total ? 'readonly' : '' ?>><?= htmlspecialchars($fiche['resume_reussite'] ?? '') ?></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label"><strong>Amélioration</strong></label>
            <textarea name="resume_amelioration" class="form-control" rows="3" <?= $verrou_total ? 'readonly' : '' ?>><?= htmlspecialchars($fiche['resume_amelioration'] ?? '') ?></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label"><strong>Problèmes</strong></label>
            <textarea name="resume_problemes" class="form-control" rows="3" <?= $verrou_total ? 'readonly' : '' ?>><?= htmlspecialchars($fiche['resume_problemes'] ?? '') ?></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label"><strong>Compétence à développer</strong></label>
            <textarea name="resume_competence_a_developper" class="form-control" rows="2" <?= $verrou_total ? 'readonly' : '' ?>><?= htmlspecialchars($fiche['resume_competence_a_developper'] ?? '') ?></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label"><strong>Compétence à utiliser</strong></label>
            <textarea name="resume_competence_a_utiliser" class="form-control" rows="2" <?= $verrou_total ? 'readonly' : '' ?>><?= htmlspecialchars($fiche['resume_competence_a_utiliser'] ?? '') ?></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label"><strong>Soutien souhaité</strong></label>
            <textarea name="resume_soutien" class="form-control" rows="2" <?= $verrou_total ? 'readonly' : '' ?>><?= htmlspecialchars($fiche['resume_soutien'] ?? '') ?></textarea>
          </div>

        </div> <!-- /.col-md-8 -->

        <div class="col-md-4">
          <div class="card p-3 mb-3">
            <p><strong>Informations</strong></p>
            <p class="small text-muted mb-1"><strong>Période :</strong> <?= htmlspecialchars($fiche['periode']) ?></p>
            <p class="small text-muted mb-1"><strong>Statut :</strong> <?= htmlspecialchars($fiche['statut']) ?></p>
            <p class="small text-muted"><strong>Superviseur actuel :</strong><br><?= htmlspecialchars($fiche['sup_nom'].' '.$fiche['sup_post_nom'] ?? '') ?></p>
          </div>

          <?php if ($verrou_total): ?>
            <div class="alert alert-warning small">La fiche est verrouillée ; les modifications sont désactivées.</div>
            <a href="fiche-evaluation.php?id=<?= $fiche_id ?>" class="btn btn-outline-secondary w-100">Retour</a>
          <?php else: ?>
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary">Enregistrer</button>
              <a href="fiche-evaluation.php?id=<?= $fiche_id ?>" class="btn btn-outline-secondary">Annuler</a>
            </div>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Bootstrap validation
(function(){
  'use strict';
  var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function(form){
    form.addEventListener('submit', function(event){
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add('was-validated');
    }, false);
  });
})();

// Toast helper : affiche un toast Bootstrap dans #toast-container
function showToast(type, message, delay = 4000) {
  var container = document.getElementById('toast-container');
  if (!container) return;
  var toastId = 't' + Date.now();
  var bg = 'bg-primary text-white';
  if (type === 'success') bg = 'bg-success text-white';
  if (type === 'danger') bg = 'bg-danger text-white';
  if (type === 'warning') bg = 'bg-warning text-dark';
  var html = `
    <div id="${toastId}" class="toast ${bg}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}">
      <div class="d-flex">
        <div class="toast-body">
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
      </div>
    </div>`;
  container.insertAdjacentHTML('beforeend', html);
  var el = document.getElementById(toastId);
  var bs = new bootstrap.Toast(el);
  bs.show();
  // remove after hidden
  el.addEventListener('hidden.bs.toast', function(){ el.remove(); });
}

// Si la session contient un message toast, affiche-le (message réglé côté serveur)
<?php if (!empty($_SESSION['toast'])): 
  $t = $_SESSION['toast']; $type = htmlspecialchars($t['type']); $msg = htmlspecialchars($t['message']);
  // clear after reading
  unset($_SESSION['toast']);
?>
  document.addEventListener('DOMContentLoaded', function(){ showToast('<?= $type ?>', '<?= $msg ?>'); });
<?php endif; ?>
</script>

<?php include('../includes/footer.php'); ?>
