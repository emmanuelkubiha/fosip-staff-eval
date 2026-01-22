<?php
// pages/fiche-evaluation.php
// Fichier complet : sidebar inclus, hero decoration, objectifs + auto-eval same column, résumés à droite.
// Prérequis : includes/header.php charge Bootstrap 5 + Bootstrap Icons ; includes/db.php expose $pdo.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'mes-objectifs.php';
include('../includes/header.php');
?>

<div class="row">
  <div class="col-md-3 sidebar-col">
    <?php include('../includes/sidebar.php'); ?>
  </div>

  <div class="col-md-9 content-col">
    <div class="container mt-4">

<?php
// Auth
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

// Récupère id fiche
$fiche_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($fiche_id <= 0) {
  echo '<div class="alert alert-warning">Fiche non spécifiée. <a href="fiches-evaluation-voir.php">Retour à la liste</a></div>';
  include('../includes/footer.php'); exit;
}

// Helpers
function tableExists(PDO $pdo, string $table): bool {
  try { $st = $pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch (Throwable $e) { return false; }
}
function hasLockEntry(PDO $pdo, string $table, int $fiche_id, string $col = 'fiche_id'): bool {
  if (!tableExists($pdo, $table)) return false;
  try { $st = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?"); $st->execute([$fiche_id]); return (int)$st->fetchColumn() > 0; }
  catch (Throwable $e) { return false; }
}

// Charge la fiche et vérifie propriété
$stmt = $pdo->prepare("
  SELECT o.*, u.nom AS sup_nom, u.post_nom AS sup_post_nom, u.fonction AS sup_fonction
  FROM objectifs o
  LEFT JOIN users u ON o.superviseur_id = u.id
  WHERE o.id = ? AND o.user_id = ? LIMIT 1
");
$stmt->execute([$fiche_id, $user_id]);
$fiche = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fiche) { echo '<div class="alert alert-danger">Fiche introuvable ou non autorisée.</div>'; include('../includes/footer.php'); exit; }

// Détection verrous
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $fiche['periode']]);
$verrou_superviseur = (int)$stmtEval->fetchColumn() > 0;
$verrou_coordination = hasLockEntry($pdo, 'coordination_commentaires', $fiche_id);
$verrou_competence_ind = hasLockEntry($pdo, 'competences_individuelles', $fiche_id);
$verrou_competence_gest = hasLockEntry($pdo, 'competences_de_gestion', $fiche_id);
$verrou_qualites = hasLockEntry($pdo, 'qualites_de_leader', $fiche_id);
$verrou_total = $verrou_superviseur || $verrou_coordination || $verrou_competence_ind || $verrou_competence_gest || $verrou_qualites;

