<?php
// pages/coordination.php
// Vue coordination finale : liste toutes les fiches évaluées par les superviseurs et attend un commentaire final.
// Objectif : afficher d'abord les fiches SANS commentaire (clignotant + bouton Ajouter), puis celles déjà commentées.
// Tri: prioriser sans commentaire, puis période desc, puis id desc.
// Permet d'ajouter / mettre à jour un commentaire coordination via modal AJAX.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'coordination.php';
include('../includes/header.php');

// --- Auth rôle coordination ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'coordination') {
  header('Location: unauthorized.php');
  exit;
}
$coord_user_id = (int)$_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Helper: tableExists
function tableExists(PDO $pdo, string $table): bool {
  try { $st = $pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}

// Filtre recherche / période
$search = trim((string)($_GET['q'] ?? ''));
$filter = $_GET['filter'] ?? ''; // 'encours' pour seulement sans commentaire
$periode = $_GET['periode'] ?? ''; // mois spécifique

// Vérifier existence de la table des commentaires (pas besoin de la table supervisions)
$hasCoordCom = tableExists($pdo, 'coordination_commentaires');

// Base SQL : fiches évaluées ou terminées, sans dépendre de la table supervisions
$sql = "SELECT o.id AS fiche_id, o.periode, o.nom_projet, o.poste, o.user_id, o.statut,
    u.nom AS agent_nom, u.post_nom AS agent_post_nom,
    sup.nom AS sup_nom, sup.post_nom AS sup_post_nom,
    c.commentaire AS coord_commentaire,
    c.coord_id AS coordonateur_id,
    c.supervise_id AS superviseur_comment_id,
    c.date_commentaire AS date_commentaire
  FROM objectifs o
  JOIN users u ON o.user_id = u.id
  LEFT JOIN users sup ON o.superviseur_id = sup.id
  LEFT JOIN coordination_commentaires c ON c.fiche_id = o.id
  WHERE o.statut IN ('evalue','termine')";
$params = [];
if ($search !== '') {
  $sql .= " AND (o.nom_projet LIKE :s OR o.poste LIKE :s OR u.nom LIKE :s OR u.post_nom LIKE :s OR o.periode LIKE :s)";
  $params[':s'] = "%$search%";
}
if ($periode !== '') {
  $sql .= " AND o.periode = :periode";
  $params[':periode'] = $periode;
}
if ($filter === 'encours') {
  $sql .= " AND o.statut = 'evalue'";
} elseif ($filter === 'cloture') {
  $sql .= " AND o.statut = 'termine'";
}
$sql .= " ORDER BY (c.commentaire IS NULL OR c.commentaire = '') DESC, o.periode DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fiches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les stats d'objectifs atteints/dépassés par fiche depuis auto_evaluation
$objectifsStats = [];
if (!empty($fiches)) {
  $ficheIds = array_map(fn($f) => (int)$f['fiche_id'], $fiches);
  $in = implode(',', $ficheIds);
  try {
    // Compter total d'items par fiche
    $stTotal = $pdo->query("SELECT fiche_id, COUNT(*) AS total FROM objectifs_items WHERE fiche_id IN ($in) GROUP BY fiche_id");
    $totaux = [];
    foreach ($stTotal->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $totaux[(int)$row['fiche_id']] = (int)$row['total'];
    }
    // Compter items atteints ou dépassés (statut_atteinte IN ('atteint','depasse'))
    if (tableExists($pdo, 'auto_evaluation')) {
      $stAtteint = $pdo->query("SELECT fiche_id, COUNT(*) AS atteints FROM auto_evaluation WHERE fiche_id IN ($in) AND statut_atteinte IN ('atteint','depasse') GROUP BY fiche_id");
      foreach ($stAtteint->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['fiche_id'];
        $objectifsStats[$fid] = [
          'atteints' => (int)$row['atteints'],
          'total' => $totaux[$fid] ?? 0
        ];
      }
    }
    // Compléter avec les fiches sans auto-évaluation
    foreach ($totaux as $fid => $tot) {
      if (!isset($objectifsStats[$fid])) {
        $objectifsStats[$fid] = ['atteints' => 0, 'total' => $tot];
      }
    }
  } catch (Throwable $e) {
    // Ignorer erreur silencieusement
  }
}

// Pré-calcul éventuel
$nbSans = 0; $nbAvec = 0;
?>

<div class="row">
  <div class="col-md-3 sidebar-col">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9 content-col">
    <div class="container mt-4">
      <div class="d-flex align-items-center mb-3 py-2 px-3 rounded-3 shadow-sm" style="background:#f8f9fa;">
        <div class="me-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:#e9ecef;border-radius:50%;">
          <i class="bi bi-chat-left-text" style="font-size:2rem;color:#6c757d;"></i>
        </div>
        <div class="flex-grow-1">
          <h4 class="mb-1 fw-bold" style="color:#495057;">Commentaire final des évaluations</h4>
          <div class="small text-muted">Accorder, modifier ou supprimer le commentaire final d'une évaluation supervisée.</div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
          <a href="coordination.php" class="btn btn-sm btn-outline-secondary<?= ($filter==='' || $filter==='all') ? ' active' : '' ?>" title="Toutes les fiches">Toutes</a>
          <a href="coordination.php?filter=encours" class="btn btn-sm btn-outline-secondary<?= $filter==='encours' ? ' active' : '' ?>" title="À commenter">À commenter</a>
          <a href="coordination.php?filter=cloture" class="btn btn-sm btn-outline-secondary<?= $filter==='cloture' ? ' active' : '' ?>" title="Clôturées">Clôturées</a>
        </div>
      </div>

      <form class="row g-2 align-items-end mb-3" method="get" action="coordination.php" novalidate>
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="col-sm-5 col-md-4 col-lg-5">
          <label class="form-label mb-0 small">Recherche</label>
            <input type="text" class="form-control form-control-sm" name="q" placeholder="Agent, projet, poste..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-sm-4 col-md-3 col-lg-3">
          <label class="form-label mb-0 small">Période</label>
          <input type="month" class="form-control form-control-sm" name="periode" value="<?= htmlspecialchars($periode) ?>">
        </div>
        <div class="col-sm-3 col-md-2 col-lg-2">
          <button class="btn btn-primary btn-sm w-100" type="submit">Filtrer</button>
        </div>
        <div class="col-sm-3 col-md-2 col-lg-2">
          <a href="coordination.php?filter=<?= urlencode($filter) ?>" class="btn btn-outline-secondary btn-sm w-100">Réinit.</a>
        </div>
      </form>

      <?php if (empty($fiches)): ?>
        <div class="alert alert-info">Aucune fiche ne correspond à vos critères.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($fiches as $i => $f):
            $needs = (string)($f['statut'] ?? '') === 'evalue';
            $agentNom = trim(($f['agent_nom'] ?? '') . ' ' . ($f['agent_post_nom'] ?? ''));
            $supNom = trim(($f['sup_nom'] ?? '') . ' ' . ($f['sup_post_nom'] ?? ''));
            $badge = ($f['statut'] === 'termine') ? 'success' : (($f['statut'] === 'evalue') ? 'warning' : 'secondary');
            $fid = (int)$f['fiche_id'];
            // Statistiques objectifs
            $objAtteints = $objectifsStats[$fid]['atteints'] ?? 0;
            $objTotal = $objectifsStats[$fid]['total'] ?? 0;
          ?>
          <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-<?= $needs ? 'warning' : 'success' ?>">
              <div class="card-body d-flex flex-column card-clickable" data-fiche-id="<?= (int)$f['fiche_id'] ?>" style="cursor:pointer;">
                <div class="d-flex align-items-start mb-3">
                  <div class="rounded-circle bg-light p-2 me-2"><i class="bi bi-person" style="color:#6c757d"></i></div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold" title="Agent"><?= htmlspecialchars($agentNom) ?></div>
                    <div class="small text-muted" title="Période"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($f['periode'] ?? '') ?></div>
                  </div>
                  <span class="badge bg-<?= $badge ?> ms-2"><?= htmlspecialchars(ucfirst($f['statut'])) ?></span>
                </div>

                <div class="mb-2 pb-2 border-bottom">
                  <div class="fw-semibold text-secondary mb-1" title="Projet"><i class="bi bi-kanban me-1"></i><?= htmlspecialchars($f['nom_projet'] ?? '') ?></div>
                  <div class="small text-muted" title="Poste"><i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($f['poste'] ?? '') ?></div>
                </div>

                <div class="small mb-2">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted"><i class="bi bi-person-badge me-1"></i> Superviseur:</span>
                    <span class="fw-semibold"><?= htmlspecialchars($supNom ?: '—') ?></span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted"><i class="bi bi-bullseye me-1"></i> Objectifs atteints:</span>
                    <span class="badge <?= $objAtteints >= $objTotal ? 'bg-success' : 'bg-warning text-dark' ?>">
                      <?= $objAtteints ?>/<?= $objTotal ?>
                    </span>
                  </div>
                </div>

                <div class="mb-3 pt-2 border-top">
                  <span class="small text-muted me-2"><i class="bi bi-chat-left-dots me-1"></i> Commentaire final:</span>
                  <?php if ($needs): ?>
                    <span class="badge bg-light text-dark blink border">Aucun</span>
                  <?php else: ?>
                    <span class="badge bg-success">Ajouté</span>
                  <?php endif; ?>
                </div>

                <div class="mt-auto d-flex flex-wrap gap-2">
                  <?php if ($needs): ?>
                    <button type="button" class="btn btn-sm btn-success btn-add-com flex-grow-1" data-fiche-id="<?= (int)$f['fiche_id'] ?>" data-fiche-projet="<?= htmlspecialchars($f['nom_projet'] ?? '', ENT_QUOTES) ?>" title="Ajouter commentaire final">
                      <i class="bi bi-plus-circle me-1"></i> Ajouter commentaire
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-warning btn-edit-com" data-fiche-id="<?= (int)$f['fiche_id'] ?>" data-fiche-projet="<?= htmlspecialchars($f['nom_projet'] ?? '', ENT_QUOTES) ?>" title="Modifier commentaire">
                      <i class="bi bi-pencil me-1"></i> Modifier
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-del-com" data-fiche-id="<?= (int)$f['fiche_id'] ?>" title="Supprimer commentaire">
                      <i class="bi bi-trash me-1"></i> Supprimer
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- (Ancien tableau supprimé) -->

<!-- Modal commentaire coordination -->
<div class="modal fade" id="modalCoordCom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="formCoordCom" class="modal-content" method="post" action="coordination-save.php" onsubmit="return false;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="fiche_id" id="coord_fiche_id" value="">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i> <span id="coordModalTitle">Ajouter commentaire</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Commentaire final</label>
          <textarea name="commentaire" id="coord_commentaire" class="form-control" rows="5" required placeholder="Votre synthèse finale sur la fiche..."></textarea>
          <div class="form-text">Ce commentaire clôture la fiche après la supervision. Il est visible par l'agent et le superviseur.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-fosip" id="btnSaveCoord">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast container -->
<div aria-live="polite" aria-atomic="true" class="position-relative">
  <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>
</div>

<style>
@keyframes blink { 0%{opacity:1} 50%{opacity:.35} 100%{opacity:1} }
.blink { animation: blink 1.2s linear infinite; }
.card-clickable:hover { transform: translateY(-2px); transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; }
</style>

<script>
function showToast(type, message, delay = 4500) {
  var container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = 1080;
    document.body.appendChild(container);
  }
  var id = 't' + Date.now();
  var bg = 'bg-primary text-white';
  if (type==='success') bg='bg-success text-white';
  if (type==='danger') bg='bg-danger text-white';
  if (type==='warning') bg='bg-warning text-dark';
  var html = `<div id="${id}" class="toast ${bg}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
  container.insertAdjacentHTML('beforeend', html);
  var el = document.getElementById(id); var bs = new bootstrap.Toast(el); bs.show(); el.addEventListener('hidden.bs.toast', ()=> el.remove());
}

// Ouvrir modal ajout / édition
let coordModal = null;
function openCoordModal(ficheId, projet, existingText) {
  document.getElementById('coord_fiche_id').value = ficheId;
  const ta = document.getElementById('coord_commentaire');
  ta.value = existingText || '';
  document.getElementById('coordModalTitle').textContent = existingText ? 'Modifier commentaire' : 'Ajouter commentaire';
  coordModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCoordCom'));
  coordModal.show();
}

// Gestion des boutons d'action (intercepter AVANT la propagation)
document.addEventListener('click', function(e){
  // Vérifier si on a cliqué sur un bouton ou à l'intérieur d'un bouton
  const addBtn = e.target.closest('.btn-add-com');
  const editBtn = e.target.closest('.btn-edit-com');
  const delBtn = e.target.closest('.btn-del-com');
  
  if (addBtn) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Bouton ajouter cliqué, fiche:', addBtn.getAttribute('data-fiche-id'));
    openCoordModal(addBtn.getAttribute('data-fiche-id'), addBtn.getAttribute('data-fiche-projet'), '');
    return;
  }
  
  if (editBtn) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Bouton modifier cliqué');
    const ficheId = editBtn.getAttribute('data-fiche-id');
    fetch('coordination-save.php?fiche_id='+encodeURIComponent(ficheId)+'&load=1')
      .then(r => r.ok ? r.json() : Promise.reject())
      .then(data => {
        openCoordModal(ficheId, editBtn.getAttribute('data-fiche-projet'), data.commentaire || '');
      })
      .catch(()=> openCoordModal(ficheId, editBtn.getAttribute('data-fiche-projet'), ''));
    return;
  }
  
  if (delBtn) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Bouton supprimer cliqué');
    const ficheId = delBtn.getAttribute('data-fiche-id');
    const ask = (window.confirmDialog)
      ? confirmDialog({
          title: 'Confirmation',
          message: "Supprimer le commentaire final de cette fiche ?<br><small class='text-muted'>Cette action est irréversible.</small>",
          confirmText: 'Supprimer',
          confirmVariant: 'danger'
        })
      : Promise.resolve(confirm('Supprimer le commentaire final de cette fiche ?'));
    ask.then(ok => {
      if (!ok) return;
      // Toast d'information immédiat avant l'appel réseau
      showToast('warning','Suppression du commentaire en cours...');
      const fd = new FormData();
      fd.append('csrf_token','<?= htmlspecialchars($csrf) ?>');
      fd.append('fiche_id', ficheId);
      fd.append('action','delete');
      fetch('coordination-save.php', { method:'POST', body: fd })
        .then r => r.json())
        .then(j => { if (j.ok) { showToast('success', j.message || 'Supprimé'); setTimeout(()=>location.reload(),600); } else { showToast('danger', j.error||'Erreur suppression'); } })
        .catch(()=> showToast('danger','Erreur réseau'));
    });
    return;
  }
  
  // Clic sur carte : ouvrir fiche complète (seulement si aucun bouton cliqué)
  const card = e.target.closest('.card-clickable');
  if (card && !e.target.closest('button')) {
    const ficheId = card.getAttribute('data-fiche-id');
    window.location.href = 'fiche-evaluation-complete.php?fiche_id=' + ficheId;
  }
}, true); // Utiliser la phase de capture pour intercepter AVANT

// Sauvegarde AJAX
const formCoord = document.getElementById('formCoordCom');
formCoord.addEventListener('submit', function(){
  const btn = document.getElementById('btnSaveCoord');
  btn.disabled = true; const original = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement...';
  const fd = new FormData(formCoord);
  fd.append('action','save');
  fetch('coordination-save.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(json => {
      if (!json.ok) { showToast('danger', json.error || 'Erreur'); btn.disabled=false; btn.innerHTML=original; return; }
      const status = (json.status || '').toLowerCase();
      const ficheIdRedirect = json.fiche_id || fd.get('fiche_id');
      const msg = status === 'added' ? "Commentaire d'évaluation ajouté" : "Commentaire d'évaluation mis à jour";
      showToast('success', msg, 2500);
      coordModal.hide();
      // Redirection vers la fiche complète pour récapitulatif
      setTimeout(()=> { window.location.href = 'fiche-evaluation-complete.php?fiche_id=' + encodeURIComponent(ficheIdRedirect) + '&coord_comment=' + (status==='added'?'added':'updated'); }, 800);
    })
    .catch(err => { console.error(err); showToast('danger','Erreur réseau'); btn.disabled=false; btn.innerHTML=original; });
});
</script>

<?php include('../includes/footer.php'); ?>
