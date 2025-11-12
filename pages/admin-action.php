<?php
// pages/admin-action.php
// Endpoint d'actions sensibles admin: suppression de fiche complète, suppression du commentaire coordination
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Non autorisé']);
  exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf === '' || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'CSRF invalide']);
  exit;
}

$action = $_POST['action'] ?? '';
$fiche_id = (int)($_POST['fiche_id'] ?? 0);
$password = (string)($_POST['password'] ?? '');
if ($fiche_id <= 0 || $action === '') { echo json_encode(['ok'=>false,'error'=>'Paramètres manquants']); exit; }

// Vérifier mot de passe admin
try {
  $st = $pdo->prepare('SELECT mot_de_passe FROM users WHERE id=? AND role = "admin" LIMIT 1');
  $st->execute([ (int)$_SESSION['user_id'] ]);
  $hash = $st->fetchColumn();
  if (!$hash || !password_verify($password, $hash)) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Mot de passe incorrect']);
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erreur authentification']);
  exit;
}

function tableExists(PDO $pdo, string $table): bool {
  try { $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch (Throwable $e) { return false; }
}

try {
  $pdo->beginTransaction();
  if ($action === 'delete_coord_comment') {
    if (tableExists($pdo,'coordination_commentaires')) {
      $del = $pdo->prepare('DELETE FROM coordination_commentaires WHERE fiche_id = ?');
      $del->execute([$fiche_id]);
    }
    $upd = $pdo->prepare('UPDATE objectifs SET statut = ?, updated_at = NOW() WHERE id = ?');
    $upd->execute(['evalue', $fiche_id]);
    $pdo->commit();
    echo json_encode(['ok'=>true,'message'=>'Commentaire coordination supprimé']);
    exit;
  }
  if ($action === 'delete_fiche') {
    // Récupérer user_id et periode pour supprimer supervisions liées
    $ficheInfo = $pdo->prepare('SELECT user_id, periode FROM objectifs WHERE id = ? LIMIT 1');
    $ficheInfo->execute([$fiche_id]);
    $fiche = $ficheInfo->fetch(PDO::FETCH_ASSOC);
    
    // Supprimer toutes les dépendances liées (cascade complète)
    $tables = [
      ['table'=>'cote_des_objectifs','col'=>'fiche_id'],
      ['table'=>'auto_evaluation','col'=>'fiche_id'],
      ['table'=>'coordination_commentaires','col'=>'fiche_id'],
      ['table'=>'actions_recommandations','col'=>'fiche_id'],
      ['table'=>'objectifs_items','col'=>'fiche_id'],
      ['table'=>'objectifs_resumes','col'=>'fiche_id']
    ];
    foreach ($tables as $t) {
      if (tableExists($pdo, $t['table'])) {
        $stmt = $pdo->prepare("DELETE FROM `{$t['table']}` WHERE `{$t['col']}` = ?");
        $stmt->execute([$fiche_id]);
      }
    }
    
    // Supprimer supervisions liées (par agent + période)
    if ($fiche && tableExists($pdo, 'supervisions')) {
      $delSup = $pdo->prepare('DELETE FROM supervisions WHERE agent_id = ? AND periode = ?');
      $delSup->execute([$fiche['user_id'], $fiche['periode']]);
    }
    
    // Supprimer enregistrement principal objectifs
    $delF = $pdo->prepare('DELETE FROM objectifs WHERE id = ?');
    $delF->execute([$fiche_id]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'message'=>'Fiche et toutes ses dépendances supprimées']);
    exit;
  }
  // Action inconnue
  $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>'Action invalide']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erreur serveur: '.$e->getMessage()]);
}