// Charger items
$stmtItems = $pdo->prepare("SELECT * FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC");
$stmtItems->execute([$fiche_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Charger auto_evaluations existantes
$auto_map_by_item = [];
if (tableExists($pdo, 'auto_evaluation')) {
  try {
    $stm = $pdo->prepare("SELECT * FROM auto_evaluation WHERE fiche_id = ? AND user_id = ?");
    $stm->execute([$fiche_id, $user_id]);
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $r) $auto_map_by_item[(int)$r['item_id']] = $r;
  } catch (Throwable $e) { error_log('load auto eval error: '.$e->getMessage()); }
}

// Champs résumés
$resume_fields = [
  'resume_reussite','resume_amelioration','resume_problemes',
  'resume_competence_a_developper','resume_competence_a_utiliser','resume_soutien'
];
$resume_count = 0; foreach ($resume_fields as $f) if (!empty($fiche[$f])) $resume_count++;
$resume_total = count($resume_fields);

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Charger les photos de profil
$agent_photo = 'default.png';
$superviseur_photo = 'default.png';

try {
  // Photo de l'agent (utilisateur connecté)
  $stAgent = $pdo->prepare('SELECT photo FROM users WHERE id = ?');
  $stAgent->execute([$user_id]);
  $agentData = $stAgent->fetch(PDO::FETCH_ASSOC);
  if ($agentData && !empty($agentData['photo'])) {
    $agent_photo = $agentData['photo'];
  }
  
  // Photo du superviseur
  if (!empty($fiche['superviseur_id'])) {
    $stSup = $pdo->prepare('SELECT photo FROM users WHERE id = ?');
    $stSup->execute([$fiche['superviseur_id']]);
    $supData = $stSup->fetch(PDO::FETCH_ASSOC);
    if ($supData && !empty($supData['photo'])) {
      $superviseur_photo = $supData['photo'];
    }
  }
} catch (Throwable $e) {
  // En cas d'erreur, on garde les photos par défaut
}

$profile_base = '../assets/img/profiles/';
$agent_photo_path = $profile_base . htmlspecialchars($agent_photo);
$superviseur_photo_path = $profile_base . htmlspecialchars($superviseur_photo);
?>

<!-- Remplacer toute la section style par : -->
<link rel="stylesheet" href="../assets/css/style.css">

<!-- Entête dans une carte -->
      <div class="card section-card mb-4">
        <div class="card-body">
          <div class="d-flex align-items-start">
            <div class="me-3">
              <div class="rounded-circle bg-primary bg-opacity-10 p-6">
                <i class="bi bi-clipboard2-data" style="font-size:2rem;color:#3D74B9"></i>
              </div>
            </div>
            <div style="flex:1">
              <hr class="my-3">
              <div class="small-muted">
                <div><strong>Statut évaluation</strong></div>
                <div class="mt-2"><?= $verrou_superviseur ? '<span class="badge bg-success">Le superviseur a évalué</span>' : '<span class="badge bg-secondary">Pas encore évalué</span>' ?></div>
                <div class="mt-2"><?= $verrou_coordination ? '<span class="badge bg-info text-dark">La coordination a commenté</span>' : '<span class="badge bg-light text-muted">Pas de commentaire</span>' ?></div>
              </div>
              <h3 class="mb-0">Fiche d'évaluation — <?= htmlspecialchars($fiche['periode']) ?></h3>
              <div class="small-muted mt-2"> Cette fiche synthétise vos objectifs pour la période indiquée et les résumés attendus. Remplissez les rubriques "résumés" pour aider votre superviseur et la coordination à comprendre vos réalisations, difficultés et besoins de soutien. </div>
            </div>
            <div class="text-end ms-3">
              <?php if ($verrou_total): ?>
                <span class="badge bg-danger py-2 px-3">
                  <i class="bi bi-lock-fill me-1"></i> Verrouillée
                </span>
              <?php else: ?>
                <span class="badge badge-soft py-2 px-3">
                  <i class="bi bi-pencil-square me-1"></i> Modifiable
                </span>
              <?php endif; ?>

              <!-- Bouton Télécharger / Imprimer la fiche -->
              <div class="mt-3">
                <a href="imprimer-fiche-evaluation.php?fiche_id=<?= $fiche_id ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-download me-1"></i> Télécharger (PDF)
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

<!-- Informations du projet -->
<div class="card section-card mb-4">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-8">
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
              <i class="bi bi-kanban-fill" style="color:#3D74B9;font-size:1.5rem"></i>
            </div>
            <div>
              <strong class="text-secondary">Projet :</strong>
              <span class="ms-2"><?= htmlspecialchars($fiche['nom_projet']) ?></span>
            </div>
          </div>

          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
              <i class="bi bi-briefcase-fill" style="color:#3D74B9;font-size:1.5rem"></i>
            </div>
            <div>
              <strong class="text-secondary">Poste :</strong>
              <span class="ms-2"><?= htmlspecialchars($fiche['poste']) ?></span>
            </div>
          </div>

          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
              <i class="bi bi-calendar-event-fill" style="color:#3D74B9;font-size:1.5rem"></i>
            </div>
            <div>
              <strong class="text-secondary">Date de commencement :</strong>
              <span class="ms-2"><?= htmlspecialchars($fiche['date_commencement']) ?></span>
            </div>
          </div>

          <!-- Agent avec photo -->
          <div class="d-flex align-items-center">
            <img src="<?= htmlspecialchars($agent_photo_path) ?>" 
                 alt="Photo agent" 
                 class="rounded-circle me-3"
                 style="width: 42px; height: 42px; object-fit: cover; border: 2px solid #3D74B9;"
                 onerror="this.onerror=null;this.src='<?= $profile_base ?>default.png';">
            <div>
              <strong class="text-secondary">Agent :</strong>
              <span class="ms-2">Vous</span>
            </div>
          </div>

          <!-- Superviseur avec photo -->
          <div class="d-flex align-items-center">
            <img src="<?= htmlspecialchars($superviseur_photo_path) ?>" 
                 alt="Photo superviseur" 
                 class="rounded-circle me-3"
                 style="width: 42px; height: 42px; object-fit: cover; border: 2px solid #3D74B9;"
                 onerror="this.onerror=null;this.src='<?= $profile_base ?>default.png';">
            <div>
              <strong class="text-secondary">Superviseur :</strong>
              <span class="ms-2"><?= htmlspecialchars($fiche['sup_nom'].' '.$fiche['sup_post_nom']) ?> — <?= htmlspecialchars($fiche['sup_fonction']) ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4 text-end">
        <?php 
          $badge = 'secondary';
          $label = ucfirst($fiche['statut'] ?? '');
          switch ($fiche['statut'] ?? '') {
            case 'encours': $badge='info'; $label='En cours'; break;
            case 'attente': $badge='warning'; $label='En attente'; break;
            case 'evalue': $badge='primary'; $label='Évalué'; break;
            case 'termine': $badge='success'; $label='Terminé'; break;
          }
        ?>
        <div class="mb-3">
          <span class="badge bg-<?= $badge ?> py-2 px-3">
            <i class="bi bi-circle-fill me-1"></i>
            <?= $label ?>
          </span>
        </div>

        <?php if ($verrou_total): ?>
          <div class="alert alert-danger mb-0">
            <i class="bi bi-lock-fill me-1"></i>
            Verrouillée : évaluation/coordination/compétences existantes.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- MAIN: left column holds Objectifs + Auto-eval (same width); right column holds Résumés -->
<div class="row g-3">
  <div class="col-lg-8">
    <!-- Objectifs -->
    <div class="card section-card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
              <i class="bi bi-list-check" style="color:#3D74B9;font-size:1.5rem"></i>
            </div>
            <h5 class="mb-0">Objectifs</h5>
          </div>
          <div class="ms-auto">
            <?php if (!$verrou_total): ?>
              <button class="btn btn-sm btn-fosip" data-bs-toggle="modal" data-bs-target="#modalAddItem">
                <i class="bi bi-plus-circle me-2"></i>Ajouter un objectif
              </button>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" onclick="showLockedReason()">
                <i class="bi bi-lock-fill me-2"></i>Ajouter (verrouillé)
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if (empty($items)): ?>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Aucun objectif défini pour cette fiche.
          </div>
        <?php else: ?>
          <div class="list-group">
            <?php foreach ($items as $idx => $it): ?>
              <div class="list-group-item border rounded-3 mb-2 hover-shadow">
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div class="d-flex gap-3">
                    <div class="text-primary fw-bold" style="min-width:24px"><?= ($idx+1) ?>.</div>
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($it['contenu']) ?></div>
                      <?php if (!empty($it['resume'])): ?>
                        <div class="small-muted mt-2"><?= nl2br(htmlspecialchars($it['resume'])) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    <?php if (!$verrou_total): ?>
                      <button class="btn btn-sm btn-outline-primary" data-item-id="<?= $it['id'] ?>" data-item-content="<?= htmlspecialchars($it['contenu'], ENT_QUOTES) ?>" onclick="openEditSimple(this)" title="Modifier">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger btn-item-delete" data-item-id="<?= $it['id'] ?>" title="Supprimer">
                        <i class="bi bi-trash"></i>
                      </button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-secondary" onclick="showLockedReason()">
                        <i class="bi bi-lock-fill"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Auto-evaluation -->
    <div class="card section-card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="d-flex align-items-center">
              <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                <i class="bi bi-speedometer2" style="color:#3D74B9;font-size:1.5rem"></i>
              </div>
              <h5 class="mb-0">Auto‑évaluation</h5>
            </div>
            <div class="ms-3 small-muted">Notez chaque objectif et ajoutez un commentaire</div>
            <div class="ms-auto">
              <?php if ($verrou_total): ?>
                <span class="badge bg-danger py-2 px-3">
                  <i class="bi bi-lock-fill me-1"></i>Saisie verrouillée
                </span>
              <?php else: ?>
                <span class="badge badge-soft">
                  <i class="bi bi-pencil-square me-1"></i>Personnelle
                </span>
              <?php endif; ?>
            </div>
          </div>

        <?php if (empty($items)): ?>
          <div class="alert alert-info">Aucun objectif à évaluer.</div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($items as $idx => $it):
              $itemId = (int)$it['id'];
              $ae = $auto_map_by_item[$itemId] ?? null;
              $stateClass = 'text-muted'; $stateIcon = '<i class="bi bi-slash-circle" style="color:#6c757d"></i>'; $stateLabel = 'Indisponible';
              if ($ae && !empty($ae['note'])) {
                if ($ae['note']==='depasse') { $stateClass='text-success'; $stateIcon='<i class="bi bi-arrow-up-circle-fill" style="color:#198754"></i>'; $stateLabel='Dépasse'; }
                elseif ($ae['note']==='atteint') { $stateClass='text-warning'; $stateIcon='<i class="bi bi-dash-circle-fill" style="color:#ffc107"></i>'; $stateLabel='Atteint'; }
                else { $stateClass='text-danger'; $stateIcon='<i class="bi bi-arrow-down-circle-fill" style="color:#dc3545"></i>'; $stateLabel='Non atteint'; }
              }
            ?>
              <div class="col-12 ae-item p-3 border rounded d-flex align-items-center">
                <div style="flex:1">
                  <div class="fw-semibold"><?= ($idx+1) . '. ' . htmlspecialchars($it['contenu']) ?></div>
                  <?php if (!empty($it['resume'])): ?><div class="small-muted"><?= nl2br(htmlspecialchars($it['resume'])) ?></div><?php endif; ?>
                </div>

                <div class="text-center me-3">
                  <div><?= $stateIcon ?></div>
                  <div class="<?= $stateClass ?> small"><?= htmlspecialchars($stateLabel) ?></div>
                </div>

                <div style="width:520px; max-width:100%;">
                  <?php if (!$verrou_total): ?>
                    <form class="d-flex gap-2 align-items-center auto-eval-form" data-item-id="<?= $itemId ?>" data-fiche-id="<?= $fiche_id ?>" onsubmit="return false;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">
                      <input type="hidden" name="item_id" value="<?= $itemId ?>">

                      <select name="note" class="form-select form-select-sm ae-select">
                        <option value="">-- Choisir --</option>
                        <option value="non_atteint" <?= ($ae && $ae['note']==='non_atteint') ? 'selected' : '' ?>>Non atteint</option>
                        <option value="atteint" <?= ($ae && $ae['note']==='atteint') ? 'selected' : '' ?>>Atteint</option>
                        <option value="depasse" <?= ($ae && $ae['note']==='depasse') ? 'selected' : '' ?>>Dépasse</option>
                      </select>

                      <input type="text" name="commentaire" class="form-control form-control-sm" placeholder="Commentaire (facultatif)" value="<?= htmlspecialchars($ae['commentaire'] ?? '') ?>">

                      <button type="button" class="btn btn-sm btn-primary btn-save-ae" title="Enregistrer"><i class="bi bi-cloud-arrow-up"></i></button>
                      <?php if ($ae): ?><button type="button" class="btn btn-sm btn-outline-danger btn-delete-ae" title="Supprimer"><i class="bi bi-trash"></i></button><?php endif; ?>
                    </form>
                  <?php else: ?>
                    <div class="small-muted">Saisie désactivée (fiche verrouillée)</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Résumés (right column) -->
  <div class="col-lg-4">
    <div class="card section-card mb-3">
      <div class="card-body">
        <h6 class="mb-2"><i class="bi bi-card-text me-1" style="color:#3D74B9;font-size:1.3rem"></i> Résumés de la fiche</h6>
        <div class="small-muted mb-3">Remplissez ces synthèses pour aider la coordination et votre superviseur à comprendre les résultats, difficultés et besoins.</div>

        <form method="post" action="fiche-evaluation-save-resumes.php">
          <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">

          <?php foreach ($resume_fields as $field):
            $label = ucwords(str_replace('_',' ', str_replace('resume_','', $field)));
            $label = str_replace('reussite','réussite',$label);
            $label = str_replace('amelioration','amélioration',$label);
            $label = str_replace('problemes','problèmes',$label);
            $label = str_replace('competence a developper','compétence à développer',$label);
            $label = str_replace('competence a utiliser','compétence à utiliser',$label);
            $value = htmlspecialchars($fiche[$field] ?? '');
          ?>
            <div class="mb-3">
              <label class="form-label"><strong><?= $label ?></strong></label>
              <textarea name="<?= $field ?>" class="form-control form-control-sm" rows="3" <?= $verrou_total ? 'readonly' : '' ?>><?= $value ?></textarea>
              <div class="form-text small text-muted mt-1">
                <?php
                  switch ($field) {
                    case 'resume_reussite':
                      echo "Ce que vous avez bien réalisé durant la période : livrables, résultats concrets et indicateurs atteints.";
                      break;
                    case 'resume_amelioration':
                      echo "Axes d'amélioration : points à améliorer, causes des écarts et actions correctives envisagées.";
                      break;
                    case 'resume_problemes':
                      echo "Problèmes rencontrés : obstacles, incidents et leur impact sur l'atteinte des objectifs.";
                      break;
                    case 'resume_competence_a_developper':
                      echo "Compétences à développer : formations, accompagnement ou mentorat nécessaires.";
                      break;
                    case 'resume_competence_a_utiliser':
                      echo "Compétences à mieux mobiliser : forces actuelles à valoriser.";
                      break;
                    case 'resume_soutien':
                      echo "Soutien attendu : ressources, validations ou appuis requis.";
                      break;
                  }
                ?>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="d-grid">
            <?php if ($verrou_total): ?>
              <button class="btn btn-secondary" type="button" onclick="showLockedReason()">Modification bloquée</button>
            <?php else: ?>
              <button class="btn btn-fosip" type="submit">Enregistrer les résumés</button>
            <?php endif; ?>
          </div>
        </form>

        
      </div>
    </div>
  </div>
</div>

<!-- Modals: Add / Edit / ConfirmDelete (conservés) -->
<!-- Modal Add -->
<div class="modal fade" id="modalAddItem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="formAddItem" class="modal-content" method="post" action="objectif-item-ajouter.php">
      <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">
      <div class="modal-header"><h5 class="modal-title">Ajouter un objectif</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Contenu de l'objectif</label><textarea name="contenu" class="form-control" rows="4" required></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-fosip">Ajouter</button></div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEditItem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="formEditItem" class="modal-content" method="post" action="objectif-item-modifier.php">
      <input type="hidden" name="item_id" id="edit_item_id" value="">
      <div class="modal-header"><h5 class="modal-title">Modifier l'objectif</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><div class="mb-2"><label class="form-label">Contenu</label><textarea name="contenu" id="edit_contenu" class="form-control" rows="4" required></textarea></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-fosip">Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- Confirm delete modal for objectives -->
<div class="modal fade" id="modalConfirmDeleteItem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body">
        <p id="confirmDeleteText">Supprimer cet objectif ?</p>
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button id="confirmDeleteBtn" class="btn btn-sm btn-danger">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast container already present above -->
<script>
// Helpers
function openEditSimple(btn) {
  var id = btn.getAttribute('data-item-id'), content = btn.getAttribute('data-item-content') || '';
  document.getElementById('edit_item_id').value = id;
  document.getElementById('edit_contenu').value = content;
  new bootstrap.Modal(document.getElementById('modalEditItem')).show();
}
function showLockedReason(){ alert('Action verrouillée : demandez le retrait des évaluations/coordination avant modification.'); }

// Variable pour stocker l'ID de l'item à supprimer
let itemToDelete = null;

// Deletion flow
document.addEventListener('click', function(e){
  var btn = e.target.closest('.btn-item-delete');
  if (!btn) return;
  e.preventDefault();
  
  // Récupérer le libellé de l'objectif depuis le contenu proche du bouton
  const itemElement = btn.closest('.list-group-item');
  const itemContent = itemElement.querySelector('.fw-semibold').textContent.trim();
  itemToDelete = btn.getAttribute('data-item-id');
  
  document.getElementById('confirmDeleteText').textContent = `Voulez-vous supprimer l'objectif : "${itemContent}" ?`;
  new bootstrap.Modal(document.getElementById('modalConfirmDeleteItem')).show();
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function(){
  if (!itemToDelete) return;
  var btn = this; btn.disabled = true; btn.textContent = 'Suppression...';
  fetch('objectif-item-supprimer.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'item_id='+encodeURIComponent(itemToDelete) })
    .then(r => { if(!r.ok) throw new Error('Erreur serveur'); return r.text(); })
    .then(()=> location.reload())
    .catch(err => { console.error(err); alert('Échec suppression'); btn.disabled=false; btn.textContent='Supprimer'; });
});

// Toast helper
function showToast(type, message, delay = 4500) {
  var container = document.getElementById('toast-container');
  if (!container) { container = document.createElement('div'); container.id='toast-container'; container.className='position-fixed top-0 end-0 p-3'; container.style.zIndex=1080; document.body.appendChild(container); }
  var id = 't' + Date.now(); var bg='bg-primary text-white';
  if (type === 'success') bg='bg-success text-white'; if (type === 'danger') bg='bg-danger text-white'; if (type === 'warning') bg='bg-warning text-dark';
  var html = `<div id="${id}" class="toast ${bg}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
  container.insertAdjacentHTML('beforeend', html);
  var el = document.getElementById(id); var bs = new bootstrap.Toast(el); bs.show(); el.addEventListener('hidden.bs.toast', ()=> el.remove());
}

// Delegated auto-eval handlers (save/delete)
document.addEventListener('click', function(e){
  if (e.target.closest('.btn-save-ae')) {
    const btn = e.target.closest('.btn-save-ae'); const form = btn.closest('.auto-eval-form'); saveAutoEval(form, btn);
  }
  if (e.target.closest('.btn-delete-ae')) {
    const btn = e.target.closest('.btn-delete-ae'); const form = btn.closest('.auto-eval-form'); deleteAutoEval(form, btn);
  }
});

// Async save/delete (same robust versions)
async function saveAutoEval(form, btn) {
  const fiche_id = form.querySelector('input[name="fiche_id"]').value;
  const item_id = form.querySelector('input[name="item_id"]').value;
  const note = form.querySelector('select[name="note"]').value;
  const commentaire = form.querySelector('input[name="commentaire"]').value;
  const csrf = form.querySelector('input[name="csrf_token"]').value;
  if (!note) { showToast('warning','Choisissez une note.'); return; }
  btn.disabled = true; const original = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  const body = new URLSearchParams(); body.append('action','save'); body.append('fiche_id', fiche_id); body.append('item_id', item_id); body.append('note', note); body.append('commentaire', commentaire); body.append('csrf_token', csrf);
  try {
    const resp = await fetch('auto-evaluation-save.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString(), credentials:'same-origin' });
    if (!resp.ok) { const text = await resp.text(); showToast('danger','Erreur serveur: '+resp.status); console.error('Save AE server error', resp.status, text); btn.disabled=false; btn.innerHTML=original; return; }
    const json = await resp.json();
    if (!json.ok) { showToast('danger', json.error || 'Erreur'); console.error('Save AE payload', json); btn.disabled=false; btn.innerHTML=original; return; }
    showToast('success', json.message || 'Enregistré');
    const row = form.closest('.ae-item') || form.closest('.list-group-item');
    if (row) {
      const label = row.querySelector('.small, .small-muted');
      if (label) {
        label.textContent = note === 'depasse' ? 'Dépasse' : (note === 'atteint' ? 'Atteint' : 'Non atteint');
        label.classList.remove('text-muted','text-success','text-warning','text-danger');
        label.classList.add(note === 'depasse' ? 'text-success' : (note === 'atteint' ? 'text-warning' : 'text-danger'));
      }
    }
    if (!form.querySelector('.btn-delete-ae')) {
      const del = document.createElement('button'); del.type='button'; del.className='btn btn-sm btn-outline-danger btn-delete-ae'; del.title='Supprimer'; del.innerHTML='<i class="bi bi-trash"></i>';
      form.appendChild(del);
    }
    btn.disabled=false; btn.innerHTML=original;
  } catch (err) {
    console.error('Network/save AE error', err); showToast('danger','Erreur réseau'); btn.disabled=false; btn.innerHTML=original;
  }
}

async function deleteAutoEval(form, btn) {
  const itemContent = form.closest('.ae-item').querySelector('.fw-semibold').textContent.trim();
  document.getElementById('confirmDeleteText').textContent = `Supprimer l'auto-évaluation de l'objectif : ${itemContent}`;
  new bootstrap.Modal(document.getElementById('modalConfirmDeleteItem')).show();
  
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  confirmBtn.onclick = async () => {
    btn.disabled = true;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Suppression...';
    
    try {
      const resp = await fetch('auto-evaluation-save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'delete',
          fiche_id: form.querySelector('input[name="fiche_id"]').value,
          item_id: form.querySelector('input[name="item_id"]').value,
          csrf_token: form.querySelector('input[name="csrf_token"]').value
        })
      });
      
      if (!resp.ok) throw new Error('Erreur serveur');
      const json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Erreur de suppression');

      bootstrap.Modal.getInstance(document.getElementById('modalConfirmDeleteItem')).hide();
      showToast('success', 'Auto-évaluation supprimée avec succès');
      form.closest('.ae-item').remove();
      
    } catch (err) {
      console.error('Erreur:', err);
      showToast('danger', 'Impossible de supprimer l\'auto-évaluation');
    } finally {
      btn.disabled = false;
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = 'Supprimer';
    }
  };
}

// afficher toast serveur si présent
<?php if (!empty($_SESSION['toast'])):
  $t = $_SESSION['toast']; $type = json_encode($t['type']); $msg = json_encode($t['message']); unset($_SESSION['toast']);
?>
document.addEventListener('DOMContentLoaded', function(){ showToast(<?= $type ?>, <?= $msg ?>); });
<?php endif; ?>
</script>

<!-- Ajouter SweetAlert2 dans le head -->
<link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include('../includes/footer.php'); ?>
