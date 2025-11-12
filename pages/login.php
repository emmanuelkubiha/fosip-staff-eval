<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

$error = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  // Requête préparée pour éviter les injections SQL
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  // Vérification des identifiants
  if (!$user) {
    $error = "Adresse email introuvable.";
  } elseif (!password_verify($password, $user['mot_de_passe'])) {
    $error = "Mot de passe incorrect.";
  } else {
    // Connexion réussie : on stocke les infos en session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nom'] = $user['nom'];
    $_SESSION['photo'] = $user['photo'] ?? null;
    $_SESSION['role'] = $user['role'];

    // Redirection vers le tableau de bord
    header('Location: dashboard.php');
    exit;
  }
}

// Inclure le header SEULEMENT après le traitement POST
include('../includes/header.php');
?>

<!-- Page de connexion modernisée -->
<div class="auth-wrapper d-flex align-items-center justify-content-center py-5">
  <div class="auth-card shadow-lg">
    <div class="auth-brand mb-4 text-center">
      <div class="logo-circle mb-3"><i class="bi bi-shield-lock"></i></div>
      <h1 class="h5 mb-0">Connexion</h1>
      <p class="text-muted small mb-0">Accédez à votre espace d'évaluation</p>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
      </div>
    <?php endif; ?>
    <form method="post" class="auth-form" autocomplete="off">
      <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-uppercase">Adresse email</label>
        <div class="input-with-icon">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" id="email" class="form-control" placeholder="exemple@domaine.org" required autofocus>
        </div>
      </div>
      <div class="mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <label for="password" class="form-label small fw-semibold text-uppercase mb-0">Mot de passe</label>
          <button type="button" class="btn btn-link p-0 small text-decoration-none" id="togglePwd" data-target="password">Afficher</button>
        </div>
        <div class="input-with-icon">
          <i class="bi bi-lock"></i>
          <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="form-check small">
          <input class="form-check-input" type="checkbox" value="1" id="remember">
          <label class="form-check-label" for="remember">Se souvenir</label>
        </div>
        <a href="aide.php" class="small text-decoration-none">Aide ?</a>
      </div>
      <button type="submit" class="btn btn-fosip w-100 py-2 fw-semibold">
        <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
      </button>
      <div class="mt-3 small text-muted text-center">© <?= date('Y') ?> FOSIP – Accès restreint</div>
    </form>
  </div>
</div>

<!-- Script pour afficher/masquer le mot de passe -->
<script>
// Fonction pour basculer l'affichage du mot de passe
const toggleBtn = document.getElementById('togglePwd');
if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    const target = document.getElementById(toggleBtn.dataset.target);
    if (!target) return;
    if (target.type === 'password') { target.type='text'; toggleBtn.textContent='Masquer'; }
    else { target.type='password'; toggleBtn.textContent='Afficher'; }
  });
}
</script>

<?php include('../includes/footer.php'); ?>
