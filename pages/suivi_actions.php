<?php
// pages/suivi_actions.php
// Gestion des actions & recommandations post-coordination
// Rôle: coordination (lecture/écriture). Liste des fiches supervisées + commentées par la coordination.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'suivi_actions.php';
include('../includes/header.php');

// --- Auth ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'coordination') {
  header('Location: unauthorized.php');
  exit;
}
$coord_user_id = (int)$_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

function tableExists(PDO $pdo, string $table): bool {
  try { $st = $pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}

$search = trim((string)($_GET['q'] ?? ''));
$periode = trim((string)($_GET['periode'] ?? '')); // YYYY-MM
$filter = $_GET['filter'] ?? ''; // 'sans-actions' pour cibler celles sans actions/reco

// Base: fiches complètes, supervision complète et commentaire coordination présent
$sql = "SELECT o.id AS fiche_id, o.periode, o.nom_projet, o.poste, o.user_id AS agent_id, o.superviseur_id,
               u.nom AS agent_nom, u.post_nom AS agent_post_nom,
               sup.nom AS sup_nom, sup.post_nom AS sup_post_nom,
               c.commentaire AS coord_commentaire
        FROM objectifs o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users sup ON o.superviseur_id = sup.id
        JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut = 'complet'
        JOIN coordination_commentaires c ON c.fiche_id = o.id AND c.commentaire IS NOT NULL AND c.commentaire <> ''
        WHERE o.statut = 'complet'";
$params = [];
if ($search !== '') {
  $sql .= " AND (o.nom_projet LIKE :s OR o.poste LIKE :s OR u.nom LIKE :s OR u.post_nom LIKE :s OR o.periode LIKE :s)";
  $params[':s'] = "%$search%";
}
if ($periode !== '') {
  $sql .= " AND o.periode = :p"; $params[':p'] = $periode;
}
$sql .= " ORDER BY o.periode DESC, o.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$fiches = $st->fetchAll(PDO::FETCH_ASSOC);

// Pré-charger existence actions/reco pour chaque fiche via une requête groupée en construisant (superviseur_id, agent_id, periode -> cycle)
// On affichera un badge 'Aucun' ou 'Présent'; le chargement précis/édition se fait via modal AJAX.
function periodeToMonthYear(string $periode): array {
  // 'YYYY-MM' -> [annee, mois] int
  $annee = (int)substr($periode, 0, 4);
  $mois = (int)substr($periode, 5, 2);
  return [$annee, $mois];
}

$mapHasActions = [];
if (!empty($fiches)) {
  // On essaie de retrouver les cycles existants pour réduire les requêtes
  $byPeriod = [];
  foreach ($fiches as $f) { $byPeriod[$f['periode']] = true; }
  $cycles = [];
  if (tableExists($pdo, 'evaluation_cycles')) {
    $in = [];
    $paramsC = [];
    $idx = 0;
    foreach (array_keys($byPeriod) as $p) {
      [$a,$m] = periodeToMonthYear($p);
      $in[] = '(mois = :m'.$idx.' AND annee = :a'.$idx.')';
      $paramsC[':m'.$idx] = $m; $paramsC[':a'.$idx] = $a; $idx++;
    }
    if ($in) {
      $sqlc = 'SELECT id, mois, annee FROM evaluation_cycles WHERE '.implode(' OR ', $in);
      $sc = $pdo->prepare($sqlc);
      $sc->execute($paramsC);
      foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) { $cycles[(int)$r['annee'].'-'.str_pad((int)$r['mois'],2,'0',STR_PAD_LEFT)] = (int)$r['id']; }
    }
  }
  // Pour chaque fiche, si cycle id connu, vérifier suivi_actions existence
  if (tableExists($pdo, 'suivi_actions')) {
    foreach ($fiches as $f) {
      $key = $f['periode'];
      $cid = $cycles[$key] ?? null;
      if ($cid) {
        $qs = $pdo->prepare('SELECT COUNT(*) FROM suivi_actions WHERE superviseur_id = ? AND supervise_id = ? AND cycle_id = ?');
        $qs->execute([(int)$f['superviseur_id'], (int)$f['agent_id'], (int)$cid]);
        $mapHasActions[(int)$f['fiche_id']] = ((int)$qs->fetchColumn() > 0);
      } else {
        $mapHasActions[(int)$f['fiche_id']] = false;
      }
    }
  }
}

