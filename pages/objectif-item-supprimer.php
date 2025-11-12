<?php
// pages/objectif-item-supprimer.php
// Supprime un objectif (POST) — sécurisé avec vérifications de verrou et propriété.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}
$user_id = $_SESSION['user_id'];

// Accept only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: FICHES-EVALUATION-VOIR.php');
  exit;
}

$item_id = intval($_POST['item_id'] ?? 0);
if ($item_id <= 0) {
  header('Location: FICHES-EVALUATION-VOIR.php');
  exit;
}

/* --- Charge l'item et la fiche associée, vérifie propriété --- */
$stmt = $pdo->prepare("SELECT i.fiche_id, o.user_id, o.periode
                       FROM objectifs_items i
                       JOIN objectifs o ON i.fiche_id = o.id
                       WHERE i.id = ?");
$stmt->execute([$item_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  header('Location: FICHES-EVALUATION-VOIR.php');
  exit;
}

$fiche_id = (int)$row['fiche_id'];
if ((int)$row['user_id'] !== (int)$user_id) {
  header("Location: FICHE-EVALUATION.php?id=$fiche_id");
  exit;
}

/* --- Vérifications de verrou (mêmes règles que le front) --- */
function tableExists(PDO $pdo, string $table): bool {
  try { $st = $pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch (Throwable $e) { return false; }
}
function hasLockTable(PDO $pdo, string $table, int $fiche_id, string $col = 'fiche_id'): bool {
  if (!tableExists($pdo, $table)) return false;
  $st = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
  $st->execute([$fiche_id]);
  return (int)$st->fetchColumn() > 0;
}

// superviseur
$stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
$stmtEval->execute([$user_id, $row['periode']]);
$verrou_superviseur = $stmtEval->fetchColumn() > 0;

// coordination and competence tables
$verrou_coord = hasLockTable($pdo, 'coordination_commentaires', $fiche_id, 'fiche_id');
$verrou_ci = hasLockTable($pdo, 'competences_individuelles', $fiche_id, 'fiche_id');
$verrou_cg = hasLockTable($pdo, 'competences_de_gestion', $fiche_id, 'fiche_id');
$verrou_ql = hasLockTable($pdo, 'qualites_de_leader', $fiche_id, 'fiche_id');

if ($verrou_superviseur || $verrou_coord || $verrou_ci || $verrou_cg || $verrou_ql) {
  // renvoyer vers la fiche avec message de verrou
  header("Location: FICHE-EVALUATION.php?id=$fiche_id&msg=verrouille");
  exit;
}

/* --- Suppression de l'item --- */
$stmtDel = $pdo->prepare("DELETE FROM objectifs_items WHERE id = ?");
$stmtDel->execute([$item_id]);

/* --- Réordonnage optionnel (bonne pratique) --- */
try {
  $pdo->beginTransaction();
  // récupère les ids ordonnés et réécrit l'ordre 1..N
  $st = $pdo->prepare("SELECT id FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC, id ASC");
  $st->execute([$fiche_id]);
  $rows = $st->fetchAll(PDO::FETCH_COLUMN);
  $ord = 1;
  $upd = $pdo->prepare("UPDATE objectifs_items SET ordre = ? WHERE id = ?");
  foreach ($rows as $rid) {
    $upd->execute([$ord, $rid]);
    $ord++;
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  // on ignore l'erreur de réordonnage mais la suppression est faite
}

/* --- Redirection finale vers la fiche --- */
header("Location: FICHE-EVALUATION.php?id=$fiche_id");
exit;
