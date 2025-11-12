<?php
// pages/competence-profile.php
// Mode classique (POST) — UI + server handling (save / delete) sans AJAX.
// Ajouts : toasts JavaScript pour confirmations (ajout / modification / suppression) et modal JS pour confirmer suppression.
// Prérequis : includes/header.php initialise session et $_SESSION['csrf_token'] et charge Bootstrap 5 + Icons.
// includes/db.php doit exposer $pdo.
// Table attendue : competence_votre_profil(id, user_id, competence, created_at, updated_at)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
$current_page = 'competences-profile';
include __DIR__ . '/../includes/header.php';

// ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// flash helper (store one message at a time)
function set_flash($type, $message) {
  $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function get_flash() {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}

// require login
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf_in = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], (string)$csrf_in)) {
    set_flash('danger', 'Token CSRF invalide.');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
  }

  try {
    if ($action === 'save') {
      $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
      $competence = trim((string)($_POST['competence'] ?? ''));
      if ($competence === '') throw new RuntimeException('Le champ Compétence est requis.');

      if ($id && $id > 0) {
        // update
        $st = $pdo->prepare("SELECT user_id FROM competence_votre_profil WHERE id = ? LIMIT 1");
        $st->execute([$id]); $owner = $st->fetchColumn();
        if (!$owner) throw new RuntimeException('Compétence introuvable.');
        if ((int)$owner !== $user_id) throw new RuntimeException('Non autorisé.');
        $up = $pdo->prepare("UPDATE competence_votre_profil SET competence = ?, updated_at = NOW() WHERE id = ?");
        $up->execute([$competence, $id]);
        set_flash('success', 'Compétence modifiée.');
      } else {
        // insert
        $ins = $pdo->prepare("INSERT INTO competence_votre_profil (user_id, competence, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $ins->execute([$user_id, $competence]);
        set_flash('success', 'Compétence ajoutée.');
      }
      header('Location: ' . $_SERVER['PHP_SELF']);
      exit;
    }

    if ($action === 'delete') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id <= 0) throw new RuntimeException('Identifiant invalide.');
      $st = $pdo->prepare("SELECT user_id FROM competence_votre_profil WHERE id = ? LIMIT 1");
      $st->execute([$id]); $owner = $st->fetchColumn();
      if (!$owner) throw new RuntimeException('Compétence introuvable.');
      if ((int)$owner !== $user_id) throw new RuntimeException('Non autorisé.');
      $del = $pdo->prepare("DELETE FROM competence_votre_profil WHERE id = ?");
      $del->execute([$id]);
      set_flash('success', 'Compétence supprimée.');
      header('Location: ' . $_SERVER['PHP_SELF']);
      exit;
    }

    // unknown action
    set_flash('warning', 'Action inconnue.');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;

  } catch (Throwable $e) {
    set_flash('danger', $e->getMessage());
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
  }
}

// Fetch list for display
$st = $pdo->prepare("SELECT id, competence, created_at, updated_at FROM competence_votre_profil WHERE user_id = ? ORDER BY id DESC");
$st->execute([$user_id]);
$competences = $st->fetchAll(PDO::FETCH_ASSOC);

// get flash for JS to show toast
$flash = get_flash();
?>

