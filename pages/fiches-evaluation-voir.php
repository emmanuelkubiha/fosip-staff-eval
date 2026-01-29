<?php
// pages/fiches-evaluation-voir.php
// Vue liste — colonnes : Projet / Poste | Période | Superviseur | Auto-évaluation | Commentaire | Évaluation | Statut | Actions
// - Retire la colonne "Nb objectifs" comme demandé
// - Colonne Commentaire montre présence/remarque coordination
// - Colonne Auto-évaluation montre icône couleur selon notes agrégées, sinon clignotant + bouton Ajouter
// - Conservé: modal suppression (AJAX + CSRF), toasts, verrouillage
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php'); // $pdo attendu
$current_page = 'fiche-evaluation-voir.php';
include('../includes/header.php');

/* ---------- Helpers ---------- */
// ...existing code...
function ensure_csrf(): string {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
  return $_SESSION['csrf_token'];
}

/* ---------- Auth ---------- */
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

/* ---------- GET filters ---------- */
$search = trim((string)($_GET['q'] ?? ''));
$periode_debut = $_GET['debut'] ?? '';
$periode_fin = $_GET['fin'] ?? '';
$statut_filter = $_GET['statut'] ?? ''; // complet | attente | encours

/* ---------- Fetch fiches ---------- */

// Ajout des dates de création et modification
$sql = "SELECT o.id, o.periode, o.nom_projet, o.poste, o.date_commencement, o.statut,
         o.superviseur_id, u.nom AS sup_nom, u.post_nom AS sup_post_nom,
         o.created_at AS fiche_created_at, o.updated_at AS fiche_updated_at
  FROM objectifs o
  LEFT JOIN users u ON o.superviseur_id = u.id
  WHERE o.user_id = :user_id";
$params = [':user_id' => $user_id];

if ($search !== '') {
  $sql .= " AND (o.nom_projet LIKE :s OR o.poste LIKE :s OR o.periode LIKE :s)";
  $params[':s'] = "%$search%";
}
if ($periode_debut !== '') {
  $sql .= " AND o.periode >= :debut"; $params[':debut'] = $periode_debut;
}
if ($periode_fin !== '') {
  $sql .= " AND o.periode <= :fin"; $params[':fin'] = $periode_fin;
}
// Filtre statut
if ($statut_filter !== '' && in_array($statut_filter, ['complet','attente','encours'], true)) {
  $sql .= " AND o.statut = :st"; 
  $params[':st'] = $statut_filter;
}
$sql .= " ORDER BY o.periode DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fiches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Aggregate auto_evaluation per fiche ----------
   Build auto_map[fiche_id] = ['depasse'=>n,'atteint'=>n,'non_atteint'=>n]
*/
$auto_map = [];
$ids = array_map('intval', array_column($fiches, 'id'));
if (!empty($ids) && tableExists($pdo, 'auto_evaluation')) {
  // simple safe IN using int-cast implode (keeps same approach)
  $in = implode(',', $ids);
  try {
    $sqlAuto = "SELECT fiche_id, note, COUNT(*) AS cnt FROM auto_evaluation WHERE fiche_id IN ($in) GROUP BY fiche_id, note";
    $stm = $pdo->query($sqlAuto);
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $fid = (int)$r['fiche_id'];
      $note = $r['note'];
      if (!isset($auto_map[$fid])) $auto_map[$fid] = ['depasse'=>0,'atteint'=>0,'non_atteint'=>0];
      if (in_array($note, ['depasse','atteint','non_atteint'], true)) $auto_map[$fid][$note] = (int)$r['cnt'];
    }
  } catch (Throwable $e) {
    error_log('Erreur aggregation auto_evaluation: ' . $e->getMessage());
  }
}
// ensure keys for all fiches
foreach ($ids as $fid) if (!isset($auto_map[(int)$fid])) $auto_map[(int)$fid] = ['depasse'=>0,'atteint'=>0,'non_atteint'=>0];

/* ---------- Préparer token CSRF ---------- */
$csrf = ensure_csrf();
?>

<style>
.page-header-gradient {
  background: linear-gradient(135deg, #3D74B9 0%, #5a8fd4 100%);
  color: white;
  padding: 2rem;
  border-radius: 15px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 15px rgba(61, 116, 185, 0.2);
}

.stats-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 2px solid #e9ecef;
  transition: all 0.3s ease;
  height: 100%;
}

