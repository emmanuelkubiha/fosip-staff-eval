
<?php
// Sécurité et accès
include('../includes/auth.php');
require_role(['admin']);
include('../includes/db.php');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: users.php'); exit; }

// Récupération utilisateur
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: users.php?error=user_not_found'); exit; }
if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) { header('Location: users.php?error=cannot_delete_self'); exit; }

// Compteurs associés
$summary = [
  'fiches' => (int)$pdo->query('SELECT COUNT(*) FROM objectifs WHERE user_id = '.$id)->fetchColumn(),
  'supervisions_faites' => (int)$pdo->query('SELECT COUNT(*) FROM supervisions WHERE superviseur_id = '.$id)->fetchColumn(),
  'supervisions_recues' => (int)$pdo->query('SELECT COUNT(*) FROM supervisions WHERE agent_id = '.$id)->fetchColumn(),
  'coord_comments' => ($user['role']==='coordination') ? (int)$pdo->query('SELECT COUNT(*) FROM coordination_commentaires WHERE coordination_id = '.$id)->fetchColumn() : 0,
  'agents_supervises' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE superviseur_id = '.$id)->fetchColumn()
];

// CSRF
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

// Suppression AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $pwd = $_POST['password'] ?? '';
  $csrf = $_POST['csrf_token'] ?? '';
  if ($csrf !== ($_SESSION['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF invalide']); exit; }
  $adminId = (int)($_SESSION['user_id'] ?? 0);
  $stmt = $pdo->prepare('SELECT mot_de_passe FROM users WHERE id = ?');
  $stmt->execute([$adminId]);
  $hash = $stmt->fetchColumn();
  if (!$hash || !password_verify($pwd, $hash)) { echo json_encode(['ok'=>false,'error'=>'Mot de passe incorrect']); exit; }
  try {
    $pdo->beginTransaction();
    // Suppression en cascade
    $pdo->prepare('DELETE FROM cote_des_objectifs WHERE fiche_id IN (SELECT id FROM objectifs WHERE user_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM auto_evaluation WHERE fiche_id IN (SELECT id FROM objectifs WHERE user_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM coordination_commentaires WHERE fiche_id IN (SELECT id FROM objectifs WHERE user_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM actions_recommandations WHERE fiche_id IN (SELECT id FROM objectifs WHERE user_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM objectifs_items WHERE fiche_id IN (SELECT id FROM objectifs WHERE user_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM objectifs_resumes WHERE fiche_id IN (SELECT id FROM objectifs WHERE user_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM supervisions WHERE superviseur_id = ?')->execute([$id]);
    $pdo->prepare('UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM coordination_commentaires WHERE coord_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM objectifs WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    $pdo->commit();
    echo json_encode(['ok'=>true,'message'=>'Utilisateur supprimé']);
  } catch (Exception $e) {
    $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'Erreur: '.$e->getMessage()]);
  }
  exit;
}

include('../includes/header.php');
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-3"><?php include('../includes/sidebar.php'); ?></div>
    <div class="col-lg-9">
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Suppression d'utilisateur</h5>
          <a href="users.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
        </div>
        <div class="card-body">
          <h6 class="text-uppercase text-muted">Utilisateur ciblé</h6>
          <div class="d-flex align-items-center gap-3 mb-3">
            <?php $photoPath = $user['photo'] ? "../assets/img/profiles/".$user['photo'] : "../assets/img/profiles/default.png"; ?>
            <img src="<?= htmlspecialchars($photoPath) ?>" width="64" height="64" class="rounded-circle border" onerror="this.onerror=null;this.src='../assets/img/profiles/default.png';">
            <div>
              <div class="fw-semibold fs-5 mb-1"><?= htmlspecialchars($user['nom'].' '.$user['post_nom']) ?></div>
              <div class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email'] ?: '—') ?></div>
              <span class="badge bg-primary"><?= htmlspecialchars($user['role']) ?></span>
              <?php if ($user['fonction']): ?><span class="badge bg-secondary ms-1"><?= htmlspecialchars($user['fonction']) ?></span><?php endif; ?>
            </div>
          </div>
          <hr>
          <h6 class="text-uppercase text-muted">Données associées</h6>
          <ul class="list-unstyled mb-3 small">
            <li>• Fiches d'objectifs: <strong><?= $summary['fiches'] ?></strong></li>
            <li>• Supervisions faites: <strong><?= $summary['supervisions_faites'] ?></strong></li>
            <li>• Supervisions reçues: <strong><?= $summary['supervisions_recues'] ?></strong></li>
            <li>• Agents supervisés: <strong><?= $summary['agents_supervises'] ?></strong></li>
            <?php if ($summary['coord_comments']>0): ?><li>• Commentaires coordination: <strong><?= $summary['coord_comments'] ?></strong></li><?php endif; ?>
          </ul>
          <div class="alert alert-danger"><i class="bi bi-shield-lock me-1"></i> Cette action est définitive et supprimera toutes les données listées ci-dessus.</div>
          <button id="btnConfirmDelete" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Confirmer la suppression</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal mot de passe -->
<div class="modal fade" id="pwdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Confirmation finale</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Confirmez la suppression définitive de cet utilisateur et de toutes ses données associées.</div>
        <p class="mb-2">Entrez votre mot de passe administrateur pour confirmer :</p>
        <input type="password" class="form-control" id="adminPwd" placeholder="Mot de passe" autocomplete="current-password">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-danger" id="btnDoDelete"><i class="bi bi-check2-circle me-1"></i> Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const csrf = '<?= htmlspecialchars($csrf_token) ?>';
  const btnConfirm = document.getElementById('btnConfirmDelete');
  const modalEl = document.getElementById('pwdModal');
  const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const pwdInput = document.getElementById('adminPwd');
  const btnDo = document.getElementById('btnDoDelete');
  const userId = <?= (int)$id ?>;
  function toast(t,m){ if(window.globalShowToast) globalShowToast(t,m); else alert(m); }
  btnConfirm && btnConfirm.addEventListener('click',()=>{
    pwdInput.value='';
    bsModal.show();
    setTimeout(()=>pwdInput.focus(),300);
  });
  btnDo && btnDo.addEventListener('click',()=>{
    const pwd = pwdInput.value.trim();
    if(!pwd){ window.globalShowToast('warning','Mot de passe requis'); pwdInput.focus(); return; }
    btnDo.disabled = true; btnDo.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Suppression...';
    const fd = new FormData(); fd.append('csrf_token',csrf); fd.append('password',pwd);
    fetch('user_delete.php?id='+userId,{method:'POST',body:fd})
      .then(r => r.json().catch(() => ({ok:false,error:'Réponse serveur non valide'})))
      .then(j => {
        if(!j.ok){
          window.globalShowToast('danger',j.error||'Erreur lors de la suppression');
          btnDo.disabled=false;
          btnDo.innerHTML='<i class="bi bi-check2-circle me-1"></i> Supprimer';
          return;
        }
        window.globalShowToast('success','Utilisateur supprimé');
        setTimeout(()=>{ window.location.href='users.php?deleted=1'; },800);
      })
      .catch((err)=>{
        window.globalShowToast('danger','Erreur réseau ou serveur: '+(err?.message||err));
        btnDo.disabled=false;
        btnDo.innerHTML='<i class="bi bi-check2-circle me-1"></i> Supprimer';
      });
  });
});
</script>
<?php include('../includes/footer.php'); ?>
