<?php
/**
 * pages/supervision-save.php
 * -------------------------------------------------------------
 * OBJECTIF
 *   Fournit un endpoint JSON pour :
 *     - Charger une supervision existante (GET ?load=1&fiche_id=...)
 *     - Créer / Mettre à jour une supervision (POST)
 *
 * SÉCURITÉ
 *   - Accès limité au rôle 'superviseur'.
 *   - Vérification CSRF sur POST.
 *   - Vérifie que l'agent de la fiche est bien rattaché au superviseur.
 *
 * FONCTIONNEMENT
 *   - Une supervision est identifiée par (agent_id, superviseur_id, periode).
 *   - Note forcée à 0 si non fournie (schema NOT NULL).
 *   - Passage du statut 'encours' à 'complet' fixe date_validation.
 * -------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once('../includes/db.php');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) {
  echo json_encode(['ok'=>false,'error'=>'Accès refusé']); exit;
}
$superviseur_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf_session = $_SESSION['csrf_token'];

$method = $_SERVER['REQUEST_METHOD'];

function respond($arr){ echo json_encode($arr); exit; }

// MODE CHARGEMENT (pré-remplissage modal) ----------------------
if ($method === 'GET' && isset($_GET['load'])) {
  $fiche_id = (int)($_GET['fiche_id'] ?? 0);
  if ($fiche_id <= 0) respond(['ok'=>false,'error'=>'Fiche invalide']);
  // Récupérer fiche pour agent et période
  $st = $pdo->prepare('SELECT o.id AS fiche_id, o.periode, o.user_id AS agent_id, s.id AS supervision_id, s.note, s.commentaire, s.statut FROM objectifs o LEFT JOIN supervisions s ON s.agent_id = o.user_id AND s.superviseur_id = :sid AND s.periode = o.periode WHERE o.id = :fid');
  $st->execute([':sid'=>$superviseur_id, ':fid'=>$fiche_id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) respond(['ok'=>false,'error'=>'Fiche introuvable']);
  if ($row['note'] === null && $row['commentaire'] === null && $row['statut'] === null) {
    respond(['ok'=>true,'empty'=>true]);
  }
  respond(['ok'=>true,'note'=>$row['note'],'commentaire'=>$row['commentaire'],'statut'=>$row['statut']]);
}

// MODE ENREGISTREMENT -----------------------------------------
if ($method === 'POST') {
  $csrf_post = $_POST['csrf_token'] ?? '';
  if (!hash_equals($csrf_session, $csrf_post)) respond(['ok'=>false,'error'=>'Jeton CSRF invalide']);

  $fiche_id = (int)($_POST['fiche_id'] ?? 0);
  $note = trim((string)($_POST['note'] ?? ''));
  $commentaire = trim((string)($_POST['commentaire'] ?? ''));
  $statut = trim((string)($_POST['statut'] ?? 'encours'));

  if ($fiche_id <= 0) respond(['ok'=>false,'error'=>'Fiche invalide']);
  if ($statut !== 'encours' && $statut !== 'complet') respond(['ok'=>false,'error'=>'Statut non reconnu']);
  if ($note !== '') {
    if (!ctype_digit($note)) respond(['ok'=>false,'error'=>'Note doit être un entier']);
    $noteInt = (int)$note; if ($noteInt < 0 || $noteInt > 100) respond(['ok'=>false,'error'=>'Note hors limite (0-100)']);
  } else { $noteInt = 0; }

  // Trouver agent + période à partir de la fiche (évite de faire confiance au client)
  $stF = $pdo->prepare('SELECT o.user_id AS agent_id, o.periode FROM objectifs o WHERE o.id = :fid');
  $stF->execute([':fid'=>$fiche_id]);
  $fiche = $stF->fetch(PDO::FETCH_ASSOC);
  if (!$fiche) respond(['ok'=>false,'error'=>'Fiche introuvable']);
  $agent_id = (int)$fiche['agent_id'];
  $periode = $fiche['periode'];

  // Vérifier l'appartenance de l'agent au superviseur courant
  $stAuth = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :aid AND superviseur_id = :sid');
  $stAuth->execute([':aid'=>$agent_id, ':sid'=>$superviseur_id]);
  if (!$stAuth->fetchColumn()) respond(['ok'=>false,'error'=>'Vous ne pouvez pas évaluer cette fiche']);

  // Vérifier si une supervision existe déjà pour la combinaison (agent, superviseur, période)
  $stCheck = $pdo->prepare('SELECT id FROM supervisions WHERE agent_id = :aid AND superviseur_id = :sid AND periode = :p LIMIT 1');
  $stCheck->execute([':aid'=>$agent_id, ':sid'=>$superviseur_id, ':p'=>$periode]);
  $existing = $stCheck->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
  // Mise à jour : date_validation ajustée uniquement si statut devient 'complet'
  $sqlUp = 'UPDATE supervisions SET note = :note, commentaire = :commentaire, statut = :statut, date_validation = CASE WHEN :statut = "complet" THEN NOW() ELSE date_validation END WHERE id = :id';
    $stUp = $pdo->prepare($sqlUp);
  $stUp->bindValue(':note', $noteInt, PDO::PARAM_INT);
    $stUp->bindValue(':commentaire', $commentaire === '' ? null : $commentaire, $commentaire === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stUp->bindValue(':statut', $statut);
    $stUp->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
    $stUp->execute();
    respond(['ok'=>true,'message'=>'Supervision mise à jour']);
  } else {
  // Insertion : si statut déjà 'complet' on enregistre date_validation directement
  $sqlIns = 'INSERT INTO supervisions (agent_id, superviseur_id, periode, note, commentaire, statut, date_validation) VALUES (:aid, :sid, :p, :note, :commentaire, :statut, CASE WHEN :statut = "complet" THEN NOW() ELSE NULL END)';
    $stIns = $pdo->prepare($sqlIns);
    $stIns->bindValue(':aid', $agent_id, PDO::PARAM_INT);
    $stIns->bindValue(':sid', $superviseur_id, PDO::PARAM_INT);
    $stIns->bindValue(':p', $periode, PDO::PARAM_STR);
  $stIns->bindValue(':note', $noteInt, PDO::PARAM_INT);
    $stIns->bindValue(':commentaire', $commentaire === '' ? null : $commentaire, $commentaire === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stIns->bindValue(':statut', $statut, PDO::PARAM_STR);
    $stIns->execute();
    respond(['ok'=>true,'message'=>'Supervision enregistrée']);
  }
}

respond(['ok'=>false,'error'=>'Requête non supportée']);
