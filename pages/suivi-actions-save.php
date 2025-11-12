<?php
// pages/suivi-actions-save.php
// Charger / Enregistrer actions & recommandations liées à une fiche, pour un binôme (superviseur, agent) et sa période (cycle).
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'coordination') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Non autorisé']);
  exit;
}

function tableExists(PDO $pdo, string $table): bool {
  try { $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}

if (!tableExists($pdo,'objectifs') || !tableExists($pdo,'supervisions')) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Tables manquantes (objectifs/supervisions)']);
  exit;
}

$csrfSession = $_SESSION['csrf_token'] ?? '';

// Utilitaires
function periodeToMonthYear(string $periode): array {
  $annee = (int)substr($periode, 0, 4);
  $mois = (int)substr($periode, 5, 2);
  return [$annee, $mois];
}

// Obtenir info fiche
function getFicheInfo(PDO $pdo, int $fiche_id): ?array {
  $q = $pdo->prepare('SELECT id, user_id AS agent_id, superviseur_id, periode FROM objectifs WHERE id = ? LIMIT 1');
  $q->execute([$fiche_id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

// S'assurer qu'un cycle existe pour la période
function ensureCycleId(PDO $pdo, string $periode): ?int {
  if (!tableExists($pdo,'evaluation_cycles')) return null; // facultatif
  [$annee,$mois] = periodeToMonthYear($periode);
  // existe ?
  $q = $pdo->prepare('SELECT id FROM evaluation_cycles WHERE mois = ? AND annee = ? LIMIT 1');
  $q->execute([$mois, $annee]);
  $id = $q->fetchColumn();
  if ($id) return (int)$id;
  // créer
  $ins = $pdo->prepare('INSERT INTO evaluation_cycles (mois, annee, date_creation) VALUES (?, ?, CURDATE())');
  $ins->execute([$mois, $annee]);
  return (int)$pdo->lastInsertId();
}

// Charger existant
if (isset($_GET['load']) && $_GET['load'] == '1') {
  $fiche_id = (int)($_GET['fiche_id'] ?? 0);
  if ($fiche_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Fiche invalide']); exit; }
  $fiche = getFicheInfo($pdo, $fiche_id);
  if (!$fiche) { echo json_encode(['ok'=>false,'error'=>'Fiche introuvable']); exit; }

  $cycleId = ensureCycleId($pdo, $fiche['periode'] ?? '');
  if (!$cycleId || !tableExists($pdo,'suivi_actions')) { echo json_encode(['ok'=>true,'actions'=>'','recommandations'=>'']); exit; }
  $q = $pdo->prepare('SELECT actions, recommandations FROM suivi_actions WHERE superviseur_id = ? AND supervise_id = ? AND cycle_id = ? LIMIT 1');
  $q->execute([(int)$fiche['superviseur_id'], (int)$fiche['agent_id'], (int)$cycleId]);
  $row = $q->fetch(PDO::FETCH_ASSOC) ?: ['actions'=>'','recommandations'=>''];
  echo json_encode(['ok'=>true,'actions'=>$row['actions'] ?? '','recommandations'=>$row['recommandations'] ?? '']);
  exit;
}

// Enregistrer
$action = $_POST['action'] ?? '';
if ($action !== 'save') { echo json_encode(['ok'=>false,'error'=>'Action invalide']); exit; }
$fiche_id = (int)($_POST['fiche_id'] ?? 0);
$actions = trim((string)($_POST['actions'] ?? ''));
$recommandations = trim((string)($_POST['recommandations'] ?? ''));
$csrf = $_POST['csrf_token'] ?? '';

if ($fiche_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Fiche manquante']); exit; }
if ($csrf === '' || $csrf !== $csrfSession) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF invalide']); exit; }
if ($actions === '' && $recommandations === '') { echo json_encode(['ok'=>false,'error'=>'Renseignez au moins un champ']); exit; }

try {
  $fiche = getFicheInfo($pdo, $fiche_id);
  if (!$fiche) { echo json_encode(['ok'=>false,'error'=>'Fiche introuvable']); exit; }

  $cycleId = ensureCycleId($pdo, $fiche['periode'] ?? '');
  if (!tableExists($pdo,'suivi_actions')) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Table suivi_actions absente']); exit; }

  // Existe ?
  $q = $pdo->prepare('SELECT id FROM suivi_actions WHERE superviseur_id = ? AND supervise_id = ? AND (cycle_id <=> ?) LIMIT 1');
  $q->execute([(int)$fiche['superviseur_id'], (int)$fiche['agent_id'], $cycleId]);
  $id = $q->fetchColumn();
  if ($id) {
    $up = $pdo->prepare('UPDATE suivi_actions SET actions = ?, recommandations = ? WHERE id = ?');
    $up->execute([$actions, $recommandations, (int)$id]);
    echo json_encode(['ok'=>true,'message'=>'Suivi mis à jour']);
  } else {
    $ins = $pdo->prepare('INSERT INTO suivi_actions (superviseur_id, supervise_id, cycle_id, actions, recommandations) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([(int)$fiche['superviseur_id'], (int)$fiche['agent_id'], $cycleId, $actions, $recommandations]);
    echo json_encode(['ok'=>true,'message'=>'Suivi enregistré']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erreur serveur']);
}
