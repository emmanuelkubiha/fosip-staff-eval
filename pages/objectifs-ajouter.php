<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'fiches-evaluation-voir.php'; // Pour activer le menu dans sidebar
include('../includes/header.php');
?>

<div class="row">
  <div class="col-md-3 sidebar-col">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9 content-col d-flex justify-content-center align-items-start">
    <div class="container py-4" style="max-width: 800px;">

<?php
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$success = false;

// Chargement des superviseurs (tous sauf admin et moi-même, avec photo)
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, nom, post_nom, fonction, photo FROM users WHERE role != 'admin' AND id != ? ORDER BY nom, post_nom");
$stmt->execute([$user_id]);
$superviseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom_projet = $_POST['nom_projet'];
  $poste = $_POST['poste'];
  $date_commencement = $_POST['date_commencement'];
  $periode = $_POST['periode'];
  $superviseur_id = $_POST['superviseur_id'];
  $statut = 'encours';

  $stmt = $pdo->prepare("INSERT INTO objectifs (user_id, nom_projet, poste, date_commencement, periode, superviseur_id, statut)
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([$user_id, $nom_projet, $poste, $date_commencement, $periode, $superviseur_id, $statut]);
  $fiche_id = $pdo->lastInsertId();

  $objectifs = $_POST['objectifs'] ?? [];
  foreach ($objectifs as $index => $obj) {
    $ordre = $index + 1;
    $stmt = $pdo->prepare("INSERT INTO objectifs_items (fiche_id, contenu, ordre) VALUES (?, ?, ?)");
    $stmt->execute([$fiche_id, $obj, $ordre]);
  }

  $success = true;
}
?>

