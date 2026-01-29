<?php
/**
 * pages/supervision.php
 * -------------------------------------------------------------
 * OBJECTIF
 *   Page principale de travail pour un superviseur : affiche les fiches
 *   (objectifs) de ses agents avec leur statut de supervision et permet
 *   d'ouvrir une modal pour saisir / mettre à jour la note, le commentaire
 *   et le statut (en cours / complet).
 *
 * POINTS CLÉS
 *   - Sécurité : accès restreint au rôle 'superviseur'.
 *   - Filtrage : recherche textuelle + filtre de période + filtre statut.
 *   - Priorité : fiche sans supervision complète en haut quand filtre 'pending'.
 *   - UX : modal AJAX pour éviter rechargements multiples; toasts de feedback.
 *   - Robustesse : utilisation de LEFT JOIN pour récupérer supervision si elle existe.
 *
 * STRUCTURE
 *   1. Initialisation + auth + CSRF.
 *   2. Construction dynamique de la requête selon filtres.
 *   3. Affichage tableau avec badges de statut et action Évaluer.
 *   4. Modal de saisie supervision + JS (load + save).
 *   5. Utilitaires JS (toasts, ouverture modal, fetch).
 * -------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'supervision.php';
include('../includes/header.php');

// Auth
// Vérification stricte du rôle : superviseur ou coordination peuvent accéder
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) {
  header('Location: unauthorized.php');
  exit;
}
$superviseur_id = (int)$_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Helper générique : teste l'existence d'une table (utile si le schéma évolue)
function tableExists(PDO $pdo, string $table): bool {
  try { $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}

$search = trim((string)($_GET['q'] ?? ''));
$periode = trim((string)($_GET['periode'] ?? ''));
// Filtre de statut (pending=non complétées, done=complétées, all=toutes)
$filter = $_GET['statut'] ?? 'encours'; // Nouveau jeu de filtres: a-evaluer | evalue | termine | all

$sql = "SELECT 
          o.id AS fiche_id, o.periode, o.nom_projet, o.poste, o.statut AS fiche_statut, o.created_at AS fiche_created_at, o.updated_at AS fiche_updated_at,
          a.id AS agent_id, a.nom AS agent_nom, a.post_nom AS agent_post_nom, a.photo AS agent_photo,
          s.id AS supervision_id, s.statut AS sup_statut, s.note AS sup_note, s.commentaire AS sup_commentaire, s.date_validation
        FROM objectifs o
        JOIN users a ON a.id = o.user_id AND a.superviseur_id = :sid1
        LEFT JOIN supervisions s 
          ON s.agent_id = o.user_id AND s.superviseur_id = :sid2 AND s.periode = o.periode
        WHERE 1 = 1";
$params = [':sid1'=>$superviseur_id, ':sid2'=>$superviseur_id];

// Recherche multi-champs (projet, poste, nom agent, période)
if ($search !== '') {
  $sql .= " AND (o.nom_projet LIKE :s OR o.poste LIKE :s OR a.nom LIKE :s OR a.post_nom LIKE :s OR o.periode LIKE :s)";
  $params[':s'] = "%$search%";
}
if ($periode !== '') { $sql .= " AND o.periode = :p"; $params[':p'] = $periode; }

// Filtrage
// Application du filtre de statut
// Nouveau filtrage basé uniquement sur objectifs.statut
if ($filter === 'encours') {
  // Fiches à évaluer par le superviseur (statut encours ou attente)
  $sql .= " AND (o.statut='encours' OR o.statut='attente')";
} elseif ($filter === 'evalue') {
  // Fiches évaluées par le superviseur, en attente de commentaire coordination
  $sql .= " AND o.statut='evalue'";
} elseif ($filter === 'termine') {
  // Fiches commentées par la coordination
  $sql .= " AND o.statut='termine'";
} elseif ($filter === 'all') {
  // Pas de condition supplémentaire
}

$sql .= " ORDER BY o.periode DESC, o.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Préparer indicateurs: nombre d'objectifs et avancement auto‑évaluation par fiche
$itemCount = [];
$autoCount = [];
$ficheIds = array_map(fn($r)=> (int)$r['fiche_id'], $rows);
if (!empty($ficheIds)) {
  $in = implode(',', array_map('intval', $ficheIds));
  try {
    $q1 = $pdo->query("SELECT fiche_id, COUNT(*) cnt FROM objectifs_items WHERE fiche_id IN ($in) GROUP BY fiche_id");
    foreach ($q1->fetchAll(PDO::FETCH_ASSOC) as $r) $itemCount[(int)$r['fiche_id']] = (int)$r['cnt'];
  } catch (Throwable $e) { /* ignore */ }
  if (tableExists($pdo,'auto_evaluation')) {
    try {
      // On récupère pour chaque fiche l'agent associé
      $ficheAgentMap = [];
      foreach ($rows as $row) {
        $ficheAgentMap[(int)$row['fiche_id']] = (int)$row['agent_id'];
      }
      // Pour chaque fiche, compter les auto-évaluations de l'agent de la fiche
      foreach ($ficheAgentMap as $ficheId => $agentId) {
        $q2 = $pdo->prepare("SELECT COUNT(*) FROM auto_evaluation WHERE fiche_id = ? AND user_id = ?");
        $q2->execute([$ficheId, $agentId]);
        $autoCount[$ficheId] = (int)$q2->fetchColumn();
      }
    } catch (Throwable $e) { /* ignore */ }
  }
}

