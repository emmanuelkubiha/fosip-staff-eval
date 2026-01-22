<?php
// admin-fiches.php - Gestion complète des fiches d'évaluation (admin uniquement)
include('../includes/auth.php');
require_role(['admin']);
require_once('../includes/db.php');
include('../includes/header.php');

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf_admin = $_SESSION['csrf_token'];

// KPIs fiches globales
$kpi_fiches = ['total'=>0,'encours'=>0,'attente'=>0,'evalue'=>0,'termine'=>0,'sans_commentaire'=>0];
$rowK = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN statut='encours' THEN 1 ELSE 0 END) AS encours,
    SUM(CASE WHEN statut='attente' THEN 1 ELSE 0 END) AS attente,
    SUM(CASE WHEN statut='evalue' THEN 1 ELSE 0 END) AS evalue,
    SUM(CASE WHEN statut='termine' THEN 1 ELSE 0 END) AS termine
  FROM objectifs")->fetch(PDO::FETCH_ASSOC) ?: [];
foreach ($kpi_fiches as $k=>$_){ $kpi_fiches[$k] = (int)($rowK[$k] ?? 0); }
$kpi_fiches['sans_commentaire'] = (int)($pdo->query("SELECT COUNT(*) FROM objectifs o WHERE o.statut='evalue'")->fetchColumn() ?: 0);

// Filtres
$f_q = trim($_GET['q'] ?? '');
$f_statut = $_GET['statut'] ?? '';
$f_periode = $_GET['periode'] ?? '';
$f_sort = $_GET['sort'] ?? '';
$valid_statuts = ['encours','attente','evalue','termine'];
if ($f_statut && !in_array($f_statut,$valid_statuts,true)) $f_statut='';

$where=[]; $params=[];
if ($f_q !== '') {
  $where[] = "(u.nom LIKE ? OR u.post_nom LIKE ? OR u.email LIKE ? OR sup.nom LIKE ? OR sup.post_nom LIKE ? OR o.periode LIKE ? OR CAST(o.id AS CHAR) LIKE ?)";
  $like = "%$f_q%";
  array_push($params,$like,$like,$like,$like,$like,$like,$like);
}
if ($f_statut !== '') { $where[] = "o.statut = ?"; $params[] = $f_statut; }
if ($f_periode !== '') { $where[] = "o.periode = ?"; $params[] = $f_periode; }

$sqlF = "SELECT o.id, o.periode, o.statut, o.user_id, o.superviseur_id,
            u.nom, u.post_nom, u.email,
            sup.nom AS sup_nom, sup.post_nom AS sup_post,
            s.date_validation,
            (SELECT COUNT(*) FROM coordination_commentaires c WHERE c.fiche_id = o.id) AS has_coord
          FROM objectifs o
          JOIN users u ON u.id = o.user_id
          LEFT JOIN users sup ON sup.id = o.superviseur_id
          LEFT JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode";
if ($where) $sqlF .= " WHERE " . implode(' AND ',$where);

// Tri
$order = " ORDER BY o.periode DESC, o.id DESC";
if ($f_sort==='agent') $order = " ORDER BY u.nom, u.post_nom, o.periode DESC";
if ($f_sort==='statut') $order = " ORDER BY FIELD(o.statut,'encours','attente','evalue','termine'), o.periode DESC";
if ($f_sort==='age') $order = " ORDER BY s.date_validation IS NULL, s.date_validation ASC";
$sqlF .= $order . " LIMIT 150";

$stF = $pdo->prepare($sqlF);
$stF->execute($params);
$fiches = $stF->fetchAll(PDO::FETCH_ASSOC);

// Périodes disponibles (dernières 12)
$periodes = [];
foreach ($pdo->query("SELECT DISTINCT periode FROM objectifs ORDER BY periode DESC LIMIT 12") as $rr){
  $periodes[] = $rr['periode'];
}
?>

