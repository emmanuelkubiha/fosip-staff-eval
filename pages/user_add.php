<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include('../includes/auth.php');
require_role(['admin']);
include('../includes/db.php');

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom = $_POST['nom'];
  $post_nom = $_POST['post_nom'];
  $email = $_POST['email'];
  $fonction = $_POST['fonction'];
  $role = $_POST['role'];
  $superviseur_id = $_POST['superviseur_id'] ?: null;
  $mot_de_passe = $_POST['mot_de_passe'];
  $mot_de_passe_confirm = $_POST['mot_de_passe_confirm'];

  if ($mot_de_passe !== $mot_de_passe_confirm) {
    echo "<script>alert('Les mots de passe ne correspondent pas.');</script>";
  } else {
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    // Upload photo
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
      $photo = uniqid() . '_' . $_FILES['photo']['name'];
      move_uploaded_file($_FILES['photo']['tmp_name'], "../assets/img/profiles/" . $photo);
    }

    $stmt = $pdo->prepare("INSERT INTO users (nom, post_nom, email, fonction, role, superviseur_id, mot_de_passe, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nom, $post_nom, $email, $fonction, $role, $superviseur_id, $mot_de_passe_hash, $photo]);

    header('Location: users.php');
    exit;
  }
}

include('../includes/header.php');
?>

