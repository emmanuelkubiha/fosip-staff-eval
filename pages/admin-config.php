<?php
/**
 * pages/admin-config.php
 * Outils de configuration et réinitialisation globale (admin seulement).
 * Opérations disponibles:
 *  - Reset commentaires coordination
 *  - Reset supervisions (table supervisions)
 *  - Reset fiches objectifs (objectifs + dépendances)
 *  - Reset utilisateurs (supprime tous sauf recrée admin par défaut)
 *  - Reset total (tout ce qui précède)
 * Chaque action exige: CSRF + mot de passe admin vérifié.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
include('../includes/auth.php');
require_once('../includes/version.php');
require_role(['admin']);

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

$feedback = ['ok'=>null,'msg'=>''];

// $adminId = (int)($_SESSION['user_id'] ?? 0);

// ACTIONS POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf_token'] ?? '';
  $pwd = $_POST['dev_password'] ?? '';

  if ($token !== $csrf) {
    $feedback = ['ok'=>false,'msg'=>'CSRF invalide'];
  } else {
    // Vérifier le mot de passe de l'admin connecté
    $stmtAdmin = $pdo->prepare("SELECT mot_de_passe FROM users WHERE id = ? AND role = 'admin'");
    $stmtAdmin->execute([$adminId]);
    $adminData = $stmtAdmin->fetch();
    
    if (!$adminData || !password_verify($pwd, $adminData['mot_de_passe'])) {
      $feedback = ['ok'=>false,'msg'=>'Mot de passe administrateur incorrect'];
    } else {
      try {
        $pdo->beginTransaction();
        $logs = [];

        if ($action === 'reset_coordination' || $action === 'reset_all') {
          $count = (int)$pdo->query('SELECT COUNT(*) FROM coordination_commentaires')->fetchColumn();
          $pdo->exec('DELETE FROM coordination_commentaires');
          $logs[] = "Commentaires coordination supprimés ($count)";
        }

        if ($action === 'reset_supervisions' || $action === 'reset_all') {
          $count = (int)$pdo->query('SELECT COUNT(*) FROM supervisions')->fetchColumn();
          $pdo->exec('DELETE FROM supervisions');
          $logs[] = "Supervisions supprimées ($count)";
        }

        if ($action === 'reset_fiches' || $action === 'reset_all') {
          $cObj = (int)$pdo->query('SELECT COUNT(*) FROM objectifs')->fetchColumn();
          $pdo->exec('DELETE FROM cote_des_objectifs');
          $pdo->exec('DELETE FROM auto_evaluation');
          $pdo->exec('DELETE FROM actions_recommandations');
          $pdo->exec('DELETE FROM objectifs_items');
          $pdo->exec('DELETE FROM objectifs_resumes');
          $pdo->exec('DELETE FROM coordination_commentaires');
          $pdo->exec('DELETE FROM objectifs');
          $logs[] = "Fiches et dépendances supprimées ($cObj)";
        }

        if ($action === 'reset_users' || $action === 'reset_all') {
          $countU = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
          $pdo->exec('DELETE FROM users');
          $defaultPass = password_hash('admin', PASSWORD_DEFAULT);
          $stI = $pdo->prepare('INSERT INTO users (nom, post_nom, email, role, mot_de_passe, fonction, photo) VALUES (?,?,?,?,?,?,?)');
          $stI->execute(['Admin','System','admin@admin','admin',$defaultPass,'Administrateur','default.png']);
          $logs[] = "Utilisateurs réinitialisés ($countU supprimés, admin recréé: email=admin@admin / mdp=admin)";
        }

        $pdo->commit();
        // Forcer logout si utilisateurs ou tout
        if ($action === 'reset_users' || $action === 'reset_all') {
          session_destroy();
          header('Location: logout.php');
          exit;
        }
        $feedback = ['ok'=>true,'msg'=>'Action exécutée','details'=>$logs];
      } catch (Exception $e) {
        $pdo->rollBack();
        $feedback = ['ok'=>false,'msg'=>'Erreur: '.$e->getMessage()];
      }
    }
  }
}

include('../includes/header.php');
?>
<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-md-9" style="padding-left: 8rem;">
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
        <div class="d-flex align-items-center gap-3 mb-2 mb-md-0">
          <img src="../assets/img/logocircular.png" alt="Logo FOSIP" style="height:45px;">
          <div>
            <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Configurations système</h5>
            <div class="small text-muted">Version <?= FOSIP_VERSION ?> — <?= FOSIP_RELEASE_DATE ?></div>
          </div>
        </div>
        <div class="small text-muted">Réservés à l'administrateur</div>
      </div>
      <div class="card-body">
        <?php if ($feedback['ok'] !== null): ?>
          <div class="alert alert-<?= $feedback['ok'] ? 'success' : 'danger' ?>">
            <i class="bi <?= $feedback['ok'] ? 'bi-check2-circle' : 'bi-x-circle' ?> me-1"></i>
            <?= htmlspecialchars($feedback['msg']) ?>
            <?php if (!empty($feedback['details'])): ?>
              <ul class="small mt-2 mb-0">
                <?php foreach ($feedback['details'] as $d): ?><li><?= htmlspecialchars($d) ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="alert alert-warning mb-4">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <strong>Attention :</strong> Ces opérations sont <u>irréversibles</u>. Assurez-vous d'avoir effectué les sauvegardes nécessaires avant de continuer.
        </div>

        <div class="row g-4">
          <div class="col-md-6">
            <div class="p-3 border rounded">
              <h6 class="fw-semibold"><i class="bi bi-chat-left-text me-1"></i> Reset commentaires coordination</h6>
              <p class="small text-muted mb-2">Supprime tous les commentaires finaux associés aux fiches.</p>
              <button type="button" class="btn btn-sm btn-danger" data-action="reset_coordination">Supprimer tous les commentaires</button>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border rounded">
              <h6 class="fw-semibold"><i class="bi bi-check2-circle me-1"></i> Reset supervisions</h6>
              <p class="small text-muted mb-2">Efface toutes les validations / évaluations des superviseurs.</p>
              <button type="button" class="btn btn-sm btn-danger" data-action="reset_supervisions">Supprimer toutes les supervisions</button>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border rounded">
              <h6 class="fw-semibold"><i class="bi bi-clipboard2-data me-1"></i> Reset fiches objectifs</h6>
              <p class="small text-muted mb-2">Supprime toutes les fiches avec leurs objectifs et dépendances.</p>
              <button type="button" class="btn btn-sm btn-danger" data-action="reset_fiches">Supprimer toutes les fiches</button>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border rounded">
              <h6 class="fw-semibold"><i class="bi bi-people-fill me-1"></i> Reset utilisateurs</h6>
              <p class="small text-muted mb-2">Supprime tous les utilisateurs et recrée un administrateur par défaut (admin@admin / mdp=admin).</p>
              <button type="button" class="btn btn-sm btn-danger" data-action="reset_users">Supprimer tous les utilisateurs</button>
            </div>
          </div>
          <div class="col-md-12">
            <div class="p-3 border rounded bg-light">
              <h6 class="fw-semibold text-danger"><i class="bi bi-fire me-1"></i> Reset total</h6>
              <p class="small text-muted mb-2">Exécute toutes les opérations ci-dessus. Ramène le système à son état initial avec seulement l'admin par défaut.</p>
              <button type="button" class="btn btn-sm btn-outline-danger" data-action="reset_all">Exécuter un reset complet</button>
            </div>
          </div>
        </div>

        <hr class="my-4">
        <div class="small text-muted">Chaque opération demandera le mot de passe développeur pour confirmation.</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal mot de passe (soumission standard POST) -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Confirmation requise</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="modalActionLabel" class="fw-semibold mb-2"></p>
        <p class="small text-muted">Cette opération est irréversible. Entrez le mot de passe développeur pour continuer :</p>
        <form id="confirmForm" method="post" action="admin-config.php" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" id="modalActionInput" value="">
          <input type="password" name="dev_password" id="devPassword" class="form-control" placeholder="Mot de passe développeur" required>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" form="confirmForm" class="btn btn-danger" id="btnDoAction"><i class="bi bi-check2-circle me-1"></i> Confirmer</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const modalEl = document.getElementById('confirmModal');
  const bsModal = new bootstrap.Modal(modalEl);
  const actionInput = document.getElementById('modalActionInput');
  const actionLabel = document.getElementById('modalActionLabel');
  const pwdInput = document.getElementById('devPassword');
  
  const mapLabels = {
    reset_coordination: 'Suppression de TOUS les commentaires de coordination',
    reset_supervisions: 'Suppression de TOUTES les supervisions',
    reset_fiches: 'Suppression de TOUTES les fiches d\'objectifs et dépendances',
    reset_users: 'Suppression de TOUS les utilisateurs (admin par défaut recréé)',
    reset_all: 'RÉINITIALISATION COMPLÈTE DU SYSTÈME'
  };
  
  document.querySelectorAll('button[data-action]').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const action = this.getAttribute('data-action');
      actionInput.value = action;
      const text = mapLabels[action] || 'Action';
      actionLabel.textContent = text;
      pwdInput.value = '';
      bsModal.show();
      setTimeout(() => pwdInput.focus(), 300);
    });
  });
});
</script>
<?php include('../includes/footer.php'); ?>