<style>
  /* Cards superviseur sélection */
  .user-card.selected {
    border: 2px solid #3D74B9 !important;
    box-shadow: 0 0 0 2px #3D74B933 !important;
  }
  .user-avatar { width: 56px; height: 56px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  .fosip-label { font-weight: 600; color: #3D74B9; }
  .btn-fosip {
    background-color: #3D74B9;
    color: white;
    border-radius: 20px;
    transition: background-color 0.3s ease;
  }
  .btn-fosip:hover {
    background-color: #FDC300;
    color: #000;
  }
  .step-section { display: none; }
  .step-section.active { display: block; animation: fadeIn 0.5s ease-in-out; }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  #success-message {
    display: none;
    text-align: center;
    padding: 40px 20px;
  }
</style>

<h4 class="mb-3 text-center" style="color: #3D74B9;">
  <i class="bi bi-pencil-square me-2"></i> Remplissez vos objectifs pour ce mois
</h4>

<?php if ($success): ?>
  <div id="success-message">
    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
    <h4 class="mt-3 text-success">Félicitations !</h4>
    <p class="text-muted">Vous avez bien défini vos objectifs pour ce mois.</p>
    <p class="text-secondary">Revenez bientôt pour les compléter et nous dire comment vous les avez atteints.<br>Nous sommes impatients de voir vos retours !</p>
    <a href="fiches-evaluation-voir.php" class="btn btn-fosip mt-3">
      <i class="bi bi-arrow-right-circle me-1"></i> Voir mes objectifs
    </a>
  </div>
  <script>
    document.querySelectorAll('form, .step-section').forEach(el => el.style.display = 'none');
    document.getElementById('success-message').style.display = 'block';
    setTimeout(() => window.location.href = 'fiches-evaluation-voir.php', 6000);
  </script>
<?php else: ?>

<form method="post" id="objectifForm" class="row g-4">

  <!-- Étape 1 : Projet & Poste -->
  <div class="step-section active" id="step1">
    <div class="col-12">
      <label class="form-label fosip-label">Nom du Projet</label>
      <input type="text" name="nom_projet" class="form-control" required placeholder="Ex : Appui à la formation des jeunes">
      <small class="text-muted">Indiquez le nom du projet ou programme auquel vous êtes affecté.</small>
    </div>
    <div class="col-12 mt-3">
      <label class="form-label fosip-label">Titre du Poste</label>
      <input type="text" name="poste" class="form-control" required placeholder="Ex : Chargé de communication">
      <small class="text-muted">Fonction que vous occupez dans le cadre de ce projet.</small>
    </div>
    <div class="col-12 text-end mt-3">
      <div class="d-flex justify-content-end gap-2">
        <!-- Pas de bouton retour sur la première étape -->
        <button type="button" class="btn btn-fosip" onclick="nextStep(2)">Suivant</button>
      </div>
    </div>
  </div>

  <!-- Étape 2 : Dates -->
  <div class="step-section" id="step2">
    <div class="col-12">
      <label class="form-label fosip-label">Date de Commencement</label>
      <input type="date" name="date_commencement" class="form-control" required>
      <small class="text-muted">Date à laquelle vous avez commencé à travailler sur ce projet.</small>
    </div>
    <div class="col-12 mt-3">
      <label class="form-label fosip-label">Période d’Évaluation</label>
      <input type="month" name="periode" class="form-control" required>
      <small class="text-muted">Choisissez le mois concerné par cette fiche d’objectifs.</small>
    </div>
    <div class="col-12 text-end mt-3">
      <div class="d-flex justify-content-between gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="prevStep(1)"><i class="bi bi-arrow-left"></i> Retour</button>
        <button type="button" class="btn btn-fosip" onclick="nextStep(3)">Suivant</button>
      </div>
    </div>
  </div>

  <!-- Étape 3 : Superviseur -->
  <div class="step-section" id="step3">
    <div class="col-12 mb-3">
      <label class="form-label fosip-label">Sélectionnez votre superviseur</label>
      <input type="text" id="search-superviseur" class="form-control mb-3" placeholder="Rechercher par nom, fonction...">
      <div id="superviseur-list" class="row g-3">
        <?php foreach ($superviseurs as $s):
          $hasPhoto = !empty($s['photo']);
          $photo = $hasPhoto ? "../assets/img/profiles/{$s['photo']}" : null;
          $nomComplet = trim(($s['nom'] ?? '').' '.($s['post_nom'] ?? ''));
        ?>
        <div class="col-md-6 col-lg-4 superviseur-card-item">
          <label class="card user-card h-100 shadow-sm p-3 mb-0 w-100" style="cursor:pointer;">
            <input type="radio" name="superviseur_id" value="<?= $s['id'] ?>" class="d-none">
            <div class="d-flex align-items-center mb-2">
              <?php if ($hasPhoto): ?>
                <img src="<?= htmlspecialchars($photo) ?>" class="user-avatar rounded-circle me-3" style="width:56px;height:56px;object-fit:cover;">
              <?php else: ?>
                <span class="user-avatar rounded-circle me-3 d-flex align-items-center justify-content-center bg-light border" style="width:56px;height:56px;font-size:2rem;color:#b0b0b0;">
                  <i class="bi bi-person-circle"></i>
                </span>
              <?php endif; ?>
              <div>
                <div class="fw-bold" style="font-size:1.1rem; color:#212529;">
                  <?= htmlspecialchars($nomComplet) ?>
                </div>
                <div class="text-muted" style="font-size:0.95rem;">
                  <?= htmlspecialchars($s['fonction'] ?? '') ?>
                </div>
              </div>
            </div>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <small class="text-muted">Votre superviseur direct pour cette période d’évaluation.</small>
    </div>
    <div class="col-12 d-flex justify-content-between gap-2 mt-3">
      <button type="button" class="btn btn-outline-secondary" onclick="prevStep(2)"><i class="bi bi-arrow-left"></i> Retour</button>
      <button type="button" class="btn btn-fosip" onclick="nextStep(4)">Suivant</button>
    </div>
  </div>

  <!-- Étape 4 : Objectifs -->
  <div class="step-section" id="step4">
    <h5 class="text-center" style="color: #3D74B9;">
      <i class="bi bi-list-check me-2"></i> Objectifs du mois
    </h5>
    <p class="text-muted text-center">Décrivez vos objectifs pour ce mois. Chaque objectif doit être clair et atteignable.</p>
    <div id="objectifs-container">
      <div class="objectif-item mb-2">
        <textarea name="objectifs[]" class="form-control" rows="2" placeholder="Objectif 1"></textarea>
      </div>
    </div>
    <div class="text-start mt-2">
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterObjectif()">
        <i class="bi bi-plus-circle me-1"></i> Ajouter un objectif
      </button>
    </div>
    <div class="col-12 d-flex justify-content-between gap-2 mt-4">
      <button type="button" class="btn btn-outline-secondary" onclick="prevStep(3)"><i class="bi bi-arrow-left"></i> Retour</button>
      <button type="submit" class="btn btn-fosip px-4">
        <i class="bi bi-save me-1"></i> Enregistrer
      </button>
    </div>
  </div> <!-- Fin de l'étape 4 -->
</form>

<?php endif; ?> <!-- Fin du bloc conditionnel $success -->

</div> <!-- fin container -->
</div> <!-- fin col-md-9 -->
</div> <!-- fin row -->

<script>
// Fonction pour revenir à l'étape précédente
function prevStep(step) {
  document.querySelectorAll('.step-section').forEach(s => s.classList.remove('active'));
  document.getElementById('step' + step).classList.add('active');
}
// Sélection visuelle de la card superviseur et recherche dynamique
document.addEventListener('DOMContentLoaded', function () {
  const cards = document.querySelectorAll('.superviseur-card-item label');
  cards.forEach(card => {
    card.addEventListener('click', function () {
      cards.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
    });
  });
  // Recherche dynamique
  const searchInput = document.getElementById('search-superviseur');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const val = searchInput.value.toLowerCase();
      document.querySelectorAll('.superviseur-card-item').forEach(item => {
        const txt = item.textContent.toLowerCase();
        item.style.display = txt.includes(val) ? '' : 'none';
      });
    });
  }
  // Empêcher le passage à l'étape suivante sans superviseur sélectionné
  const btnSuivant = document.querySelector('#step3 .btn-fosip');
  if (btnSuivant) {
    btnSuivant.addEventListener('click', function (e) {
      const checked = document.querySelector('input[name="superviseur_id"]:checked');
      if (!checked) {
        e.preventDefault();
        if (window.globalShowToast) {
          globalShowToast('danger', 'Veuillez sélectionner votre superviseur avant de continuer.');
        } else {
          alert('Veuillez sélectionner votre superviseur avant de continuer.');
        }
        return false;
      }
      nextStep(4);
    });
  }
});
// Vérification superviseur obligatoire avant soumission (toast Bootstrap)
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('objectifForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      const superviseur = form.querySelector('select[name="superviseur_id"]');
      if (superviseur && !superviseur.value) {
        e.preventDefault();
        if (window.globalShowToast) {
          globalShowToast('danger', 'Veuillez sélectionner un superviseur avant de valider.');
        } else {
          alert('Veuillez sélectionner un superviseur avant de valider.');
        }
      }
    });
  }
});
function nextStep(step) {
  document.querySelectorAll('.step-section').forEach(s => s.classList.remove('active'));
  document.getElementById('step' + step).classList.add('active');
}

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