.stats-card:hover {
  border-color: #3D74B9;
  box-shadow: 0 4px 15px rgba(61, 116, 185, 0.15);
  transform: translateY(-2px);
}

.stats-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.fiche-card {
  background: white;
  border-radius: 15px;
  border: 2px solid #e9ecef;
  transition: all 0.3s ease;
  overflow: hidden;
  min-height: 370px; /* Correction: assure une hauteur uniforme pour éviter les décalages */
  display: flex;
  flex-direction: column;
}

.fiche-card:hover {
  border-color: #3D74B9;
  box-shadow: 0 8px 25px rgba(61, 116, 185, 0.15);
  transform: translateY(-4px);
}

.fiche-card-header {
  background: linear-gradient(135deg, rgba(61, 116, 185, 0.05) 0%, rgba(61, 116, 185, 0.02) 100%);
  border-bottom: 2px solid #e9ecef;
  padding: 1.25rem;
}

.fiche-card .card-body {
  padding: 1.25rem !important;
}

.filter-pills .btn {
  border-radius: 25px;
  padding: 0.5rem 1.25rem;
  font-weight: 500;
  transition: all 0.3s ease;
}

.filter-pills .btn.active {
  background: linear-gradient(135deg, #3D74B9 0%, #5a8fd4 100%);
  border-color: #3D74B9;
  color: white;
  box-shadow: 0 4px 10px rgba(61, 116, 185, 0.3);
}

.search-box {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 2px solid #e9ecef;
  margin-bottom: 1.5rem;
}

.progress-stacked {
  height: 8px;
  border-radius: 10px;
  overflow: hidden;
  background: #e9ecef;
}
</style>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>

  <div class="col-md-9" style="padding-left: 6rem;">
    <div class="container mt-4">

      <!-- Header avec gradient -->
      <div class="page-header-gradient">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h3 class="mb-2 fw-bold">
              <i class="bi bi-journal-text me-2"></i> Vos fiches d'évaluation
            </h3>
            <p class="mb-0 opacity-90">Gérez et suivez l'évolution de vos évaluations de performance</p>
          </div>
          <a href="objectifs-ajouter.php" class="btn btn-light px-4" style="border-radius: 25px; font-weight: 600;">
            <i class="bi bi-plus-circle me-2"></i> Nouvelle fiche
          </a>
        </div>
      </div>

      <!-- Statistiques rapides -->
      <div class="row g-3 mb-4">
        <?php
        // Calculer les stats basées sur le statut de la table objectifs
        $totalFiches = count($fiches);
        $fichesComplet = 0;
        $fichesAttente = 0;
        $fichesEncours = 0;
        
        foreach ($fiches as $f) {
          if ($f['statut'] === 'complet') $fichesComplet++;
          elseif ($f['statut'] === 'attente') $fichesAttente++;
          elseif ($f['statut'] === 'encours') $fichesEncours++;
        }
        ?>
        <div class="col-md-3">
          <div class="stats-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Nombre total de fiches d'évaluation créées">
            <div class="d-flex align-items-center">
              <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                <i class="bi bi-file-earmark-text-fill"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold"><?php echo $totalFiches; ?></div>
                <small class="text-muted">Total fiches</small>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="stats-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Fiches avec statut 'complet' - évaluations finalisées et clôturées">
            <div class="d-flex align-items-center">
              <div class="stats-icon bg-success bg-opacity-10 text-success me-3">
                <i class="bi bi-check-circle-fill"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold"><?php echo $fichesComplet; ?></div>
                <small class="text-muted">Clôturées</small>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="stats-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Fiches avec statut 'attente' - en attente de validation ou d'action">
            <div class="d-flex align-items-center">
              <div class="stats-icon bg-warning bg-opacity-10 text-warning me-3">
                <i class="bi bi-clock-fill"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold"><?php echo $fichesAttente; ?></div>
                <small class="text-muted">En attente</small>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="stats-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Fiches avec statut 'encours' - évaluations actuellement en cours de traitement">
            <div class="d-flex align-items-center">
              <div class="stats-icon bg-info bg-opacity-10 text-info me-3">
                <i class="bi bi-arrow-repeat"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold"><?php echo $fichesEncours; ?></div>
                <small class="text-muted">En cours</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filtres statut rapides -->
      <div class="filter-pills d-flex gap-2 mb-4" role="group" aria-label="Filtre statut">
        <?php
          // helper pour active
          function activeStat($val){ return ($GLOBALS['statut_filter'] === $val) ? 'active' : ''; }
          $baseUrl = 'fiches-evaluation-voir.php';
          // Conserver autres paramètres (recherche/périodes)
          $queryBase = [];
          if ($search !== '') $queryBase['q'] = $search;
          if ($periode_debut !== '') $queryBase['debut'] = $periode_debut;
          if ($periode_fin !== '') $queryBase['fin'] = $periode_fin;
          function buildLink($extra){
            global $queryBase, $baseUrl; $q = array_merge($queryBase, $extra);
            return $baseUrl . (empty($q)?'':'?' . http_build_query($q));
          }
        ?>
        <a href="<?= buildLink([]) ?>" class="btn btn-outline-primary <?= $statut_filter===''?'active':'' ?>">
          <i class="bi bi-grid-3x3-gap me-1"></i> Tous
        </a>
        <a href="<?= buildLink(['statut'=>'encours']) ?>" class="btn btn-outline-primary <?= activeStat('encours') ?>">
          <i class="bi bi-arrow-repeat me-1"></i> En cours
        </a>
        <a href="<?= buildLink(['statut'=>'attente']) ?>" class="btn btn-outline-primary <?= activeStat('attente') ?>">
          <i class="bi bi-clock me-1"></i> En attente
        </a>
        <a href="<?= buildLink(['statut'=>'complet']) ?>" class="btn btn-outline-primary <?= activeStat('complet') ?>">
          <i class="bi bi-check-circle me-1"></i> Clôturés
        </a>
      </div>

      <!-- Barre de recherche et filtres -->
      <div class="search-box">
        <form class="row g-3 align-items-end" method="get" novalidate>
          <div class="col-md-4">
            <label class="form-label fw-semibold mb-2">
              <i class="bi bi-search me-1 text-primary"></i> Recherche
            </label>
            <input type="text" 
                   name="q" 
                   value="<?= htmlspecialchars($search) ?>" 
                   class="form-control" 
                   placeholder="Projet, poste, période..."
                   style="border-radius: 8px;">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold mb-2">
              <i class="bi bi-calendar-event me-1 text-primary"></i> Période début
            </label>
            <input type="month" 
                   name="debut" 
                   value="<?= htmlspecialchars($periode_debut) ?>" 
                   class="form-control"
                   style="border-radius: 8px;">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold mb-2">
              <i class="bi bi-calendar-check me-1 text-primary"></i> Période fin
            </label>
            <input type="month" 
                   name="fin" 
                   value="<?= htmlspecialchars($periode_fin) ?>" 
                   class="form-control"
                   style="border-radius: 8px;">
          </div>
          <?php if($statut_filter!==''): ?>
            <input type="hidden" name="statut" value="<?= htmlspecialchars($statut_filter) ?>">
          <?php endif; ?>
          <div class="col-md-2">
            <button class="btn btn-primary w-100 mb-2" style="border-radius: 8px;">
              <i class="bi bi-funnel me-1"></i> Filtrer
            </button>
            <a href="fiches-evaluation-voir.php" class="btn btn-outline-secondary w-100" style="border-radius: 8px;">
              <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
            </a>
          </div>
        </form>
      </div>

      <?php if (empty($fiches)): ?>
        <div class="alert alert-info border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #0dcaf0 !important;">
          <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill me-3" style="font-size: 2rem;"></i>
            <div>
              <h6 class="mb-1">Aucune fiche trouvée</h6>
              <small>Ajustez vos critères de recherche ou créez une nouvelle fiche d'évaluation</small>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Correction: Masonry responsive avec flex-wrap -->
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($fiches as $i => $f):
                $id = (int)$f['id'];

                // Aggregated auto-eval for this fiche
                $am = $auto_map[$id] ?? ['depasse'=>0,'atteint'=>0,'non_atteint'=>0];

                // Decide representative auto-eval state with priority
                if (!empty($am['depasse'])) {
                  $auto_state = 'depasse';
                  $auto_label = 'Dépasse';
                  $auto_icon_html = '<i class="bi bi-arrow-up-circle-fill" style="color:#198754"></i>';
                  $auto_class = 'text-success';
                } elseif (!empty($am['atteint'])) {
                  $auto_state = 'atteint';
                  $auto_label = 'Atteint';
                  $auto_icon_html = '<i class="bi bi-dash-circle-fill" style="color:#ffc107"></i>';
                  $auto_class = 'text-warning';
                } elseif (!empty($am['non_atteint'])) {
                  $auto_state = 'non_atteint';
                  $auto_label = 'Non atteint';
                  $auto_icon_html = '<i class="bi bi-arrow-down-circle-fill" style="color:#dc3545"></i>';
                  $auto_class = 'text-danger';
                } else {
                  $auto_state = 'absent';
                  $auto_label = 'Indisponible';
                  // icon grey plus clignotant label + ajouter button
                  $auto_icon_html = '<i class="bi bi-slash-circle" style="color:#6c757d"></i>';
                  $auto_class = 'text-muted blink'; // blink CSS class added below
                }

                // Commentaire coordination check
                $hasCom = false;
                if (tableExists($pdo, 'coordination_commentaires')) {
                  try {
                    $stC = $pdo->prepare("SELECT COUNT(*) FROM coordination_commentaires WHERE fiche_id = ?");
                    $stC->execute([$id]);
                    $hasCom = (int)$stC->fetchColumn() > 0;
                  } catch (Throwable $e) { error_log('coord check error: '.$e->getMessage()); }
                }

                // Supervision evaluation presence
                $hasEval = false;
                if (tableExists($pdo, 'supervisions')) {
                  try {
                    $stE = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
                    $stE->execute([$user_id, $f['periode']]);
                    $hasEval = (int)$stE->fetchColumn() > 0;
                  } catch (Throwable $e) { error_log('eval check error: '.$e->getMessage()); }
                }

                $statut = $f['statut'] ?? '';
                $badge = $statut === 'complet' ? 'success' : ($statut === 'attente' ? 'warning' : 'secondary');
                $locked = $hasCom || $hasEval;
              
                // Mini distribution bar (stacked) pour auto-évaluations
                $totalAE = $am['depasse'] + $am['atteint'] + $am['non_atteint'];
                $totalItems = $am['depasse'] + $am['atteint'] + $am['non_atteint']; // ici même distribution car map déjà cumulée
                $dPerc = $totalAE ? round($am['depasse']*100/$totalAE) : 0;
                $aPerc = $totalAE ? round($am['atteint']*100/$totalAE) : 0;
                $nPerc = $totalAE ? 100 - $dPerc - $aPerc : 0;
              ?>
              <div class="fiche-card mb-3" data-href="fiche-evaluation.php?id=<?php echo $id; ?>" style="cursor:pointer; flex: 1 1 320px; max-width: 370px;">
                <div class="fiche-card-header">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center flex-grow-1">
                      <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                        <i class="bi bi-kanban" style="color:#3D74B9; font-size: 1.25rem;"></i>
                      </div>
                      <div class="flex-grow-1">
                        <div class="fw-bold text-truncate" title="Projet"><?php echo htmlspecialchars($f['nom_projet'] ?? ''); ?></div>
                        <div class="small text-muted text-truncate" title="Poste"><?php echo htmlspecialchars($f['poste'] ?? ''); ?></div>
                      </div>
                    </div>
                    <span class="badge bg-<?php echo $badge; ?> px-3 py-2" style="border-radius: 20px;">
                      <?php echo htmlspecialchars(ucfirst($statut)); ?>
                    </span>
                  </div>
                </div>
                <div class="card-body d-flex flex-column">
                <div class="small mb-3 d-flex align-items-center text-muted">
                  <i class="bi bi-calendar-event me-2 text-primary"></i> 
                  <strong style="color: #495057;"><?php echo htmlspecialchars($f['periode'] ?? ''); ?></strong>
                </div>
                <div class="small mb-3 d-flex align-items-center">
                  <i class="bi bi-person-badge me-2 text-primary"></i>
                  <span class="text-muted">Superviseur:</span>
                  <strong class="ms-1" style="color: #495057;"><?php echo htmlspecialchars(trim(($f['sup_nom'] ?? '') . ' ' . ($f['sup_post_nom'] ?? ''))); ?></strong>
                </div>
                <div class="d-flex flex-wrap gap-2 small mb-2 align-items-center">
                  <div title="Auto-évaluation principale">
                    <?php echo $auto_icon_html; ?> <span class="<?php echo $auto_class; ?>"><?php echo htmlspecialchars($auto_label); ?></span>
                  </div>
                  <div>
                    <?php if ($hasEval): ?>
                      <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Évalué</span>
                    <?php else: ?>
                      <span class="badge bg-light text-muted"><i class="bi bi-clock me-1"></i>À évaluer</span>
                    <?php endif; ?>
                  </div>
                  <div>
                    <?php if ($hasCom): ?>
                      <span class="badge bg-info text-dark"><i class="bi bi-chat-left-text me-1"></i>Coordination</span>
                    <?php else: ?>
                      <span class="badge bg-light text-muted"><i class="bi bi-chat-left-dots me-1"></i>Pas de com.</span>
                    <?php endif; ?>
                  </div>
                </div>
                <!-- Mini barre empilée de distribution auto-éval -->
                <div class="progress-stacked mb-3">
                  <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $dPerc; ?>%" title="Dépasse <?php echo $dPerc; ?>%"></div>
                  <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $aPerc; ?>%" title="Atteint <?php echo $aPerc; ?>%"></div>
                  <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $nPerc; ?>%" title="Non atteint <?php echo $nPerc; ?>%"></div>
                </div>
                <!-- Dates de soumission et modification, discret en bas à droite -->
                <div class="d-flex justify-content-end align-items-center mt-2 mb-1">
                  <small class="text-secondary d-flex align-items-center gap-2" style="font-size:0.85em;">
                    <span title="Date de soumission">
                      <i class="bi bi-upload me-1" style="font-size:1em;opacity:0.7;"> Soumise le</i>
                        <?php
                        $hasCreated = !empty($f['fiche_created_at']);
                        $hasUpdated = !empty($f['fiche_updated_at']) && $f['fiche_updated_at'] !== $f['fiche_created_at'];
                        if ($hasCreated || $hasUpdated) {
                          echo '<span class="small text-muted" style="font-size:0.85em;line-height:1.2;">';
                          if ($hasCreated) {
                            echo '<i class="bi bi-calendar-plus"></i> ' . date('d/m/Y', strtotime($f['fiche_created_at']));
                          }
                          if ($hasUpdated) {
                            echo ' <i class="bi bi-pencil"></i> ' . date('d/m/Y', strtotime($f['fiche_updated_at']));
                          }
                          echo '</span>';
                        }
                        ?>
                    </span>
                    <span class="mx-1" style="opacity:0.5;">·</span>
                    <span title="Date de modification">
                      <i class="bi bi-pencil-square me-1" style="font-size:1em;opacity:0.7;"></i>
                      <!-- Affichage modif inclus dans la ligne compacte ci-dessus -->
                    </span>
                  </small>
                </div>
                <div class="mt-auto d-flex gap-2 flex-wrap">
                  <a href="fiche-evaluation.php?id=<?php echo $id; ?>" class="btn btn-sm btn-primary" title="Ouvrir" style="border-radius: 8px;">
                    <i class="bi bi-eye me-1"></i>Ouvrir
                  </a>
                  <?php if ($auto_state==='absent'): ?>
                    <a href="fiche-evaluation.php?id=<?php echo $id; ?>#auto-eval" class="btn btn-sm btn-success" title="Ajouter auto-évaluation" style="border-radius: 8px;">
                      <i class="bi bi-plus-circle me-1"></i>Auto‑éval
                    </a>
                  <?php else: ?>
                    <a href="imprimer-fiche-evaluation.php?fiche_id=<?php echo $id; ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Imprimer / PDF" style="border-radius: 8px;">
                      <i class="bi bi-printer me-1"></i>PDF
                    </a>
                  <?php endif; ?>
                  <?php if (!$locked): ?>
                    <a href="fiche-evaluation-modifier.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-warning" title="Modifier" style="border-radius: 8px;">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-danger btn-delete-fiche"
                            data-fiche-id="<?php echo $id; ?>"
                            data-fiche-projet="<?php echo htmlspecialchars($f['nom_projet'] ?? '', ENT_QUOTES); ?>"
                            title="Supprimer"
                            style="border-radius: 8px;">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled title="Verrouillé" style="border-radius: 8px;">
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
</div>

<!-- Modal suppression -->
<div class="modal fade" id="modalDeleteFiche" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-trash me-1"></i> Confirmer la suppression</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <p id="modalDeleteMessage" class="mb-2">Voulez-vous supprimer cette fiche ?</p>
        <div class="text-muted small">La suppression est irréversible. Si la fiche est verrouillée, la suppression sera refusée.</div>
      </div>
      <div class="modal-footer">
        <button id="btnCancelDelete" type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button id="btnConfirmDelete" type="button" class="btn btn-danger">Supprimer</button>
      </div>
    </div>
  </div>
</div>

<!-- Toasts container -->
<div aria-live="polite" aria-atomic="true" class="position-relative">
  <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>
</div>

<style>
/* simple blink animation for absent auto-eval label */
@keyframes blink {
  0% { opacity: 1; }
  50% { opacity: 0.25; }
  100% { opacity: 1; }
}
.blink { animation: blink 1.2s linear infinite; }
</style>

<script>
// clic sur la ligne (ignore clicks sur controls)
document.querySelectorAll('tr[data-href]').forEach(row=>{
  row.addEventListener('click', function(e){
    if (e.target.closest('a,button,input,svg,path')) return;
    window.location = this.dataset.href;
  });
});

// clic sur la carte (ignore clicks sur contrôles)
document.querySelectorAll('.fiche-card[data-href]').forEach(card => {
  card.addEventListener('click', function(e){
    if (e.target.closest('a,button,input,.btn,svg,path')) return;
    window.location = this.dataset.href;
  });
});

// toast helper
function showToast(type, message, delay = 4500) {
  var container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = 1080;
    document.body.appendChild(container);
  }
  var toastId = 't' + Date.now();
  var bg = 'bg-primary text-white';
  if (type === 'success') bg = 'bg-success text-white';
  if (type === 'danger')  bg = 'bg-danger text-white';
  if (type === 'warning') bg = 'bg-warning text-dark';
  var html = `
    <div id="${toastId}" class="toast ${bg}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}">
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
      </div>
    </div>`;
  container.insertAdjacentHTML('beforeend', html);
  var el = document.getElementById(toastId);
  var bs = new bootstrap.Toast(el);
  bs.show();
  el.addEventListener('hidden.bs.toast', function(){ el.remove(); });
}