<style>
  /* ========================================
     THEME FOSIP - Override Bootstrap Primary
     Objectif : Remplacer la couleur primary de Bootstrap (#0d6efd) 
                par le bleu FOSIP (#3D74B9) sur TOUS les éléments
     ======================================== */
  
  /* ----- BOUTONS PRIMARY ----- */
  /* Override du bouton .btn-primary de Bootstrap
     On force le fond, la bordure et le texte avec !important
     pour écraser les styles par défaut de Bootstrap */
  .btn-primary {
    background-color: #3D74B9 !important; /* Fond bleu FOSIP */
    border-color: #3D74B9 !important;     /* Bordure bleue FOSIP */
    color: white !important;               /* Texte blanc */
  }
  
  /* États hover, focus et active du bouton primary
     Version légèrement plus foncée (#2a5a94) pour le feedback visuel */
  .btn-primary:hover,
  .btn-primary:focus,
  .btn-primary:active {
    background-color: #2a5a94 !important; /* Bleu plus foncé au survol */
    border-color: #2a5a94 !important;
    color: white !important;
  }
  
  /* ----- BOUTONS OUTLINE PRIMARY ----- */
  /* Override du bouton .btn-outline-primary de Bootstrap
     Style inversé : fond transparent, bordure et texte en bleu FOSIP */
  .btn-outline-primary {
    border-color: #3D74B9 !important;      /* Bordure bleue FOSIP */
    color: #3D74B9 !important;             /* Texte bleu FOSIP */
    background-color: transparent !important; /* Fond transparent */
  }
  
  /* États hover, focus et active du bouton outline primary
     Inversion : fond bleu FOSIP, texte blanc */
  .btn-outline-primary:hover,
  .btn-outline-primary:focus,
  .btn-outline-primary:active {
    background-color: #3D74B9 !important; /* Fond bleu FOSIP */
    border-color: #3D74B9 !important;     /* Bordure bleue FOSIP */
    color: white !important;               /* Texte blanc */
  }
  
  /* Animation fluide pour les boutons outline (0.3s) */
  .btn-outline-primary {
    transition: all 0.3s ease;
  }
  
  /* ----- TEXTE PRIMARY ----- */
  /* Override de la classe .text-primary de Bootstrap
     Applique le bleu FOSIP à tous les textes avec cette classe */
  .text-primary {
    color: #3D74B9 !important;
  }
  
  /* Icône spécifique pour le titre de la page (Bootstrap Icons) */
  .bi-list-check {
    color: #3D74B9 !important;
  }
  
  /* ----- BADGES PRIMARY ----- */
  /* Override du badge .badge.bg-primary de Bootstrap
     Applique le bleu FOSIP comme fond avec texte blanc */
  .badge.bg-primary {
    background-color: #3D74B9 !important;
    color: white !important;
  }
  
  /* ----- BORDURES PRIMARY ----- */
  /* Override de la classe .border-primary de Bootstrap
     Applique le bleu FOSIP aux bordures */
  .border-primary {
    border-color: #3D74B9 !important;
  }
  
  /* Classe custom pour bordure gauche épaisse (5px)
     Utilisée sur les items de liste pour un accent visuel */
  .border-left-primary {
    border-left-color: #3D74B9 !important;
  }
  
  /* ----- TOASTS PRIMARY ----- */
  /* Override du toast .toast.bg-primary de Bootstrap
     Applique le bleu FOSIP pour les notifications */
  .toast.bg-primary {
    background-color: #3D74B9 !important;
    color: white !important;
  }
  
  /* ----- LIENS PRIMARY ----- */
  /* Override de la classe .link-primary de Bootstrap
     Applique le bleu FOSIP aux liens */
  .link-primary {
    color: #3D74B9 !important;
  }
  
  /* États hover et focus des liens primary
     Version plus foncée pour le feedback visuel */
  .link-primary:hover,
  .link-primary:focus {
    color: #2a5a94 !important;
  }
  
  /* ----- ALERTES PRIMARY ----- */
  /* Override de l'alerte .alert-primary de Bootstrap
     Fond bleu clair (transparence 10%), bordure et texte bleu FOSIP */
  .alert-primary {
    background-color: rgba(61, 116, 185, 0.1) !important; /* Fond bleu clair */
    border-color: #3D74B9 !important;                      /* Bordure bleue */
    color: #2a5a94 !important;                             /* Texte bleu foncé */
  }
  
  /* ----- PROGRESS BAR PRIMARY ----- */
  /* Override de la barre de progression .progress-bar.bg-primary
     Applique le bleu FOSIP comme fond */
  .progress-bar.bg-primary {
    background-color: #3D74B9 !important;
  }
  
  /* ----- FORMULAIRES - FOCUS PRIMARY ----- */
  /* Override du focus des inputs pour utiliser le bleu FOSIP
     Applique bordure et ombre (box-shadow) en bleu FOSIP */
  .form-control:focus {
    border-color: #3D74B9 !important;
    box-shadow: 0 0 0 0.25rem rgba(61, 116, 185, 0.25) !important; /* Ombre bleue */
  }
  
  /* Override des checkbox et radio cochés
     Applique le bleu FOSIP comme fond */
  .form-check-input:checked {
    background-color: #3D74B9 !important;
    border-color: #3D74B9 !important;
  }
  
  /* ----- PAGINATION PRIMARY ----- */
  /* Override de l'item actif de pagination
     Applique le bleu FOSIP comme fond */
  .page-item.active .page-link {
    background-color: #3D74B9 !important;
    border-color: #3D74B9 !important;
  }
  
  /* Liens de pagination en bleu FOSIP */
  .page-link {
    color: #3D74B9 !important;
  }
  
  /* Liens de pagination au survol en bleu plus foncé */
  .page-link:hover {
    color: #2a5a94 !important;
  }
  
  /* ----- SPINNERS ET LOADERS PRIMARY ----- */
  /* Override du spinner .spinner-border.text-primary
     Applique le bleu FOSIP */
  .spinner-border.text-primary {
    color: #3D74B9 !important;
  }
  
  /* ----- DROPDOWN PRIMARY ----- */
  /* Override des items de dropdown actifs
     Applique le bleu FOSIP comme fond */
  .dropdown-item.active,
  .dropdown-item:active {
    background-color: #3D74B9 !important;
    color: white !important;
  }
  
  /* ----- NAV TABS ET NAV PILLS PRIMARY ----- */
  /* Override de l'onglet actif pour nav-tabs et nav-pills
     Applique le bleu FOSIP */
  .nav-tabs .nav-link.active,
  .nav-pills .nav-link.active {
    background-color: #3D74B9 !important;
    color: white !important;
  }
  
  /* ======================================== 
     FIN DES OVERRIDES BOOTSTRAP PRIMARY
     Tous les composants Bootstrap utilisant 
     la couleur 'primary' utilisent maintenant
     le bleu FOSIP (#3D74B9)
     ======================================== */
