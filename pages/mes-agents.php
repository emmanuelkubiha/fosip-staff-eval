<?php
// pages/mes-agents.php
// Page de visualisation des agents supervisés par le superviseur connecté
// Lecture seule - Seul l'admin peut gérer les affectations

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
$current_page = 'mes-agents';
include __DIR__ . '/../includes/header.php';

// Vérification de la connexion
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Vérification du rôle (superviseur ou coordination)
if (!in_array($role, ['superviseur', 'coordination'])) {
  header('Location: dashboard.php');
  exit;
}

// Récupération des agents sous supervision
$stmt = $pdo->prepare("
  SELECT 
    u.id,
    u.nom,
    u.post_nom,
    u.email,
    u.fonction,
    u.role,
    u.photo,
    COUNT(DISTINCT o.id) AS total_objectifs,
    SUM(CASE WHEN o.statut = 'termine' THEN 1 ELSE 0 END) AS objectifs_termines,
    SUM(CASE WHEN o.statut = 'encours' THEN 1 ELSE 0 END) AS objectifs_encours,
    MAX(o.updated_at) AS derniere_activite
  FROM users u
  LEFT JOIN objectifs o ON o.user_id = u.id
  WHERE u.superviseur_id = ?
  GROUP BY u.id
  ORDER BY u.nom, u.post_nom
");
$stmt->execute([$user_id]);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$total_agents = count($agents);
$agents_actifs = 0;
$total_objectifs_tous = 0;

foreach ($agents as $agent) {
  if ($agent['total_objectifs'] > 0) $agents_actifs++;
  $total_objectifs_tous += (int)$agent['total_objectifs'];
}
?>

<style>
  /* ========================================
     THEME FOSIP - Override Bootstrap Primary
     ======================================== */
  .btn-primary {
    background-color: #3D74B9 !important;
    border-color: #3D74B9 !important;
    color: white !important;
  }
  
  .btn-primary:hover,
  .btn-primary:focus {
    background-color: #2a5a94 !important;
    border-color: #2a5a94 !important;
  }
  
  .btn-outline-primary {
    border-color: #3D74B9 !important;
    color: #3D74B9 !important;
  }
  
  .btn-outline-primary:hover {
    background-color: #3D74B9 !important;
    color: white !important;
  }
  
  .badge.bg-primary {
    background-color: #3D74B9 !important;
  }
  
  .text-primary {
    color: #3D74B9 !important;
  }
  
  .border-primary {
    border-color: #3D74B9 !important;
  }
  
  /* ========================================
     STYLES SPÉCIFIQUES PAGE MES AGENTS
     ======================================== */
  
  /* Carte agent */
  .agent-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
  }
  
  .agent-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(61, 116, 185, 0.15);
    border-color: #3D74B9;
  }
  
  /* Photo de profil */
  .agent-photo {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #3D74B9;
  }
  
  /* Badge de statut */
  .status-badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
  }
  
  /* Statistiques agent */
  .agent-stat {
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
  }
  
  .agent-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3D74B9;
    display: block;
  }
  
  .agent-stat-label {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  /* État vide */
  .empty-state {
    padding: 4rem 2rem;
    text-align: center;
  }
  
  .empty-state-icon {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 1rem;
  }
</style>

<div class="container-fluid">
  <div class="row">
    <div class="col-lg-3 col-xl-3">
      <?php include(__DIR__ . '/../includes/sidebar.php'); ?>
    </div>
    
    <div class="col-lg-9 col-xl-9 p-4">
      <!-- En-tête de page -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="d-flex align-items-center gap-3 mb-2">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <h4 class="mb-0">
              <i class="bi bi-people me-2" style="color:#3D74B9"></i>
              Mes agents sous supervision
            </h4>
          </div>
          <p class="text-muted mb-0 small">
            Liste des collaborateurs dont vous êtes responsable de l'évaluation
          </p>
        </div>
      </div>

      <!-- Statistiques globales -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 p-3" style="background-color: rgba(61, 116, 185, 0.1);">
                <i class="bi bi-people-fill" style="font-size: 2rem; color: #3D74B9;"></i>
              </div>
              <div>
                <div class="small text-muted">Total agents</div>
                <div class="h3 mb-0" style="color: #3D74B9;"><?= $total_agents ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 p-3 bg-success bg-opacity-10">
                <i class="bi bi-person-check-fill text-success" style="font-size: 2rem;"></i>
              </div>
              <div>
                <div class="small text-muted">Agents actifs</div>
                <div class="h3 mb-0 text-success"><?= $agents_actifs ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 p-3 bg-info bg-opacity-10">
                <i class="bi bi-clipboard-data-fill text-info" style="font-size: 2rem;"></i>
              </div>
              <div>
                <div class="small text-muted">Objectifs totaux</div>
                <div class="h3 mb-0 text-info"><?= $total_objectifs_tous ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Liste des agents -->
      <?php if (empty($agents)): ?>
        <div class="card shadow-sm border-0">
          <div class="card-body empty-state">
            <i class="bi bi-person-x empty-state-icon"></i>
            <h5 class="text-muted mb-2">Aucun agent sous votre supervision</h5>
            <p class="text-muted small mb-0">
              Contactez l'administrateur pour vous assigner des agents à superviser.
            </p>
          </div>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($agents as $agent): 
            $photo_path = '../assets/img/profiles/' . ($agent['photo'] ?? 'default.PNG');
            $performance = $agent['total_objectifs'] > 0 
              ? round(($agent['objectifs_termines'] / $agent['total_objectifs']) * 100) 
              : 0;
            
            $status_class = $agent['objectifs_encours'] > 0 ? 'bg-success' : 'bg-secondary';
            $status_text = $agent['objectifs_encours'] > 0 ? 'Actif' : 'Inactif';
          ?>
            <div class="col-md-6 col-xl-4">
              <div class="card agent-card h-100">
                <div class="card-body">
                  <!-- En-tête avec photo et statut -->
                  <div class="d-flex align-items-start mb-3">
                    <img src="<?= htmlspecialchars($photo_path) ?>" 
                         alt="Photo" 
                         class="agent-photo me-3"
                         onerror="this.src='../assets/img/profiles/default.PNG'">
                    <div class="flex-grow-1">
                      <h5 class="mb-1">
                        <?= htmlspecialchars($agent['nom'] . ' ' . $agent['post_nom']) ?>
                      </h5>
                      <p class="text-muted small mb-2">
                        <i class="bi bi-briefcase me-1"></i>
                        <?= htmlspecialchars($agent['fonction'] ?? 'Non défini') ?>
                      </p>
                      <span class="status-badge <?= $status_class ?> text-white">
                        <?= $status_text ?>
                      </span>
                    </div>
                  </div>

                  <!-- Informations de contact -->
                  <div class="mb-3 pb-3 border-bottom">
                    <div class="small text-muted mb-1">
                      <i class="bi bi-envelope me-2"></i>
                      <?= htmlspecialchars($agent['email'] ?? 'Non renseigné') ?>
                    </div>
                    <div class="small text-muted">
                      <i class="bi bi-person-badge me-2"></i>
                      <?= htmlspecialchars(ucfirst($agent['role'])) ?>
                    </div>
                  </div>

                  <!-- Statistiques de l'agent -->
                  <div class="row g-2 mb-3">
                    <div class="col-4">
                      <div class="agent-stat">
                        <span class="agent-stat-value"><?= (int)$agent['total_objectifs'] ?></span>
                        <span class="agent-stat-label">Total</span>
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="agent-stat">
                        <span class="agent-stat-value text-success"><?= (int)$agent['objectifs_termines'] ?></span>
                        <span class="agent-stat-label">Terminés</span>
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="agent-stat">
                        <span class="agent-stat-value text-warning"><?= (int)$agent['objectifs_encours'] ?></span>
                        <span class="agent-stat-label">En cours</span>
                      </div>
                    </div>
                  </div>

                  <!-- Barre de performance -->
                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span class="small text-muted">Performance</span>
                      <span class="small fw-bold" style="color: #3D74B9;"><?= $performance ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                      <div class="progress-bar" 
                           style="width: <?= $performance ?>%; background-color: #3D74B9;" 
                           role="progressbar"></div>
                    </div>
                  </div>

                  <!-- Dernière activité -->
                  <?php if ($agent['derniere_activite']): ?>
                    <div class="small text-muted">
                      <i class="bi bi-clock-history me-1"></i>
                      Dernière activité : <?= date('d/m/Y', strtotime($agent['derniere_activite'])) ?>
                    </div>
                  <?php endif; ?>

                  <!-- Actions -->
                  <div class="d-grid gap-2 mt-3">
                    <a href="supervision.php?agent_id=<?= $agent['id'] ?>" 
                       class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-eye me-2"></i>
                      Voir les objectifs
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Note informative -->
      <div class="alert alert-info mt-4 border-0" style="background-color: rgba(61, 116, 185, 0.1);">
        <div class="d-flex align-items-start">
          <i class="bi bi-info-circle me-3" style="font-size: 1.5rem; color: #3D74B9;"></i>
          <div>
            <h6 class="alert-heading mb-2" style="color: #3D74B9;">
              Information importante
            </h6>
            <p class="mb-0 small">
              Cette page vous permet de consulter la liste des agents sous votre responsabilité. 
              Pour modifier les affectations ou gérer les utilisateurs, veuillez contacter l'administrateur système.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