<style>
.page-header-admin {
  background: linear-gradient(135deg, #3D74B9 0%, #5a8fd4 100%);
  color: white;
  padding: 2rem;
  border-radius: 15px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 15px rgba(61, 116, 185, 0.2);
}

.stats-card-admin {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  border: 2px solid #e9ecef;
  transition: all 0.3s ease;
  height: 100%;
  text-align: center;
}

.stats-card-admin:hover {
  border-color: #3D74B9;
  box-shadow: 0 4px 15px rgba(61, 116, 185, 0.15);
  transform: translateY(-3px);
}

.stats-card-admin .stats-value {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.stats-card-admin .stats-label {
  font-size: 0.8rem;
  color: #6c757d;
  font-weight: 500;
}

.filter-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 2px solid #e9ecef;
  margin-bottom: 1.5rem;
}

.table-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  border: 2px solid #e9ecef;
}

.table-card .card-header {
  background: linear-gradient(135deg, rgba(61, 116, 185, 0.08) 0%, rgba(61, 116, 185, 0.03) 100%);
  border-bottom: 2px solid #e9ecef;
  padding: 1.25rem 1.5rem;
}

.badge-stat {
  padding: 0.4rem 0.875rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.75rem;
}

.table-card table thead th {
  font-weight: 600;
  font-size: 0.85rem;
  color: #495057;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #dee2e6;
  padding: 1rem 0.75rem;
}

.table-card table tbody tr {
  transition: all 0.2s ease;
}

.table-card table tbody tr:hover {
  background-color: rgba(61, 116, 185, 0.04);
}

.table-card table tbody td {
  vertical-align: middle;
  padding: 0.875rem 0.75rem;
  font-size: 0.875rem;
}

.btn-group .btn {
  transition: all 0.2s ease;
}

