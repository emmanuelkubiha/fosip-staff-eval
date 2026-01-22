<?php
/**
 * pages/supervision-fiche.php
 * -------------------------------------------------------------
 * OBJECTIF
 *   Afficher la fiche d'objectifs d'un agent pour un superviseur, en lecture
 *   seule, avec les objectifs et leurs auto‑évaluations, ainsi que les
 *   résumés renseignés par l'agent.
 *
 * ACCÈS
 *   Réservé aux superviseurs; contrôle supplémentaire que l'agent est bien
 *   rattaché au superviseur courant (via users.superviseur_id ou fiche.superviseur_id).
 *
 * CONTENU
 *   - En‑tête: infos agent, période, projet, poste + statut/note/commentaire
 *     de supervision si existants.
 *   - Colonne gauche: objectifs + auto‑évaluations détaillées (icône + label + commentaire).
 *   - Colonne droite: résumés (réussites, améliorations, problèmes, etc.).
 * -------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'supervision-fiche.php';
include('../includes/header.php');

// Vérification d'accès: rôle superviseur ou coordination
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) {
  echo '<div class="alert alert-danger m-4">Accès refusé.</div>'; include('../includes/footer.php'); exit;
}
$superviseur_id = (int)$_SESSION['user_id'];

// Helper : existence d'une table (schéma extensible)
function tableExists(PDO $pdo, string $table): bool {
  try{ $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}

$fiche_id = isset($_GET['fiche_id']) ? (int)$_GET['fiche_id'] : 0;
if ($fiche_id <= 0) { echo '<div class="alert alert-warning m-4">Fiche non spécifiée. <a href="supervision-agent.php">Retour</a></div>'; include('../includes/footer.php'); exit; }

// Charger la fiche + agent et vérifier rattachement
$st = $pdo->prepare("SELECT o.*, a.id AS agent_id, a.nom AS agent_nom, a.post_nom AS agent_post_nom, a.fonction AS agent_fonction
                     FROM objectifs o JOIN users a ON a.id=o.user_id
                     WHERE o.id=:id AND a.superviseur_id=:sid LIMIT 1");
$st->execute([':id'=>$fiche_id, ':sid'=>$superviseur_id]);
$fiche = $st->fetch(PDO::FETCH_ASSOC);
if (!$fiche) { echo '<div class="alert alert-danger m-4">Fiche introuvable ou non autorisée. <a href="supervision-agent.php">Retour</a></div>'; include('../includes/footer.php'); exit; }
$agent_id = (int)$fiche['agent_id'];

// Préparer un token CSRF pour utilisation dans le formulaire modal inline d'évaluation
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf_token = $_SESSION['csrf_token'];

// Items
$stmI = $pdo->prepare('SELECT * FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC');
$stmI->execute([$fiche_id]);
$items = $stmI->fetchAll(PDO::FETCH_ASSOC);

// Auto-évaluations de l'agent pour chaque objectif de la fiche
$auto_map = [];
if (tableExists($pdo,'auto_evaluation')) {
  try {
    $stmA = $pdo->prepare('SELECT * FROM auto_evaluation WHERE fiche_id = ? AND user_id = ?');
    $stmA->execute([$fiche_id, $agent_id]);
    foreach ($stmA->fetchAll(PDO::FETCH_ASSOC) as $r) $auto_map[(int)$r['item_id']] = $r;
  } catch (Throwable $e) {}
}

// Supervision existante (statut, note et commentaire si déjà saisis)
$supervision = null;
try {
  $stmS = $pdo->prepare('SELECT * FROM supervisions WHERE agent_id = ? AND superviseur_id = ? AND periode = ? LIMIT 1');
  $stmS->execute([$agent_id, $superviseur_id, $fiche['periode']]);
  $supervision = $stmS->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {}

// Résumés: mapping champ => libellé affiché
$resume_fields = [
  'resume_reussite' => 'Réussites',
  'resume_amelioration' => 'Améliorations',
  'resume_problemes' => 'Problèmes',
  'resume_competence_a_developper' => 'Compétences à développer',
  'resume_competence_a_utiliser' => 'Compétences à utiliser',
  'resume_soutien' => 'Soutien attendu',
];
?>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container mt-4">

      <div class="d-flex align-items-start mb-3">
        <h4 class="mb-0" style="color:#3D74B9;"><i class="bi bi-journal-text me-2"></i> Fiche — Vue superviseur</h4>
        <div class="ms-auto d-flex gap-2">
          <a href="supervision-agent.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Retour</a>
          <?php if (!$supervision || $supervision['statut'] !== 'complet'): ?>
            <a href="supervision-evaluer.php?fiche_id=<?= (int)$fiche_id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i> Évaluer</a>
          <?php else: ?>
            <span class="btn btn-sm btn-success disabled" title="Évaluation terminée"><i class="bi bi-check2-circle me-1"></i> Terminé</span>
          <?php endif; ?>
          <a href="imprimer-fiche-evaluation.php?fiche_id=<?= (int)$fiche_id ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Exporter PDF"><i class="bi bi-filetype-pdf me-1"></i> PDF</a>
        </div>
      </div>

      <!-- Carte entête -->
      <div class="card section-card mb-3">
        <div class="card-body">
          <div class="row g-3 align-items-center">
            <div class="col-md-8">
              <div class="d-flex align-items-center mb-2">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                  <i class="bi bi-person-badge" style="color:#3D74B9;font-size:1.6rem"></i>
                </div>
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars(trim(($fiche['agent_nom'] ?? '').' '.($fiche['agent_post_nom'] ?? ''))) ?></div>
                  <div class="small text-muted">Fonction: <?= htmlspecialchars($fiche['agent_fonction'] ?? '') ?></div>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-3 small text-muted">
                <div><i class="bi bi-calendar-event me-1"></i> Période: <strong><?= htmlspecialchars($fiche['periode']) ?></strong></div>
                <div><i class="bi bi-kanban me-1"></i> Projet: <strong><?= htmlspecialchars($fiche['nom_projet']) ?></strong></div>
                <div><i class="bi bi-briefcase me-1"></i> Poste: <strong><?= htmlspecialchars($fiche['poste']) ?></strong></div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <?php $badge = ($supervision && $supervision['statut']==='complet')?'success':(($supervision && $supervision['statut']==='encours')?'warning':'secondary');
                    $label = ($supervision && $supervision['statut']==='complet')?'Évaluation complétée':(($supervision && $supervision['statut']==='encours')?'Évaluation en cours':'Pas encore évaluée'); ?>
              <div class="mb-2"><span class="badge bg-<?= $badge ?> py-2 px-3"><?= $label ?></span></div>
              <?php if ($supervision): ?>
                <div class="small">Note: <strong><?= (int)$supervision['note'] ?></strong></div>
                <?php if (!empty($supervision['commentaire'])): ?>
                  <div class="small text-muted mt-1">Commentaire superviseur:</div>
                  <div class="small"><?= nl2br(htmlspecialchars($supervision['commentaire'])) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <div class="small text-muted">Pas encore de note / commentaire.</div>
              <?php endif; ?>
              <?php
              // Afficher la cote générale (moyenne des notes/20) pour cette fiche
              try {
                $stAvg = $pdo->prepare('SELECT AVG(note) FROM cote_des_objectifs WHERE fiche_id = :fid AND superviseur_id = :sid');
                $stAvg->execute([':fid'=>(int)$fiche_id, ':sid'=>$superviseur_id]);
                $avg20 = (float)$stAvg->fetchColumn();
                if ($avg20 > 0) {
                  $pct = round($avg20 * 5); // convertir /20 en pourcentage
                  echo '<div class="mt-2 small">Cote (moy.) : <strong>'.number_format($avg20,1).'/20</strong> <span class="text-muted">('.$pct.'%)</span></div>';
                }
              } catch (Throwable $e) {}
              ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- Colonne objectifs + auto-eval -->
        <div class="col-lg-8">
          <div class="card section-card">
            <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2"><i class="bi bi-list-check" style="color:#3D74B9;font-size:1.3rem"></i></div>
                <h5 class="mb-0">Objectifs et auto‑évaluations</h5>
              </div>
              <?php if (empty($items)): ?>
                <div class="alert alert-warning">Aucun objectif dans cette fiche.</div>
              <?php else: ?>
                <div class="list-group">
                  <?php foreach ($items as $idx => $it):
                    $ae = $auto_map[(int)$it['id']] ?? null;
                    $stateIcon = '<i class="bi bi-slash-circle" style="color:#6c757d"></i>';
                    $stateLabel = 'Indisponible';
                    $stateClass = 'text-muted';
                    if ($ae && !empty($ae['note'])) {
                      if ($ae['note']==='depasse') { $stateIcon='<i class="bi bi-arrow-up-circle-fill" style="color:#198754"></i>'; $stateLabel='Dépasse'; $stateClass='text-success'; }
                      elseif ($ae['note']==='atteint') { $stateIcon='<i class="bi bi-dash-circle-fill" style="color:#ffc107"></i>'; $stateLabel='Atteint'; $stateClass='text-warning'; }
                      else { $stateIcon='<i class="bi bi-arrow-down-circle-fill" style="color:#dc3545"></i>'; $stateLabel='Non atteint'; $stateClass='text-danger'; }
                    }
                  ?>
                  <div class="list-group-item border rounded-3 mb-2">
                    <div class="d-flex gap-3 align-items-start">
                      <div class="fw-bold text-primary" style="min-width:22px;"><?= $idx+1 ?>.</div>
                      <div style="flex:1">
                        <div class="fw-semibold mb-1"><?= htmlspecialchars($it['contenu']) ?></div>
                        <?php if (!empty($it['resume'])): ?><div class="small text-muted"><?= nl2br(htmlspecialchars($it['resume'])) ?></div><?php endif; ?>
                      </div>
                      <div class="text-center">
                        <div><?= $stateIcon ?></div>
                        <div class="small <?= $stateClass ?>"><?= htmlspecialchars($stateLabel) ?></div>
                      </div>
                    </div>
                    <?php if ($ae && !empty($ae['commentaire'])): ?>
                      <div class="mt-2 p-2 bg-light rounded small"><strong>Commentaire:</strong> <?= nl2br(htmlspecialchars($ae['commentaire'])) ?></div>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Colonne résumés -->
        <div class="col-lg-4">
          <div class="card section-card">
            <div class="card-body">
              <h6 class="mb-2"><i class="bi bi-card-text me-2" style="color:#3D74B9"></i> Résumés</h6>
              <?php foreach ($resume_fields as $field => $label): $val = trim((string)($fiche[$field] ?? '')); ?>
                <div class="mb-3">
                  <div class="small text-muted mb-1"><?= htmlspecialchars($label) ?></div>
                  <?php if ($val !== ''): ?>
                    <div class="p-2 bg-light rounded small"><?= nl2br(htmlspecialchars($val)) ?></div>
                  <?php else: ?>
                    <div class="small text-muted">—</div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