// suppression modal + fetch
let ficheToDelete = null;
document.querySelectorAll('.btn-delete-fiche').forEach(btn=>{
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    ficheToDelete = { id: this.getAttribute('data-fiche-id'), projet: this.getAttribute('data-fiche-projet') };
    document.getElementById('modalDeleteMessage').textContent = 'Supprimer la fiche "' + (ficheToDelete.projet || '—') + '" (ID ' + ficheToDelete.id + ') ?';
    new bootstrap.Modal(document.getElementById('modalDeleteFiche')).show();
  });
});

document.getElementById('btnConfirmDelete').addEventListener('click', function(){
  if (!ficheToDelete || !ficheToDelete.id) return;
  const btn = this;
  btn.disabled = true;
  const original = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Suppression...';
  const body = 'fiche_id=' + encodeURIComponent(ficheToDelete.id) + '&csrf_token=' + encodeURIComponent('<?= $csrf ?>');
  fetch('fiche-supprimer.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body,
    credentials: 'same-origin'
  })
  .then(async resp => {
    let data = null;
    let raw = await resp.text();
    try { data = JSON.parse(raw); } catch(e) { data = null; }
    if (resp.ok && data && data.ok) {
      showToast('success', data.message || 'Fiche supprimée');
      new bootstrap.Modal(document.getElementById('modalDeleteFiche')).hide();
      setTimeout(()=> location.reload(), 700);
      return;
    }
    if (resp.status === 423 && data) showToast('warning', data.message || 'Suppression refusée : fiche verrouillée', 7000);
    else if (resp.status === 403) showToast('danger', data && data.error ? data.error : 'Non autorisé', 7000);
    else {
      showToast('danger',
        'Erreur HTTP ' + resp.status +
        '<div style="max-height:120px;overflow:auto;font-size:0.85em;background:#f8f9fa;border-radius:6px;padding:6px 8px;margin-top:4px;">'+
        raw.replace(/</g,'&lt;').replace(/>/g,'&gt;')+
        '</div>',
        12000
      );
    }
  })
  .catch(err => { console.error(err); showToast('danger', 'Erreur réseau lors de la suppression', 7000); })
  .finally(()=>{ btn.disabled = false; btn.innerHTML = original; ficheToDelete = null; });
});

// Initialiser les tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});

// afficher toast si serveur a mis $_SESSION['toast']
<?php if (!empty($_SESSION['toast'])):
  $t = $_SESSION['toast']; $type = json_encode($t['type']); $msg = json_encode($t['message']);
  unset($_SESSION['toast']);
?>
document.addEventListener('DOMContentLoaded', function(){ showToast(<?= $type ?>, <?= $msg ?>); });
<?php endif; ?>
</script>

<?php include('../includes/footer.php'); ?>
