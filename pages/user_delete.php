<?php<?php<?php<?php

/**

 * pages/user_delete.phpinclude('../includes/auth.php');

 * Page de suppression d'utilisateur avec analyse des dépendances

 */require_role(['admin']);include('../includes/auth.php');include('../includes/auth.php');



include('../includes/auth.php');include('../includes/db.php');

require_role(['admin']);

include('../includes/db.php');require_role(['admin']);require_role(['admin']);



$id = (int)($_GET['id'] ?? 0);$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {

  header('Location: users.php');if ($id <= 0) {include('../includes/db.php');include('../includes/db.php');

  <?php
  /**
   * pages/user_delete.php (version propre)
   * Suppression sécurisée d'un utilisateur avec analyse et cascade.
   */

  include('../includes/auth.php');
  require_role(['admin']);
  include('../includes/db.php');

  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) { header('Location: users.php'); exit; }

  // Charger utilisateur
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $stmt->execute([$id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) { header('Location: users.php?error=user_not_found'); exit; }
  if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) { header('Location: users.php?error=cannot_delete_self'); exit; }

  // Résumé des données (compteurs)
  $summary = [
    'fiches' => 0,
    'supervisions_faites' => 0,
    'supervisions_recues' => 0,
    'coord_comments' => 0,
    'agents_supervises' => 0
  ];

  $stmt = $pdo->prepare('SELECT COUNT(*) FROM objectifs WHERE user_id = ?');
  $stmt->execute([$id]); $summary['fiches'] = (int)$stmt->fetchColumn();
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM supervisions WHERE superviseur_id = ?');
  $stmt->execute([$id]); $summary['supervisions_faites'] = (int)$stmt->fetchColumn();
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM supervisions WHERE agent_id = ?');
  $stmt->execute([$id]); $summary['supervisions_recues'] = (int)$stmt->fetchColumn();
  if ($user['role'] === 'coordination') { $stmt = $pdo->prepare('SELECT COUNT(*) FROM coordination_commentaires WHERE coordination_id = ?'); $stmt->execute([$id]); $summary['coord_comments'] = (int)$stmt->fetchColumn(); }
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE superviseur_id = ?');
  $stmt->execute([$id]); $summary['agents_supervises'] = (int)$stmt->fetchColumn();

  // POST suppression
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $pwd = $_POST['password'] ?? ''; $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF invalide']); exit; }
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT mot_de_passe FROM users WHERE id = ?'); // champ réel selon login.php
    $stmt->execute([$adminId]); $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($pwd, $hash)) { echo json_encode(['ok'=>false,'error'=>'Mot de passe incorrect']); exit; }
    try {
      $pdo->beginTransaction();
      if ($summary['fiches'] > 0) {
        $sf = $pdo->prepare('SELECT id, user_id, periode FROM objectifs WHERE user_id = ?'); $sf->execute([$id]);
        foreach ($sf->fetchAll(PDO::FETCH_ASSOC) as $fiche) {
          $fid = (int)$fiche['id']; $periode = $fiche['periode']; $agentId = (int)$fiche['user_id'];
          $pdo->prepare('DELETE FROM cote_des_objectifs WHERE objectif_id = ?')->execute([$fid]);
          $pdo->prepare('DELETE FROM auto_evaluation WHERE objectif_id = ?')->execute([$fid]);
          $pdo->prepare('DELETE FROM coordination_commentaires WHERE objectif_id = ?')->execute([$fid]);
          $pdo->prepare('DELETE FROM actions_recommandations WHERE objectif_id = ?')->execute([$fid]);
          $pdo->prepare('DELETE FROM objectifs_items WHERE objectif_id = ?')->execute([$fid]);
          $pdo->prepare('DELETE FROM objectifs_resumes WHERE objectif_id = ?')->execute([$fid]);
          $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ? AND periode = ?')->execute([$agentId, $periode]);
          $pdo->prepare('DELETE FROM objectifs WHERE id = ?')->execute([$fid]);
        }
      }
      if ($summary['supervisions_faites'] > 0) { $pdo->prepare('DELETE FROM supervisions WHERE superviseur_id = ?')->execute([$id]); }
      if ($summary['agents_supervises'] > 0) { $pdo->prepare('UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?')->execute([$id]); }
      if ($summary['coord_comments'] > 0) { $pdo->prepare('DELETE FROM coordination_commentaires WHERE coordination_id = ?')->execute([$id]); }
      if ($summary['supervisions_recues'] > 0) { $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ?')->execute([$id]); }
      $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
      $pdo->commit();
      echo json_encode(['ok'=>true,'message'=>'Utilisateur supprimé']);
    } catch (Exception $e) {
      $pdo->rollRollBack(); echo json_encode(['ok'=>false,'error'=>'Erreur: '.$e->getMessage()]);
    }
    exit;
  }
  if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
  $csrf_token = $_SESSION['csrf_token'];
  include('../includes/header.php');
  ?>
  <div class="row">
    <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
    <div class="col-md-9">
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

  <!-- Modal mot de passe -->
  <div class="modal fade" id="pwdModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Confirmation finale</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
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
  (function(){
    const csrf='<?= htmlspecialchars($csrf_token) ?>';
    const btnConfirm=document.getElementById('btnConfirmDelete');
    const modalEl=document.getElementById('pwdModal');
    const bsModal = modalEl? new bootstrap.Modal(modalEl):null;
    const pwdInput=document.getElementById('adminPwd');
    const btnDo=document.getElementById('btnDoDelete');
    const userId=<?= (int)$id ?>;
    function toast(t,m){ if(window.globalShowToast) globalShowToast(t,m); else alert(m); }
    btnConfirm && btnConfirm.addEventListener('click',()=>{ if(confirm('Supprimer définitivement cet utilisateur et ses données ?')){ pwdInput.value=''; bsModal.show(); setTimeout(()=>pwdInput.focus(),300);} });
    btnDo && btnDo.addEventListener('click',()=>{
      const pwd=pwdInput.value.trim();
      if(!pwd){ toast('warning','Mot de passe requis'); pwdInput.focus(); return; }
      btnDo.disabled=true; btnDo.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Suppression...';
      const fd=new FormData(); fd.append('csrf_token',csrf); fd.append('password',pwd);
      fetch('user_delete.php?id='+userId,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(!j.ok){ toast('danger',j.error||'Erreur'); btnDo.disabled=false; btnDo.innerHTML='<i class="bi bi-check2-circle me-1"></i> Supprimer'; return; }
        toast('success','Utilisateur supprimé'); setTimeout(()=>{ window.location.href='users.php?deleted=1'; },800);
      }).catch(()=>{ toast('danger','Erreur réseau'); btnDo.disabled=false; btnDo.innerHTML='<i class="bi bi-check2-circle me-1"></i> Supprimer'; });
    });
  })();
  </script>
  <?php include('../includes/footer.php'); ?>

  // Suppression en cascade  header('Location: users.php?error=cannot_delete_self');  header('Location: users.php?error=cannot_delete_self');

  try {

    $pdo->beginTransaction();// Compte les supervisions faites PAR cet utilisateur (en tant que superviseur)

    

    // 1. Supprimer les fiches et leurs dépendances$stmt = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE superviseur_id = ?");  exit;  exit;

    if ($summary['fiches'] > 0) {

      $stmtFiches = $pdo->prepare('SELECT id, user_id, periode FROM objectifs WHERE user_id = ?');$stmt->execute([$id]);

      $stmtFiches->execute([$id]);

      $deletionSummary['supervisions_superviseur'] = (int)$stmt->fetchColumn();}}

      foreach ($stmtFiches->fetchAll(PDO::FETCH_ASSOC) as $fiche) {

        $ficheId = (int)$fiche['id'];

        $agentId = (int)$fiche['user_id'];

        $periode = $fiche['periode'];// Compte les fiches supervisées par cet utilisateur (fiches dont il est superviseur)

        

        // Supprimer toutes les données liées à cette fiche$stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs o JOIN users u ON o.user_id = u.id WHERE u.superviseur_id = ?");

        $pdo->prepare('DELETE FROM cote_des_objectifs WHERE objectif_id = ?')->execute([$ficheId]);

        $pdo->prepare('DELETE FROM auto_evaluation WHERE objectif_id = ?')->execute([$ficheId]);$stmt->execute([$id]);// ========== ANALYSE DES DONNÉES À SUPPRIMER ==========// ========== ANALYSE DES DONNÉES À SUPPRIMER ==========

        $pdo->prepare('DELETE FROM coordination_commentaires WHERE objectif_id = ?')->execute([$ficheId]);

        $pdo->prepare('DELETE FROM actions_recommandations WHERE objectif_id = ?')->execute([$ficheId]);$deletionSummary['fiches_superviseur'] = (int)$stmt->fetchColumn();

        $pdo->prepare('DELETE FROM objectifs_items WHERE objectif_id = ?')->execute([$ficheId]);

        $pdo->prepare('DELETE FROM objectifs_resumes WHERE objectif_id = ?')->execute([$ficheId]);<?php

        $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ? AND periode = ?')->execute([$agentId, $periode]);// Réparation : fichier nettoyé. Version minimale fonctionnelle.

        $pdo->prepare('DELETE FROM objectifs WHERE id = ?')->execute([$ficheId]);include('../includes/auth.php');

      }require_role(['admin']);

    }include('../includes/db.php');

    

    // 2. Supprimer les supervisions faites$id = (int)($_GET['id'] ?? 0);

    if ($summary['supervisions_faites'] > 0) {if ($id <= 0) { header('Location: users.php'); exit; }

      $pdo->prepare('DELETE FROM supervisions WHERE superviseur_id = ?')->execute([$id]);$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');

    }$stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Détacher les agents supervisésif (!$user) { header('Location: users.php?error=user_not_found'); exit; }

    if ($summary['agents_supervises'] > 0) {if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) { header('Location: users.php?error=cannot_delete_self'); exit; }

      $pdo->prepare('UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?')->execute([$id]);

    }// Comptages simples

    $summary = [ 'fiches' => 0, 'supervisions_faites' => 0, 'supervisions_recues' => 0, 'coord_comments' => 0, 'agents_supervises' => 0 ];

    // 4. Supprimer commentaires coordination restants$stmt = $pdo->prepare('SELECT COUNT(*) FROM objectifs WHERE user_id = ?'); $stmt->execute([$id]); $summary['fiches'] = (int)$stmt->fetchColumn();

    if ($summary['coord_comments'] > 0) {$stmt = $pdo->prepare('SELECT COUNT(*) FROM supervisions WHERE superviseur_id = ?'); $stmt->execute([$id]); $summary['supervisions_faites'] = (int)$stmt->fetchColumn();

      $pdo->prepare('DELETE FROM coordination_commentaires WHERE coordination_id = ?')->execute([$id]);$stmt = $pdo->prepare('SELECT COUNT(*) FROM supervisions WHERE agent_id = ?'); $stmt->execute([$id]); $summary['supervisions_recues'] = (int)$stmt->fetchColumn();

    }if ($user['role'] === 'coordination') { $stmt = $pdo->prepare('SELECT COUNT(*) FROM coordination_commentaires WHERE coordination_id = ?'); $stmt->execute([$id]); $summary['coord_comments'] = (int)$stmt->fetchColumn(); }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE superviseur_id = ?'); $stmt->execute([$id]); $summary['agents_supervises'] = (int)$stmt->fetchColumn();

    // 5. Supprimer supervisions reçues (sécurité)

    if ($summary['supervisions_recues'] > 0) {// Suppression

      $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ?')->execute([$id]);if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    }  header('Content-Type: application/json');

      $pwd = $_POST['password'] ?? ''; $csrf = $_POST['csrf_token'] ?? '';

    // 6. Supprimer l'utilisateur  if ($csrf !== ($_SESSION['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF invalide']); exit; }

    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);  $adminId = (int)($_SESSION['user_id'] ?? 0);

      $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?'); $stmt->execute([$adminId]); $hash = $stmt->fetchColumn();

    $pdo->commit();  if (!$hash || !password_verify($pwd, $hash)) { echo json_encode(['ok'=>false,'error'=>'Mot de passe incorrect']); exit; }

      try {

    echo json_encode(['ok' => true, 'message' => 'Utilisateur et toutes ses données supprimés avec succès']);    $pdo->beginTransaction();

        // Fiches

  } catch (Exception $e) {    if ($summary['fiches'] > 0) {

    $pdo->rollBack();      $sf = $pdo->prepare('SELECT id, user_id, periode FROM objectifs WHERE user_id = ?'); $sf->execute([$id]);

    echo json_encode(['ok' => false, 'error' => 'Erreur lors de la suppression : ' . $e->getMessage()]);      foreach ($sf->fetchAll(PDO::FETCH_ASSOC) as $fiche) {

  }        $fid = (int)$fiche['id']; $periode = $fiche['periode']; $agentId = (int)$fiche['user_id'];

          $pdo->prepare('DELETE FROM cote_des_objectifs WHERE objectif_id = ?')->execute([$fid]);

  exit;        $pdo->prepare('DELETE FROM auto_evaluation WHERE objectif_id = ?')->execute([$fid]);

}        $pdo->prepare('DELETE FROM coordination_commentaires WHERE objectif_id = ?')->execute([$fid]);

        $pdo->prepare('DELETE FROM actions_recommandations WHERE objectif_id = ?')->execute([$fid]);

// Générer token CSRF        $pdo->prepare('DELETE FROM objectifs_items WHERE objectif_id = ?')->execute([$fid]);

if (!isset($_SESSION['csrf_token'])) {        $pdo->prepare('DELETE FROM objectifs_resumes WHERE objectif_id = ?')->execute([$fid]);

  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));        $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ? AND periode = ?')->execute([$agentId, $periode]);

}        $pdo->prepare('DELETE FROM objectifs WHERE id = ?')->execute([$fid]);