.btn-group .btn:hover {
  transform: scale(1.05);
  z-index: 1;
}
</style>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-3">
      <?php include('../includes/sidebar.php'); ?>
    </div>
    <div class="col-md-9 p-4" style="padding-left: 8rem !important;">
      <!-- En-tête avec gradient -->
      <div class="page-header-admin">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h4 class="mb-2"><i class="bi bi-clipboard2-data me-2"></i> Gestion des fiches d'évaluation</h4>
          </div>
          <div>
          </div>
        </div>
      </div>

      <!-- Statistiques -->
      <div class="row g-3 mb-4">
        <div class="col-md-2">
          <div class="stats-card-admin" data-bs-toggle="tooltip" title="Nombre total de fiches dans le système">
            <div class="stats-value" style="color:#3D74B9;">
              <i class="bi bi-file-earmark-text me-1"></i><?= (int)$kpi_fiches['total'] ?>
            </div>
            <div class="stats-label">Total fiches</div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="stats-card-admin" data-bs-toggle="tooltip" title="Fiches en cours de traitement">
            <div class="stats-value text-info">
              <i class="bi bi-arrow-repeat me-1"></i><?= (int)$kpi_fiches['encours'] ?>
            </div>
            <div class="stats-label">En cours</div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="stats-card-admin" data-bs-toggle="tooltip" title="Fiches en attente d'action">
            <div class="stats-value text-secondary">
              <i class="bi bi-clock me-1"></i><?= (int)$kpi_fiches['attente'] ?>
            </div>
            <div class="stats-label">Attente</div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="stats-card-admin" data-bs-toggle="tooltip" title="Fiches évaluées par superviseur">
            <div class="stats-value text-warning">
              <i class="bi bi-clipboard-check me-1"></i><?= (int)$kpi_fiches['evalue'] ?>
            </div>
            <div class="stats-label">Évaluées</div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="stats-card-admin" data-bs-toggle="tooltip" title="Fiches terminées et clôturées">
            <div class="stats-value text-success">
              <i class="bi bi-check-circle me-1"></i><?= (int)$kpi_fiches['termine'] ?>
            </div>
            <div class="stats-label">Terminées</div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="stats-card-admin" data-bs-toggle="tooltip" title="Fiches sans commentaire de coordination">
            <div class="stats-value text-danger">
              <i class="bi bi-chat-x me-1"></i><?= (int)$kpi_fiches['sans_commentaire'] ?>
            </div>
            <div class="stats-label">Sans coord.</div>
          </div>
        </div>
      </div>

      <!-- Filtres et recherche -->
      <div class="filter-card">
        <h6 class="mb-3" style="color: #3D74B9;"><i class="bi bi-funnel me-2"></i>Filtres et recherche</h6>
        <form class="row g-3 mb-3" method="get">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($f_sort) ?>">
          <div class="col-md-4">
            <label class="form-label small fw-semibold"><i class="bi bi-search me-1"></i>Recherche</label>
            <input class="form-control" 
                   type="text" 
                   name="q" 
                   value="<?= htmlspecialchars($f_q) ?>" 
                   placeholder="ID, agent, superviseur, email..."
                   style="border-radius: 8px;">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold"><i class="bi bi-tag me-1"></i>Statut</label>
            <select class="form-select" name="statut" onchange="this.form.submit()" style="border-radius: 8px;">
              <option value="">Tous statuts</option>
              <?php foreach ($valid_statuts as $st): ?>
                <option value="<?= $st ?>" <?= $f_statut===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold"><i class="bi bi-calendar-range me-1"></i>Période</label>
            <select class="form-select" name="periode" onchange="this.form.submit()" style="border-radius: 8px;">
              <option value="">Toutes périodes</option>
              <?php foreach ($periodes as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $f_periode===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit" style="border-radius: 8px;">
              <i class="bi bi-funnel-fill me-1"></i>Filtrer
            </button>
            <a class="btn btn-outline-secondary" href="admin-fiches.php" title="Réinitialiser" style="border-radius: 8px;">
              <i class="bi bi-arrow-clockwise"></i>
            </a>
          </div>
        </form>
        
        <div class="border-top pt-3">
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="text-muted small fw-semibold"><i class="bi bi-sort-down me-1"></i>Trier par:</span>
            <a class="btn btn-sm btn-outline-primary <?= $f_sort==='agent'?'active':'' ?>" 
               href="?sort=agent&q=<?= urlencode($f_q) ?>&statut=<?= urlencode($f_statut) ?>&periode=<?= urlencode($f_periode) ?>"
               style="border-radius: 20px;">
              <i class="bi bi-person me-1"></i>Agent
            </a>
            <a class="btn btn-sm btn-outline-primary <?= $f_sort==='statut'?'active':'' ?>" 
               href="?sort=statut&q=<?= urlencode($f_q) ?>&statut=<?= urlencode($f_statut) ?>&periode=<?= urlencode($f_periode) ?>"
               style="border-radius: 20px;">
              <i class="bi bi-tag me-1"></i>Statut
            </a>
            <a class="btn btn-sm btn-outline-primary <?= $f_sort==='age'?'active':'' ?>" 
               href="?sort=age&q=<?= urlencode($f_q) ?>&statut=<?= urlencode($f_statut) ?>&periode=<?= urlencode($f_periode) ?>"
               style="border-radius: 20px;">
              <i class="bi bi-clock-history me-1"></i>Ancienneté
            </a>
          </div>
        </div>
      </div>

      <!-- Tableau fiches -->
      <div class="table-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h6 class="mb-0 fw-bold" style="color:#3D74B9;">
            <i class="bi bi-table me-2"></i> Liste des fiches d'évaluation
          </h6>
          <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2" style="border-radius: 20px; font-weight: 600;">
              <i class="bi bi-file-earmark-text me-1"></i><?= count($fiches) ?> résultats
            </span>
            <span class="badge bg-light text-muted px-3 py-2" style="border-radius: 20px;">
              <i class="bi bi-info-circle me-1"></i>Max 150
            </span>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 50px;">#</th>
                  <th>Agent</th>
                  <th>Superviseur</th>
                  <th>Période</th>
                  <th>Statut</th>
                  <th><i class="bi bi-chat-left-text me-1"></i>Commentaire coord.</th>
                  <th>Validation sup.</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="fichesTableBody">
                <?php if (empty($fiches)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-3">Aucune fiche trouvée.</td></tr>
                <?php else: 
                  $rowNumber = 1; // Compteur pour la numérotation
                  foreach ($fiches as $fa):
                  $nm = trim(($fa['nom']??'').' '.($fa['post_nom']??''));
                  $snm = trim(($fa['sup_nom']??'').' '.($fa['sup_post']??''));
                  $coordOk = ((int)$fa['has_coord']>0);
                ?>
                <tr>
                  <td class="text-center"><span class="badge bg-light text-dark" style="font-weight: 600;"><?= $rowNumber++ ?></span></td>
                  <td>
                    <div><?= htmlspecialchars($nm) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($fa['email'] ?? '') ?></div>
                  </td>
                  <td><?= htmlspecialchars($snm ?: '—') ?></td>
                  <td><?= htmlspecialchars($fa['periode'] ?? '') ?></td>
                  <td>
                    <?php
                      $badgeClass = 'bg-light text-dark';
                      $badgeIcon = 'circle-fill';
                      if ($fa['statut'] === 'encours') {
                        $badgeClass = 'bg-info text-white';
                        $badgeIcon = 'arrow-repeat';
                      } elseif ($fa['statut'] === 'attente') {
                        $badgeClass = 'bg-secondary';
                        $badgeIcon = 'clock';
                      } elseif ($fa['statut'] === 'evalue') {
                        $badgeClass = 'bg-warning text-dark';
                        $badgeIcon = 'clipboard-check';
                      } elseif ($fa['statut'] === 'termine') {
                        $badgeClass = 'bg-success';
                        $badgeIcon = 'check-circle';
                      }
                    ?>
                    <span class="badge badge-stat <?= $badgeClass ?>">
                      <i class="bi bi-<?= $badgeIcon ?> me-1"></i><?= htmlspecialchars($fa['statut'] ?? '') ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <?= $coordOk ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?>
                  </td>
                  <td><?= htmlspecialchars($fa['date_validation'] ?? '—') ?></td>
                  <td class="text-end">
                    <div class="d-flex gap-2 justify-content-end">
                      <a class="btn btn-sm btn-outline-primary" 
                         href="fiche-evaluation-complete.php?fiche_id=<?= (int)$fa['id'] ?>" 
                         title="Voir la fiche"
                         style="border-radius: 8px;">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if ($coordOk): ?>
                        <button type="button" 
                                class="btn btn-sm btn-outline-warning" 
                                data-bs-toggle="modal" 
                                data-bs-target="#adminPasswordModal"
                                data-fiche-id="<?= (int)$fa['id'] ?>"
                                data-action-type="delete_coord_comment"
                                title="Supprimer commentaire coordination"
                                style="border-radius: 8px;">
                          <i class="bi bi-chat-x"></i>
                        </button>
                      <?php endif; ?>
                      <button type="button" 
                              class="btn btn-sm btn-outline-danger" 
                              data-bs-toggle="modal" 
                              data-bs-target="#adminPasswordModal"
                              data-fiche-id="<?= (int)$fa['id'] ?>"
                              data-action-type="delete_fiche"
                              title="Supprimer fiche complète"
                              style="border-radius: 8px;">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Note d'avertissement -->
      <div class="mt-3 p-3" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.08) 0%, rgba(220, 53, 69, 0.03) 100%); border-radius: 12px; border-left: 4px solid #dc3545;">
        <div class="d-flex align-items-start">
          <i class="bi bi-exclamation-triangle-fill text-danger me-3" style="font-size: 1.5rem;"></i>
          <div>
            <strong style="color: #dc3545;">Avertissement important</strong>
            <p class="mb-0 small text-muted mt-1">
              La suppression d'une fiche est <strong>irréversible</strong> et entraîne la suppression de <strong>tous les enregistrements liés</strong> : 
              objectifs, items, auto-évaluations, résumés, commentaires de coordination et supervisions.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<!-- Modal confirmation admin -->
