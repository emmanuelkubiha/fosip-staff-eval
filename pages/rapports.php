<?php
// pages/rapports.php
// Page de génération et téléchargement de rapports pour la coordination

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

// --- Auth rôle coordination et admin ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['coordination', 'admin'])) {
  header('Location: unauthorized.php');
  exit;
}
$coord_user_id = (int)$_SESSION['user_id'];

$current_page = 'rapports.php';
include('../includes/header.php');

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Récupération des paramètres de filtrage
$periode_debut = $_GET['periode_debut'] ?? '';
$periode_fin = $_GET['periode_fin'] ?? '';
$superviseur = $_GET['superviseur'] ?? '';
$statut = $_GET['statut'] ?? '';

// Liste des superviseurs pour le filtre
$superviseurs = [];
$stmtSup = $pdo->query("SELECT DISTINCT u.id, u.nom, u.post_nom 
  FROM users u 
  WHERE u.role = 'superviseur' 
  ORDER BY u.nom, u.post_nom");
$superviseurs = $stmtSup->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$stats = [
  'total_fiches' => 0,
  'fiches_terminees' => 0,
  'fiches_evaluees' => 0,
  'fiches_encours' => 0,
  'total_agents' => 0,
  'total_objectifs' => 0,
  'objectifs_atteints' => 0,
  'moyenne_atteinte' => 0
];

// Total fiches
$stmt = $pdo->query("SELECT COUNT(*) FROM objectifs");
$stats['total_fiches'] = (int)$stmt->fetchColumn();

// Fiches par statut
$stmt = $pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut = 'termine'");
$stats['fiches_terminees'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut = 'evalue'");
$stats['fiches_evaluees'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut IN ('encours', 'attente')");
$stats['fiches_encours'] = (int)$stmt->fetchColumn();

// Total agents ayant des fiches
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM objectifs");
$stats['total_agents'] = (int)$stmt->fetchColumn();

// Total objectifs et objectifs atteints
$stmt = $pdo->query("SELECT COUNT(*) FROM objectifs_items");
$stats['total_objectifs'] = (int)$stmt->fetchColumn();

try {
  $stmt = $pdo->query("SELECT COUNT(*) FROM auto_evaluation WHERE statut_atteinte IN ('atteint', 'depasse')");
  $stats['objectifs_atteints'] = (int)$stmt->fetchColumn();
  
  if ($stats['total_objectifs'] > 0) {
    $stats['moyenne_atteinte'] = round(($stats['objectifs_atteints'] / $stats['total_objectifs']) * 100, 1);
  }
} catch (Exception $e) {
  // Table auto_evaluation n'existe pas
}

// Statistiques par superviseur
$statsSuperviseurs = [];
$sqlSup = "SELECT 
    u.id, u.nom, u.post_nom,
    COUNT(DISTINCT o.id) as nb_fiches,
    COUNT(DISTINCT o.user_id) as nb_agents,
    SUM(CASE WHEN o.statut = 'termine' THEN 1 ELSE 0 END) as nb_terminees,
    SUM(CASE WHEN o.statut = 'evalue' THEN 1 ELSE 0 END) as nb_evaluees,
    SUM(CASE WHEN o.statut IN ('encours', 'attente') THEN 1 ELSE 0 END) as nb_encours
  FROM users u
  LEFT JOIN objectifs o ON o.superviseur_id = u.id
  WHERE u.role = 'superviseur'
  GROUP BY u.id, u.nom, u.post_nom
  ORDER BY nb_fiches DESC";
$stmtSup = $pdo->query($sqlSup);
$statsSuperviseurs = $stmtSup->fetchAll(PDO::FETCH_ASSOC);

// Statistiques par période (6 derniers mois)
$statsPeriodes = [];
$sqlPer = "SELECT 
    o.periode,
    COUNT(DISTINCT o.id) as nb_fiches,
    COUNT(DISTINCT o.user_id) as nb_agents,
    SUM(CASE WHEN o.statut = 'termine' THEN 1 ELSE 0 END) as nb_terminees
  FROM objectifs o
  WHERE o.periode >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH), '%Y-%m')
  GROUP BY o.periode
  ORDER BY o.periode DESC";
$stmtPer = $pdo->query($sqlPer);
$statsPeriodes = $stmtPer->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container-fluid mt-4" style="padding-left: 8rem;">
      
      <!-- En-tête de page -->
      <div class="d-flex align-items-center mb-4 py-3 px-4 rounded-3 shadow-sm" style="background:linear-gradient(135deg, #3D74B9 0%, #2a5a94 100%);">
        <div class="me-3 d-flex align-items-center justify-content-center" style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;">
          <i class="bi bi-file-earmark-bar-graph" style="font-size:2rem;color:white;"></i>
        </div>
        <div class="flex-grow-1">
          <h3 class="mb-1 fw-bold text-white">Rapports et statistiques</h3>
          <div class="small text-white opacity-75">Consultez et téléchargez les rapports d'évaluation</div>
        </div>
      </div>

      <!-- Statistiques globales -->
      <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
              <div class="mb-2">
                <i class="bi bi-file-earmark-text" style="font-size:2.5rem;color:#3D74B9;"></i>
              </div>
              <h4 class="fw-bold mb-1" style="color:#3D74B9;"><?= $stats['total_fiches'] ?></h4>
              <p class="text-muted mb-0 small">Fiches totales</p>
            </div>
          </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
              <div class="mb-2">
                <i class="bi bi-check-circle" style="font-size:2.5rem;color:#28a745;"></i>
              </div>
              <h4 class="fw-bold mb-1 text-success"><?= $stats['fiches_terminees'] ?></h4>
              <p class="text-muted mb-0 small">Fiches terminées</p>
            </div>
          </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
              <div class="mb-2">
                <i class="bi bi-people" style="font-size:2.5rem;color:#6f42c1;"></i>
              </div>
              <h4 class="fw-bold mb-1" style="color:#6f42c1;"><?= $stats['total_agents'] ?></h4>
              <p class="text-muted mb-0 small">Agents évalués</p>
            </div>
          </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
              <div class="mb-2">
                <i class="bi bi-bullseye" style="font-size:2.5rem;color:#fd7e14;"></i>
              </div>
              <h4 class="fw-bold mb-1" style="color:#fd7e14;"><?= $stats['moyenne_atteinte'] ?>%</h4>
              <p class="text-muted mb-0 small">Taux d'atteinte</p>
              <small class="text-muted" style="font-size:0.7rem;"><?= $stats['objectifs_atteints'] ?>/<?= $stats['total_objectifs'] ?> objectifs</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Filtres de rapport -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
          <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtres de rapport</h5>
        </div>
        <div class="card-body">
          <form method="get" action="rapports.php" class="row g-3">
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Période début</label>
              <input type="month" class="form-control" name="periode_debut" value="<?= htmlspecialchars($periode_debut) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Période fin</label>
              <input type="month" class="form-control" name="periode_fin" value="<?= htmlspecialchars($periode_fin) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Superviseur</label>
              <select class="form-select" name="superviseur">
                <option value="">Tous les superviseurs</option>
                <?php foreach ($superviseurs as $sup): ?>
                  <option value="<?= $sup['id'] ?>" <?= $superviseur == $sup['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(trim($sup['nom'] . ' ' . $sup['post_nom'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Statut</label>
              <select class="form-select" name="statut">
                <option value="">Tous les statuts</option>
                <option value="termine" <?= $statut === 'termine' ? 'selected' : '' ?>>Terminé</option>
                <option value="evalue" <?= $statut === 'evalue' ? 'selected' : '' ?>>Évalué</option>
                <option value="encours" <?= $statut === 'encours' ? 'selected' : '' ?>>En cours</option>
                <option value="attente" <?= $statut === 'attente' ? 'selected' : '' ?>>En attente</option>
              </select>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-2"></i>Appliquer les filtres
              </button>
              <a href="rapports.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Réinitialiser
              </a>
            </div>
          </form>
        </div>
      </div>

      <!-- Actions de téléchargement -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
          <h5 class="mb-0"><i class="bi bi-download me-2"></i>Télécharger les rapports</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="card h-100 border" style="border-color:#3D74B9 !important;">
                <div class="card-body">
                  <div class="d-flex align-items-start mb-3">
                    <div class="me-3">
                      <i class="bi bi-file-earmark-pdf" style="font-size:2.5rem;color:#dc3545;"></i>
                    </div>
                    <div>
                      <h5 class="mb-1">Rapport PDF</h5>
                      <p class="text-muted small mb-0">Rapport formaté avec graphiques et tableaux</p>
                    </div>
                  </div>
                  <form method="post" action="rapports-export.php" target="_blank">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="format" value="pdf">
                    <input type="hidden" name="periode_debut" value="<?= htmlspecialchars($periode_debut) ?>">
                    <input type="hidden" name="periode_fin" value="<?= htmlspecialchars($periode_fin) ?>">
                    <input type="hidden" name="superviseur" value="<?= htmlspecialchars($superviseur) ?>">
                    <input type="hidden" name="statut" value="<?= htmlspecialchars($statut) ?>">
                    <button type="submit" class="btn btn-danger w-100">
                      <i class="bi bi-file-pdf me-2"></i>Télécharger PDF
                    </button>
                  </form>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="card h-100 border" style="border-color:#28a745 !important;">
                <div class="card-body">
                  <div class="d-flex align-items-start mb-3">
                    <div class="me-3">
                      <i class="bi bi-file-earmark-spreadsheet" style="font-size:2.5rem;color:#28a745;"></i>
                    </div>
                    <div>
                      <h5 class="mb-1">Rapport Excel</h5>
                      <p class="text-muted small mb-0">Données brutes pour analyse approfondie</p>
                    </div>
                  </div>
                  <form method="post" action="rapports-export.php" target="_blank">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="format" value="excel">
                    <input type="hidden" name="periode_debut" value="<?= htmlspecialchars($periode_debut) ?>">
                    <input type="hidden" name="periode_fin" value="<?= htmlspecialchars($periode_fin) ?>">
                    <input type="hidden" name="superviseur" value="<?= htmlspecialchars($superviseur) ?>">
                    <input type="hidden" name="statut" value="<?= htmlspecialchars($statut) ?>">
                    <button type="submit" class="btn btn-success w-100">
                      <i class="bi bi-file-excel me-2"></i>Télécharger Excel
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistiques par superviseur -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
          <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Performance par superviseur</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Superviseur</th>
                  <th class="text-center">Agents</th>
                  <th class="text-center">Fiches</th>
                  <th class="text-center">En cours</th>
                  <th class="text-center">Évaluées</th>
                  <th class="text-center">Terminées</th>
                  <th class="text-center">Taux</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($statsSuperviseurs)): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted">Aucune donnée disponible</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($statsSuperviseurs as $supStat): 
                    $taux = $supStat['nb_fiches'] > 0 ? round(($supStat['nb_terminees'] / $supStat['nb_fiches']) * 100) : 0;
                    $tauxClass = $taux >= 75 ? 'success' : ($taux >= 50 ? 'warning' : 'danger');
                  ?>
                    <tr>
                      <td class="fw-semibold">
                        <i class="bi bi-person-circle me-2 text-muted"></i>
                        <?= htmlspecialchars(trim($supStat['nom'] . ' ' . $supStat['post_nom'])) ?>
                      </td>
                      <td class="text-center"><?= $supStat['nb_agents'] ?></td>
                      <td class="text-center fw-bold"><?= $supStat['nb_fiches'] ?></td>
                      <td class="text-center">
                        <span class="badge bg-secondary"><?= $supStat['nb_encours'] ?></span>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-warning text-dark"><?= $supStat['nb_evaluees'] ?></span>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-success"><?= $supStat['nb_terminees'] ?></span>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-<?= $tauxClass ?>"><?= $taux ?>%</span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Statistiques par période -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
          <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Évolution par période (6 derniers mois)</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Période</th>
                  <th class="text-center">Agents</th>
                  <th class="text-center">Fiches créées</th>
                  <th class="text-center">Terminées</th>
                  <th class="text-center">Taux de complétion</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($statsPeriodes)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">Aucune donnée disponible</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($statsPeriodes as $perStat): 
                    $taux = $perStat['nb_fiches'] > 0 ? round(($perStat['nb_terminees'] / $perStat['nb_fiches']) * 100) : 0;
                    // Formater la période
                    $periodeDate = DateTime::createFromFormat('Y-m', $perStat['periode']);
                    $periodeFormatted = $periodeDate ? $periodeDate->format('F Y') : $perStat['periode'];
                    // Traduire le mois en français
                    $moisFr = [
                      'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars', 
                      'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
                      'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
                      'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
                    ];
                    foreach ($moisFr as $en => $fr) {
                      $periodeFormatted = str_replace($en, $fr, $periodeFormatted);
                    }
                  ?>
                    <tr>
                      <td class="fw-semibold">
                        <i class="bi bi-calendar-check me-2 text-primary"></i>
                        <?= htmlspecialchars($periodeFormatted) ?>
                      </td>
                      <td class="text-center"><?= $perStat['nb_agents'] ?></td>
                      <td class="text-center fw-bold"><?= $perStat['nb_fiches'] ?></td>
                      <td class="text-center">
                        <span class="badge bg-success"><?= $perStat['nb_terminees'] ?></span>
                      </td>
                      <td class="text-center">
                        <div class="progress" style="height:25px;">
                          <div class="progress-bar bg-<?= $taux >= 75 ? 'success' : ($taux >= 50 ? 'warning' : 'danger') ?>" 
                               role="progressbar" 
                               style="width:<?= $taux ?>%;" 
                               aria-valuenow="<?= $taux ?>" 
                               aria-valuemin="0" 
                               aria-valuemax="100">
                            <?= $taux ?>%
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