$csrf_token = $_SESSION['csrf_token'];      }

    }

include('../includes/header.php');    // Supervisions faites

?>    if ($summary['supervisions_faites'] > 0) { $pdo->prepare('DELETE FROM supervisions WHERE superviseur_id = ?')->execute([$id]); }

    // Agents supervisés -> détacher

<style>    if ($summary['agents_supervises'] > 0) { $pdo->prepare('UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?')->execute([$id]); }

.danger-card {    // Commentaires coordination

  border: 2px solid #dc3545;    if ($summary['coord_comments'] > 0) { $pdo->prepare('DELETE FROM coordination_commentaires WHERE coordination_id = ?')->execute([$id]); }

  border-radius: 12px;    // Supervisions reçues (sécurité si non supprimées via cascade précédente)

  overflow: hidden;    if ($summary['supervisions_recues'] > 0) { $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ?')->execute([$id]); }

}    // Utilisateur

.danger-header {    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

  background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);    $pdo->commit();

  color: white;    echo json_encode(['ok'=>true,'message'=>'Utilisateur supprimé']);

  padding: 20px;  } catch (Exception $e) {

}    $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'Erreur: '.$e->getMessage()]);

.stat-box {  }

  background: #fff3cd;  exit;

  border-left: 4px solid #ffc107;}

  padding: 12px 16px;if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

  border-radius: 8px;$csrf_token = $_SESSION['csrf_token'];

  margin-bottom: 10px;include('../includes/header.php');

}?>