<div class="modal fade" id="adminPasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
      <!-- Header dynamique -->
      <div class="modal-header border-0 text-white" id="modalHeader" style="padding: 1.75rem 2rem; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
        <div class="w-100">
          <h5 class="modal-title mb-1 fw-bold" id="modalTitle">
            <i class="bi bi-shield-lock-fill me-2"></i> Confirmation requise
          </h5>
          <small style="opacity: 0.9;" id="modalSubtitle">Action sensible nécessitant votre authentification</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body" style="padding: 2rem;">
        
        <!-- Message spécifique pour suppression de fiche -->
        <div id="deleteFicheDetails" class="d-none">
          <div class="card border-danger mb-4" style="border-width: 2px; border-radius: 12px;">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                  <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 1.75rem;"></i>
                </div>
                <div>
                  <h6 class="mb-1 fw-bold text-danger">Action irréversible</h6>
                  <small class="text-muted">Suppression complète de la fiche d'évaluation</small>
                </div>
              </div>
              
              <div class="alert alert-danger border-0 mb-0" style="background-color: #fff5f5;">
                <p class="mb-2 fw-semibold"><i class="bi bi-folder-x me-2"></i>Éléments qui seront supprimés :</p>
                <div class="row g-2">
                  <div class="col-md-6">
                    <div class="d-flex align-items-start">
                      <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                      <small>Tous les objectifs et items</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-start">
                      <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                      <small>Toutes les auto-évaluations</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-start">
                      <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                      <small>Tous les résumés d'évaluation</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-start">
                      <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                      <small>Commentaires de coordination</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-start">
                      <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                      <small>Validations de supervision</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex align-items-start">
                      <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                      <small>Historique complet</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Message spécifique pour suppression commentaire coordination -->
        <div id="deleteCoordDetails" class="d-none">
          <div class="card border-warning mb-4" style="border-width: 2px; border-radius: 12px;">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                  <i class="bi bi-chat-left-text-fill text-warning" style="font-size: 1.75rem;"></i>
                </div>
                <div>
                  <h6 class="mb-1 fw-bold text-warning">Suppression du commentaire</h6>
                  <small class="text-muted">Le commentaire de coordination sera retiré</small>
                </div>
              </div>
              
              <div class="alert alert-warning border-0 mb-0" style="background-color: #fffbf0;">
                <p class="mb-2"><i class="bi bi-arrow-left-right me-2"></i><strong>Changement de statut :</strong></p>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-success px-3 py-2">TERMINÉE</span>
                  <i class="bi bi-arrow-right text-muted"></i>
                  <span class="badge bg-warning text-dark px-3 py-2">ÉVALUÉE</span>
                </div>
                <small class="d-block mt-2 text-muted">
                  <i class="bi bi-info-circle me-1"></i>
                  La fiche reviendra au statut "évaluée" après suppression du commentaire
                </small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section authentification -->
        <div class="card border-secondary" style="border-radius: 12px;">
          <div class="card-body">
            <label class="form-label fw-bold mb-3 d-flex align-items-center">
              <i class="bi bi-shield-lock-fill me-2 text-primary"></i> 
              Authentification administrateur
            </label>
            <div class="position-relative">
              <input type="password" 
                     class="form-control form-control-lg" 
                     id="adminConfirmPassword" 
                     placeholder="Entrez votre mot de passe" 
                     autocomplete="current-password"
                     style="border-radius: 10px; padding-right: 3.5rem; border: 2px solid #dee2e6; font-size: 1rem;">
              <button type="button" 
                      class="btn position-absolute top-50 end-0 translate-middle-y me-2" 
                      onclick="togglePasswordVisibility()"
                      tabindex="-1"
                      style="border: none; background: transparent; color: #6c757d;">
                <i class="bi bi-eye-fill" id="togglePasswordIcon"></i>
              </button>
            </div>
            <div class="d-flex align-items-center mt-2 text-muted">
              <i class="bi bi-info-circle-fill me-2" style="font-size: 0.875rem;"></i>
              <small>Votre mot de passe est requis pour valider cette action critique</small>
            </div>
          </div>
        </div>
        
        <input type="hidden" id="adminActionFicheId" value="">
        <input type="hidden" id="adminActionType" value="">
      </div>
      
      <!-- Footer -->
      <div class="modal-footer border-0" style="padding: 1.25rem 2rem; background-color: #f8f9fa;">
        <button type="button" 
                class="btn btn-light border px-4 py-2" 
                data-bs-dismiss="modal"
                style="border-radius: 8px; font-weight: 500;">
          <i class="bi bi-x-lg me-2"></i> Annuler
        </button>
        <button type="button" 
                class="btn btn-danger px-4 py-2" 
                id="adminDoActionBtn"
                style="border-radius: 8px; font-weight: 500; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border: none;">
          <i class="bi bi-check-lg me-2"></i> Confirmer la suppression
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Variables globales
const CSRF_TOKEN = '<?= htmlspecialchars($csrf_admin) ?>';
let currentFicheId = null;
let currentActionType = null;

