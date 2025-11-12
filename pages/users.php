<?php
include('../includes/auth.php');
require_role(['admin']); // Seul l'admin peut accéder
include('../includes/db.php');
include('../includes/header.php');

// Filtres simples
$q = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$allowedRoles = ['admin','coordination','superviseur','staff'];
if ($roleFilter && !in_array($roleFilter, $allowedRoles, true)) { $roleFilter = ''; }

// Construit la requête selon filtres
$where = [];
$params = [];
if ($q !== '') {
  $where[] = "(u.nom LIKE ? OR u.post_nom LIKE ? OR u.email LIKE ?)";
  $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($roleFilter !== '') {
  $where[] = "u.role = ?"; $params[] = $roleFilter;
}
$sql = "SELECT u.*, s.nom AS superviseur_nom, s.post_nom AS superviseur_post
        FROM users u
        LEFT JOIN users s ON u.superviseur_id = s.id";
if ($where) { $sql .= " WHERE " . implode(" AND ", $where); }
$sql .= " ORDER BY u.role, u.nom, u.post_nom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.user-card {
  transition: all 0.3s ease;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  overflow: hidden;
}
.user-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
  border-color: #3D74B9;
}
.user-avatar {
  width: 70px;
  height: 70px;
  object-fit: cover;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.role-badge {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.role-admin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.role-coordination { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
.role-superviseur { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
.role-staff { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
.role-agent { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
.info-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  color: #6c757d;
  font-weight: 600;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}
.info-value {
  font-size: 0.9rem;
  color: #212529;
  font-weight: 500;
}
.missing-badge {
  background: #fff3cd;
  color: #856404;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
}
.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  transition: all 0.2s;
}
.action-btn:hover {
  transform: scale(1.1);
}
.btn-edit {
  background: #e3f2fd;
  color: #1976d2;
}
.btn-edit:hover {
  background: #1976d2;
  color: white;
}
.btn-delete {
  background: #ffebee;
  color: #c62828;
}
.btn-delete:hover {
  background: #c62828;
  color: white;
}
.search-bar {
  border-radius: 10px;
  border: 2px solid #e9ecef;
  padding: 12px 20px;
  transition: all 0.3s;
}
.search-bar:focus {
  border-color: #3D74B9;
  box-shadow: 0 0 0 0.2rem rgba(61, 116, 185, 0.1);
}
.filter-select {
  border-radius: 10px;
  border: 2px solid #e9ecef;
  padding: 12px 16px;
}
.header-card {
  background: linear-gradient(135deg, #3D74B9 0%, #3D74B9 100%);
  border-radius: 16px;
  padding: 24px;
  color: white;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}
</style>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px; border-left: 4px solid #28a745;">
    <i class="bi bi-check-circle-fill me-2"></i> <strong>Succès!</strong> Utilisateur supprimé avec succès.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-md-9" style="padding-left: 8rem;">
    
    <!-- En-tête moderne -->
    <div class="header-card">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-1"><i class="bi bi-people-fill me-2"></i>Gestion des utilisateurs</h4>
          <p class="mb-0 opacity-75" style="font-size: 0.9rem;"><?= count($rows) ?> utilisateur<?= count($rows) > 1 ? 's' : '' ?> trouvé<?= count($rows) > 1 ? 's' : '' ?></p>
        </div>
        <a href="user_add.php" class="btn btn-light px-4" style="border-radius: 10px; font-weight: 600;">
          <i class="bi bi-person-plus-fill me-2"></i>Ajouter un utilisateur
        </a>
      </div>
    </div>

    <!-- Barre de recherche et filtres -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
      <div class="card-body p-4">
        <form class="row g-3" method="get">
          <div class="col-md-5">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0" style="border-radius: 10px 0 0 10px; border: 2px solid #e9ecef; border-right: none;">
                <i class="bi bi-search text-muted"></i>
              </span>
              <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" 
                     class="form-control search-bar border-start-0 ps-0" 
                     placeholder="Rechercher par nom ou email..."
                     style="border-left: none !important;">
            </div>
          </div>
          <div class="col-md-3">
            <select name="role" class="form-select filter-select" onchange="this.form.submit()">
              <option value="">🔍 Tous les rôles</option>
              <?php 
              $roleIcons = ['admin' => '', 'coordination' => '', 'superviseur' => '', 'staff' => ''];
              foreach ($allowedRoles as $r): 
              ?>
                <option value="<?= $r ?>" <?= $roleFilter===$r ? 'selected' : '' ?>>
                  <?= $roleIcons[$r] ?? '•' ?> <?= ucfirst($r) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit" style="border-radius: 10px; padding: 12px; font-weight: 600;">
              <i class="bi bi-funnel-fill me-1"></i> Filtrer
            </button>
          </div>
          <div class="col-md-2">
            <a class="btn btn-outline-secondary w-100" href="users.php" style="border-radius: 10px; padding: 12px; font-weight: 600;" title="Effacer tous les filtres et afficher tous les utilisateurs">
              <i class="bi bi-x-circle me-1"></i> Tout afficher
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Grille de cartes utilisateur -->
    <div class="row g-4">
      <?php 
      foreach ($rows as $row):
        $photo = $row['photo'] ? "../assets/img/profiles/" . $row['photo'] : "../assets/img/profiles/default.png";
        $nomComplet = trim(($row['nom'] ?? '').' '.($row['post_nom'] ?? ''));
        $supName = trim(($row['superviseur_nom'] ?? '').' '.($row['superviseur_post'] ?? ''));
        $missing = [];
        if (empty(trim($row['email'] ?? ''))) $missing[] = 'email';
        if (empty(trim($row['fonction'] ?? ''))) $missing[] = 'fonction';
        if (in_array($row['role'] ?? '', ['staff','agent'], true) && (empty($row['superviseur_id']) || (int)$row['superviseur_id'] === 0)) $missing[] = 'superviseur';
        $roleClass = 'role-' . ($row['role'] ?? 'agent');
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card user-card h-100 shadow-sm">
          <div class="card-body p-4">
            <!-- Photo et badge rôle -->
            <div class="d-flex justify-content-between align-items-start mb-3">
              <img src="<?= htmlspecialchars($photo) ?>" 
                   class="user-avatar rounded-circle" 
                   onerror="this.onerror=null;this.src='../assets/img/profiles/default.png';">
              <span class="role-badge <?= $roleClass ?>">
                <?= htmlspecialchars($row['role'] ?? 'agent') ?>
              </span>
            </div>

            <!-- Nom -->
            <h5 class="mb-1 fw-bold" style="color: #212529;">
              <?= htmlspecialchars($nomComplet ?: 'Sans nom') ?>
            </h5>

            <!-- Email -->
            <p class="text-muted mb-3" style="font-size: 0.85rem;">
              <i class="bi bi-envelope me-1"></i>
              <?= htmlspecialchars($row['email'] ?: 'Aucun email') ?>
            </p>

            <hr class="my-3" style="opacity: 0.1;">

            <!-- Informations détaillées -->
            <div class="mb-3">
              <?php if (!empty(trim($row['fonction'] ?? ''))): ?>
              <div class="mb-2">
                <div class="info-label">Fonction</div>
                <div class="info-value">
                  <i class="bi bi-briefcase me-1" style="color: #3D74B9;"></i>
                  <?= htmlspecialchars($row['fonction']) ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($supName): ?>
              <div class="mb-2">
                <div class="info-label">Superviseur</div>
                <div class="info-value">
                  <i class="bi bi-person-badge me-1" style="color: #3D74B9;"></i>
                  <?= htmlspecialchars($supName) ?>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- Alertes champs manquants -->
            <?php if (!empty($missing)): ?>
            <div class="alert alert-warning py-2 px-3 mb-3" style="border-radius: 8px; font-size: 0.85rem; border-left: 3px solid #ffc107;">
              <i class="bi bi-exclamation-triangle-fill me-1"></i>
              <strong>Incomplet:</strong>
              <?php foreach ($missing as $m): ?>
                <span class="missing-badge ms-1"><?= htmlspecialchars($m) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="d-flex gap-2 justify-content-end mt-3">
              <a href="user_edit.php?id=<?= (int)$row['id'] ?>" 
                 class="action-btn btn-edit" 
                 title="Modifier l'utilisateur">
                <i class="bi bi-pencil-square"></i>
              </a>
              <a href="user_delete.php?id=<?= (int)$row['id'] ?>" 
                 class="action-btn btn-delete" 
                 title="Supprimer l'utilisateur">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($rows)): ?>
      <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
          <div class="card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
            <h5 class="mt-3 text-muted">Aucun utilisateur trouvé</h5>
            <p class="text-muted">Essayez de modifier vos critères de recherche</p>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php include('../includes/footer.php'); ?>