.stat-box strong {

  color: #856404;    $stmt = $pdo->prepare('SELECT COUNT(*) FROM supervisions WHERE superviseur_id = ?');

}    $stmt->execute([$id]);

.user-info-box {    $summary['supervisions_superviseur'] = (int)$stmt->fetchColumn();

  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);

  border-radius: 10px;    $stmt = $pdo->prepare('SELECT COUNT(*) FROM objectifs o JOIN users u ON o.user_id = u.id WHERE u.superviseur_id = ?');

  padding: 20px;    $stmt->execute([$id]);

}    $summary['fiches_superviseur'] = (int)$stmt->fetchColumn();

.avatar-lg {

  width: 80px;    if ($user['role'] === 'coordination') {

  height: 80px;      $stmt = $pdo->prepare('SELECT COUNT(*) FROM coordination_commentaires WHERE coordination_id = ?');

  border: 4px solid white;      $stmt->execute([$id]);

  box-shadow: 0 4px 12px rgba(0,0,0,0.15);      $summary['coord_comments'] = (int)$stmt->fetchColumn();

}    }

</style>

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE superviseur_id = ?');

<div class="row">    $stmt->execute([$id]);

  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>    $summary['supervised_users'] = (int)$stmt->fetchColumn();

  <div class="col-md-9">

        // POST suppression

    <div class="card danger-card shadow-lg">    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

      <div class="danger-header">      header('Content-Type: application/json');

        <h4 class="mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Suppression d'utilisateur</h4>      $password = $_POST['password'] ?? ''; $csrf = $_POST['csrf_token'] ?? '';

        <p class="mb-0 opacity-75">Action irréversible - Toutes les données seront supprimées</p>      if ($csrf !== ($_SESSION['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'Token CSRF invalide']); exit; }

      </div>      $adminId = (int)($_SESSION['user_id'] ?? 0);

            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?'); $stmt->execute([$adminId]); $hash = $stmt->fetchColumn();

      <div class="card-body p-4">      if (!$hash || !password_verify($password, $hash)) { echo json_encode(['ok'=>false,'error'=>'Mot de passe incorrect']); exit; }

              try {

        <!-- Informations utilisateur -->        $pdo->beginTransaction();

        <div class="user-info-box mb-4">        // Fiches de l'agent

          <h5 class="mb-3"><i class="bi bi-person-x me-2 text-danger"></i>Utilisateur ciblé</h5>        if ($summary['fiches_agent'] > 0) {

          <div class="d-flex align-items-center gap-3">          $stmtF = $pdo->prepare('SELECT id, user_id, periode FROM objectifs WHERE user_id = ?');

            <?php           $stmtF->execute([$id]);

            $photoPath = $user['photo'] ? "../assets/img/profiles/" . $user['photo'] : "../assets/img/profiles/default.png";          foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $fiche) {

            ?>            $fid = (int)$fiche['id']; $agentId = (int)$fiche['user_id']; $periode = $fiche['periode'];

            <img src="<?= htmlspecialchars($photoPath) ?>"             $pdo->prepare('DELETE FROM cote_des_objectifs WHERE objectif_id = ?')->execute([$fid]);

                 class="avatar-lg rounded-circle"             $pdo->prepare('DELETE FROM auto_evaluation WHERE objectif_id = ?')->execute([$fid]);

                 onerror="this.onerror=null;this.src='../assets/img/profiles/default.png';">            $pdo->prepare('DELETE FROM coordination_commentaires WHERE objectif_id = ?')->execute([$fid]);

            <div>            $pdo->prepare('DELETE FROM actions_recommandations WHERE objectif_id = ?')->execute([$fid]);

              <h4 class="mb-1"><?= htmlspecialchars($user['nom'] . ' ' . $user['post_nom']) ?></h4>            $pdo->prepare('DELETE FROM objectifs_items WHERE objectif_id = ?')->execute([$fid]);

              <p class="mb-1"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($user['email'] ?: 'Aucun email') ?></p>            $pdo->prepare('DELETE FROM objectifs_resumes WHERE objectif_id = ?')->execute([$fid]);

              <span class="badge bg-primary"><?= htmlspecialchars($user['role']) ?></span>            $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ? AND periode = ?')->execute([$agentId, $periode]);

              <?php if (!empty($user['fonction'])): ?>            $pdo->prepare('DELETE FROM objectifs WHERE id = ?')->execute([$fid]);

                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($user['fonction']) ?></span>          }

              <?php endif; ?>        }

            </div>        // Supervisions faites

          </div>        if ($summary['supervisions_superviseur'] > 0) {

        </div>          $pdo->prepare('DELETE FROM supervisions WHERE superviseur_id = ?')->execute([$id]);

        }

        <!-- Analyse des données à supprimer -->        // Lien superviseur

        <div class="alert alert-warning border-0" style="border-left: 4px solid #ffc107 !important; border-radius: 10px;">        if ($summary['supervised_users'] > 0) {

          <h5 class="alert-heading"><i class="bi bi-database-fill-x me-2"></i>Données qui seront supprimées</h5>          $pdo->prepare('UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?')->execute([$id]);

          <hr>        }

          <div class="row g-3">        // Commentaires coordination

            <div class="col-md-6">        if ($summary['coord_comments'] > 0) {

              <div class="stat-box">          $pdo->prepare('DELETE FROM coordination_commentaires WHERE coordination_id = ?')->execute([$id]);

                <i class="bi bi-clipboard2-data me-2"></i>        }

                <strong><?= $summary['fiches'] ?></strong> fiche(s) d'objectifs        // Supprimer utilisateur

                <small class="d-block text-muted mt-1">+ objectifs, items, auto-évaluations, résumés</small>        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

              </div>        $pdo->commit();

            </div>        echo json_encode(['ok'=>true,'message'=>'Utilisateur supprimé']);

                  } catch (Exception $e) {

            <div class="col-md-6">        $pdo->rollBack();

              <div class="stat-box">        echo json_encode(['ok'=>false,'error'=>'Erreur: '.$e->getMessage()]);

                <i class="bi bi-check-circle me-2"></i>      }

                <strong><?= $summary['supervisions_faites'] ?></strong> supervision(s) effectuée(s)      exit;

                <small class="d-block text-muted mt-1">Validations réalisées en tant que superviseur</small>    }

              </div>    if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

            </div>    $csrf_token = $_SESSION['csrf_token'];

                include('../includes/header.php');

            <div class="col-md-6">    ?>

              <div class="stat-box">  <div class="col-md-9">

                <i class="bi bi-person-check me-2"></i>

                <strong><?= $summary['supervisions_recues'] ?></strong> supervision(s) reçue(s)    <div class="card shadow-sm border-0">  }  $stmt->execute([$adminId]);

                <small class="d-block text-muted mt-1">Validations reçues en tant qu'agent</small>

              </div>      <div class="card-header bg-danger text-white">

            </div>

                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Suppression d'utilisateur</h5>    $hash = $stmt->fetchColumn();

            <?php if ($summary['coord_comments'] > 0): ?>

            <div class="col-md-6">      </div>

              <div class="stat-box">

                <i class="bi bi-chat-left-text me-2"></i>      <div class="card-body">  // Vérification mot de passe admin  

                <strong><?= $summary['coord_comments'] ?></strong> commentaire(s) de coordination

                <small class="d-block text-muted mt-1">Commentaires finaux sur les fiches</small>        <!-- Info utilisateur -->

              </div>

            </div>        <div class="alert alert-danger border-danger mb-4">  $adminId = (int)$_SESSION['user_id'];  if (!password_verify($password, $hash)) {

            <?php endif; ?>

                      <h5 class="alert-heading"><i class="bi bi-person-x me-2"></i> Utilisateur ciblé</h5>

            <?php if ($summary['agents_supervises'] > 0): ?>

            <div class="col-md-6">          <hr>  $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");    echo json_encode(['ok' => false, 'error' => 'Mot de passe incorrect']);

              <div class="stat-box">

                <i class="bi bi-people me-2"></i>          <script>

                <strong><?= $summary['agents_supervises'] ?></strong> agent(s) supervisé(s)          (function(){

                <small class="d-block text-muted mt-1">Les agents seront détachés (superviseur = NULL)</small>            const csrf = '<?= htmlspecialchars($csrf_token) ?>';

              </div>            const btnConfirm = document.getElementById('btnConfirmDelete');

            </div>            const modalEl = document.getElementById('pwdModal');

            <?php endif; ?>            const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

          </div>            const pwdInput = document.getElementById('adminPwd');

                      const btnDo = document.getElementById('btnDoDelete');

          <div class="mt-3 p-3" style="background: rgba(220,53,69,0.1); border-radius: 8px;">            const userId = <?= (int)$id ?>;

            <p class="mb-0 text-danger fw-bold">            function toast(t,m){ if(window.globalShowToast) globalShowToast(t,m); else alert(m); }

              <i class="bi bi-exclamation-circle-fill me-2"></i>            btnConfirm && btnConfirm.addEventListener('click', ()=> {

              Cette action est <u>définitive et irréversible</u>. Toutes les données listées ci-dessus seront <strong>supprimées de façon permanente</strong>.              let lignes = [

            </p>                '⚠️ SUPPRESSION DÉFINITIVE',

          </div>                '- Fiches + objectifs + items + auto-évaluations + résumés'

        </div>              ];

              if (<?= $summary['supervisions_superviseur'] ?> > 0 || <?= $summary['supervisions_agent'] ?> > 0) lignes.push('- Supervisions (validations)');

        <!-- Actions -->              if (<?= $summary['coord_comments'] ?> > 0) lignes.push('- Commentaires coordination');

        <div class="d-flex gap-3 justify-content-end mt-4">              if (<?= $summary['supervised_users'] ?> > 0) lignes.push('- Liens de supervision (détachés)');

          <a href="users.php" class="btn btn-secondary px-4">              lignes.push('', 'Confirmez-vous ?');

            <i class="bi bi-arrow-left me-2"></i>Annuler et retourner              if(!confirm(lignes.join('\n'))) return;

          </a>              pwdInput.value=''; bsModal.show(); setTimeout(()=>pwdInput.focus(),250);

          <button type="button" class="btn btn-danger px-4" id="btnConfirmDelete">            });

            <i class="bi bi-trash me-2"></i>Confirmer la suppression            btnDo && btnDo.addEventListener('click', ()=> {

          </button>              const pwd = pwdInput.value.trim();

        </div>              if(!pwd){ toast('warning','Mot de passe requis'); pwdInput.focus(); return; }

              btnDo.disabled=true; btnDo.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Suppression...';

      </div>              const fd = new FormData(); fd.append('csrf_token', csrf); fd.append('password', pwd);

    </div>              fetch('user_delete.php?id='+userId, { method:'POST', body: fd })

                .then(r=>r.json())

  </div>                .then(j=>{

</div>                  btnDo.disabled=false; btnDo.innerHTML='<i class="bi bi-check-circle me-1"></i> Confirmer';

                  if(!j.ok){ toast('danger','❌ '+(j.error||'Erreur')); return; }

<!-- Modal confirmation mot de passe -->                  toast('success','✅ Utilisateur supprimé'); bsModal.hide(); setTimeout(()=> location.href='users.php?deleted=1', 900);

<div class="modal fade" id="passwordModal" tabindex="-1">                })

  <div class="modal-dialog modal-dialog-centered">                .catch(()=>{ btnDo.disabled=false; btnDo.innerHTML='<i class="bi bi-check-circle me-1"></i> Confirmer'; toast('danger','❌ Erreur réseau'); });

    <div class="modal-content" style="border-radius: 12px; border: none;">            });

      <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">            pwdInput && pwdInput.addEventListener('keypress', e=> { if(e.key==='Enter'){ e.preventDefault(); btnDo.click(); }});

        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Confirmation requise</h5>          })();

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>          </script>

      </div>

      <div class="modal-body p-4">            <div class="col-md-6">          $fiches = $stmtFiches->fetchAll();

        <div class="alert alert-danger border-0 mb-3">

          <i class="bi bi-exclamation-triangle-fill me-2"></i>              <div class="card border-danger">

          <strong>Dernière confirmation avant suppression définitive</strong>

        </div>                <div class="card-body">    // 1. Supprimer les fiches de l'agent (cascade)      

        <p class="text-muted mb-3">Pour des raisons de sécurité, veuillez saisir votre mot de passe administrateur pour valider cette action sensible.</p>

        <input type="password"                   <h6 class="card-title text-danger"><i class="bi bi-file-earmark-text me-1"></i> Fiches d'évaluation</h6>

               class="form-control form-control-lg" 

               id="adminPassword"                   <p class="mb-0 fs-4 fw-bold"><?= $deletionSummary['fiches_agent'] ?> fiche(s)</p>    if ($deletionSummary['fiches_agent'] > 0) {      foreach ($fiches as $fiche) {

               placeholder="Mot de passe administrateur" 

               autocomplete="current-password"                  <small class="text-muted">Incluant tous les objectifs, items, auto-évaluations, résumés</small>

               style="border-radius: 8px;">

      </div>                </div>      $stmtFiches = $pdo->prepare("SELECT id, user_id, periode FROM objectifs WHERE user_id = ?");        $ficheId = (int)$fiche['id'];

      <div class="modal-footer">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">              </div>

          <i class="bi bi-x-circle me-1"></i>Annuler

        </button>            </div>      $stmtFiches->execute([$id]);        $userId = (int)$fiche['user_id'];

        <button type="button" class="btn btn-danger" id="btnFinalDelete">

          <i class="bi bi-check-circle me-1"></i>Supprimer définitivement            <?php endif; ?>

        </button>

      </div>      $fiches = $stmtFiches->fetchAll();        $periode = $fiche['periode'];

    </div>

  </div>            <?php if ($deletionSummary['supervisions_superviseur'] > 0 || $deletionSummary['fiches_superviseur'] > 0): ?>

</div>

            <div class="col-md-6">              

<script>

(function(){              <div class="card border-warning">

  const userId = <?= (int)$id ?>;

  const csrf = '<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>';                <div class="card-body">      foreach ($fiches as $fiche) {        // Suppression cascade des données liées

  const modalEl = document.getElementById('passwordModal');

  const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;                  <h6 class="card-title text-warning"><i class="bi bi-shield-check me-1"></i> Supervisions</h6>

  const pwdInput = document.getElementById('adminPassword');

  const btnConfirm = document.getElementById('btnConfirmDelete');                  <p class="mb-0"><strong><?= $deletionSummary['supervisions_superviseur'] ?></strong> validation(s) de supervision</p>        $ficheId = (int)$fiche['id'];        $pdo->prepare("DELETE FROM cote_des_objectifs WHERE objectif_id = ?")->execute([$ficheId]);

  const btnFinal = document.getElementById('btnFinalDelete');

                  <p class="mb-0"><strong><?= $deletionSummary['fiches_superviseur'] ?></strong> fiche(s) supervisée(s)</p>

  function showToast(type, message) {

    if (window.globalShowToast) {                  <small class="text-muted">Les validations seront supprimées</small>        $userId = (int)$fiche['user_id'];        $pdo->prepare("DELETE FROM auto_evaluation WHERE objectif_id = ?")->execute([$ficheId]);

      window.globalShowToast(type, message);

    } else {                </div>

      alert(message);

    }              </div>        $periode = $fiche['periode'];        $pdo->prepare("DELETE FROM coordination_commentaires WHERE objectif_id = ?")->execute([$ficheId]);

  }

            </div>

  // Étape 1 : Bouton principal "Confirmer la suppression"

  btnConfirm && btnConfirm.addEventListener('click', function(){            <?php endif; ?>                $pdo->prepare("DELETE FROM actions_recommandations WHERE objectif_id = ?")->execute([$ficheId]);

    const userName = '<?= htmlspecialchars(addslashes($user['nom'] . ' ' . $user['post_nom']), ENT_QUOTES) ?>';

    const warningMsg = '⚠️ DERNIÈRE VÉRIFICATION\n\n' +

                      'Vous êtes sur le point de supprimer définitivement :\n' +

                      userName + '\n\n' +            <?php if ($deletionSummary['coord_comments'] > 0): ?>        // Suppression cascade des données liées        $pdo->prepare("DELETE FROM objectifs_items WHERE objectif_id = ?")->execute([$ficheId]);

                      '📊 Données à supprimer :\n' +

                      '• <?= $summary['fiches'] ?> fiche(s) d\'objectifs\n' +            <div class="col-md-6">

                      '• <?= $summary['supervisions_faites'] ?> supervision(s) effectuée(s)\n' +

                      '• <?= $summary['supervisions_recues'] ?> supervision(s) reçue(s)\n' +              <div class="card border-info">        $pdo->prepare("DELETE FROM cote_des_objectifs WHERE objectif_id = ?")->execute([$ficheId]);        $pdo->prepare("DELETE FROM objectifs_resumes WHERE objectif_id = ?")->execute([$ficheId]);

                      <?php if ($summary['coord_comments'] > 0): ?>

                      '• <?= $summary['coord_comments'] ?> commentaire(s) de coordination\n' +                <div class="card-body">

                      <?php endif; ?>

                      <?php if ($summary['agents_supervises'] > 0): ?>                  <h6 class="card-title text-info"><i class="bi bi-chat-square-text me-1"></i> Commentaires coordination</h6>        $pdo->prepare("DELETE FROM auto_evaluation WHERE objectif_id = ?")->execute([$ficheId]);        $pdo->prepare("DELETE FROM supervisions WHERE agent_id = ? AND periode = ?")->execute([$userId, $periode]);

                      '• <?= $summary['agents_supervises'] ?> agent(s) supervisé(s) (seront détachés)\n' +

                      <?php endif; ?>                  <p class="mb-0 fs-4 fw-bold"><?= $deletionSummary['coord_comments'] ?> commentaire(s)</p>

                      '\n⛔ Cette action est IRRÉVERSIBLE !\n\n' +

                      'Voulez-vous vraiment continuer ?';                  <small class="text-muted">Commentaires de finalisation des fiches</small>        $pdo->prepare("DELETE FROM coordination_commentaires WHERE objectif_id = ?")->execute([$ficheId]);        $pdo->prepare("DELETE FROM objectifs WHERE id = ?")->execute([$ficheId]);

    

    if (!confirm(warningMsg)) {                </div>

      return;

    }              </div>        $pdo->prepare("DELETE FROM actions_recommandations WHERE objectif_id = ?")->execute([$ficheId]);      }

    

    // Afficher la modal pour le mot de passe            </div>

    pwdInput.value = '';

    bsModal.show();            <?php endif; ?>        $pdo->prepare("DELETE FROM objectifs_items WHERE objectif_id = ?")->execute([$ficheId]);    }

    setTimeout(() => pwdInput.focus(), 300);

  });



  // Étape 2 : Soumission avec mot de passe            <?php if ($deletionSummary['supervised_users'] > 0): ?>        $pdo->prepare("DELETE FROM objectifs_resumes WHERE objectif_id = ?")->execute([$ficheId]);    

  btnFinal && btnFinal.addEventListener('click', function(){

    const pwd = pwdInput.value.trim();            <div class="col-md-6">

    

    if (!pwd) {              <div class="card border-secondary">        $pdo->prepare("DELETE FROM supervisions WHERE agent_id = ? AND periode = ?")->execute([$userId, $periode]);    // 2. Supprimer les supervisions faites par cet utilisateur

      showToast('warning', '⚠️ Veuillez saisir votre mot de passe');

      pwdInput.focus();                <div class="card-body">

      return;

    }                  <h6 class="card-title text-secondary"><i class="bi bi-people me-1"></i> Utilisateurs supervisés</h6>        $pdo->prepare("DELETE FROM objectifs WHERE id = ?")->execute([$ficheId]);    if ($deletionSummary['supervisions_superviseur'] > 0) {



    btnFinal.disabled = true;                  <p class="mb-0 fs-4 fw-bold"><?= $deletionSummary['supervised_users'] ?> utilisateur(s)</p>

    btnFinal.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression en cours...';

                  <small class="text-muted">Le lien de supervision sera retiré (utilisateurs non supprimés)</small>      }      $pdo->prepare("DELETE FROM supervisions WHERE superviseur_id = ?")->execute([$id]);

    const fd = new FormData();

    fd.append('csrf_token', csrf);                </div>

    fd.append('password', pwd);

              </div>    }    }

    fetch('user_delete.php?id=' + userId, {

      method: 'POST',            </div>

      body: fd

    })            <?php endif; ?>        

    .then(r => r.json())

    .then(j => {          </div>

      if (!j.ok) {

        showToast('danger', '❌ ' + (j.error || 'Erreur inconnue'));    // 2. Supprimer les supervisions faites par cet utilisateur    // 3. Retirer le superviseur_id des utilisateurs supervisés

        btnFinal.disabled = false;

        btnFinal.innerHTML = '<i class="bi bi-check-circle me-1"></i> Supprimer définitivement';          <?php

        pwdInput.value = '';

        pwdInput.focus();          $totalImpact = $deletionSummary['fiches_agent'] +     if ($deletionSummary['supervisions_superviseur'] > 0) {    if ($deletionSummary['supervised_users'] > 0) {

        return;

      }                        $deletionSummary['supervisions_superviseur'] + 



      showToast('success', '✅ Utilisateur supprimé avec succès');                        $deletionSummary['coord_comments'] +       $pdo->prepare("DELETE FROM supervisions WHERE superviseur_id = ?")->execute([$id]);      $pdo->prepare("UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?")->execute([$id]);

      bsModal.hide();

                              $deletionSummary['supervised_users'];

      setTimeout(() => {

        window.location.href = 'users.php?deleted=1';          ?>    }    }

      }, 1200);

    })          

    .catch(err => {

      console.error(err);          <?php if ($totalImpact === 0): ?>        

      showToast('danger', '❌ Erreur réseau - Impossible de contacter le serveur');

      btnFinal.disabled = false;            <div class="alert alert-info mt-3 mb-0">

      btnFinal.innerHTML = '<i class="bi bi-check-circle me-1"></i> Supprimer définitivement';

    });              <i class="bi bi-info-circle me-2"></i> Cet utilisateur n'a aucune donnée associée. La suppression sera simple.    // 3. Retirer le superviseur_id des utilisateurs supervisés    // 4. Supprimer les commentaires coordination si coordination

  });

            </div>

  // Enter dans le champ mot de passe = cliquer sur le bouton

  pwdInput && pwdInput.addEventListener('keypress', function(e){          <?php else: ?>    if ($deletionSummary['supervised_users'] > 0) {    if ($deletionSummary['coord_comments'] > 0) {

    if (e.key === 'Enter') {

      e.preventDefault();            <div class="alert alert-danger mt-3 mb-0">

      btnFinal.click();

    }              <i class="bi bi-exclamation-octagon me-2"></i> <strong>Impact total :</strong> <?= $totalImpact ?> élément(s) seront affecté(s) ou supprimé(s) définitivement.      $pdo->prepare("UPDATE users SET superviseur_id = NULL WHERE superviseur_id = ?")->execute([$id]);      $pdo->prepare("DELETE FROM coordination_commentaires WHERE coordination_id = ?")->execute([$id]);

  });

})();            </div>

</script>

          <?php endif; ?>    }    }