</style>

<div class="container-fluid">
  <div class="row">
    <div class="col-lg-3 col-xl-3">
      <?php include('../includes/sidebar.php'); ?>
    </div>
    <div class="col-lg-9 col-xl-9 p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="d-flex align-items-center gap-3 mb-2">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <h4 class="mb-0"><i class="bi bi-list-check me-2"></i> Mes compétences</h4>
          </div>
          <p class="text-muted mb-0 small">Listez ici, une seule fois, les compétences qui décrivent votre profil professionnel (savoir‑faire et savoir‑être)</p>
        </div>
        <div>
          <button id="btnAddComp" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalComp" data-bs-toggle="tooltip" title="Ajouter une nouvelle compétence">
            <i class="bi bi-plus-circle me-2"></i> Ajouter une compétence
          </button>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8">
          <div class="card shadow-sm mb-4" style="border-radius:15px; border: 1px solid white;">
            <div class="card-body p-0">
              <?php if (empty($competences)): ?>
                <div class="p-4 text-center">
                  <i class="bi bi-journal-text display-4 text-muted mb-3"></i>
                  <p class="text-muted mb-0">Aucune compétence n'a encore été ajoutée.</p>
                  <p class="text-muted small">Commencez par cliquer sur "Ajouter une compétence".</p>
                </div>
              <?php else: ?>
                <ul class="list-group list-group-flush">
                  <?php 
                  $counter = 1;
                  foreach ($competences as $c): 
                  ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center p-3 border-start border-5 border-left-primary">
                      <div>
                        <div class="d-flex align-items-center mb-1">
                          <h6 class="mb-0 me-2"><?= htmlspecialchars($c['competence']) ?></h6>
                          <span class="badge bg-primary">N°<?= $counter ?></span>
                        </div>
                        <div class="small text-muted">
                          <i class="bi bi-clock me-1"></i> Mise à jour : <?= date('d/m/Y H:i', strtotime($c['updated_at'])) ?>
                        </div>
                      </div>

                      <div class="btn-group">
                        <button class="btn btn-outline-primary btn-edit"
                                data-id="<?= $c['id'] ?>" 
                                data-competence="<?= htmlspecialchars($c['competence'], ENT_QUOTES) ?>" 
                                data-bs-toggle="tooltip" 
                                title="Modifier cette compétence">
                          <i class="bi bi-pencil"></i>
                        </button>

                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="d-inline delete-form" data-id="<?= $c['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= $c['id'] ?>">
                          <button type="button" class="btn btn-outline-danger btn-delete-trigger" 
                                  data-id="<?= $c['id'] ?>" 
                                  data-bs-toggle="tooltip" 
                                  title="Supprimer cette compétence">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                    </li>
                  <?php 
                  $counter++;
                  endforeach; 
                  ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-muted small p-2">
            <i class="bi bi-info-circle me-1"></i>
            Ces compétences seront présentées à votre superviseur et pourront être notées à chaque fiche d'évaluation (par projet ou période) pour refléter comment elles ont été exploitées dans le contexte.
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card shadow-sm mb-4" style="border-radius:15px; border: 1px solid #3D74B9;">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>Bonnes pratiques</h6>
              <ul class="small text-muted mb-0">
                <li>Saisissez des libellés courts et précis</li>
                <li>Exemple : "Gestion de projet", "Analyse de données"</li>
                <li>Évitez les doublons dans vos compétences</li>
                <li>Tenez la liste à jour après formations ou changement de rôle.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="modalComp" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="formComp" class="modal-content shadow-lg" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCompTitle">Ajouter une compétence</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Compétence</label>
          <input type="text" name="competence" class="form-control form-control-lg" maxlength="255" required autocomplete="off" placeholder="Ex. Gestion de projet, Graphisme">
          <div class="form-text mt-2">
            <i class="bi bi-lightbulb me-1"></i> Conseil : utilisez un intitulé court (1–4 mots) et évitez les doublons.
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Confirm Delete -->
<div class="modal fade" id="modalConfirmDel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body">
        <p id="confirmDelText" class="mb-3 text-center">Voulez-vous supprimer cette compétence ?</p>
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button id="confirmDelBtn" class="btn btn-sm btn-danger">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast container (Bootstrap toast) -->
<div id="toast-area" class="position-fixed top-0 end-0 p-3" style="z-index: 10850;"></div>

