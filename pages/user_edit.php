<?php
include('../includes/auth.php');
include('../includes/db.php');


// Récupère l'ID depuis l'URL
$id = $_GET['id'] ?? null;
if (!$id) {
  header('Location: users.php');
  exit;
}

// Récupère les infos de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
  echo "<div class='alert alert-danger'>Utilisateur introuvable.</div>";
  include('../includes/header.php');
  include('../includes/footer.php');
  exit;
}


// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom = $_POST['nom'];
  $post_nom = $_POST['post_nom'];
  $email = $_POST['email'];
  $fonction = $_POST['fonction'];
  $role = $_POST['role'];
  $superviseur_id = $_POST['superviseur_id'] ?: null;

  // Mot de passe (optionnel)
  $mot_de_passe = $_POST['mot_de_passe'];
  $mot_de_passe_confirm = $_POST['mot_de_passe_confirm'];
  $mot_de_passe_hash = $user['mot_de_passe'];

  if ($mot_de_passe && $mot_de_passe === $mot_de_passe_confirm) {
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
  }

  // Photo (optionnelle)
  $photo = $user['photo'];
  if (!empty($_FILES['photo']['name'])) {
    $photo = uniqid() . '_' . $_FILES['photo']['name'];
    move_uploaded_file($_FILES['photo']['tmp_name'], "../assets/img/profiles/" . $photo);
  }

  // Mise à jour
  $stmt = $pdo->prepare("UPDATE users SET nom=?, post_nom=?, email=?, fonction=?, role=?, superviseur_id=?, mot_de_passe=?, photo=? WHERE id=?");
  $stmt->execute([$nom, $post_nom, $email, $fonction, $role, $superviseur_id, $mot_de_passe_hash, $photo, $id]);

  header('Location: users.php');
  exit;
}

include('../includes/header.php');
?>

<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-md-9">
    <div class="card shadow-sm">
      <div class="card-header">
        <i class="bi bi-pencil-square me-2"></i> Modifier l’utilisateur
      </div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <div class="row">
            <!-- Nom et Post-nom -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Nom</label>
              <input type="text" name="nom" class="form-control" value="<?= $user['nom'] ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Post-nom</label>
              <input type="text" name="post_nom" class="form-control" value="<?= $user['post_nom'] ?>" required>
            </div>

            <!-- Email et Fonction -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= $user['email'] ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Fonction</label>
              <input type="text" name="fonction" class="form-control" value="<?= $user['fonction'] ?>" required>
            </div>

            <!-- Mot de passe (optionnel) -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Nouveau mot de passe</label>
              <div class="input-group">
                <input type="password" name="mot_de_passe" id="mot_de_passe" class="form-control">
                <span class="input-group-text" onclick="togglePassword('mot_de_passe', this)">
                  <i class="bi bi-eye-fill"></i>
                </span>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Confirmer le mot de passe</label>
              <div class="input-group">
                <input type="password" name="mot_de_passe_confirm" id="mot_de_passe_confirm" class="form-control">
                <span class="input-group-text" onclick="togglePassword('mot_de_passe_confirm', this)">
                  <i class="bi bi-eye-fill"></i>
                </span>
              </div>
            </div>

            <!-- Rôle et Superviseur -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Rôle</label>
              <select name="role" class="form-select" required>
                <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                <option value="superviseur" <?= $user['role'] === 'superviseur' ? 'selected' : '' ?>>Superviseur</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="coordination" <?= $user['role'] === 'coordination' ? 'selected' : '' ?>>Coordination</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Superviseur</label>
              <select name="superviseur_id" class="form-select">
                <option value="">-- Aucun --</option>
                <?php
                $current_id = $_SESSION['user_id'] ?? null;
                $sql = "SELECT id, nom, post_nom FROM users WHERE role != 'admin'";
                if ($current_id) $sql .= " AND id != " . intval($current_id);
                $sql .= " ORDER BY nom, post_nom";
                $superviseurs = $pdo->query($sql);
                while ($s = $superviseurs->fetch()) {
                  $selected = $user['superviseur_id'] == $s['id'] ? 'selected' : '';
                  echo "<option value='{$s['id']}' $selected>" . htmlspecialchars($s['nom'] . ' ' . $s['post_nom']) . "</option>";
                }
                ?>
              </select>
            </div>

            <!-- Photo de profil + prévisualisation -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Photo de profil</label><br>
              <img id="photo-preview" src="../assets/img/profiles/<?= $user['photo'] ?? 'default.png' ?>" width="60" height="60" class="rounded-circle mb-2">
              <input type="file" name="photo" class="form-control">
            </div>
          </div>

          <button type="submit" class="btn btn-fosip">
            <i class="bi bi-save me-1"></i> Enregistrer les modifications
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<?php include('../includes/footer.php'); ?>