<?php include('../includes/footer.php'); ?>

        </div>

        

        <!-- Boutons d'action -->

        <div class="d-flex justify-content-between align-items-center mt-4">    // 4. Supprimer les commentaires coordination si coordination    // 5. Supprimer l'utilisateur

          <a href="users.php" class="btn btn-secondary">

            <i class="bi bi-arrow-left me-1"></i> Annuler et retourner    if ($deletionSummary['coord_comments'] > 0) {    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

          </a>

          <button type="button" class="btn btn-danger btn-lg" id="btnConfirmDelete">      $pdo->prepare("DELETE FROM coordination_commentaires WHERE coordination_id = ?")->execute([$id]);    

            <i class="bi bi-trash-fill me-2"></i> Confirmer la suppression

          </button>    }    $pdo->commit();

        </div>

      </div>        echo json_encode(['ok' => true, 'message' => 'Utilisateur supprimé avec succès']);

    </div>

  </div>    // 5. Supprimer l'utilisateur    exit;

</div>

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);    

<!-- Modal confirmation avec mot de passe -->

<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">      } catch (Exception $e) {

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content border-danger">    $pdo->commit();    $pdo->rollBack();

      <div class="modal-header bg-danger text-white">

        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i> Authentification requise</h5>    echo json_encode(['ok' => true, 'message' => 'Utilisateur supprimé avec succès']);    echo json_encode(['ok' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>

      </div>    exit;    exit;

      <div class="modal-body">

        <p class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> Dernière confirmation avant suppression définitive</p>      }

        <p class="small">Veuillez saisir votre mot de passe administrateur pour confirmer cette action sensible.</p>

        <input type="password" class="form-control" id="adminPassword" placeholder="Mot de passe administrateur" autocomplete="current-password">  } catch (Exception $e) {}

      </div>

      <div class="modal-footer">    $pdo->rollBack();

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>

        <button type="button" class="btn btn-danger" id="btnFinalDelete">    echo json_encode(['ok' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);// Génération token CSRF

          <i class="bi bi-check-circle me-1"></i> Supprimer définitivement

        </button>    exit;if (!isset($_SESSION['csrf_token'])) {

      </div>

    </div>  }  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

  </div>

</div>}}



