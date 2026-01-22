<?php
// pages/objectif-item-ajouter.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$fiche_id = intval($_POST['fiche_id'] ?? 0);
$contenu = trim($_POST['contenu'] ?? '');

if ($fiche_id <= 0 || $contenu === '') {
  header("Location: fiche-evaluation.php?id=$fiche_id");
  exit;
}

// Vérifier que la fiche appartient à l'utilisateur et n'est pas verrouillée
$stmt = $pdo->prepare("SELECT user_id FROM objectifs WHERE id = ?");
$stmt->execute([$fiche_id]);
$owner = $stmt->fetchColumn();
if (!$owner || $owner != $user_id) {
  header("Location: FICHES-EVALUATION-VOIR.php");
  exit;
}

// Vérifier verrou (supervision/coordination)
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $fiche_id ? $pdo->query("SELECT periode FROM objectifs WHERE id = $fiche_id")->fetchColumn() : '']);
$verrou_superviseur = $stmtEval->fetchColumn() > 0;

$stmtCom = $pdo->prepare("SELECT COUNT(*) FROM coordination_commentaires WHERE fiche_id = ?");
$stmtCom->execute([$fiche_id]);
$verrou_coordination = $stmtCom->fetchColumn() > 0;

if ($verrou_superviseur || $verrou_coordination) {
  // Bloqué
  header("Location: fiche-evaluation.php?id=$fiche_id");
  exit;
}

// Trouver ordre suivant
$stmtOrd = $pdo->prepare("SELECT COALESCE(MAX(ordre),0) + 1 FROM objectifs_items WHERE fiche_id = ?");
$stmtOrd->execute([$fiche_id]);
$ordre = $stmtOrd->fetchColumn();

// Insertion
$stmtIns = $pdo->prepare("INSERT INTO objectifs_items (fiche_id, contenu, ordre) VALUES (?, ?, ?)");
$stmtIns->execute([$fiche_id, $contenu, $ordre]);

header("Location: fiche-evaluation.php?id=$fiche_id");
exit;