?>
<div class="row">
  <div class="col-md-3 sidebar-col">
    <div class="sidebar-wrapper">
      <?php include('../includes/sidebar.php'); ?>
    </div>
  </div>
  <div class="col-md-9 content-col" style="padding-left: 6rem;">
    <div class="container mt-4">

      <!-- En-tête moderne -->
      <div class="mb-4">
        <div class="d-flex align-items-start">
          <div class="me-3">
            <div class="d-flex align-items-center justify-content-center" 
                 style="width:50px;height:50px;background:#3D74B9;border-radius:12px;box-shadow: 0 4px 6px rgba(61, 116, 185, 0.3);">
              <i class="bi bi-clipboard-check" style="font-size:1.5rem;color:#fff;"></i>
            </div>
          </div>
          <div class="flex-grow-1">
            <h3 class="mb-2 fw-bold" style="color:#2d3748;">Supervision des Employés</h3>
            <p class="text-muted mb-0">Bienvenue dans votre espace de supervision. Consultez l'ensemble des fiches d'objectifs de votre équipe, suivez l'avancement des auto-évaluations et effectuez vos évaluations. Filtrez par statut pour une gestion optimale.</p>
          </div>
          <div class="ms-3">
            <div class="d-flex flex-wrap gap-2">
              <a href="supervision.php?statut=encours" 
                 class="btn btn-sm <?= $filter==='encours'?'btn-fosip':'btn-outline-primary' ?>" 
                 style="border-radius:20px;<?= $filter==='encours'?'':'border-color:#3D74B9;color:#3D74B9;' ?>">
                <i class="bi bi-hourglass-split me-1"></i> À évaluer
              </a>
              <a href="supervision.php?statut=evalue" 
                 class="btn btn-sm <?= $filter==='evalue'?'btn-fosip':'btn-outline-primary' ?>" 
                 style="border-radius:20px;<?= $filter==='evalue'?'':'border-color:#3D74B9;color:#3D74B9;' ?>">
                <i class="bi bi-check-circle me-1"></i> Évaluées
              </a>
              <a href="supervision.php?statut=termine" 
                 class="btn btn-sm <?= $filter==='termine'?'btn-success':'btn-outline-success' ?>" 
                 style="border-radius:20px;">
                <i class="bi bi-check-all me-1"></i> Terminées
              </a>
              <a href="supervision.php?statut=all" 
                 class="btn btn-sm <?= $filter==='all'?'btn-secondary':'btn-outline-secondary' ?>" 
                 style="border-radius:20px;">
                <i class="bi bi-list-ul me-1"></i> Toutes
              </a>
            </div>
          </div>
        </div>
      </div>

      <form class="row g-2 align-items-end mb-3" method="get" novalidate>
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="col-auto">
          <label class="form-label mb-0 small">Recherche</label>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Agent, projet, poste, période...">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0 small">Période</label>
          <input type="month" name="periode" value="<?= htmlspecialchars($periode) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
          <button class="btn btn-fosip btn-sm">Filtrer</button>
          <a href="supervision.php" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
        </div>
      </form>

      <?php if (empty($rows)): ?>
        <div class="alert alert-info">Aucune fiche à afficher selon vos critères.</div>
      <?php else: ?>
        <div class="card-grid">
          <?php foreach ($rows as $r):
            $agentNom = trim(($r['agent_nom'] ?? '').' '.($r['agent_post_nom'] ?? ''));
            $agentPhoto = $r['agent_photo'] ?? 'default.PNG';
            $photoPath = '../assets/img/profiles/' . htmlspecialchars($agentPhoto);
            $ficheStatut = $r['fiche_statut'] ?? '';
            $supStatut = $r['sup_statut'] ?? null;
            
            // Système de badges basé sur le statut de la fiche dans objectifs.statut
            $badge = 'secondary'; $label = 'Pas encore évalué';
            
            // Priorité au statut de la fiche objectif
            if ($ficheStatut === 'termine') {
              // Fiche commentée par la coordination
              $badge = 'success'; 
              $label = 'Terminé';
            } elseif ($ficheStatut === 'evalue') {
              // Fiche évaluée par le superviseur, en attente coordination
              $badge = 'info'; 
              $label = 'Évalué';
            } elseif ($ficheStatut === 'encours' || $ficheStatut === 'attente') {
              // Fiche en cours d'évaluation par l'agent ou en attente d'évaluation superviseur
              if ($supStatut === 'encours') {
                $badge = 'warning'; 
                $label = 'En cours d\'évaluation';
              } else {
                $badge = 'secondary'; 
                $label = 'Non évalué';
              }
            }
            
            $noteDisplay = is_null($r['sup_note']) ? '—' : (int)$r['sup_note'];
            $fid = (int)$r['fiche_id'];
            $itCnt = $itemCount[$fid] ?? 0; $aeCnt = $autoCount[$fid] ?? 0;
            $percent = $itCnt > 0 ? round($aeCnt*100/$itCnt) : 0;
            $pClass = $percent < 34 ? 'bg-danger' : ($percent < 67 ? 'bg-warning' : 'bg-success');
          ?>
          <div class="card border-0 h-100" style="border-radius:16px;overflow:hidden;box-shadow: 0 2px 8px rgba(0,0,0,0.08);transition: all 0.3s ease;">
            <div class="card-body d-flex flex-column p-3">
              <!-- En-tête avec photo et statut -->
              <div class="d-flex align-items-center mb-3 pb-3" style="border-bottom: 2px solid #e8f2ff;">
                <img src="<?= $photoPath ?>" 
                     alt="Photo <?= htmlspecialchars($agentNom) ?>" 
                     class="rounded-circle me-3"
                     style="width: 50px; height: 50px; object-fit: cover; border: 3px solid #3D74B9;box-shadow: 0 2px 4px rgba(61, 116, 185, 0.2);"
                     onerror="this.src='../assets/img/profiles/default.PNG'">
                <div class="flex-grow-1" style="min-width:0;">
                  <h6 class="mb-1 fw-bold text-truncate" style="color:#2d3748;"><?= htmlspecialchars($agentNom) ?></h6>
                  <span class="badge bg-<?= $badge ?>" style="font-size:0.7rem;padding:4px 10px;border-radius:12px;"><?= $label ?></span>
                  <div class="small text-muted mt-1">Soumise le : <?= isset($r['fiche_created_at']) ? date('d/m/Y H:i', strtotime($r['fiche_created_at'])) : '-' ?></div>
                  <?php
                  // Dates de la supervision (évaluation) — compact
                  if (!empty($r['supervision_id'])) {
                    $stS = $pdo->prepare('SELECT created_at, updated_at, date_validation FROM supervisions WHERE id = ?');
                    $stS->execute([$r['supervision_id']]);
                    $rowS = $stS->fetch(PDO::FETCH_ASSOC);
                    $dates = [];
                    if ($rowS) {
                      if (!empty($rowS['created_at'])) $dates[] = '<i class="bi bi-calendar-plus me-1"></i>'.date('d/m/Y', strtotime($rowS['created_at']));
                      if (!empty($rowS['updated_at']) && $rowS['updated_at'] !== $rowS['created_at']) $dates[] = '<i class="bi bi-pencil-square me-1"></i>'.date('d/m/Y', strtotime($rowS['updated_at']));
                      if (!empty($rowS['date_validation'])) $dates[] = '<i class="bi bi-check2-circle me-1"></i>'.date('d/m/Y', strtotime($rowS['date_validation']));
                    }
                    if ($dates) echo '<div class="text-secondary" style="font-size:0.70em;line-height:1.1;">Éval. : '.implode(' · ', $dates).'</div>';
                  }
                  // Dates du dernier commentaire coordination — compact
                  $ficheId = (int)($r['fiche_id'] ?? 0);
                  if ($ficheStatut === 'termine' && $ficheId) {
                    $stC = $pdo->prepare('SELECT created_at, updated_at FROM coordination_commentaires WHERE fiche_id = ? ORDER BY GREATEST(COALESCE(updated_at, "0000-00-00"), COALESCE(created_at, "0000-00-00")) DESC LIMIT 1');
                    $stC->execute([$ficheId]);
                    $rowCom = $stC->fetch(PDO::FETCH_ASSOC);
                    $datesC = [];
                    if ($rowCom) {
                      if (!empty($rowCom['created_at'])) $datesC[] = '<i class="bi bi-chat-left-text me-1"></i>'.date('d/m/Y', strtotime($rowCom['created_at']));
                      if (!empty($rowCom['updated_at']) && $rowCom['updated_at'] !== $rowCom['created_at']) $datesC[] = '<i class="bi bi-pencil me-1"></i>'.date('d/m/Y', strtotime($rowCom['updated_at']));
                    }
                    if ($datesC) echo '<div class="text-secondary" style="font-size:0.70em;line-height:1.1;">Com. : '.implode(' · ', $datesC).'</div>';
                  }
                  ?>
                  <?php
                  // Date de dernière évaluation (supervision)
                  if (!empty($r['sup_statut']) && !empty($r['sup_statut']) && !empty($r['supervision_id'])) {
                    $dateEval = $r['date_validation'] ?? $r['fiche_updated_at'] ?? null;
                    if ($dateEval) {
                      echo '<div class="text-secondary" style="font-size:0.70em;line-height:1.1;">';
                      echo '<i class="bi bi-check2-circle me-1"></i>Évalué le '.date('d/m/Y', strtotime($dateEval));
                      echo '</div>';
                    }
                  }
                  // Date de commentaire coordination (table coordination_commentaires)
                  $ficheId = (int)($r['fiche_id'] ?? 0);
                  if ($ficheStatut === 'termine' && $ficheId) {
                    $stC = $pdo->prepare('SELECT date_commentaire FROM coordination_commentaires WHERE fiche_id = ? ORDER BY date_commentaire DESC LIMIT 1');
                    $stC->execute([$ficheId]);
                    $dateCom = $stC->fetchColumn();
                    if ($dateCom) {
                      echo '<div class="text-secondary" style="font-size:0.70em;line-height:1.1;">';
                      echo '<i class="bi bi-chat-left-text me-1"></i>Commenté le '.date('d/m/Y', strtotime($dateCom));
                      echo '</div>';
                    }
                  }
                  ?>
                  <!-- Date de modification déplacée en bas -->
                </div>
              </div>

              <!-- Informations principales -->
              <div class="mb-3">
                <div class="d-flex align-items-start mb-2">
                  <div class="me-2" style="color:#3D74B9;"><i class="bi bi-kanban"></i></div>
                  <div class="flex-grow-1" style="min-width:0;">
                    <div class="small text-muted">Projet</div>
                    <div class="fw-semibold text-truncate" style="color:#2d3748;" title="<?= htmlspecialchars($r['nom_projet'] ?? '') ?>"><?= htmlspecialchars($r['nom_projet'] ?? '') ?></div>
                  </div>
                </div>
                <div class="d-flex align-items-start mb-2">
                  <div class="me-2" style="color:#3D74B9;"><i class="bi bi-briefcase"></i></div>
                  <div class="flex-grow-1" style="min-width:0;">
                    <div class="small text-muted">Poste</div>
                    <div class="small text-truncate" style="color:#4a5568;" title="<?= htmlspecialchars($r['poste'] ?? '') ?>"><?= htmlspecialchars($r['poste'] ?? '') ?></div>
                  </div>
                </div>
                <div class="d-flex align-items-center">
                  <div class="me-2" style="color:#3D74B9;"><i class="bi bi-calendar-event"></i></div>
                  <div>
                    <span class="small text-muted">Période:</span>
                    <span class="small fw-semibold ms-1" style="color:#2d3748;"><?= htmlspecialchars($r['periode'] ?? '') ?></span>
                    <div class="small text-muted mt-1">Soumise le : <?= isset($r['fiche_created_at']) ? date('d/m/Y H:i', strtotime($r['fiche_created_at'])) : '-' ?></div>
                    <div class="small text-secondary mt-1" style="font-size:0.85em;">
                      <i class="bi bi-clock-history me-1"></i>
                      Modifié le : <?= isset($r['fiche_updated_at']) ? date('d/m/Y H:i', strtotime($r['fiche_updated_at'])) : '-' ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Statistiques -->
              <div class="mb-3 p-2" style="background:#f0f7ff;border-radius:10px;border:1px solid #d6e9ff;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="small text-muted"><i class="bi bi-star me-1" style="color:#3D74B9;"></i>Note</span>
                  <span class="fw-bold" style="color:#3D74B9;font-size:1.1rem;"><?= $noteDisplay ?></span>
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-muted"><i class="bi bi-speedometer2 me-1" style="color:#3D74B9;"></i>Auto-évaluation</span>
                    <span class="small fw-semibold"><?= $aeCnt ?>/<?= $itCnt ?> objectifs</span>
                  </div>
                  <div class="progress" style="height:6px;border-radius:10px;background:#e2e8f0;">
                    <div class="progress-bar <?= $pClass ?>" role="progressbar" 
                         style="width:<?= $percent ?>%;border-radius:10px;"
                         aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="text-center mt-1">
                    <span class="small fw-semibold" style="color:<?= $percent<34?'#dc3545':($percent<67?'#ffc107':'#28a745') ?>;"><?= $percent ?>%</span>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="mt-auto d-grid gap-2">
                <a href="supervision-evaluer.php?fiche_id=<?= (int)$r['fiche_id'] ?>" 
                   class="btn btn-sm btn-fosip"
                   style="border-radius:10px;font-weight:500;">
                  <i class="bi bi-pencil-square me-1"></i> Évaluer
                </a>
                <a href="fiche-evaluation-complete.php?fiche_id=<?= (int)$r['fiche_id'] ?>" 
                   class="btn btn-sm btn-outline-primary"
                   style="border-radius:10px;border-color:#3D74B9;color:#3D74B9;">
                  <i class="bi bi-file-text me-1"></i> Voir synthèse
                </a>
                <button class="btn btn-sm btn-outline-danger btn-delete-eval"
                        data-fiche-id="<?= (int)$r['fiche_id'] ?>"
                        data-agent-nom="<?= htmlspecialchars($agentNom, ENT_QUOTES) ?>"
                        style="border-radius:10px;">
                  <i class="bi bi-trash me-1"></i> Annuler l'évaluation
                </button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Toasts -->