?>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container mt-4">

      <div class="d-flex align-items-start mb-3">
        <h4 class="mb-0" style="color:#3D74B9;"><i class="bi bi-clipboard-check me-2"></i> Suivi & actions après coordination</h4>
        <div class="ms-auto">
          <a href="suivi_actions.php?filter=sans-actions" class="btn btn-sm btn-outline-warning me-2"><i class="bi bi-hourglass-split me-1"></i> Sans actions</a>
          <a href="suivi_actions.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i> Tout</a>
        </div>
      </div>

      <p class="text-muted mb-3">Renseignez les actions et recommandations à mettre en œuvre après le commentaire final de la coordination.</p>

      <form class="row g-2 align-items-end mb-3" method="get" novalidate>
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
          <a href="suivi_actions.php" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
        </div>
      </form>

      <?php if (empty($fiches)): ?>
        <div class="alert alert-info">Aucune fiche trouvée répondant aux critères.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:40px">#</th>
                <th>Agent</th>
                <th>Projet / Poste</th>
                <th>Période</th>
                <th>Superviseur</th>
                <th>Coordination</th>
                <th>Actions / Recommandations</th>
                <th style="width:220px">Gestion</th>
              </tr>
            </thead>
            <tbody>
              <?php $idx=1; foreach ($fiches as $f):
                $has = $mapHasActions[$f['fiche_id']] ?? false;
                if ($filter==='sans-actions' && $has) continue;
                $agentNom = trim(($f['agent_nom'] ?? '').' '.($f['agent_post_nom'] ?? ''));
                $supNom = trim(($f['sup_nom'] ?? '').' '.($f['sup_post_nom'] ?? ''));
              ?>
              <tr>
                <td><?= $idx++ ?></td>
                <td><strong><?= htmlspecialchars($agentNom) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($f['nom_projet'] ?? '') ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($f['poste'] ?? '') ?></small>
                </td>
                <td><?= htmlspecialchars($f['periode'] ?? '') ?></td>
                <td><?= htmlspecialchars($supNom) ?></td>
                <td>
                  <?php if (!empty($f['coord_commentaire'])): ?>
                    <span class="badge bg-success">Commenté</span>
                  <?php else: ?>
                    <span class="badge bg-light text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($has): ?>
                    <span class="badge bg-primary">Présent</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">Aucun</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex gap-1 flex-wrap">
                    <a href="fiche-evaluation.php?id=<?= (int)$f['fiche_id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir fiche"><i class="bi bi-eye"></i></a>
                    <button class="btn btn-sm btn-outline-success btn-suivi" data-fiche-id="<?= (int)$f['fiche_id'] ?>" data-projet="<?= htmlspecialchars($f['nom_projet'] ?? '', ENT_QUOTES) ?>" title="Ajouter / Modifier suivi">
                      <i class="bi bi-pencil-square me-1"></i> Saisir
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Modal suivi actions -->
<div class="modal fade" id="modalSuivi" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="formSuivi" class="modal-content" method="post" action="suivi-actions-save.php" onsubmit="return false;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="fiche_id" id="suivi_fiche_id" value="">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i> <span id="suiviModalTitle">Suivi & actions</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Actions à mener</label>
            <textarea name="actions" id="suivi_actions" class="form-control" rows="7" placeholder="Actions concrètes à mettre en œuvre..." required></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Recommandations</label>
            <textarea name="recommandations" id="suivi_recommandations" class="form-control" rows="7" placeholder="Recommandations et appuis nécessaires..." required></textarea>
          </div>
        </div>
        <div class="form-text mt-2">Le suivi est lié à la période de la fiche et au binôme (superviseur ⇄ agent). Un cycle est créé si nécessaire.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-fosip" id="btnSaveSuivi">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Toasts -->
<div aria-live="polite" aria-atomic="true" class="position-relative">
  <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>
</div>

<style>
@keyframes blink { 0%{opacity:1} 50%{opacity:.35} 100%{opacity:1} }
.blink { animation: blink 1.2s linear infinite; }
</style>

<script>
function showToast(type, message, delay = 4500) {
  var container = document.getElementById('toast-container');
  if (!container) { container = document.createElement('div'); container.id='toast-container'; container.className='position-fixed top-0 end-0 p-3'; container.style.zIndex=1080; document.body.appendChild(container); }
  var id = 't' + Date.now(); var bg='bg-primary text-white';
  if (type==='success') bg='bg-success text-white'; if (type==='danger') bg='bg-danger text-white'; if (type==='warning') bg='bg-warning text-dark';
  var html = `<div id="${id}" class="toast ${bg}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
  container.insertAdjacentHTML('beforeend', html);
  var el = document.getElementById(id); var bs = new bootstrap.Toast(el); bs.show(); el.addEventListener('hidden.bs.toast', ()=> el.remove());
}

let suiviModal = null;
function openSuiviModal(ficheId, projet) {
  document.getElementById('suivi_fiche_id').value = ficheId;
  document.getElementById('suiviModalTitle').textContent = 'Suivi & actions — ' + (projet || 'Fiche #' + ficheId);
  document.getElementById('suivi_actions').value = '';
  document.getElementById('suivi_recommandations').value = '';
  suiviModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSuivi'));
  suiviModal.show();
  // charger données existantes
  fetch('suivi-actions-save.php?load=1&fiche_id='+encodeURIComponent(ficheId))
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(data => {
      if (data && data.ok) {
        document.getElementById('suivi_actions').value = data.actions || '';
        document.getElementById('suivi_recommandations').value = data.recommandations || '';
      }
    })
    .catch(()=>{});
}

document.addEventListener('click', function(e){
  const btn = e.target.closest('.btn-suivi');
  if (btn) {
    openSuiviModal(btn.getAttribute('data-fiche-id'), btn.getAttribute('data-projet'));
  }
});

const formSuivi = document.getElementById('formSuivi');
formSuivi.addEventListener('submit', function(){
  const btn = document.getElementById('btnSaveSuivi');
  btn.disabled = true; const original = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement...';
  const fd = new FormData(formSuivi);
  fd.append('action','save');
  fetch('suivi-actions-save.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(json => {
      if (!json.ok) { showToast('danger', json.error || 'Erreur'); btn.disabled=false; btn.innerHTML=original; return; }
      showToast('success', json.message || 'Enregistré');
      suiviModal.hide();
      setTimeout(()=> location.reload(), 600);
    })
    .catch(err => { console.error(err); showToast('danger','Erreur réseau'); btn.disabled=false; btn.innerHTML=original; });
});
</script>

<?php include('../includes/footer.php'); ?>