// Fonction pour basculer la visibilité du mot de passe
function togglePasswordVisibility() {
  const passwordInput = document.getElementById('adminConfirmPassword');
  const toggleIcon = document.getElementById('togglePasswordIcon');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    toggleIcon.classList.remove('bi-eye-fill');
    toggleIcon.classList.add('bi-eye-slash-fill');
  } else {
    passwordInput.type = 'password';
    toggleIcon.classList.remove('bi-eye-slash-fill');
    toggleIcon.classList.add('bi-eye-fill');
  }
}

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM chargé, initialisation...');
  
  const modalElement = document.getElementById('adminPasswordModal');
  
  if (!modalElement) {
    console.error('Modal non trouvée!');
    return;
  }
  
  // Écouter l'ouverture de la modal
  modalElement.addEventListener('show.bs.modal', function(event) {
    console.log('Modal en cours d\'ouverture');
    
    // Récupérer le bouton qui a déclenché la modal
    const button = event.relatedTarget;
    
    if (button) {
      // Récupérer les données du bouton
      currentFicheId = button.getAttribute('data-fiche-id');
      currentActionType = button.getAttribute('data-action-type');
      
      console.log('Fiche ID:', currentFicheId, 'Action:', currentActionType);
      
      // Mettre à jour les champs cachés
      document.getElementById('adminActionFicheId').value = currentFicheId;
      document.getElementById('adminActionType').value = currentActionType;
      
      // Cacher tous les détails spécifiques
      document.getElementById('deleteFicheDetails').classList.add('d-none');
      document.getElementById('deleteCoordDetails').classList.add('d-none');
      
      // Éléments à modifier
      const modalHeader = document.getElementById('modalHeader');
      const modalTitle = document.getElementById('modalTitle');
      const modalSubtitle = document.getElementById('modalSubtitle');
      const confirmBtn = document.getElementById('adminDoActionBtn');
      
      if (currentActionType === 'delete_fiche') {
        // Configuration pour suppression de fiche
        modalHeader.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
        modalTitle.innerHTML = '<i class="bi bi-trash-fill me-2"></i> Suppression de fiche complète';
        modalSubtitle.textContent = 'Action irréversible - Toutes les données liées seront supprimées';
        document.getElementById('deleteFicheDetails').classList.remove('d-none');
        confirmBtn.innerHTML = '<i class="bi bi-trash-fill me-2"></i> Supprimer définitivement';
        confirmBtn.className = 'btn btn-danger px-4 py-2';
        confirmBtn.style.borderRadius = '8px';
        confirmBtn.style.fontWeight = '500';
        confirmBtn.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
        confirmBtn.style.border = 'none';
      } else if (currentActionType === 'delete_coord_comment') {
        // Configuration pour suppression commentaire
        modalHeader.style.background = 'linear-gradient(135deg, #ffc107 0%, #ffb300 100%)';
        modalTitle.innerHTML = '<i class="bi bi-chat-left-text-fill me-2"></i> Suppression commentaire coordination';
        modalSubtitle.textContent = 'La fiche reviendra au statut "évaluée"';
        document.getElementById('deleteCoordDetails').classList.remove('d-none');
        confirmBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i> Confirmer la suppression';
        confirmBtn.className = 'btn btn-warning px-4 py-2 text-dark';
        confirmBtn.style.borderRadius = '8px';
        confirmBtn.style.fontWeight = '500';
        confirmBtn.style.background = 'linear-gradient(135deg, #ffc107 0%, #ffb300 100%)';
        confirmBtn.style.border = 'none';
      }
    }
  });
  
  // Focus automatique sur le champ mot de passe quand la modal s'ouvre
  modalElement.addEventListener('shown.bs.modal', function() {
    document.getElementById('adminConfirmPassword').focus();
  });
  
  // Réinitialiser le champ quand la modal se ferme
  modalElement.addEventListener('hidden.bs.modal', function() {
    document.getElementById('adminConfirmPassword').value = '';
    document.getElementById('adminConfirmPassword').type = 'password';
    const toggleIcon = document.getElementById('togglePasswordIcon');
    if (toggleIcon) {
      toggleIcon.classList.remove('bi-eye-slash-fill');
      toggleIcon.classList.add('bi-eye-fill');
    }
  });
  
  // Gestionnaire pour le bouton de confirmation
  const confirmBtn = document.getElementById('adminDoActionBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', handleConfirmation);
  }
  
  // Permettre la validation avec Enter
  const passwordInput = document.getElementById('adminConfirmPassword');
  if (passwordInput) {
    passwordInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleConfirmation();
      }
    });
  }
  
  console.log('Initialisation terminée');
});

