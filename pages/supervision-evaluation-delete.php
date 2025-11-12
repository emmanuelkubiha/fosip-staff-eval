<?php
/**
 * pages/supervision-evaluation-delete.php
 * Supprime l'évaluation (competence_evaluation + cote_des_objectifs) d'un superviseur pour une fiche.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once('../includes/db.php');

function respond($arr){ echo json_encode($arr); exit; }

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) respond(['ok'=>false,'error'=>'Accès refusé']);
$superviseur_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['ok'=>false,'error'=>'Méthode non supportée']);

$fiche_id = (int)($_POST['fiche_id'] ?? 0);
if ($fiche_id <= 0) respond(['ok'=>false,'error'=>'Fiche invalide']);

// Charger agent + période
$st = $pdo->prepare('SELECT o.user_id AS agent_id, o.periode, o.superviseur_id FROM objectifs o WHERE o.id = :fid');
$st->execute([':fid'=>$fiche_id]);
$fiche = $st->fetch(PDO::FETCH_ASSOC);
if (!$fiche) respond(['ok'=>false,'error'=>'Fiche introuvable']);
if ((int)$fiche['superviseur_id'] !== $superviseur_id) respond(['ok'=>false,'error'=>'Non autorisé pour cette fiche']);
$agent_id = (int)$fiche['agent_id'];
$periode = $fiche['periode'];

// Trouver cycle
$cycle_id = null;
try {
  if (preg_match('/^(\\d{4})-(\\d{2})$/', (string)$periode, $m)) {
    $stC = $pdo->prepare('SELECT id FROM evaluation_cycles WHERE annee = :a AND mois = :m LIMIT 1');
    $stC->execute([':a'=>(int)$m[1], ':m'=>(int)$m[2]]);
    $row = $stC->fetch(PDO::FETCH_ASSOC); if ($row) $cycle_id = (int)$row['id'];
  }
} catch (Throwable $e) { $cycle_id = null; }

try {
  $pdo->beginTransaction();

  // 1) Supprimer cotes des objectifs pour cette fiche
  $stDel1 = $pdo->prepare('DELETE FROM cote_des_objectifs WHERE fiche_id = :fid AND superviseur_id = :sup');
  $stDel1->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);

  // 2) Supprimer compétences évaluées pour cet agent/superviseur sur le cycle ou sans cycle
  if ($cycle_id) {
    $stDel2 = $pdo->prepare('DELETE FROM competence_evaluation WHERE superviseur_id = :sup AND supervise_id = :agent AND cycle_id = :cid');
    $stDel2->execute([':sup'=>$superviseur_id, ':agent'=>$agent_id, ':cid'=>$cycle_id]);
  } else {
    $stDel2 = $pdo->prepare('DELETE FROM competence_evaluation WHERE superviseur_id = :sup AND supervise_id = :agent AND (cycle_id IS NULL OR cycle_id = 0)');
    $stDel2->execute([':sup'=>$superviseur_id, ':agent'=>$agent_id]);
  }

  // 3) Supprimer actions et recommandations
  $stDel3 = $pdo->prepare('DELETE FROM actions_recommandations WHERE fiche_id = :fid AND superviseur_id = :sup');
  $stDel3->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);

  $pdo->commit();
  respond(['ok'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  respond(['ok'=>false,'error'=>$e->getMessage()]);
}
