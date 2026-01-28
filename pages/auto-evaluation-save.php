<?php
// pages/auto-evaluation-save.php
// Endpoint create/update/delete auto_evaluation (POST). Retour JSON.
// Attendu POST: action=save|delete, fiche_id, item_id, note, commentaire, csrf_token

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once('../includes/db.php');

// helpers
function jsonError($msg, $code = 400) { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
function ensureCsrf($token) { return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token); }

// auth + method
if (empty($_SESSION['user_id'])) jsonError('Non autorisé', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Méthode non autorisée', 405);
// Sécurité : on ignore tout user_id transmis par le client, on prend toujours celui de la session
$user_id = (int)$_SESSION['user_id'];

$action = $_POST['action'] ?? 'save';
$csrf = $_POST['csrf_token'] ?? '';
if (!ensureCsrf($csrf)) jsonError('Token CSRF invalide', 403);

$fiche_id = isset($_POST['fiche_id']) ? (int)$_POST['fiche_id'] : 0;
$item_id  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
if ($fiche_id <= 0 || $item_id <= 0) jsonError('fiche_id et item_id requis', 400);

// verify fiche belongs to user
try {
  $st = $pdo->prepare("SELECT id FROM objectifs WHERE id = ? AND user_id = ? LIMIT 1");
  $st->execute([$fiche_id, $user_id]);
  if (!$st->fetchColumn()) jsonError('Fiche introuvable ou non autorisée', 403);
} catch (Throwable $e) { jsonError('Erreur serveur (vérif fiche)', 500); }

// verify item belongs to fiche
try {
  $st = $pdo->prepare("SELECT id FROM objectifs_items WHERE id = ? AND fiche_id = ? LIMIT 1");
  $st->execute([$item_id, $fiche_id]);
  if (!$st->fetchColumn()) jsonError('Item non trouvé pour cette fiche', 400);
} catch (Throwable $e) { jsonError('Erreur serveur (vérif item)', 500); }

if ($action === 'delete') {
  try {
    $del = $pdo->prepare("DELETE FROM auto_evaluation WHERE fiche_id = ? AND item_id = ? AND user_id = ?");
    $del->execute([$fiche_id, $item_id, $user_id]);
    echo json_encode(['ok'=>true,'message'=>'Auto-évaluation supprimée']);
    exit;
  } catch (Throwable $e) { jsonError('Erreur suppression', 500); }
}

// save
$note = $_POST['note'] ?? '';
$commentaire = trim((string)($_POST['commentaire'] ?? ''));
$allowed = ['non_atteint','atteint','depasse'];
if (!in_array($note, $allowed, true)) jsonError('Note invalide', 400);

try {
  // existe ?
  $st = $pdo->prepare("SELECT id FROM auto_evaluation WHERE fiche_id = ? AND item_id = ? AND user_id = ? LIMIT 1");
  $st->execute([$fiche_id, $item_id, $user_id]);
  $existing = $st->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
    $up = $pdo->prepare("UPDATE auto_evaluation SET note = ?, commentaire = ?, updated_at = NOW() WHERE id = ?");
    $up->execute([$note, $commentaire === '' ? null : $commentaire, (int)$existing['id']]);
    echo json_encode(['ok'=>true,'message'=>'Auto-évaluation mise à jour','data'=>['id'=>(int)$existing['id'],'note'=>$note,'commentaire'=>$commentaire]]);
    exit;
  } else {
    $ins = $pdo->prepare("INSERT INTO auto_evaluation (fiche_id, item_id, user_id, note, commentaire, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $ins->execute([$fiche_id, $item_id, $user_id, $note, $commentaire === '' ? null : $commentaire]);
    $newId = (int)$pdo->lastInsertId();
    echo json_encode(['ok'=>true,'message'=>'Auto-évaluation enregistrée','data'=>['id'=>$newId,'note'=>$note,'commentaire'=>$commentaire]]);
    exit;
  }
} catch (Throwable $e) {
  jsonError('Erreur lors de la sauvegarde: '.$e->getMessage(), 500);
}