// Fonction pour afficher un toast
function afficherToast(type, message) {
  console.log('afficherToast:', type, message);
  
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '11000';
    document.body.appendChild(container);
  }
  
  const toastId = 'toast-' + Date.now();
  let bgClass = 'bg-primary';
  let icon = 'info-circle-fill';
  let title = 'Info';
  
  if (type === 'success') {
    bgClass = 'bg-success';
    icon = 'check-circle-fill';
    title = 'Succès';
  } else if (type === 'danger') {
    bgClass = 'bg-danger';
    icon = 'exclamation-triangle-fill';
    title = 'Erreur';
  } else if (type === 'warning') {
    bgClass = 'bg-warning text-dark';
    icon = 'exclamation-circle-fill';
    title = 'Attention';
  }
  
  const toastHTML = `
    <div id="${toastId}" class="toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header ${bgClass} text-white border-0">
        <i class="bi bi-${icon} me-2"></i>
        <strong class="me-auto">${title}</strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">${message}</div>
    </div>
  `;
  
  container.insertAdjacentHTML('beforeend', toastHTML);
  
  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, { delay: 4000 });
  toast.show();
  
  toastElement.addEventListener('hidden.bs.toast', function() {
    toastElement.remove();
  });
}

// Fonction pour gérer la confirmation
function handleConfirmation() {
  const ficheId = document.getElementById('adminActionFicheId').value;
  const actionType = document.getElementById('adminActionType').value;
  const password = document.getElementById('adminConfirmPassword').value.trim();
  const btn = document.getElementById('adminDoActionBtn');
  
  console.log('handleConfirmation - ficheId:', ficheId, 'actionType:', actionType);
  
  if (!password) {
    afficherToast('warning', 'Mot de passe requis pour confirmer cette action.');
    return;
  }
  
  btn.disabled = true;
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Traitement...';
  
  const formData = new FormData();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('action', actionType);
  formData.append('fiche_id', ficheId);
  formData.append('password', password);
  
  fetch('admin-action.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
    
    if (!data.ok) {
      afficherToast('danger', data.error || 'Erreur inconnue');
      return;
    }
    
    let successMsg = 'Opération réussie';
    if (actionType === 'delete_fiche') {
      successMsg = 'Fiche et toutes ses dépendances supprimées avec succès';
    } else if (actionType === 'delete_coord_comment') {
      successMsg = 'Commentaire de coordination supprimé. Fiche remise en statut "évaluée"';
    }
    
    afficherToast('success', successMsg);
    
    // Fermer la modal
    const modalElement = document.getElementById('adminPasswordModal');
    const modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) {
      modal.hide();
    }
    
    setTimeout(() => {
      window.location.reload();
    }, 1200);
  })
  .catch(error => {
    console.error('Erreur réseau:', error);
    btn.disabled = false;
    btn.innerHTML = originalHTML;
    afficherToast('danger', 'Erreur réseau - Impossible de contacter le serveur');
  });
}
</script>

<?php include('../includes/footer.php'); ?>