<script>
// Minimal JS to handle modal population and toasts
(function(){
  // Escape helper
  function esc(s){ return s == null ? '' : String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'); }

  // Show bootstrap toast
  function showToast(type, message, delay = 3500) {
    const area = document.getElementById('toast-area');
    const id = 'toast-' + Date.now();
    let bg = 'text-white';
    if (type === 'success') { bg += ' bg-success'; }
    else if (type === 'danger') { bg += ' bg-danger'; }
    else if (type === 'warning') { bg = 'bg-warning text-dark'; }
    else { bg += ' bg-primary'; } // primary = bleu FOSIP via CSS
    
    const html = `
      <div id="${id}" class="toast ${bg}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}">
        <div class="d-flex">
          <div class="toast-body">${esc(message)}</div>
          <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`;
    area.insertAdjacentHTML('beforeend', html);
    const el = document.getElementById(id);
    const bs = new bootstrap.Toast(el);
    bs.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  // handle Edit button: populate modal with existing values
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-edit');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const comp = btn.getAttribute('data-competence') || '';
    const modal = document.getElementById('modalComp');
    modal.querySelector('input[name="id"]').value = id;
    modal.querySelector('input[name="competence"]').value = comp;
    modal.querySelector('.modal-title').textContent = 'Modifier la compétence';
    new bootstrap.Modal(modal).show();
  });

  // reset modal on close
  const modalCompEl = document.getElementById('modalComp');
  modalCompEl.addEventListener('hidden.bs.modal', function(){
    const form = modalCompEl.querySelector('form');
    form.reset();
    form.querySelector('input[name="id"]').value = '';
    modalCompEl.querySelector('.modal-title').textContent = 'Ajouter une compétence';
  });

  // handle delete trigger: open confirm modal and store form reference
  let pendingDeleteForm = null;
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-delete-trigger');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const competenceElement = btn.closest('.list-group-item').querySelector('h6');
    const competenceLibelle = competenceElement ? competenceElement.textContent.trim() : '';
    
    // find the corresponding form
    pendingDeleteForm = btn.closest('.delete-form');
    document.getElementById('confirmDelText').textContent = `Voulez-vous supprimer la compétence "${competenceLibelle}" ?`;
    new bootstrap.Modal(document.getElementById('modalConfirmDel')).show();
  });

  // when user confirms deletion, submit the form
  document.getElementById('confirmDelBtn').addEventListener('click', function(){
    if (!pendingDeleteForm) return;
    // submit the stored form (standard POST)
    pendingDeleteForm.submit();
  });

  // show flash toast if exists (server set)
  <?php if (!empty($flash)): ?>
    document.addEventListener('DOMContentLoaded', function(){
      showToast(<?= json_encode($flash['type']) ?>, <?= json_encode($flash['message']) ?>);
    });
  <?php endif; ?>

  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