<div aria-live="polite" aria-atomic="true" class="position-relative">
  <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>
</div>

<script>
// Utilise la fonction globale showToast définie dans main.js (fallback minimal si absente)
function showToast(type, message, delay = 4000) {
  if (window.globalShowToast) return window.globalShowToast(type, message, delay);
  var c = document.getElementById('toast-container'); if (!c) { c = document.createElement('div'); c.id='toast-container'; c.className='position-fixed top-0 end-0 p-3'; c.style.zIndex=1080; document.body.appendChild(c); }
  var id = 't'+Date.now(); var bg='bg-primary text-white'; if (type==='success') bg='bg-success text-white'; if (type==='danger') bg='bg-danger text-white'; if (type==='warning') bg='bg-warning text-dark';
  var html = `<div id="${id}" class="toast ${bg}" role="alert" data-bs-delay="${delay}"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
  c.insertAdjacentHTML('beforeend', html); var el=document.getElementById(id); var bs=new bootstrap.Toast(el); bs.show(); el.addEventListener('hidden.bs.toast', ()=> el.remove());
}

// Gestion de la suppression d'évaluation
document.addEventListener('click', async function(e) {
  const btn = e.target.closest('.btn-delete-eval');
  if (!btn) return;
  const ficheId = btn.getAttribute('data-fiche-id');
  const agentNom = btn.getAttribute('data-agent-nom');
  const ok = await (window.confirmDialog ? confirmDialog({
    title: 'Suppression',
    message: `Supprimer l'évaluation complète de <strong>${agentNom}</strong> ?<br><br>Cela effacera :<br>• Évaluations de compétences<br>• Notes des objectifs<br>• Actions & recommandations<br><br>Opération irréversible.`,
    confirmText: 'Supprimer',
    confirmVariant: 'danger'
  }) : Promise.resolve(confirm('Confirmer la suppression ?')));
  if (!ok) return;
  btn.disabled = true;
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression...';
  const fd = new FormData(); fd.append('fiche_id', ficheId); fd.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
  fetch('supervision-evaluation-delete.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) { showToast('success', 'Évaluation supprimée'); setTimeout(()=> location.reload(), 800); }
      else { btn.disabled=false; btn.innerHTML=originalHTML; showToast('danger', 'Erreur: ' + (data.error||'Suppression échouée')); }
    })
    .catch(err => { console.error(err); btn.disabled=false; btn.innerHTML=originalHTML; showToast('danger','Erreur réseau'); });
});
</script>

<?php include('../includes/footer.php'); ?>
