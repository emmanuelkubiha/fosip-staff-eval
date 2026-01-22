<?php
/**
 * pages/profile.php
 * Profil utilisateur: changer mot de passe et photo; admin peut aussi changer ses infos.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'invité';

// Charger infos utilisateur
$st = $pdo->prepare('SELECT id, nom, post_nom, email, fonction, role, photo, mot_de_passe FROM users WHERE id = ?');
$st->execute([$user_id]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me) { header('Location: logout.php'); exit; }

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

$msg = '';
$err = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf_token'] ?? '';
  if ($token !== $csrf) { $err = 'Jeton CSRF invalide.'; }
  else if ($action === 'password') {
    $current = trim($_POST['current_password'] ?? '');
    $new = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    if ($new === '' || $new !== $confirm) { $err = 'Les mots de passe ne correspondent pas.'; }
    else if (!password_verify($current, $me['mot_de_passe'])) { $err = 'Mot de passe actuel incorrect.'; }
    else {
      $hash = password_hash($new, PASSWORD_DEFAULT);
      $up = $pdo->prepare('UPDATE users SET mot_de_passe = ? WHERE id = ?');
      $up->execute([$hash, $user_id]);
      $msg = 'Mot de passe mis à jour avec succès.';
    }
  }
  else if ($action === 'photo') {
    // Vérifier si on a reçu une image recadrée en base64
    if (!empty($_POST['cropped_image'])) {
      $croppedImage = $_POST['cropped_image'];
      
      // Vérifier la taille des données base64
      $imageSize = strlen($croppedImage);
      $maxSize = 5 * 1024 * 1024; // 5 MB en caractères base64
      
      if ($imageSize > $maxSize) {
        $err = 'L\'image est trop volumineuse (maximum 5 MB). Veuillez choisir une image plus petite ou recadrer davantage.';
      } else {
        // Extraire les données base64
        if (preg_match('/^data:image\/(\w+);base64,/', $croppedImage, $type)) {
          $croppedImage = substr($croppedImage, strpos($croppedImage, ',') + 1);
          $type = strtolower($type[1]); // jpg, png, gif
          
          // Vérifier le type
          if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $err = 'Type d\'image non supporté. Formats acceptés : JPG, PNG, GIF, WEBP';
          } else {
            $croppedImage = base64_decode($croppedImage);
            
            if ($croppedImage === false) {
              $err = 'Erreur de décodage de l\'image. Le fichier semble corrompu.';
            } else {
              // Vérifier la taille du fichier décodé
              $decodedSize = strlen($croppedImage);
              $maxDecodedSize = 4 * 1024 * 1024; // 4 MB
              
              if ($decodedSize > $maxDecodedSize) {
                $err = 'L\'image décodée est trop volumineuse (' . round($decodedSize / (1024 * 1024), 2) . ' MB). Maximum autorisé : 4 MB.';
              } else {
                $dir = '../assets/img/profiles/';
                if (!is_dir($dir)) {
                  if (!mkdir($dir, 0755, true)) {
                    $err = 'Impossible de créer le dossier de destination. Vérifiez les permissions.';
                  }
                }
                
                if (!$err) {
                  $filename = 'user_' . $user_id . '_' . time() . '.' . $type;
                  $path = $dir . $filename;
                  
                  if (file_put_contents($path, $croppedImage)) {
                    // Supprimer l'ancienne photo si elle existe et n'est pas default.png
                    if (!empty($me['photo']) && $me['photo'] !== 'default.png' && file_exists($dir . $me['photo'])) {
                      @unlink($dir . $me['photo']);
                    }
                    
                    $up = $pdo->prepare('UPDATE users SET photo = ? WHERE id = ?');
                    $up->execute([$filename, $user_id]);
                    $_SESSION['photo'] = $filename;
                    $me['photo'] = $filename;
                    $msg = 'Photo mise à jour avec succès.';
                  } else {
                    $err = 'Erreur lors de la sauvegarde de l\'image sur le serveur. Vérifiez les permissions d\'écriture du dossier.';
                  }
                }
              }
            }
          }
        } else {
          $err = 'Format d\'image invalide. Veuillez sélectionner une image valide (JPG, PNG, GIF, WEBP).';
        }
      }
    } else {
      $err = 'Aucune image reçue. Veuillez sélectionner et recadrer une photo avant de l\'enregistrer.';
    }
  }
  else if ($action === 'infos' && $role === 'admin') {
    $nom = trim($_POST['nom'] ?? '');
    $post_nom = trim($_POST['post_nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fonction = trim($_POST['fonction'] ?? '');
    if ($nom === '' || $email === '') { $err = 'Nom et email requis.'; }
    else {
      $up = $pdo->prepare('UPDATE users SET nom = ?, post_nom = ?, email = ?, fonction = ? WHERE id = ?');
      $up->execute([$nom, $post_nom, $email, $fonction, $user_id]);
      $_SESSION['nom'] = $nom;
      $me['nom'] = $nom;
      $me['post_nom'] = $post_nom;
      $me['email'] = $email;
      $me['fonction'] = $fonction;
      $msg = 'Informations mises à jour. Reconnectez-vous pour appliquer les changements.';
      header('refresh:2;url=logout.php');
    }
  }
}

include('../includes/header.php');
?>

<!-- Ajouter Cropper.js pour le recadrage d'image -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<style>
/* Nouveau design propre (remplace l'ancien et supprime duplications) */
:root {
  --profil-bg: linear-gradient(135deg,#ffffff 0%,#f4f8fc 100%);
  --profil-shadow: 0 8px 22px -8px rgba(0,0,0,.18);
}
.profile-shell{background:var(--profil-bg);border-radius:18px;padding:1.75rem 1.75rem 2.25rem;box-shadow:var(--profil-shadow);position:relative;overflow:hidden;}
.profile-shell:before{content:"";position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:radial-gradient(circle at center, var(--fosip) 0%, rgba(61,116,185,.35) 60%, rgba(61,116,185,0) 70%);opacity:.15;}
.avatar-frame{position:relative;width:120px;height:120px;border-radius:50%;overflow:hidden;box-shadow:0 0 0 5px #fff,0 0 0 8px var(--fosip),0 10px 18px -8px rgba(0,0,0,.35);} 
.avatar-frame img{width:100%;height:100%;object-fit:cover;}
.avatar-btn{position:absolute;bottom:6px;right:6px;width:38px;height:38px;border-radius:50%;background:var(--fosip);color:#fff;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 10px -3px rgba(0,0,0,.4);} 
.avatar-btn:hover{background:var(--fosip-jaune);color:#212529;}
.section-label{
  font-size:.85rem;
  font-weight:600;
  letter-spacing:.05em;
  text-transform:uppercase;
  color:#6c757d;
  display:flex;
  align-items:center;
  gap:.75rem;
  margin-bottom:.85rem;
  padding-bottom:.5rem;
  border-bottom:2px solid #e9ecef;
}
.section-label i{
  font-size:1.5rem;
  color:var(--fosip);
}
.card-block{background:#fff;border:1px solid #e4e9ef;border-radius:14px;padding:1.1rem 1.25rem;box-shadow:0 4px 12px -6px rgba(0,0,0,.08);} 
.card-block + .card-block{margin-top:1rem;}
.info-readonly{background:#fff;border:1px dashed #cfd6dc;border-radius:10px;padding:.65rem .9rem;font-size:.8rem;color:#5f666d;}
.btn-fosip{background:var(--fosip);color:#fff;border:none;font-weight:500;border-radius:10px;padding:.55rem 1rem;box-shadow:0 4px 12px -4px rgba(0,0,0,.25);} 
.btn-fosip:hover{background:var(--fosip-jaune);color:#212529;}
.password-strength{height:6px;border-radius:4px;background:#e1e5ea;overflow:hidden;margin-top:4px;} 
.password-strength .bar{height:100%;width:0;background:linear-gradient(90deg,#ff5f57,#ffd866);transition:width .35s ease;}
/* Styles pour l'éditeur d'image */
#cropperModal .modal-dialog {
  max-width: 800px;
}

#cropperContainer {
  max-height: 500px;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
}

#cropperContainer img {
  max-width: 100%;
  display: block;
}

.cropper-controls {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  margin-top: 1rem;
  flex-wrap: wrap;
}

.cropper-controls button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  background: #f0f0f0;
  cursor: pointer;
  transition: all 0.2s;
}

.cropper-controls button:hover {
  background: #e0e0e0;
}

.cropper-controls button i {
  margin-right: 0.25rem;
}

@media (max-width: 991px){.avatar-frame{width:100px;height:100px;} .profile-shell{padding:1.25rem;}}
@media (max-width: 575.98px) {
  .col-md-9 {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
}
</style>

<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-md-9" style="padding-left: 6rem;">
    <div class="profile-shell mb-4">
      <div class="d-flex flex-wrap align-items-start gap-4 mb-3">
        <div class="avatar-frame">
          <?php $photo = $me['photo'] ? '../assets/img/profiles/'.$me['photo'] : '../assets/img/profiles/default.png'; ?>
          <img id="avatarPreview" src="<?= htmlspecialchars($photo) ?>" onerror="this.onerror=null;this.src='../assets/img/profiles/default.png';">
          <button type="button" class="avatar-btn" id="triggerPhoto" data-bs-toggle="tooltip" title="Changer la photo"><i class="bi bi-camera-fill"></i></button>
        </div>
        <div class="flex-grow-1">
          <h3 class="mb-1" style="font-weight:600;">
            <?= htmlspecialchars($me['nom'].' '.$me['post_nom']) ?>
            <?php if ($role==='admin'): ?><i class="" data-bs-toggle="tooltip" title="Édition disponible"></i><?php endif; ?>
          </h3>
          <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-envelope"></i> <?= htmlspecialchars($me['email']) ?>
            <span class="badge" style="background:var(--fosip);"><?= htmlspecialchars(ucfirst($role)) ?></span>
          </div>
        </div>
      </div>
      
      <?php if ($err): ?><div class="alert alert-danger small mb-3"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <?php if ($msg): ?><div class="alert alert-success small mb-3"><i class="bi bi-check2-circle me-1"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="section-label">
            <i class="bi bi-image"></i>
            <span>Photo de profil</span>
          </div>
          <div class="card-block">
            <!-- Input caché pour sélectionner le fichier -->
            <input type="file" id="photoInput" accept="image/*" style="display: none;">
            
            <!-- Formulaire qui enverra l'image recadrée -->
            <form id="photoForm" method="post" enctype="multipart/form-data" class="d-flex flex-column gap-2">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="photo">
              <input type="hidden" id="croppedImageData" name="cropped_image" value="">
              
              <button type="button" class="btn btn-sm btn-outline-primary" id="selectPhotoBtn">
                <i class="bi bi-image me-1"></i>Choisir une photo
              </button>
              
              <button class="btn btn-sm btn-fosip" type="submit" id="uploadBtn" style="display: none;">
                <i class="bi bi-upload me-1"></i>Enregistrer la photo
              </button>
            </form>
            
            <div class="small text-muted mt-2">
              <i class="bi bi-info-circle me-1"></i>Vous pourrez recadrer votre photo avant l'upload
            </div>
          </div>
        </div>
        
        <div class="col-lg-8">
          <div class="section-label">
            <i class="bi bi-person-fill"></i>
            <span>Informations personnelles</span>
          </div>
          <form method="post" class="row g-3 card-block">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="infos">
            <div class="col-md-6">
              <label class="form-label">Nom</label>
              <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($me['nom']) ?>" <?= $role==='admin' ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label">Post-nom</label>
              <input type="text" name="post_nom" class="form-control" value="<?= htmlspecialchars($me['post_nom']) ?>" <?= $role==='admin' ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($me['email']) ?>" <?= $role==='admin' ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label">Fonction</label>
              <input type="text" name="fonction" class="form-control" value="<?= htmlspecialchars($me['fonction']) ?>" <?= $role==='admin' ? '' : 'readonly' ?>>
            </div>
            <?php if ($role !== 'admin'): ?>
            <div class="col-12"><div class="info-readonly"><i class="bi bi-info-circle me-1"></i> Modification via Ressources humaines uniquement.</div></div>
            <?php else: ?>
            <div class="col-12 d-flex justify-content-end"><button class="btn btn-fosip" type="submit"><i class="bi bi-save me-1"></i> Enregistrer</button></div>
            <?php endif; ?>
          </form>
        </div>
      </div>
      
      <div class="section-label mt-4">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Sécurité & mot de passe</span>
      </div>
      <div class="row g-4">
        <div class="col-lg-6">
          <form method="post" class="row g-3 card-block">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="password">
            <div class="col-12">
              <label class="form-label">Mot de passe actuel</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nouveau mot de passe</label>
              <input type="password" name="new_password" id="newPassword" class="form-control" required>
              <div class="password-strength"><div class="bar" id="pwdBar"></div></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirmer</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-fosip" type="submit"><i class="bi bi-key-fill me-1"></i> Mettre à jour</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal pour recadrer l'image -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-crop me-2"></i>Recadrer votre photo de profil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="cropperContainer">
          <img id="cropperImage" src="" alt="Image à recadrer">
        </div>
        
        <div class="cropper-controls">
          <button type="button" id="zoomIn" title="Zoomer">
            <i class="bi bi-zoom-in"></i> Zoom +
          </button>
          <button type="button" id="zoomOut" title="Dézoomer">
            <i class="bi bi-zoom-out"></i> Zoom -
          </button>
          <button type="button" id="rotateLeft" title="Rotation gauche">
            <i class="bi bi-arrow-counterclockwise"></i> Rotation
          </button>
          <button type="button" id="rotateRight" title="Rotation droite">
            <i class="bi bi-arrow-clockwise"></i> Rotation
          </button>
          <button type="button" id="flipHorizontal" title="Miroir horizontal">
            <i class="bi bi-arrows-expand"></i> Miroir H
          </button>
          <button type="button" id="flipVertical" title="Miroir vertical">
            <i class="bi bi-arrows-collapse"></i> Miroir V
          </button>
          <button type="button" id="reset" title="Réinitialiser">
            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
          </button>
        </div>
        
        <div class="alert alert-info mt-3 small mb-0">
          <i class="bi bi-info-circle me-1"></i>
          Déplacez et redimensionnez le cadre pour ajuster votre photo. L'image sera recadrée en format carré.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-fosip" id="cropAndSave">
          <i class="bi bi-check2-circle me-1"></i>Valider le recadrage
        </button>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
let cropper = null;
let scaleX = 1;
let scaleY = 1;

document.addEventListener('DOMContentLoaded', function(){
  // Tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));
  
  const trigger = document.getElementById('triggerPhoto');
  const selectBtn = document.getElementById('selectPhotoBtn');
  const input = document.getElementById('photoInput');
  const preview = document.getElementById('avatarPreview');
  const cropperImage = document.getElementById('cropperImage');
  const cropperModal = new bootstrap.Modal(document.getElementById('cropperModal'));
  const uploadBtn = document.getElementById('uploadBtn');
  const photoForm = document.getElementById('photoForm');
  
  // Ouvrir le sélecteur de fichier
  if (trigger) {
    trigger.addEventListener('click', () => input.click());
  }
  
  if (selectBtn) {
    selectBtn.addEventListener('click', () => input.click());
  }
  
  // Quand un fichier est sélectionné
  if (input) {
    input.addEventListener('change', function(e){
      const file = e.target.files[0];
      if (!file) return;
      
      // Vérifier que c'est bien une image
      if (!file.type.match('image.*')) {
        showErrorToast('Veuillez sélectionner un fichier image valide (JPG, PNG, GIF, WEBP)');
        this.value = '';
        return;
      }
      
      // Vérifier la taille du fichier (5 MB max)
      const maxSize = 5 * 1024 * 1024; // 5 MB
      if (file.size > maxSize) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        showErrorToast('Le fichier est trop volumineux (' + sizeMB + ' MB). Taille maximale autorisée : 5 MB');
        this.value = '';
        return;
      }
      
      // Lire le fichier et l'afficher dans la modal
      const reader = new FileReader();
      reader.onload = function(event) {
        cropperImage.src = event.target.result;
        
        // Détruire l'ancien cropper s'il existe
        if (cropper) {
          cropper.destroy();
        }
        
        // Initialiser Cropper.js
        cropper = new Cropper(cropperImage, {
          aspectRatio: 1, // Format carré
          viewMode: 2,
          dragMode: 'move',
          autoCropArea: 0.8,
          restore: false,
          guides: true,
          center: true,
          highlight: false,
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false,
          minCropBoxWidth: 200,
          minCropBoxHeight: 200,
        });
        
        // Réinitialiser les variables de flip
        scaleX = 1;
        scaleY = 1;
        
        // Ouvrir la modal
        cropperModal.show();
      };
      
      reader.onerror = function() {
        showErrorToast('Erreur lors de la lecture du fichier. Le fichier est peut-être corrompu.');
      };
      
      reader.readAsDataURL(file);
    });
  }
  
  // Contrôles du cropper
  document.getElementById('zoomIn')?.addEventListener('click', () => {
    cropper.zoom(0.1);
  });
  
  document.getElementById('zoomOut')?.addEventListener('click', () => {
    cropper.zoom(-0.1);
  });
  
  document.getElementById('rotateLeft')?.addEventListener('click', () => {
    cropper.rotate(-90);
  });
  
  document.getElementById('rotateRight')?.addEventListener('click', () => {
    cropper.rotate(90);
  });
  
  document.getElementById('flipHorizontal')?.addEventListener('click', () => {
    scaleX = scaleX === 1 ? -1 : 1;
    cropper.scaleX(scaleX);
  });
  
  document.getElementById('flipVertical')?.addEventListener('click', () => {
    scaleY = scaleY === 1 ? -1 : 1;
    cropper.scaleY(scaleY);
  });
  
  document.getElementById('reset')?.addEventListener('click', () => {
    cropper.reset();
    scaleX = 1;
    scaleY = 1;
  });
  
  // Valider le recadrage
  document.getElementById('cropAndSave')?.addEventListener('click', function() {
    if (!cropper) return;
    
    // Obtenir l'image recadrée en base64
    const canvas = cropper.getCroppedCanvas({
      width: 400,
      height: 400,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high',
    });
    
    if (!canvas) {
      alert('Erreur lors du recadrage de l\'image');
      return;
    }
    
    // Convertir en blob puis en base64
    canvas.toBlob(function(blob) {
      const reader = new FileReader();
      reader.onloadend = function() {
        const base64data = reader.result;
        
        // Mettre à jour l'aperçu
        preview.src = base64data;
        
        // Stocker les données dans le champ caché
        document.getElementById('croppedImageData').value = base64data;
        
        // Afficher le bouton d'upload
        uploadBtn.style.display = 'block';
        
        // Fermer la modal
        cropperModal.hide();
      };
      reader.readAsDataURL(blob);
    }, 'image/jpeg', 0.9);
  });
  
  // Soumettre le formulaire
  if (photoForm) {
    photoForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const croppedData = document.getElementById('croppedImageData').value;
      if (!croppedData) {
        showErrorToast('Veuillez d\'abord sélectionner et recadrer une photo');
        return;
      }
      
      // Vérifier la taille avant l'envoi
      const sizeInBytes = Math.ceil((croppedData.length - 'data:image/jpeg;base64,'.length) * 3 / 4);
      const sizeInMB = sizeInBytes / (1024 * 1024);
      
      if (sizeInMB > 4) {
        showErrorToast('L\'image est trop volumineuse (' + sizeInMB.toFixed(2) + ' MB). Veuillez recadrer davantage ou choisir une image plus petite.');
        return;
      }
      
      // Désactiver le bouton pendant l'upload
      uploadBtn.disabled = true;
      const originalHTML = uploadBtn.innerHTML;
      uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Upload...';
      
      // Envoyer via fetch
      const formData = new FormData();
      formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
      formData.append('action', 'photo');
      formData.append('cropped_image', croppedData);
      
      fetch('profile.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Erreur serveur HTTP ' + response.status);
        }
        return response.text();
      })
      .then(html => {
        // Vérifier s'il y a un message d'erreur dans la réponse
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const errorAlert = tempDiv.querySelector('.alert-danger');
        const successAlert = tempDiv.querySelector('.alert-success');
        
        if (errorAlert) {
          const errorText = errorAlert.textContent.trim().replace(/×/g, '').trim();
          showErrorToast(errorText);
          uploadBtn.disabled = false;
          uploadBtn.innerHTML = originalHTML;
        } else if (successAlert) {
          showSuccessToast('Photo mise à jour avec succès');
          // Recharger la page après un court délai
          setTimeout(() => window.location.reload(), 1000);
        } else {
          // Pas de message clair, recharger quand même
          window.location.reload();
        }
      })
      .catch(error => {
        console.error('Erreur:', error);
        showErrorToast('Erreur lors de l\'upload : ' + error.message);
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalHTML;
      });
    });
  }
  
  // Fonctions helper pour afficher les toasts
  function showErrorToast(message) {
    showToast('danger', message, 6000);
  }
  
  function showSuccessToast(message) {
    showToast('success', message, 3000);
  }
  
  function showToast(type, message, delay = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'position-fixed top-0 end-0 p-3';
      container.style.zIndex = '10000';
      document.body.appendChild(container);
    }
    
    const id = 'toast-' + Date.now();
    let bgClass = 'bg-primary text-white';
    let icon = 'info-circle-fill';
    
    if (type === 'success') {
      bgClass = 'bg-success text-white';
      icon = 'check-circle-fill';
    } else if (type === 'danger') {
      bgClass = 'bg-danger text-white';
      icon = 'exclamation-triangle-fill';
    } else if (type === 'warning') {
      bgClass = 'bg-warning text-dark';
      icon = 'exclamation-circle-fill';
    }
    
    const toastHTML = `
      <div id="${id}" class="toast ${bgClass}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}">
        <div class="toast-header ${bgClass} border-0">
          <i class="bi bi-${icon} me-2"></i>
          <strong class="me-auto">${type === 'success' ? 'Succès' : type === 'danger' ? 'Erreur' : 'Information'}</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
          ${message}
        </div>
      </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(id);
    const bsToast = new bootstrap.Toast(toastElement);
    bsToast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function() {
      toastElement.remove();
    });
  }
  
  // Validation côté client lors de la sélection du fichier
  if (input) {
    input.addEventListener('change', function(e){
      const file = e.target.files[0];
      if (!file) return;
      
      // Vérifier que c'est bien une image
      if (!file.type.match('image.*')) {
        showErrorToast('Veuillez sélectionner un fichier image valide (JPG, PNG, GIF, WEBP)');
        this.value = '';
        return;
      }
      
      // Vérifier la taille du fichier (5 MB max)
      const maxSize = 5 * 1024 * 1024; // 5 MB
      if (file.size > maxSize) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        showErrorToast('Le fichier est trop volumineux (' + sizeMB + ' MB). Taille maximale autorisée : 5 MB');
        this.value = '';
        return;
      }
      
      // Lire le fichier et l'afficher dans la modal
      const reader = new FileReader();
      reader.onload = function(event) {
        cropperImage.src = event.target.result;
        
        // Détruire l'ancien cropper s'il existe
        if (cropper) {
          cropper.destroy();
        }
        
        // Initialiser Cropper.js
        cropper = new Cropper(cropperImage, {
          aspectRatio: 1, // Format carré
          viewMode: 2,
          dragMode: 'move',
          autoCropArea: 0.8,
          restore: false,
          guides: true,
          center: true,
          highlight: false,
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false,
          minCropBoxWidth: 200,
          minCropBoxHeight: 200,
        });
        
        // Réinitialiser les variables de flip
        scaleX = 1;
        scaleY = 1;
        
        // Ouvrir la modal
        cropperModal.show();
      };
      
      reader.onerror = function() {
        showErrorToast('Erreur lors de la lecture du fichier. Le fichier est peut-être corrompu.');
      };
      
      reader.readAsDataURL(file);
    });
  }
});
</script>