<style>
.page-header-user {
  background: linear-gradient(135deg, #3D74B9 0%, #5a8fd4 100%);
  color: white;
  padding: 2rem;
  border-radius: 15px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 15px rgba(61, 116, 185, 0.2);
}

.info-card {
  background: linear-gradient(135deg, rgba(61, 116, 185, 0.08) 0%, rgba(61, 116, 185, 0.03) 100%);
  border-left: 4px solid #3D74B9;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  border: 1px solid rgba(61, 116, 185, 0.15);
}

.info-card h6 {
  color: #3D74B9;
  font-weight: 700;
  margin-bottom: 1rem;
  font-size: 1rem;
}

.info-card p {
  font-size: 0.875rem;
  color: #495057;
  margin-bottom: 8px;
  line-height: 1.6;
}

.info-card .badge {
  font-size: 0.7rem;
  padding: 0.35rem 0.65rem;
  font-weight: 600;
}

.form-section {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  border: 2px solid #e9ecef;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.form-label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.form-label i {
  color: #3D74B9;
}

.form-control, .form-select {
  border-radius: 10px;
  border: 2px solid #e9ecef;
  padding: 0.75rem 1rem;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
  border-color: #3D74B9;
  box-shadow: 0 0 0 0.25rem rgba(61, 116, 185, 0.1);
  transform: translateY(-1px);
}

.input-group-text {
  background: white;
  border: 2px solid #e9ecef;
  border-left: none;
  transition: all 0.3s ease;
}

.input-group:focus-within .input-group-text {
  border-color: #3D74B9;
}

.photo-preview-container {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1rem;
  background: linear-gradient(135deg, rgba(61, 116, 185, 0.05) 0%, rgba(61, 116, 185, 0.02) 100%);
  border-radius: 12px;
  border: 2px dashed #dee2e6;
}

.photo-preview-container img {
  border: 3px solid #3D74B9;
  box-shadow: 0 4px 12px rgba(61, 116, 185, 0.2);
}

.btn-primary-custom {
  background: linear-gradient(135deg, #3D74B9 0%, #2a5a94 100%);
  border: none;
  border-radius: 10px;
  padding: 0.875rem 2rem;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(61, 116, 185, 0.3);
  color: white !important;
}

.btn-primary-custom:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(61, 116, 185, 0.4);
  color: white !important;
}

.btn-cancel-custom {
  border: 2px solid #6c757d;
  border-radius: 10px;
  padding: 0.875rem 2rem;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-cancel-custom:hover {
  background: #6c757d;
  color: white;
  transform: translateY(-2px);
}

.form-field-description {
  font-size: 0.8rem;
  color: #6c757d;
  margin-top: 0.35rem;
  display: flex;
  align-items-center;
  gap: 0.5rem;
}

.form-field-description i {
  font-size: 0.75rem;
}
</style>

<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-md-9" style="padding-left: 8rem;">
    
    <!-- En-tête avec gradient -->
    <div class="page-header-user">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-2">
            <i class="bi bi-person-plus-fill me-2"></i>Ajouter un nouvel utilisateur
          </h4>
          <p class="mb-0 opacity-90">Créez un compte pour un nouveau membre du personnel </p>
        </div>
        <div>
          <a href="users.php" class="btn btn-light px-4" style="border-radius: 25px; font-weight: 600;">
            <i class="bi bi-arrow-left me-2"></i>Retour
          </a>
        </div>
      </div>
    </div>

    <div class="form-section">
        <form method="POST" enctype="multipart/form-data" autocomplete="off">
          <!-- Informations d'aide -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="info-card">
                <h6><i class="bi bi-lightbulb-fill me-2"></i>Guide de remplissage du formulaire</h6>
                <div class="row">
                  <div class="col-md-4 mb-2">
                    <p class="mb-1"><i class="bi bi-briefcase-fill me-2 text-primary"></i><strong>Fonction</strong></p>
                    <small class="text-muted ps-4">Poste ou titre de l'employé<br>(ex: Responsable IT, Comptable)</small>
                  </div>
                  <div class="col-md-8 mb-2">
                    <p class="mb-2"><i class="bi bi-shield-fill-check me-2 text-primary"></i><strong>Rôles système disponibles</strong></p>
                    <div class="ps-4">
                      <div class="row g-2">
                        <div class="col-md-6">
                          <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-primary" style="min-width: 85px;">Staff</span>
                            <small class="text-muted">Employé standard, accès aux propres objectifs et auto-évaluations</small>
                          </div>
                          <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-info" style="min-width: 85px;">Superviseur</span>
                            <small class="text-muted">Peut évaluer les agents sous sa supervision</small>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-warning text-dark" style="min-width: 85px;">Coordination</span>
                            <small class="text-muted">Validation finale des évaluations, accès complet</small>
                          </div>
                          <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-danger" style="min-width: 85px;">Admin</span>
                            <small class="text-muted">Accès total au système, gestion des utilisateurs</small>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4 mb-2">
                    <p class="mb-1"><i class="bi bi-person-check-fill me-2 text-primary"></i><strong>Superviseur</strong></p>
                    <small class="text-muted ps-4">Personne qui évaluera les performances<br>(requis pour les staff)</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Nom, Post-nom, Prénom -->
            <div class="col-md-4 mb-3">
              <label class="form-label">Nom</label>
              <input type="text" name="nom" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Post-nom</label>
              <input type="text" name="post_nom" class="form-control" required>
            </div>

            <!-- Email et Fonction -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Fonction</label>
              <input type="text" name="fonction" class="form-control" required>
            </div>

            <!-- Mot de passe + confirmation -->
            <div class="col-md-6 mb-3">
              <label class="form-label">
                <i class="bi bi-shield-lock me-1"></i> Mot de passe
              </label>
              <div class="input-group">
                <input type="password" name="mot_de_passe" id="mot_de_passe" 
                       class="form-control" required 
                       autocomplete="new-password"
                       style="border-radius: 10px 0 0 10px;">
                <span class="input-group-text" style="cursor: pointer; border-radius: 0 10px 10px 0;" 
                      onclick="togglePassword('mot_de_passe', this)">
                  <i class="bi bi-eye-fill"></i>
                </span>
              </div>
              <div class="form-field-description">
                <i class="bi bi-info-circle-fill text-primary"></i>
                <span>Minimum 6 caractères recommandés</span>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">
                <i class="bi bi-shield-check me-1"></i> Confirmer le mot de passe
              </label>
              <div class="input-group">
                <input type="password" name="mot_de_passe_confirm" id="mot_de_passe_confirm" 
                       class="form-control" required 
                       autocomplete="new-password"
                       style="border-radius: 10px 0 0 10px;">
                <span class="input-group-text" style="cursor: pointer; border-radius: 0 10px 10px 0;" 
                      onclick="togglePassword('mot_de_passe_confirm', this)">
                  <i class="bi bi-eye-fill"></i>
                </span>
              </div>
              <div class="form-field-description">
                <i class="bi bi-info-circle-fill text-primary"></i>
                <span>Doit correspondre au mot de passe</span>
              </div>
            </div>

            <!-- Rôle et Superviseur -->
            <div class="col-md-6 mb-3">
              <label class="form-label">
                <i class="bi bi-person-badge me-1"></i> Rôle
              </label>
              <select name="role" id="role" class="form-select" required>
                <option value="">-- Sélectionnez un rôle --</option>
                <option value="staff">Staff</option>
                <option value="superviseur">Superviseur</option>
                <option value="coordination">Coordination</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">
                <i class="bi bi-person-check me-1"></i> Superviseur
              </label>
              <select name="superviseur_id" class="form-select">
                <option value="">-- Aucun superviseur --</option>
                <?php
                $current_id = $_SESSION['user_id'] ?? null;
                $sql = "SELECT id, nom, post_nom FROM users WHERE role != 'admin'";
                if ($current_id) $sql .= " AND id != " . intval($current_id);
                $sql .= " ORDER BY nom, post_nom";
                $superviseurs = $pdo->query($sql);
                while ($s = $superviseurs->fetch()) {
                  echo "<option value='{$s['id']}'>" . htmlspecialchars($s['nom'] . ' ' . $s['post_nom']) . "</option>";
                }
                ?>
              </select>
            </div>

            <!-- Photo de profil -->
            <div class="col-md-12 mb-4">
              <label class="form-label">
                <i class="bi bi-image me-1"></i> Photo de profil (optionnel)
              </label>
              <div class="photo-preview-container">
                <img id="photo-preview" src="../assets/img/profiles/default.png" 
                     width="100" height="100" 
                     class="rounded-circle" 
                     style="object-fit: cover;">
                <div class="flex-grow-1">
                  <input type="file" name="photo" class="form-control" accept="image/*" 
                         onchange="previewImage(event)">
                  <div class="form-field-description mt-2">
                    <i class="bi bi-info-circle-fill text-primary"></i>
                    <span>Formats acceptés : JPG, PNG • Taille max : 2 MB</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Boutons d'action -->
          <div class="border-top pt-4 mt-4">
            <div class="d-flex gap-3 justify-content-end">
              <a href="users.php" class="btn btn-outline-secondary btn-cancel-custom">
                <i class="bi bi-x-circle me-2"></i> Annuler
              </a>
              <button type="submit" class="btn btn-primary-custom">
                <i class="bi bi-save me-2"></i> Enregistrer l'utilisateur
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function previewImage(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function() {
      document.getElementById('photo-preview').src = reader.result;
    };
    reader.readAsDataURL(file);
  }
}

function togglePassword(inputId, toggleButton) {
  const input = document.getElementById(inputId);
  const icon = toggleButton.querySelector('i');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('bi-eye-fill');
    icon.classList.add('bi-eye-slash-fill');
  } else {
    input.type = 'password';
    icon.classList.remove('bi-eye-slash-fill');
    icon.classList.add('bi-eye-fill');
  }
}

// Validation du mot de passe en temps réel
document.addEventListener('DOMContentLoaded', function() {
  const password = document.getElementById('mot_de_passe');
  const confirmPassword = document.getElementById('mot_de_passe_confirm');
  
  confirmPassword.addEventListener('input', function() {
    if (this.value && this.value !== password.value) {
      this.setCustomValidity('Les mots de passe ne correspondent pas');
      this.classList.add('is-invalid');
    } else {
      this.setCustomValidity('');
      this.classList.remove('is-invalid');
    }
  });
  
  password.addEventListener('input', function() {
    if (confirmPassword.value && confirmPassword.value !== this.value) {
      confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
      confirmPassword.classList.add('is-invalid');
    } else {
      confirmPassword.setCustomValidity('');
      confirmPassword.classList.remove('is-invalid');
    }
  });
});
</script>

<script src="../assets/js/main.js"></script>
<?php include('../includes/footer.php'); ?>
