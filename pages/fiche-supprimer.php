<?php
// Suppression d'une fiche d'évaluation (objectifs) complète
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non authentifié']);
  exit;
}
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Méthode non autorisée']);
  exit;
}

$fiche_id = isset($_POST['fiche_id']) ? intval($_POST['fiche_id']) : 0;
if ($fiche_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Identifiant de fiche invalide.']);
  exit;
}

// Vérifier que la fiche appartient à l'utilisateur
$stmt = $pdo->prepare('SELECT id, user_id FROM objectifs WHERE id = ?');
$stmt->execute([$fiche_id]);
$fiche = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fiche || (int)$fiche['user_id'] !== $user_id) {
  http_response_code(403);
  echo json_encode(['error' => 'Non autorisé à supprimer cette fiche.']);
  exit;
}

// Vérifier verrou (supervision, coordination, etc. comme dans fiche-evaluation-save)
$verrou = false;
try {
  $st = $pdo->prepare('SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = (SELECT periode FROM objectifs WHERE id = ?) AND statut != "en attente"');
  $st->execute([$user_id, $fiche_id]);
  $verrou = $st->fetchColumn() > 0;
} catch (Throwable $e) { $verrou = false; }
if ($verrou) {
  http_response_code(423);
  echo json_encode(['error' => 'Fiche verrouillée (supervision commencée).']);
  exit;
}

try {
  $pdo->beginTransaction();
  // Suppression en cascade (objectifs_items, auto_evaluation, etc. si ON DELETE CASCADE en base)
  $del = $pdo->prepare('DELETE FROM objectifs WHERE id = ?');
  $del->execute([$fiche_id]);
  $pdo->commit();
  echo json_encode(['ok' => true, 'message' => 'Fiche supprimée']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur lors de la suppression']);
}
