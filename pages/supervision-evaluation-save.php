<?php
/**
 * pages/supervision-evaluation-save.php
 * -------------------------------------------------------------
 * OBJECTIF
 *   Endpoint JSON pour enregistrer l'évaluation du superviseur :
 *     - Compétences (individuelle / gestion / leader / profil) avec 4 options + commentaire
 *     - Cotes des objectifs items (note 0-20 + commentaire)
 *
 * SÉCURITÉ
 *   - Rôle 'superviseur' uniquement
 *   - CSRF token requis
 *   - Vérification que la fiche appartient à un agent rattaché au superviseur courant
 *
 * PERSISTANCE
 *   - competence_evaluation : on supprime puis insère pour la combinaison (superviseur, supervise, categorie, competence)
 *   - cote_des_objectifs : upsert basique par (fiche_id, item_id, superviseur_id)
 * -------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once('../includes/db.php');

function respond($arr){ echo json_encode($arr); exit; }

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) {
  respond(['ok'=>false,'error'=>'Accès refusé']);
}
$superviseur_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['ok'=>false,'error'=>'Méthode non supportée']);

// CSRF
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  respond(['ok'=>false,'error'=>'Jeton CSRF invalide']);
}

$fiche_id = (int)($_POST['fiche_id'] ?? 0);
if ($fiche_id <= 0) respond(['ok'=>false,'error'=>'Fiche invalide']);

// Récupérer agent + période depuis la fiche (on ne fait pas confiance au client)
$stF = $pdo->prepare('SELECT o.id AS fiche_id, o.user_id AS agent_id, o.periode, COALESCE(o.superviseur_id, u.superviseur_id) AS sup_attendu
                      FROM objectifs o JOIN users u ON u.id = o.user_id WHERE o.id = :fid');
$stF->execute([':fid'=>$fiche_id]);
$fiche = $stF->fetch(PDO::FETCH_ASSOC);
if (!$fiche) respond(['ok'=>false,'error'=>'Fiche introuvable']);
$agent_id = (int)$fiche['agent_id'];
// Vérifier rattachement
if ((int)$fiche['sup_attendu'] !== $superviseur_id) respond(['ok'=>false,'error'=>'Vous ne pouvez pas évaluer cette fiche']);

// Cycle optionnel (si fourni correctement)
$cycle_id = null;
if (isset($_POST['cycle_id']) && ctype_digit((string)$_POST['cycle_id'])) $cycle_id = (int)$_POST['cycle_id'];

// Début transaction
$pdo->beginTransaction();

try {
  // 1) Compétences
  $allowed_cats = ['individuelle','gestion','leader','profil'];
  if (!empty($_POST['competences']) && is_array($_POST['competences'])) {
    foreach ($_POST['competences'] as $payload) {
      if (!is_array($payload)) continue;
      $categorie = trim((string)($payload['categorie'] ?? ''));
      $competence = trim((string)($payload['competence'] ?? ''));
      $choix = trim((string)($payload['choix'] ?? ''));
      $commentaire = trim((string)($payload['commentaire'] ?? ''));
      if ($categorie === '' || $competence === '' || !in_array($categorie, $allowed_cats, true)) continue;

      // Flags
      $flags = [
        'point_avere' => 0,
        'point_fort' => 0,
        'point_a_developper' => 0,
        'non_applicable' => 0,
      ];
      if ($choix === 'avere') $flags['point_avere'] = 1;
      elseif ($choix === 'fort') $flags['point_fort'] = 1;
      elseif ($choix === 'developper') $flags['point_a_developper'] = 1;
      elseif ($choix === 'na') $flags['non_applicable'] = 1;

      // Si aucun choix et pas de commentaire, ne rien enregistrer
      if ($flags['point_avere'] || $flags['point_fort'] || $flags['point_a_developper'] || $flags['non_applicable'] || $commentaire !== '') {
        // Supprimer éventuel doublon existant (clé logique)
        $stDel = $pdo->prepare('DELETE FROM competence_evaluation WHERE superviseur_id = :sup AND supervise_id = :agent AND categorie = :cat AND competence = :comp');
        $stDel->execute([':sup'=>$superviseur_id, ':agent'=>$agent_id, ':cat'=>$categorie, ':comp'=>$competence]);

        // Insérer
        $stIns = $pdo->prepare('INSERT INTO competence_evaluation (superviseur_id, supervise_id, cycle_id, categorie, competence, point_avere, point_fort, point_a_developper, non_applicable, commentaire) VALUES (:sup, :agent, :cycle, :cat, :comp, :av, :pf, :pd, :na, :comm)');
        $stIns->execute([
          ':sup'=>$superviseur_id,
          ':agent'=>$agent_id,
          ':cycle'=>$cycle_id,
          ':cat'=>$categorie,
          ':comp'=>$competence,
          ':av'=>$flags['point_avere'],
          ':pf'=>$flags['point_fort'],
          ':pd'=>$flags['point_a_developper'],
          ':na'=>$flags['non_applicable'],
          ':comm'=>($commentaire === '' ? null : $commentaire)
        ]);
      }
    }
  }

  // 2) Cotes des objectifs
  if (!empty($_POST['objectifs']) && is_array($_POST['objectifs'])) {
    foreach ($_POST['objectifs'] as $payload) {
      if (!is_array($payload)) continue;
      $item_id = isset($payload['item_id']) ? (int)$payload['item_id'] : 0;
      if ($item_id <= 0) continue;
      $note = trim((string)($payload['note'] ?? ''));
      $comm = trim((string)($payload['commentaire'] ?? ''));
      $note_val = 0;
      if ($note !== '') {
        if (!ctype_digit($note)) throw new Exception('Note objectif invalide');
        $note_val = (int)$note; if ($note_val < 0 || $note_val > 20) throw new Exception('Note objectif hors limite (0-20)');
      }
      // Upsert par SELECT
      $stFind = $pdo->prepare('SELECT id FROM cote_des_objectifs WHERE fiche_id = :fid AND item_id = :item AND superviseur_id = :sup LIMIT 1');
      $stFind->execute([':fid'=>$fiche_id, ':item'=>$item_id, ':sup'=>$superviseur_id]);
      $row = $stFind->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $stUp = $pdo->prepare('UPDATE cote_des_objectifs SET note = :n, commentaire = :c WHERE id = :id');
        $stUp->execute([':n'=>$note_val, ':c'=>($comm===''?null:$comm), ':id'=>(int)$row['id']]);
      } else {
        $stIns = $pdo->prepare('INSERT INTO cote_des_objectifs (fiche_id, item_id, superviseur_id, note, commentaire) VALUES (:fid, :item, :sup, :n, :c)');
        $stIns->execute([':fid'=>$fiche_id, ':item'=>$item_id, ':sup'=>$superviseur_id, ':n'=>$note_val, ':c'=>($comm===''?null:$comm)]);
      }
    }
  }

  // 3) Actions et Recommandations
  if (!empty($_POST['actions']) && is_array($_POST['actions'])) {
    $besoins = trim((string)($_POST['actions']['besoins_developpement'] ?? ''));
    $necessite = trim((string)($_POST['actions']['necessite_developpement'] ?? ''));
    $comment_atteindre = trim((string)($_POST['actions']['comment_atteindre'] ?? ''));
    $quand = trim((string)($_POST['actions']['quand_atteindre'] ?? ''));
    $autres = trim((string)($_POST['actions']['autres_actions'] ?? ''));

    // Vérifier si au moins un champ est rempli
    if ($besoins !== '' || $necessite !== '' || $comment_atteindre !== '' || $quand !== '' || $autres !== '') {
      // Upsert actions_recommandations
      $stFindAR = $pdo->prepare('SELECT id FROM actions_recommandations WHERE fiche_id = :fid AND superviseur_id = :sup LIMIT 1');
      $stFindAR->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);
      $rowAR = $stFindAR->fetch(PDO::FETCH_ASSOC);
      if ($rowAR) {
        $stUpAR = $pdo->prepare('UPDATE actions_recommandations SET besoins_developpement = :bd, necessite_developpement = :nd, comment_atteindre = :ca, quand_atteindre = :qa, autres_actions = :aa WHERE id = :id');
        $stUpAR->execute([
          ':bd'=>($besoins===''?null:$besoins),
          ':nd'=>($necessite===''?null:$necessite),
          ':ca'=>($comment_atteindre===''?null:$comment_atteindre),
          ':qa'=>($quand===''?null:$quand),
          ':aa'=>($autres===''?null:$autres),
          ':id'=>(int)$rowAR['id']
        ]);
      } else {
        $stInsAR = $pdo->prepare('INSERT INTO actions_recommandations (fiche_id, superviseur_id, besoins_developpement, necessite_developpement, comment_atteindre, quand_atteindre, autres_actions) VALUES (:fid, :sup, :bd, :nd, :ca, :qa, :aa)');
        $stInsAR->execute([
          ':fid'=>$fiche_id,
          ':sup'=>$superviseur_id,
          ':bd'=>($besoins===''?null:$besoins),
          ':nd'=>($necessite===''?null:$necessite),
          ':ca'=>($comment_atteindre===''?null:$comment_atteindre),
          ':qa'=>($quand===''?null:$quand),
          ':aa'=>($autres===''?null:$autres)
        ]);
      }
    }
  }

  // 4) Mettre à jour le statut de la fiche d'objectifs
  // Une fois que le superviseur a évalué, on change le statut à "evalue"
  $stUpdateStatut = $pdo->prepare('UPDATE objectifs SET statut = :statut, updated_at = NOW() WHERE id = :fid');
  $stUpdateStatut->execute([
    ':statut' => 'evalue', // Workflow: encours -> attente -> evalue -> termine
    ':fid' => $fiche_id
  ]);

  $pdo->commit();
  respond(['ok'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  respond(['ok'=>false,'error'=>$e->getMessage()]);
}
