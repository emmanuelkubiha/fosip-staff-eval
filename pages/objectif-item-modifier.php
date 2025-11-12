<?php
// pages/objectif-item-modifier.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}
$user_id = $_SESSION['user_id'];
$item_id = intval($_POST['item_id'] ?? 0);
$contenu = trim($_POST['contenu'] ?? '');

if ($item_id <= 0 || $contenu === '') {
  header('Location: FICHES-EVALUATION-VOIR.php');
  exit;
}

// Charger item + fiche
$stmt = $pdo->prepare("SELECT i.*, o.user_id, o.periode FROM objectifs_items i JOIN objectifs o ON i.fiche_id = o.id WHERE i.id = ?");
$stmt->execute([$item_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  header('Location: FICHES-EVALUATION-VOIR.php');
  exit;
}
$fiche_id = $row['fiche_id'];
if ($row['user_id'] != $user_id) {
  header("Location: fiche-evaluation.php?id=$fiche_id");
  exit;
}

// Vérifier verrou
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $row['periode']]);
$verrou_superviseur = $stmtEval->fetchColumn() > 0;
$stmtCom = $pdo->prepare("SELECT COUNT(*) FROM coordination_commentaires WHERE fiche_id = ?");
$stmtCom->execute([$fiche_id]);
$verrou_coordination = $stmtCom->fetchColumn() > 0;
if ($verrou_superviseur || $verrou_coordination) {
  header("Location: fiches-evaluation.php?id=$fiche_id");
  exit;
}

// Mise à jour
$stmtUp = $pdo->prepare("UPDATE objectifs_items SET contenu = ? WHERE id = ?");
$stmtUp->execute([$contenu, $item_id]);

header("Location: fiche-evaluation.php?id=$fiche_id");
exit;