<script>$csrf_token = $_SESSION['csrf_token'];

(function(){

  const userId = <?= (int)$id ?>;// Génération token CSRF?>

  const csrf = '<?= htmlspecialchars($csrf_token) ?>';

  const modalEl = document.getElementById('passwordModal');if (!isset($_SESSION['csrf_token'])) {

  const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

  const pwdInput = document.getElementById('adminPassword');  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));<?php include('../includes/header.php'); ?>

  const btnConfirm = document.getElementById('btnConfirmDelete');

  const btnFinal = document.getElementById('btnFinalDelete');}



  function showToast(type, message) {$csrf_token = $_SESSION['csrf_token'];<!-- Contenu principal -->

    if (window.globalShowToast) {

      window.globalShowToast(type, message);?><div class="row justify-content-center">

    } else {

      alert(message);  <div class="col-md-6">

    }

  }<?php include('../includes/header.php'); ?>    <div class="card shadow-sm">



  // Étape 1 : Afficher la modal au clic sur "Confirmer la suppression"      <!-- En-tête rouge avec icône de suppression -->

  btnConfirm && btnConfirm.addEventListener('click', function(){

    const warningMsg = '⚠️ DERNIÈRE VÉRIFICATION\n\n' +<div class="row">      <div class="card-header bg-danger text-white">

                      'Vous êtes sur le point de supprimer définitivement l\'utilisateur :\n' +

                      '<?= htmlspecialchars(addslashes($user['nom'] . ' ' . $user['post_nom'])) ?>\n\n' +  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>        <i class="bi bi-trash me-2"></i> Supprimer l’utilisateur

                      'Cette action supprimera TOUTES les données associées listées ci-dessus.\n\n' +

                      'Voulez-vous vraiment continuer ?';  <div class="col-md-9">      </div>

    

    if (!confirm(warningMsg)) {    <div class="card shadow-sm border-0">      <div class="card-body">

      return;

    }      <div class="card-header bg-danger text-white">        <!-- Message de confirmation -->

    

    pwdInput.value = '';        <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Suppression d'utilisateur</h5>        <p class="mb-4">

    bsModal.show();

    setTimeout(() => pwdInput.focus(), 300);      </div>          Voulez-vous vraiment supprimer <strong><?= $user['nom'] . ' ' . $user['post_nom'] ?></strong> ?

  });

      <div class="card-body">          Cette action est <span class="text-danger fw-bold">irréversible</span>.

  // Étape 2 : Soumission avec mot de passe

  btnFinal && btnFinal.addEventListener('click', function(){        <!-- Info utilisateur -->        </p>

    const pwd = pwdInput.value.trim();

    if (!pwd) {        <div class="alert alert-danger border-danger mb-4">

      showToast('warning', '⚠️ Veuillez saisir votre mot de passe');

      pwdInput.focus();          <h5 class="alert-heading"><i class="bi bi-person-x me-2"></i> Utilisateur ciblé</h5>        <!-- Bouton qui déclenche le modal Bootstrap -->

      return;

    }          <hr>        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmModal">



    btnFinal.disabled = true;          <div class="row">          <i class="bi bi-exclamation-triangle-fill me-1"></i> Confirmer la suppression

    btnFinal.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression en cours...';

            <div class="col-md-6">        </button>

    const fd = new FormData();

    fd.append('csrf_token', csrf);              <p class="mb-1"><strong>Nom complet :</strong> <?= htmlspecialchars($user['nom'] . ' ' . $user['post_nom']) ?></p>

    fd.append('password', pwd);

              <p class="mb-1"><strong>Email :</strong> <?= htmlspecialchars($user['email'] ?: '—') ?></p>        <!-- Lien pour annuler et revenir à la liste -->

    fetch('user_delete.php?id=' + userId, {

      method: 'POST',              <p class="mb-0"><strong>Fonction :</strong> <?= htmlspecialchars($user['fonction'] ?: '—') ?></p>        <a href="users.php" class="btn btn-secondary ms-2">Annuler</a>

      body: fd

    })            </div>      </div>

    .then(r => r.json())

    .then(j => {            <div class="col-md-6">    </div>

      if (!j.ok) {

        showToast('danger', '❌ ' + (j.error || 'Erreur inconnue'));              <p class="mb-1"><strong>Rôle :</strong> <span class="badge bg-dark"><?= htmlspecialchars($user['role']) ?></span></p>  </div>

        btnFinal.disabled = false;

        btnFinal.innerHTML = '<i class="bi bi-check-circle me-1"></i> Supprimer définitivement';              <p class="mb-0"><strong>Matricule :</strong> <?= htmlspecialchars($user['matricule'] ?: '—') ?></p></div>

        return;

      }            </div>



      showToast('success', '✅ Utilisateur et toutes ses données supprimés avec succès');          </div><!-- Modal Bootstrap de confirmation -->

      bsModal.hide();

      setTimeout(() => {        </div><div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">

        window.location.href = 'users.php?deleted=1';

      }, 1200);  <div class="modal-dialog modal-dialog-centered">

    })

    .catch(err => {        <!-- Synthèse de suppression -->    <div class="modal-content border-danger">

      showToast('danger', '❌ Erreur réseau - Impossible de contacter le serveur');

      btnFinal.disabled = false;        <div class="alert alert-warning border-warning">      <!-- En-tête du modal -->

      btnFinal.innerHTML = '<i class="bi bi-check-circle me-1"></i> Supprimer définitivement';

    });          <h5 class="alert-heading"><i class="bi bi-clipboard-data me-2"></i> Synthèse des données à supprimer</h5>      <div class="modal-header bg-danger text-white">

  });

          <hr>        <h5 class="modal-title" id="confirmModalLabel">

  // Enter dans le champ mot de passe = cliquer sur le bouton

  pwdInput && pwdInput.addEventListener('keypress', function(e){          <p class="fw-bold text-danger mb-3">⚠️ ATTENTION : Cette action est IRRÉVERSIBLE et supprimera définitivement :</p>          <i class="bi bi-trash-fill me-2"></i> Confirmation

    if (e.key === 'Enter') {

      e.preventDefault();                  </h5>

      btnFinal.click();

    }          <div class="row g-3">        <!-- Bouton de fermeture -->

  });

})();            <?php if ($deletionSummary['fiches_agent'] > 0): ?>        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>

