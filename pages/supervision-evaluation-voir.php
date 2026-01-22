<?php
/**
 * pages/supervision-evaluation-voir.php
 * Lecture seule des évaluations d'un superviseur pour une fiche donnée:
 *  - Compétences (individuelle/gestion/leader/profil)
 *  - Cotations des objectifs (note/20 + %)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'superviseur' && ($_SESSION['role'] ?? '') !== 'coordination' && ($_SESSION['role'] ?? '') !== 'admin')) {
  echo '<div class="alert alert-danger m-4">Accès refusé.</div>'; include('../includes/footer.php'); exit;
}
$fiche_id = isset($_GET['fiche_id']) ? (int)$_GET['fiche_id'] : 0;
if ($fiche_id <= 0) { echo '<div class="alert alert-warning m-4">Fiche non spécifiée.</div>'; include('../includes/footer.php'); exit; }

// Charger la fiche + agent + superviseur
$st = $pdo->prepare("SELECT o.*, a.nom AS agent_nom, a.post_nom AS agent_post, a.id AS agent_id, o.superviseur_id, s.nom AS sup_nom, s.post_nom AS sup_post
                     FROM objectifs o
                     JOIN users a ON a.id = o.user_id
                     LEFT JOIN users s ON s.id = o.superviseur_id
                     WHERE o.id = :fid");
$st->execute([':fid'=>$fiche_id]);
$fiche = $st->fetch(PDO::FETCH_ASSOC);
if (!$fiche) { echo '<div class="alert alert-danger m-4">Fiche introuvable.</div>'; include('../includes/footer.php'); exit; }
$agent_id = (int)$fiche['agent_id'];
$superviseur_id = (int)($fiche['superviseur_id'] ?? 0);

// Chargement compétences
$comp = [];
try {
  $stE = $pdo->prepare("SELECT categorie, competence, point_avere, point_fort, point_a_developper, non_applicable, commentaire
                        FROM competence_evaluation
                        WHERE superviseur_id = :sup AND supervise_id = :agent");
  $stE->execute([':sup'=>$superviseur_id, ':agent'=>$agent_id]);
  foreach ($stE->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cat = $r['categorie']; if (!isset($comp[$cat])) $comp[$cat] = [];
    $comp[$cat][] = $r;
  }
} catch (Throwable $e) {}

// Cotations des objectifs
$cotes = [];
$avg20 = null; $pct = null;
try {
  $stC = $pdo->prepare("SELECT co.*, oi.contenu FROM cote_des_objectifs co JOIN objectifs_items oi ON oi.id = co.item_id WHERE co.fiche_id = :fid AND co.superviseur_id = :sup ORDER BY oi.ordre ASC, oi.id ASC");
  $stC->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);
  $cotes = $stC->fetchAll(PDO::FETCH_ASSOC);
  $stAvg = $pdo->prepare('SELECT AVG(note) FROM cote_des_objectifs WHERE fiche_id = :fid AND superviseur_id = :sup');
  $stAvg->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);
  $avg20 = (float)$stAvg->fetchColumn();
  if ($avg20 > 0) $pct = round($avg20 * 5);
} catch (Throwable $e) {}

?>
<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container mt-4">
      <div class="d-flex align-items-start mb-3">
        <h4 class="mb-0" style="color:#3D74B9;"><i class="bi bi-eye me-2"></i> Évaluation — Lecture</h4>
        <div class="ms-auto d-flex gap-2">
          <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer-fill me-1"></i> Imprimer
          </button>
          <a href="supervision-agent.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Retour
          </a>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3">
          <div><i class="bi bi-person-badge me-1 text-primary"></i><strong><?= htmlspecialchars(trim(($fiche['agent_nom'] ?? '').' '.($fiche['agent_post'] ?? ''))) ?></strong></div>
          <div><i class="bi bi-person-check me-1 text-primary"></i>Superviseur: <strong><?= htmlspecialchars(trim(($fiche['sup_nom'] ?? '').' '.($fiche['sup_post'] ?? ''))) ?></strong></div>
          <div><i class="bi bi-calendar-event me-1 text-primary"></i>Période: <strong><?= htmlspecialchars($fiche['periode'] ?? '') ?></strong></div>
          <?php if ($avg20): ?><div><i class="bi bi-award me-1 text-success"></i>Cote moyenne: <strong><?= number_format($avg20,1) ?>/20</strong> (<?= (int)$pct ?>%)</div><?php endif; ?>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-collection me-2 text-primary"></i>Compétences</h6></div>
            <div class="card-body">
              <?php if (empty($comp)): ?><div class="text-muted small">Aucune compétence évaluée.</div><?php endif; ?>
              <?php foreach (['individuelle'=>'Compétences Individuelles','gestion'=>'Compétences de Gestion','leader'=>'Qualités de Leader','profil'=>'Compétences du Profil'] as $cat=>$title):
                $rows = $comp[$cat] ?? [];
              ?>
                <div class="mb-3">
                  <div class="fw-semibold mb-2"><?= htmlspecialchars($title) ?></div>
                  <?php if (empty($rows)): ?><div class="small text-muted">—</div><?php else: ?>
                  <div class="list-group">
                    <?php foreach ($rows as $r):
                      $badge = 'secondary'; $lbl = 'N/A';
                      if ((int)$r['point_avere']===1) { $badge='success'; $lbl='Point Fort Avéré'; }
                      elseif ((int)$r['point_fort']===1) { $badge='primary'; $lbl='Point Fort'; }
                      elseif ((int)$r['point_a_developper']===1) { $badge='warning text-dark'; $lbl='À développer'; }
                      elseif ((int)$r['non_applicable']===1) { $badge='secondary'; $lbl='Non applicable'; }
                    ?>
                    <div class="list-group-item">
                      <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-semibold text-truncate" style="max-width:70%"><?= htmlspecialchars($r['competence'] ?? '') ?></div>
                        <span class="badge bg-<?= $badge ?>"><?= $lbl ?></span>
                      </div>
                      <?php if (!empty($r['commentaire'])): ?><div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($r['commentaire'])) ?></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-list-ol me-2 text-primary"></i>Objectifs — Cotations</h6></div>
            <div class="card-body">
              <?php if (empty($cotes)): ?><div class="small text-muted">Aucune cote enregistrée.</div><?php else: ?>
                <div class="list-group">
                  <?php foreach ($cotes as $c): $pctItem = ($c['note']!==null)? (int)$c['note']*5 : null; ?>
                  <div class="list-group-item">
                    <div class="fw-semibold mb-1"><?= htmlspecialchars($c['contenu'] ?? '') ?></div>
                    <div class="small">Note: <strong><?= (int)$c['note'] ?>/20</strong> <?= $pctItem!==null? '(' . $pctItem . '%)' : '' ?></div>
                    <?php if (!empty($c['commentaire'])): ?><div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($c['commentaire'])) ?></div><?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Section Actions et Recommandations -->
      <?php
      $actionsReco = null;
      try {
        $stAR = $pdo->prepare('SELECT * FROM actions_recommandations WHERE fiche_id = :fid AND superviseur_id = :sup LIMIT 1');
        $stAR->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);
        $actionsReco = $stAR->fetch(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {}
      ?>
      <div class="card">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-clipboard-check me-2 text-primary"></i>Actions et Recommandations pour le Suivi</h6></div>
        <div class="card-body">
          <?php if (!$actionsReco): ?>
            <div class="text-muted small">Aucune action ou recommandation enregistrée.</div>
          <?php else: ?>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="mb-3">
                  <div class="fw-semibold mb-1">Besoins de développement</div>
                  <div class="small"><?= !empty($actionsReco['besoins_developpement']) ? nl2br(htmlspecialchars($actionsReco['besoins_developpement'])) : '<span class="text-muted">—</span>' ?></div>
                </div>
                <div class="mb-3">
                  <div class="fw-semibold mb-1">Nécessité de ce développement</div>
                  <div class="small"><?= !empty($actionsReco['necessite_developpement']) ? nl2br(htmlspecialchars($actionsReco['necessite_developpement'])) : '<span class="text-muted">—</span>' ?></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <div class="fw-semibold mb-1">Comment atteindre</div>
                  <div class="small"><?= !empty($actionsReco['comment_atteindre']) ? nl2br(htmlspecialchars($actionsReco['comment_atteindre'])) : '<span class="text-muted">—</span>' ?></div>
                </div>
                <div class="mb-3">
                  <div class="fw-semibold mb-1">Échéance</div>
                  <div class="small"><?= !empty($actionsReco['quand_atteindre']) ? htmlspecialchars($actionsReco['quand_atteindre']) : '<span class="text-muted">—</span>' ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="fw-semibold mb-1">Autres actions ou suivi convenu</div>
                <div class="small"><?= !empty($actionsReco['autres_actions']) ? nl2br(htmlspecialchars($actionsReco['autres_actions'])) : '<span class="text-muted">—</span>' ?></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Styles d'impression -->
<style>
@media print {
  /* Masquer les éléments non nécessaires à l'impression */
  .btn, button, .sidebar, nav, footer, .alert {
    display: none !important;
  }
  
  /* Supprimer les marges de Bootstrap */
  .col-md-3 {
    display: none !important;
  }
  
  .col-md-9 {
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* En-tête avec logo pour impression */
  .print-header {
    display: block !important;
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 3px solid #3D74B9;
  }
  
  .print-logo {
    max-width: 200px;
    height: auto;
    margin-bottom: 15px;
  }
  
  /* Préserver les couleurs pour l'impression */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }
  
  /* Améliorer les badges pour l'impression */
  .badge {
    border: 1px solid #333 !important;
    padding: 3px 8px !important;
  }
  
  /* Cards */
  .card {
    border: 2px solid #dee2e6 !important;
    page-break-inside: avoid;
    margin-bottom: 20px !important;
  }
  
  .card-header {
    background-color: #f8f9fa !important;
    border-bottom: 2px solid #dee2e6 !important;
    -webkit-print-color-adjust: exact !important;
  }
  
  /* Éviter les coupures de page */
  .list-group-item, .mb-3 {
    page-break-inside: avoid;
  }
  
  /* Titre de page */
  h4, h5, h6 {
    page-break-after: avoid;
  }
  
  /* Ajuster les marges de page */
  @page {
    margin: 2cm;
  }
  
  body {
    margin: 0;
  }
}

/* En-tête d'impression (masqué à l'écran) */
.print-header {
  display: none;
}
</style>

<!-- En-tête pour l'impression avec logo -->
<div class="print-header">
  <img src="../assets/img/logo-fosip.png" alt="FOSIP" class="print-logo" onerror="this.style.display='none'">
  <h2 style="color:#3D74B9; margin: 0;">FOSIP - Évaluation des Performances</h2>
  <p style="margin: 5px 0; color: #666;">
    Agent: <strong><?= htmlspecialchars(trim(($fiche['agent_nom'] ?? '').' '.($fiche['agent_post'] ?? ''))) ?></strong> | 
    Période: <strong><?= htmlspecialchars($fiche['periode'] ?? '') ?></strong>
  </p>
  <p style="margin: 5px 0; font-size: 12px; color: #999;">
    Imprimé le <?= date('d/m/Y à H:i') ?>
  </p>
</div>

<?php include('../includes/footer.php'); ?>
