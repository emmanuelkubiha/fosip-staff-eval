<?php
// pages/objectif-item-supprimer.php
// Supprime un objectif (POST). Vérifie session, propriété et verrous (supervision / coordination / compétences).
// Usage sécurisé : appeler en POST item_id=NN depuis la page qui liste les objectifs.
// Retour : redirection vers fiche-evaluation.php?id=FICHE_ID (ou code 400/403/500 en cas d'erreur).

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php'); // $pdo attendu

// Require auth
if (!isset($_SESSION['user_id'])) {
  // Si appel AJAX, renvoyer 401 ; sinon rediriger.
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
  }
  header('Location: ../login.php');
  exit;
}
$user_id = (int)$_SESSION['user_id'];

// On n'accepte que POST pour la suppression (sécurité)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Méthode non autorisée';
  exit;
}

// Récupère item_id depuis POST
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
if ($item_id <= 0) {
  http_response_code(400);
  echo 'Identifiant d\'objectif invalide.';
  exit;
}

/* ---------- Fonctions utilitaires défensives ---------- */
function tableExists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}
function hasLockTable(PDO $pdo, string $table, int $fiche_id, string $col = 'fiche_id'): bool {
  if (!tableExists($pdo, $table)) return false;
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
    $st->execute([$fiche_id]);
    return (int)$st->fetchColumn() > 0;
  } catch (Throwable $e) {
    return false;
  }
}

/* ---------- Charger l'item et la fiche associée, vérifier propriété ---------- */
$stmt = $pdo->prepare("
  SELECT i.id AS item_id, i.fiche_id, o.user_id, o.periode
  FROM objectifs_items i
  JOIN objectifs o ON i.fiche_id = o.id
  WHERE i.id = ?
  LIMIT 1
");
$stmt->execute([$item_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  echo 'Objectif introuvable.';
  exit;
}

$fiche_id = (int)$row['fiche_id'];
$fiche_owner = (int)$row['user_id'];

// Vérifie que l'utilisateur connecté est bien propriétaire de la fiche
if ($fiche_owner !== $user_id) {
  http_response_code(403);
  echo 'Non autorisé à supprimer cet objectif.';
  exit;
}

/* ---------- Vérifications de verrou (mêmes règles que la page front) ---------- */
// 1) Supervision : évaluation démarrée pour agent+periode (statut != 'en attente')
try {
  $stmtEval = $pdo->prepare("SELECT COUNT(*) FROM supervisions WHERE agent_id = ? AND periode = ? AND statut != 'en attente'");
  $stmtEval->execute([$user_id, $row['periode']]);
  $verrou_superviseur = $stmtEval->fetchColumn() > 0;
} catch (Throwable $e) {
  // En cas d'erreur, considérer qu'il n'y a pas de verrou superviseur (tolérance), mais log possible
  $verrou_superviseur = false;
}

// 2) Coordination et 3) compétences (ces tables peuvent être absentes)
$verrou_coordination = hasLockTable($pdo, 'coordination_commentaires', $fiche_id, 'fiche_id');
$verrou_ci = hasLockTable($pdo, 'competences_individuelles', $fiche_id, 'fiche_id');
$verrou_cg = hasLockTable($pdo, 'competences_de_gestion', $fiche_id, 'fiche_id');
$verrou_ql = hasLockTable($pdo, 'qualites_de_leader', $fiche_id, 'fiche_id');

if ($verrou_superviseur || $verrou_coordination || $verrou_ci || $verrou_cg || $verrou_ql) {
  // Refuse la suppression et renvoie un message clair
  // Si appel AJAX, renvoyer JSON + 423 (Locked) ; sinon rediriger vers fiche avec msg=verrouille
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    http_response_code(423); // Locked
    echo json_encode([
      'error' => 'Fiche verrouillée',
      'reasons' => [
        'supervision' => $verrou_superviseur,
        'coordination' => $verrou_coordination,
        'competences_individuelles' => $verrou_ci,
        'competences_gestion' => $verrou_cg,
        'qualites_leader' => $verrou_ql
      ]
    ]);
    exit;
  } else {
    header("Location: ../fiche-evaluation.php?id={$fiche_id}&msg=verrouille");
    exit;
  }
}

/* ---------- Suppression sécurisée ---------- */
try {
  $pdo->beginTransaction();

  // Supprime l'item
  $del = $pdo->prepare("DELETE FROM objectifs_items WHERE id = ?");
  $del->execute([$item_id]);

  // Réordonnage optionnel : remet ordre 1..N pour cette fiche
  $sel = $pdo->prepare("SELECT id FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC, id ASC");
  $sel->execute([$fiche_id]);
  $rows = $sel->fetchAll(PDO::FETCH_COLUMN);

  $upd = $pdo->prepare("UPDATE objectifs_items SET ordre = ? WHERE id = ?");
  $ord = 1;
  foreach ($rows as $rid) {
    $upd->execute([$ord, $rid]);
    $ord++;
  }

  $pdo->commit();

  // Réponse selon type de requête : AJAX => JSON 200, sinon redirect
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'Objectif supprimé', 'fiche_id' => $fiche_id]);
    exit;
  } else {
    header("Location: ../fiche-evaluation.php?id={$fiche_id}");
    exit;
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('Erreur suppression objectif id=' . $item_id . ' : ' . $e->getMessage());
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur lors de la suppression']);
    exit;
  } else {
    // redirige vers la fiche avec erreur (tu peux gérer flash en session si souhaité)
    header("Location: ../fiche-evaluation.php?id={$fiche_id}&msg=error_delete");
    exit;
  }
}