</script>

            <div class="col-md-6">      </div>

<?php include('../includes/footer.php'); ?>

              <div class="card border-danger">

                <div class="card-body">      <!-- Corps du modal -->

                  <h6 class="card-title text-danger"><i class="bi bi-file-earmark-text me-1"></i> Fiches d'évaluation</h6>      <div class="modal-body">

                  <p class="mb-0 fs-4 fw-bold"><?= $deletionSummary['fiches_agent'] ?> fiche(s)</p>        Êtes-vous sûr de vouloir supprimer <strong><?= $user['nom'] . ' ' . $user['post_nom'] ?></strong> ?

                  <small class="text-muted">Incluant tous les objectifs, items, auto-évaluations, résumés</small>      </div>

                </div>

              </div>      <!-- Pied du modal avec formulaire de suppression -->

            </div>      <div class="modal-footer">

            <?php endif; ?>        <form method="post">

          <button type="submit" class="btn btn-danger">Oui, supprimer</button>

            <?php if ($deletionSummary['supervisions_superviseur'] > 0 || $deletionSummary['fiches_superviseur'] > 0): ?>        </form>

            <div class="col-md-6">        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>

              <div class="card border-warning">      </div>

                <div class="card-body">    </div>

                  <h6 class="card-title text-warning"><i class="bi bi-shield-check me-1"></i> Supervisions</h6>  </div>

                  <p class="mb-0"><strong><?= $deletionSummary['supervisions_superviseur'] ?></strong> validation(s) de supervision</p></div>

                  <p class="mb-0"><strong><?= $deletionSummary['fiches_superviseur'] ?></strong> fiche(s) supervisée(s)</p>

                  <small class="text-muted">Les validations seront supprimées</small><?php include('../includes/footer.php'); ?>

                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($deletionSummary['coord_comments'] > 0): ?>
            <div class="col-md-6">
              <div class="card border-info">
                <div class="card-body">
                  <h6 class="card-title text-info"><i class="bi bi-chat-square-text me-1"></i> Commentaires coordination</h6>
                  <p class="mb-0 fs-4 fw-bold"><?= $deletionSummary['coord_comments'] ?> commentaire(s)</p>
                  <small class="text-muted">Commentaires de finalisation des fiches</small>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($deletionSummary['supervised_users'] > 0): ?>
            <div class="col-md-6">
              <div class="card border-secondary">
                <div class="card-body">
                  <h6 class="card-title text-secondary"><i class="bi bi-people me-1"></i> Utilisateurs supervisés</h6>
                  <p class="mb-0 fs-4 fw-bold"><?= $deletionSummary['supervised_users'] ?> utilisateur(s)</p>
                  <small class="text-muted">Le lien de supervision sera retiré (utilisateurs non supprimés)</small>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <?php
          $totalImpact = $deletionSummary['fiches_agent'] + 
                        $deletionSummary['supervisions_superviseur'] + 
                        $deletionSummary['coord_comments'] + 
                        $deletionSummary['supervised_users'];
          ?>
          
          <?php if ($totalImpact === 0): ?>
            <div class="alert alert-info mt-3 mb-0">
              <i class="bi bi-info-circle me-2"></i> Cet utilisateur n'a aucune donnée associée. La suppression sera simple.
            </div>
          <?php else: ?>
            <div class="alert alert-danger mt-3 mb-0">
              <i class="bi bi-exclamation-octagon me-2"></i> <strong>Impact total :</strong> <?= $totalImpact ?> élément(s) seront affecté(s) ou supprimé(s) définitivement.
            </div>
          <?php endif; ?>
        </div>

        <!-- Boutons d'action -->
        <div class="d-flex justify-content-between align-items-center mt-4">
          <a href="users.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Annuler et retourner
          </a>
          <button type="button" class="btn btn-danger btn-lg" id="btnConfirmDelete">
            <i class="bi bi-trash-fill me-2"></i> Confirmer la suppression
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal confirmation avec mot de passe -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i> Authentification requise</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> Dernière confirmation avant suppression définitive</p>
        <p class="small">Veuillez saisir votre mot de passe administrateur pour confirmer cette action sensible.</p>
        <input type="password" class="form-control" id="adminPassword" placeholder="Mot de passe administrateur" autocomplete="current-password">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-danger" id="btnFinalDelete">
          <i class="bi bi-check-circle me-1"></i> Supprimer définitivement
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const userId = <?= (int)$id ?>;
  const csrf = '<?= htmlspecialchars($csrf_token) ?>';
  const modalEl = document.getElementById('passwordModal');
  const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const pwdInput = document.getElementById('adminPassword');
  const btnConfirm = document.getElementById('btnConfirmDelete');
  const btnFinal = document.getElementById('btnFinalDelete');

  function showToast(type, message) {
    if (window.globalShowToast) {
      window.globalShowToast(type, message);
    } else {
      alert(message);
    }
  }

  // Étape 1 : Afficher la modal au clic sur "Confirmer la suppression"
  btnConfirm && btnConfirm.addEventListener('click', function(){
    const warningMsg = '⚠️ DERNIÈRE VÉRIFICATION\n\n' +
                      'Vous êtes sur le point de supprimer définitivement l\'utilisateur :\n' +
                      '<?= htmlspecialchars(addslashes($user['nom'] . ' ' . $user['post_nom'])) ?>\n\n' +
                      'Cette action supprimera TOUTES les données associées listées ci-dessus.\n\n' +
                      'Voulez-vous vraiment continuer ?';
    
    if (!confirm(warningMsg)) {
      return;
    }
    
    pwdInput.value = '';
    bsModal.show();
    setTimeout(() => pwdInput.focus(), 300);
  });

  // Étape 2 : Soumission avec mot de passe
  btnFinal && btnFinal.addEventListener('click', function(){
    const pwd = pwdInput.value.trim();
    if (!pwd) {
      showToast('warning', '⚠️ Veuillez saisir votre mot de passe');
      pwdInput.focus();
      return;
    }

    btnFinal.disabled = true;
    btnFinal.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression en cours...';

    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('password', pwd);

    fetch('user_delete.php?id=' + userId, {
      method: 'POST',
      body: fd
    })
    .then(r => r.json())
    .then(j => {
      if (!j.ok) {
        showToast('danger', '❌ ' + (j.error || 'Erreur inconnue'));
        btnFinal.disabled = false;
        btnFinal.innerHTML = '<i class="bi bi-check-circle me-1"></i> Supprimer définitivement';
        return;
      }

      showToast('success', '✅ Utilisateur et toutes ses données supprimés avec succès');
      bsModal.hide();
      setTimeout(() => {
        window.location.href = 'users.php?deleted=1';
      }, 1200);
    })
    .catch(err => {
      showToast('danger', '❌ Erreur réseau - Impossible de contacter le serveur');
      btnFinal.disabled = false;
      btnFinal.innerHTML = '<i class="bi bi-check-circle me-1"></i> Supprimer définitivement';
    });
  });

  // Enter dans le champ mot de passe = cliquer sur le bouton
  pwdInput && pwdInput.addEventListener('keypress', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      btnFinal.click();
    }
  });
})();
</script>

<?php include('../includes/footer.php'); ?>
