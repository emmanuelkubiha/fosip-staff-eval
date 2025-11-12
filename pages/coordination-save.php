<?php
// coordination-save.php
// Endpoint AJAX pour charger / sauvegarder le commentaire coordination final.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'coordination') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Non autorisé']);
  exit;
}

// Helpers
function tableExists(PDO $pdo, string $table): bool {
  try { $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}
if (!tableExists($pdo,'coordination_commentaires')) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Table coordination_commentaires absente']);
  exit;
}

$coord_user_id = (int)$_SESSION['user_id'];
$csrfSession = $_SESSION['csrf_token'] ?? '';

// Chargement
if (isset($_GET['load']) && $_GET['load'] == '1') {
  $fiche_id = (int)($_GET['fiche_id'] ?? 0);
  if ($fiche_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Fiche invalide']); exit; }
  $st = $pdo->prepare('SELECT commentaire FROM coordination_commentaires WHERE fiche_id = ? LIMIT 1');
  $st->execute([$fiche_id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'commentaire'=> $row['commentaire'] ?? '']);
  exit;
}

// Sauvegarde
$action = $_POST['action'] ?? ''; if (!in_array($action,['save','delete'],true)) { echo json_encode(['ok'=>false,'error'=>'Action invalide']); exit; }
$fiche_id = (int)($_POST['fiche_id'] ?? 0);
$commentaire = trim((string)($_POST['commentaire'] ?? ''));
$csrf = $_POST['csrf_token'] ?? '';
if ($fiche_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Fiche manquante']); exit; }
if ($csrf === '' || $csrf !== $csrfSession) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF invalide']); exit; }
if ($action === 'save' && $commentaire === '') { echo json_encode(['ok'=>false,'error'=>'Commentaire vide']); exit; }

try {
  // Démarrer une transaction pour garantir la cohérence
  $pdo->beginTransaction();
  
  if ($action === 'save') {
    // Vérifier si existe déjà
    $st = $pdo->prepare('SELECT id FROM coordination_commentaires WHERE fiche_id = ? LIMIT 1');
    $st->execute([$fiche_id]);
    $existing = $st->fetchColumn();
    if ($existing) {
      $up = $pdo->prepare('UPDATE coordination_commentaires SET commentaire = ?, coord_id = ?, date_commentaire = CURRENT_DATE WHERE id = ?');
      $up->execute([$commentaire, $coord_user_id, $existing]);
    } else {
      $supId = null;
      try {
        $q = $pdo->prepare('SELECT superviseur_id FROM objectifs WHERE id = ?');
        $q->execute([$fiche_id]);
        $supId = $q->fetchColumn();
        if ($supId !== null) { $supId = (int)$supId; }
      } catch (Throwable $e) { $supId = null; }
      $ins = $pdo->prepare('INSERT INTO coordination_commentaires (fiche_id, commentaire, coord_id, supervise_id, date_commentaire) VALUES (?, ?, ?, ?, CURRENT_DATE)');
      $ins->execute([$fiche_id, $commentaire, $coord_user_id, $supId]);
    }
    // statut termine
    $stUpdateStatut = $pdo->prepare('UPDATE objectifs SET statut = ?, updated_at = NOW() WHERE id = ?');
  $stUpdateStatut->execute(['termine', $fiche_id]);
  $pdo->commit();
  echo json_encode(['ok'=>true,'status'=> $existing ? 'updated' : 'added', 'fiche_id' => $fiche_id]);
  } elseif ($action === 'delete') {
    // Suppression du commentaire et retour statut evalue
    $del = $pdo->prepare('DELETE FROM coordination_commentaires WHERE fiche_id = ?');
    $del->execute([$fiche_id]);
    $stUpdateStatut = $pdo->prepare('UPDATE objectifs SET statut = ?, updated_at = NOW() WHERE id = ?');
    $stUpdateStatut->execute(['evalue', $fiche_id]);
    $pdo->commit();
    echo json_encode(['ok'=>true,'message'=>'Commentaire supprimé']);
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erreur serveur: ' . $e->getMessage()]);
}
