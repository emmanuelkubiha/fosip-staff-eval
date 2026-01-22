<?php
/**
 * pages/fiche-evaluation-complete.php
 * =====================================================================
 * SYNTHÈSE COMPLÈTE D'UNE FICHE D'ÉVALUATION
 * =====================================================================
 * 
 * Cette page affiche une vue exhaustive de toute la fiche d'évaluation :
 * 
 * SECTION 1 : INFORMATIONS DE BASE
 * - Données de l'agent (nom, fonction, période...)
 * - Informations du projet et poste
 * - Dates et statuts
 * 
 * SECTION 2 : OBJECTIFS DÉFINIS PAR L'EMPLOYÉ
 * - Liste complète des objectifs items
 * - Auto-évaluation de l'employé (si disponible)
 * 
 * SECTION 3 : ÉVALUATION DU SUPERVISEUR
 * - Compétences évaluées (Individuelles, Gestion, Leader, Profil)
 * - Notes des objectifs sur 20
 * - Plan d'action et recommandations
 * - Commentaires généraux
 * 
 * SECTION 4 : VALIDATION DE LA COORDINATION
 * - Commentaires de la coordination
 * - Note finale et statut
 * - Date de validation
 * 
 * ACCÈS :
 * - Superviseur : peut voir ses propres évaluations
 * - Coordination : peut voir toutes les fiches
 * - Admin : accès complet à toutes les données
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
include('../includes/header.php');

// Auth : superviseur, coordination ou admin
if (!isset($_SESSION['user_id'])) {
  echo '<div class="alert alert-danger m-4">Accès refusé.</div>'; 
  include('../includes/footer.php'); 
  exit;
}

$role = $_SESSION['role'] ?? '';
$user_id = (int)$_SESSION['user_id'];

if (!in_array($role, ['superviseur', 'coordination', 'admin'])) {
  echo '<div class="alert alert-danger m-4">Vous n\'avez pas les permissions pour accéder à cette page.</div>'; 
  include('../includes/footer.php'); 
  exit;
}

$fiche_id = isset($_GET['fiche_id']) ? (int)$_GET['fiche_id'] : 0;
if ($fiche_id <= 0) {
  echo '<div class="alert alert-warning m-4">Fiche non spécifiée.</div>'; 
  include('../includes/footer.php'); 
  exit;
}

// Charger la fiche complète
$stF = $pdo->prepare('
  SELECT o.*, 
         u.id AS agent_id, u.nom AS agent_nom, u.post_nom AS agent_post_nom, 
         u.fonction AS agent_fonction, u.email AS agent_email,
         sup.nom AS superviseur_nom, sup.post_nom AS superviseur_post_nom,
         sup.email AS superviseur_email
  FROM objectifs o
  JOIN users u ON u.id = o.user_id
  LEFT JOIN users sup ON sup.id = o.superviseur_id
  WHERE o.id = :fid
  LIMIT 1
');
$stF->execute([':fid' => $fiche_id]);
$fiche = $stF->fetch(PDO::FETCH_ASSOC);

if (!$fiche) {
  echo '<div class="alert alert-danger m-4">Fiche introuvable.</div>'; 
  include('../includes/footer.php'); 
  exit;
}

$agent_id = (int)$fiche['agent_id'];
$superviseur_id = (int)$fiche['superviseur_id'];
$periode = $fiche['periode'];

// Vérification des droits si superviseur
if ($role === 'superviseur' && $superviseur_id !== $user_id) {
  echo '<div class="alert alert-danger m-4">Vous ne pouvez pas consulter cette fiche.</div>'; 
  include('../includes/footer.php'); 
  exit;
}

// ==================== CHARGEMENT DES DONNÉES ====================

// 1. Objectifs items
$items = [];
try {
  $stI = $pdo->prepare('SELECT * FROM objectifs_items WHERE fiche_id = :fid ORDER BY ordre ASC');
  $stI->execute([':fid' => $fiche_id]);
  $items = $stI->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// 2. Auto-évaluation de l'employé
$autoEval = [];
try {
  $stAuto = $pdo->prepare('SELECT * FROM auto_evaluation WHERE fiche_id = :fid');
  $stAuto->execute([':fid' => $fiche_id]);
  foreach ($stAuto->fetchAll(PDO::FETCH_ASSOC) as $ae) {
    $autoEval[(int)$ae['item_id']] = $ae;
  }
} catch (Throwable $e) {}

// 3. Compétences évaluées par le superviseur
$competences = [];
try {
  $stComp = $pdo->prepare('SELECT * FROM competence_evaluation WHERE superviseur_id = :sup AND supervise_id = :agent ORDER BY categorie, id');
  $stComp->execute([':sup' => $superviseur_id, ':agent' => $agent_id]);
  $competences = $stComp->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Grouper par catégorie
$competencesParCategorie = [
  'individuelle' => [],
  'gestion' => [],
  'leader' => [],
  'profil' => []
];
foreach ($competences as $c) {
  $cat = strtolower($c['categorie']);
  if (isset($competencesParCategorie[$cat])) {
    $competencesParCategorie[$cat][] = $c;
  }
}

// 4. Cotation des objectifs
$cotes = [];
try {
  $stCote = $pdo->prepare('SELECT * FROM cote_des_objectifs WHERE fiche_id = :fid AND superviseur_id = :sup');
  $stCote->execute([':fid' => $fiche_id, ':sup' => $superviseur_id]);
  foreach ($stCote->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cotes[(int)$c['item_id']] = $c;
  }
} catch (Throwable $e) {}

// Calculer moyenne des notes
$totalNotes = 0;
$nbNotes = 0;
foreach ($cotes as $c) {
  if ($c['note'] !== null) {
    $totalNotes += (int)$c['note'];
    $nbNotes++;
  }
}
$moyenneNote = $nbNotes > 0 ? round($totalNotes / $nbNotes, 1) : null;
$moyennePourcent = $moyenneNote !== null ? round($moyenneNote * 5) : null;

// 5. Actions et recommandations
$actions = null;
try {
  $stAct = $pdo->prepare('SELECT * FROM actions_recommandations WHERE fiche_id = :fid AND superviseur_id = :sup LIMIT 1');
  $stAct->execute([':fid' => $fiche_id, ':sup' => $superviseur_id]);
  $actions = $stAct->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// 6. Supervision (commentaire général et statut)
$supervision = null;
try {
  $stSup = $pdo->prepare('SELECT * FROM supervisions WHERE agent_id = :agent AND superviseur_id = :sup AND periode = :per LIMIT 1');
  $stSup->execute([':agent' => $agent_id, ':sup' => $superviseur_id, ':per' => $periode]);
  $supervision = $stSup->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// 7. Commentaire final coordination (nouvelle table coordination_commentaires)
$coordComment = null;
try {
  $stCoordCom = $pdo->prepare('SELECT c.commentaire, c.coord_id, c.supervise_id, c.date_commentaire, u.nom AS coord_nom, u.post_nom AS coord_post_nom FROM coordination_commentaires c LEFT JOIN users u ON u.id = c.coord_id WHERE c.fiche_id = :fid LIMIT 1');
  $stCoordCom->execute([':fid' => $fiche_id]);
  $coordComment = $stCoordCom->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Fonction helper pour afficher le choix de compétence
function getChoixCompetence($comp) {
  if ((int)$comp['point_avere'] === 1) return ['label' => 'Point Fort Avéré', 'class' => 'success'];
  if ((int)$comp['point_fort'] === 1) return ['label' => 'Point Fort', 'class' => 'primary'];
  if ((int)$comp['point_a_developper'] === 1) return ['label' => 'À Développer', 'class' => 'warning'];
  if ((int)$comp['non_applicable'] === 1) return ['label' => 'Non-Applicable', 'class' => 'secondary'];
  return ['label' => 'Non évalué', 'class' => 'light'];
}

// Charger les photos de profil
$agent_photo = 'default.png';
$superviseur_photo = 'default.png';

try {
  $stPhotos = $pdo->prepare('SELECT id, photo FROM users WHERE id IN (?, ?)');
  $stPhotos->execute([$agent_id, $superviseur_id]);
  while ($row = $stPhotos->fetch(PDO::FETCH_ASSOC)) {
    if ((int)$row['id'] === $agent_id) {
      $agent_photo = $row['photo'] ?? 'default.png';
    }
    if ((int)$row['id'] === $superviseur_id) {
      $superviseur_photo = $row['photo'] ?? 'default.png';
    }
  }
} catch (Throwable $e) {}

$profile_base = '../assets/img/profiles/';
$agent_photo_path = $profile_base . htmlspecialchars($agent_photo);
$superviseur_photo_path = $profile_base . htmlspecialchars($superviseur_photo);
?>

<!-- En-tête pour impression (placé AVANT le contenu principal) -->
<div class="print-header-complete">
  <img src="../assets/img/logocolored.png" alt="FOSIP" class="print-logo-complete" onerror="this.onerror=null; this.src='../assets/img/logo-fosip.png';">
  <h2 style="margin: 10px 0; font-size: 18pt; color: #3D74B9;">
    FICHE D'ÉVALUATION COMPLÈTE
  </h2>
  <div style="font-size: 10pt; margin-top: 8px;">
    <strong>Agent:</strong> <?= htmlspecialchars(trim($fiche['agent_nom'].' '.$fiche['agent_post_nom'])) ?> | 
    <strong>Période:</strong> <?= htmlspecialchars($periode) ?> | 
    <strong>Fiche N°:</strong> <?= (int)$fiche_id ?>
  </div>
  <div style="font-size: 8pt; margin-top: 6px; color: #666;">
    Document imprimé le <?= date('d/m/Y à H:i') ?> | Système FOSIP d'Évaluation des Performances
  </div>
</div>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9 fiche-padding-gauche">
    <!-- Contenu principal de la fiche d'évaluation complète -->
    <div class="container-fluid mt-4 mb-5">
      
      <!-- En-tête -->
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
          <h3 class="mb-1" style="color:#3D74B9">
            <i class="bi bi-file-earmark-text-fill me-2"></i> Fiche d'Évaluation Complète
          </h3>
          <p class="text-muted mb-0">Synthèse exhaustive de toutes les données</p>
        </div>
        <div class="btn-group">
          <a href="javascript:window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Imprimer
          </a>
          <?php
            // Déterminer l'URL de retour : superviseur -> supervision, sinon -> dashboard
            $returnUrl = 'dashboard.php';
            if (isset($role) && $role === 'superviseur') {
              $returnUrl = 'supervision.php';
            }
          ?>
          <a href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"  style="color: #3D74B9;" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-1"></i> Retour
          </a>
        </div>
      </div>

      <!-- ===================== SECTION 1 : INFORMATIONS DE BASE ===================== -->
      <div class="card shadow-sm mb-4" style="border-left: 4px solid #3D74B9;">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Informations de Base</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <h6 class="border-bottom pb-2 mb-3">
                <i class="bi bi-person-badge me-2"></i> Agent Évalué
              </h6>
              <div class="d-flex align-items-start gap-3">
                <img src="<?= htmlspecialchars($agent_photo_path) ?>" 
                     alt="Photo agent" 
                     class="profile-photo-complete rounded-circle"
                     onerror="this.onerror=null;this.src='<?= $profile_base ?>default.png';">
                <table class="table table-sm table-borderless mb-0">
                  <tr>
                    <td class="text-muted" width="40%"><i class="bi bi-person me-2"></i> Nom complet</td>
                    <td><strong><?= htmlspecialchars(trim($fiche['agent_nom'].' '.$fiche['agent_post_nom'])) ?></strong></td>
                  </tr>
                  <tr>
                    <td class="text-muted"><i class="bi bi-briefcase me-2"></i> Fonction</td>
                    <td><?= htmlspecialchars($fiche['agent_fonction'] ?? 'Non définie') ?></td>
                  </tr>
                  <tr>
                    <td class="text-muted"><i class="bi bi-envelope me-2"></i> Email</td>
                    <td><?= htmlspecialchars($fiche['agent_email'] ?? 'Non renseigné') ?></td>
                  </tr>
                </table>
              </div>
            </div>
            <div class="col-md-6">
              <h6 class="border-bottom pb-2 mb-3">
                <i class="bi bi-person-check me-2"></i> Superviseur
              </h6>
              <div class="d-flex align-items-start gap-3">
                <img src="<?= htmlspecialchars($superviseur_photo_path) ?>" 
                     alt="Photo superviseur" 
                     class="profile-photo-complete rounded-circle"
                     onerror="this.onerror=null;this.src='<?= $profile_base ?>default.png';">
                <table class="table table-sm table-borderless mb-0">
                  <tr>
                    <td class="text-muted" width="40%"><i class="bi bi-person me-2"></i> Nom complet</td>
                    <td><strong><?= htmlspecialchars(trim($fiche['superviseur_nom'].' '.$fiche['superviseur_post_nom'])) ?></strong></td>
                  </tr>
                  <tr>
                    <td class="text-muted"><i class="bi bi-envelope me-2"></i> Email</td>
                    <td><?= htmlspecialchars($fiche['superviseur_email'] ?? 'Non renseigné') ?></td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
          <hr>
          <div class="row g-3">
            <div class="col-md-3">
              <small class="text-muted d-block">Période d'évaluation</small>
              <strong><?= htmlspecialchars($periode) ?></strong>
            </div>
            <div class="col-md-3">
              <small class="text-muted d-block">Projet</small>
              <strong><?= htmlspecialchars($fiche['nom_projet'] ?? 'Non défini') ?></strong>
            </div>
            <div class="col-md-3">
              <small class="text-muted d-block">Poste</small>
              <strong><?= htmlspecialchars($fiche['poste'] ?? 'Non défini') ?></strong>
            </div>
            <div class="col-md-3">
              <small class="text-muted d-block">Statut fiche</small>
              <?php 
                $statutClass = 'secondary';
                $statutLabel = ucfirst($fiche['statut'] ?? 'Brouillon');
                
                switch($fiche['statut']) {
                  case 'encours':
                    $statutClass = 'info';
                    $statutLabel = 'En cours';
                    break;
                  case 'attente':
                    $statutClass = 'warning';
                    $statutLabel = 'En attente';
                    break;
                  case 'evalue':
                    $statutClass = 'primary';
                    $statutLabel = 'Évalué';
                    break;
                  case 'termine':
                    $statutClass = 'success';
                    $statutLabel = 'Terminé';
                    break;
                }
              ?>
              <span class="badge bg-<?= $statutClass ?>"><?= $statutLabel ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- ===================== SECTION 2 : OBJECTIFS ET AUTO-ÉVALUATION ===================== -->
      <div class="card shadow-sm mb-4" style="border-left: 4px solid #3D74B9;">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-bullseye me-2"></i> Objectifs Définis par l'Employé</h5>
        </div>
        <div class="card-body">
          <?php if (empty($items)): ?>
            <div class="alert alert-warning">Aucun objectif défini pour cette période.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th width="5%">#</th>
                    <th width="45%">Objectif</th>
                    <th width="25%">Auto-évaluation Employé</th>
                    <th width="25%">Note Superviseur</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $idx => $item):
                    $itemId = (int)$item['id'];
                    $auto = $autoEval[$itemId] ?? null;
                    $cote = $cotes[$itemId] ?? null;
                  ?>
                  <tr>
                    <td class="fw-bold"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($item['contenu']) ?></td>
                    <td>
                      <?php if ($auto): ?>
                        <div class="small">
                          <strong>Réalisé :</strong> <?= htmlspecialchars($auto['niveau_realisation'] ?? '—') ?><br>
                          <?php if (!empty($auto['commentaire'])): ?>
                            <em class="text-muted"><?= htmlspecialchars($auto['commentaire']) ?></em>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">Non évalué</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($cote && $cote['note'] !== null): 
                        $note = (int)$cote['note'];
                        $pct = $note * 5;
                        $noteClass = $pct >= 80 ? 'success' : ($pct >= 50 ? 'primary' : 'danger');
                      ?>
                        <div class="d-flex align-items-center">
                          <span class="badge bg-<?= $noteClass ?> me-2"><?= $note ?>/20</span>
                          <small class="text-muted">(<?= $pct ?>%)</small>
                        </div>
                        <?php if (!empty($cote['commentaire'])): ?>
                          <small class="text-muted d-block mt-1"><em><?= htmlspecialchars($cote['commentaire']) ?></em></small>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">Non noté</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if ($moyenneNote !== null): ?>
              <div class="alert alert-light border mt-3">
                <i class="bi bi-calculator me-2"></i>
                <strong>Moyenne générale des objectifs :</strong> 
                <span class="badge bg-secondary ms-2"><?= $moyenneNote ?>/20</span>
                <span class="badge bg-secondary ms-1"><?= $moyennePourcent ?>%</span>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- ===================== SECTION 3 : ÉVALUATION DU SUPERVISEUR ===================== -->
      <div class="card shadow-sm mb-4" style="border-left: 4px solid #3D74B9;">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i> Évaluation du Superviseur</h5>
        </div>
        <div class="card-body">
          
          <!-- 3.1 Compétences -->
          <h6 class="mb-3 border-bottom pb-2">
            <i class="bi bi-star me-2"></i> Compétences évaluées
          </h6>
          
          <div class="row mb-4">
            <!-- Compétences Individuelles -->
            <div class="col-lg-6 mb-3">
              <div class="card h-100">
                <div class="card-header bg-light">
                  <strong><i class="bi bi-person-check me-2"></i> Compétences Individuelles</strong>
                </div>
                <div class="card-body p-2">
                  <?php if (empty($competencesParCategorie['individuelle'])): ?>
                    <p class="text-muted small mb-0">Aucune évaluation</p>
                  <?php else: ?>
                    <div class="list-group list-group-flush">
                      <?php foreach ($competencesParCategorie['individuelle'] as $c): 
                        $choix = getChoixCompetence($c);
                      ?>
                      <div class="list-group-item p-2">
                        <div class="d-flex justify-content-between align-items-start">
                          <span class="small"><?= htmlspecialchars($c['competence']) ?></span>
                          <span class="badge bg-<?= $choix['class'] ?> ms-2"><?= $choix['label'] ?></span>
                        </div>
                        <?php if (!empty($c['commentaire'])): ?>
                          <small class="text-muted d-block mt-1"><em><?= htmlspecialchars($c['commentaire']) ?></em></small>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Compétences de Gestion -->
            <div class="col-lg-6 mb-3">
              <div class="card h-100">
                <div class="card-header bg-light">
                  <strong><i class="bi bi-diagram-3 me-2"></i> Compétences de Gestion</strong>
                </div>
                <div class="card-body p-2">
                  <?php if (empty($competencesParCategorie['gestion'])): ?>
                    <p class="text-muted small mb-0">Aucune évaluation</p>
                  <?php else: ?>
                    <div class="list-group list-group-flush">
                      <?php foreach ($competencesParCategorie['gestion'] as $c): 
                        $choix = getChoixCompetence($c);
                      ?>
                      <div class="list-group-item p-2">
                        <div class="d-flex justify-content-between align-items-start">
                          <span class="small"><?= htmlspecialchars($c['competence']) ?></span>
                          <span class="badge bg-<?= $choix['class'] ?> ms-2"><?= $choix['label'] ?></span>
                        </div>
                        <?php if (!empty($c['commentaire'])): ?>
                          <small class="text-muted d-block mt-1"><em><?= htmlspecialchars($c['commentaire']) ?></em></small>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Qualités de Leader -->
            <div class="col-lg-6 mb-3">
              <div class="card h-100">
                <div class="card-header bg-light">
                  <strong><i class="bi bi-people me-2"></i> Qualités de Leader</strong>
                </div>
                <div class="card-body p-2">
                  <?php if (empty($competencesParCategorie['leader'])): ?>
                    <p class="text-muted small mb-0">Aucune évaluation</p>
                  <?php else: ?>
                    <div class="list-group list-group-flush">
                      <?php foreach ($competencesParCategorie['leader'] as $c): 
                        $choix = getChoixCompetence($c);
                      ?>
                      <div class="list-group-item p-2">
                        <div class="d-flex justify-content-between align-items-start">
                          <span class="small"><?= htmlspecialchars($c['competence']) ?></span>
                          <span class="badge bg-<?= $choix['class'] ?> ms-2"><?= $choix['label'] ?></span>
                        </div>
                        <?php if (!empty($c['commentaire'])): ?>
                          <small class="text-muted d-block mt-1"><em><?= htmlspecialchars($c['commentaire']) ?></em></small>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Compétences Profil -->
            <div class="col-lg-6 mb-3">
              <div class="card h-100">
                <div class="card-header bg-light">
                  <strong><i class="bi bi-person-gear me-2"></i> Compétences Profil</strong>
                </div>
                <div class="card-body p-2">
                  <?php if (empty($competencesParCategorie['profil'])): ?>
                    <p class="text-muted small mb-0">Aucune évaluation</p>
                  <?php else: ?>
                    <div class="list-group list-group-flush">
                      <?php foreach ($competencesParCategorie['profil'] as $c): 
                        $choix = getChoixCompetence($c);
                      ?>
                      <div class="list-group-item p-2">
                        <div class="d-flex justify-content-between align-items-start">
                          <span class="small"><?= htmlspecialchars($c['competence']) ?></span>
                          <span class="badge bg-<?= $choix['class'] ?> ms-2"><?= $choix['label'] ?></span>
                        </div>
                        <?php if (!empty($c['commentaire'])): ?>
                          <small class="text-muted d-block mt-1"><em><?= htmlspecialchars($c['commentaire']) ?></em></small>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- 3.2 Plan d'Action et Recommandations -->
          <h6 class="mb-3 border-bottom pb-2 mt-4">
            <i class="bi bi-clipboard-check me-2"></i> Plan d'Action et Recommandations
          </h6>
          
          <?php if ($actions): ?>
            <div class="row g-3">
              <div class="col-md-12">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-book me-2"></i> Besoins de développement</h6>
                    <p class="mb-0"><?= !empty($actions['besoins_developpement']) ? nl2br(htmlspecialchars($actions['besoins_developpement'])) : '<em class="text-muted">Non renseigné</em>' ?></p>
                  </div>
                </div>
              </div>
              <div class="col-md-12">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-question-circle me-2"></i> Nécessité et justification</h6>
                    <p class="mb-0"><?= !empty($actions['necessite_developpement']) ? nl2br(htmlspecialchars($actions['necessite_developpement'])) : '<em class="text-muted">Non renseigné</em>' ?></p>
                  </div>
                </div>
              </div>
              <div class="col-md-12">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-signpost me-2"></i> Comment atteindre l'objectif</h6>
                    <p class="mb-0"><?= !empty($actions['comment_atteindre']) ? nl2br(htmlspecialchars($actions['comment_atteindre'])) : '<em class="text-muted">Non renseigné</em>' ?></p>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-calendar-event me-2"></i> Échéancier</h6>
                    <p class="mb-0"><?= !empty($actions['quand_atteindre']) ? htmlspecialchars($actions['quand_atteindre']) : '<em class="text-muted">Non défini</em>' ?></p>
                  </div>
                </div>
              </div>
              <div class="col-md-12">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-chat-left-dots me-2"></i> Autres actions ou suivis</h6>
                    <p class="mb-0"><?= !empty($actions['autres_actions']) ? nl2br(htmlspecialchars($actions['autres_actions'])) : '<em class="text-muted">Non renseigné</em>' ?></p>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-light border">Aucun plan d'action défini par le superviseur.</div>
          <?php endif; ?>

          <!-- 3.3 Commentaire général du superviseur -->
          <?php if ($supervision): ?>
            <h6 class="mb-3 border-bottom pb-2 mt-4">
              <i class="bi bi-chat-quote me-2"></i> Commentaire Général du Superviseur
            </h6>
            <div class="card bg-light">
              <div class="card-body">
                <?php if (!empty($supervision['commentaire'])): ?>
                  <p class="mb-0"><?= nl2br(htmlspecialchars($supervision['commentaire'])) ?></p>
                <?php else: ?>
                  <em class="text-muted">Aucun commentaire général</em>
                <?php endif; ?>
                <hr>
                <div class="row">
                  <div class="col-md-4">
                    <small class="text-muted d-block">Note globale</small>
                    <strong><?= $supervision['note'] !== null ? (int)$supervision['note'] : '—' ?>/100</strong>
                  </div>
                  <div class="col-md-4">
                    <small class="text-muted d-block">Statut supervision</small>
                    <?php 
                      $supStatutClass = 'secondary';
                      if ($supervision['statut'] === 'complet') $supStatutClass = 'success';
                      elseif ($supervision['statut'] === 'encours') $supStatutClass = 'warning';
                    ?>
                    <span class="badge bg-<?= $supStatutClass ?>"><?= htmlspecialchars($supervision['statut'] ?? 'Non commencé') ?></span>
                  </div>
                  <div class="col-md-4">
                    <small class="text-muted d-block">Date de validation</small>
                    <strong><?= $supervision['date_validation'] ? date('d/m/Y H:i', strtotime($supervision['date_validation'])) : '—' ?></strong>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ===================== SECTION 4 : COMMENTAIRE FINAL COORDINATION ===================== -->
      <div class="card shadow-sm mb-4" style="border-left: 4px solid #3D74B9;">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-chat-square-text me-2"></i> Commentaire Final de la Coordination</h5>
        </div>
        <div class="card-body">
          <?php if (isset($_GET['coord_comment'])): ?>
            <div class="alert alert-success py-2 mb-3">
              <i class="bi bi-check-circle me-1"></i>
              <?= $_GET['coord_comment'] === 'added' ? 'Commentaire d\'évaluation ajouté' : 'Commentaire d\'évaluation mis à jour' ?>.
            </div>
          <?php endif; ?>

          <?php if ($coordComment): ?>
            <div class="mb-3">
              <h6 class="border-bottom pb-2"><i class="bi bi-chat-left-dots me-1"></i> Texte du commentaire final</h6>
              <div class="card bg-light">
                <div class="card-body">
                  <?= !empty($coordComment['commentaire']) ? nl2br(htmlspecialchars($coordComment['commentaire'])) : '<em class="text-muted">Aucun commentaire final</em>' ?>
                </div>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-md-4">
                <small class="text-muted d-block">Date du commentaire</small>
                <strong><?= !empty($coordComment['date_commentaire']) ? date('d/m/Y', strtotime($coordComment['date_commentaire'])) : '—' ?></strong>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Statut de la fiche</small>
                <?php 
                  $fStatutClass = 'secondary';
                  $fStatutLabel = 'Inconnu';
                  switch($fiche['statut']) {
                    case 'encours': $fStatutClass='info'; $fStatutLabel='En cours'; break;
                    case 'attente': $fStatutClass='warning'; $fStatutLabel='En attente'; break;
                    case 'evalue': $fStatutClass='primary'; $fStatutLabel='Évalué'; break;
                    case 'termine': $fStatutClass='success'; $fStatutLabel='Clôturé'; break;
                  }
                ?>
                <span class="badge bg-<?= $fStatutClass ?>"><?= $fStatutLabel ?></span>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Coordination</small>
                <strong><?= !empty($coordComment['coord_nom']) ? htmlspecialchars(trim(($coordComment['coord_nom'] ?? '').' '.($coordComment['coord_post_nom'] ?? ''))) : '—' ?></strong>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-light border">
              <i class="bi bi-info-circle me-2"></i>
              Aucun commentaire final de la coordination n'a encore été ajouté pour cette fiche.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Boutons d'action en bas -->
      <div class="text-center mt-4 no-print">
        <a href="javascript:window.print()"  style="background-color: #3D74B9;" class="btn btn-primary btn-lg me-2">
          <i class="bi bi-printer me-2"></i> Imprimer cette synthèse
        </a>

      </div>

      <!-- Section Signatures (visible uniquement à l'impression) -->
      <div class="signatures-section print-only">
        <div class="signatures-header">
          <h5 style="color: #3D74B9; margin-bottom: 1.5rem; border-bottom: 2px solid #3D74B9; padding-bottom: 0.5rem;">
            <i class="bi bi-pen me-2"></i> Signatures et Validations
          </h5>
        </div>
        
        <div class="signatures-grid">
          <!-- Signature Employé -->
          <div class="signature-box">
            <div class="signature-header">
              <i class="bi bi-person-fill me-2"></i>
              <strong>Employé / Agent</strong>
            </div>
            <div class="signature-name">
              <?= htmlspecialchars(trim($fiche['agent_nom'].' '.$fiche['agent_post_nom'])) ?>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Signature</div>
            <div class="signature-date-box">
              <span>Date : </span>
              <div class="signature-date-line"></div>
            </div>
          </div>

          <!-- Signature Superviseur -->
          <div class="signature-box">
            <div class="signature-header">
              <i class="bi bi-person-badge me-2"></i>
              <strong>Superviseur</strong>
            </div>
            <div class="signature-name">
              <?= htmlspecialchars(trim($fiche['superviseur_nom'].' '.$fiche['superviseur_post_nom'])) ?>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Signature</div>
            <div class="signature-date-box">
              <span>Date : </span>
              <div class="signature-date-line"></div>
            </div>
          </div>

          <!-- Signature Coordination -->
          <div class="signature-box">
            <div class="signature-header">
              <i class="bi bi-chat-left-text me-2"></i>
              <strong>Coordination</strong>
            </div>
            <div class="signature-name">
              <?php if ($coordComment && !empty($coordComment['coord_nom'])): ?>
                <?= htmlspecialchars(trim(($coordComment['coord_nom'] ?? '').' '.($coordComment['coord_post_nom'] ?? ''))) ?>
              <?php else: ?>
                <span style="color: #6c757d; font-style: italic;">À compléter</span>
              <?php endif; ?>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Signature et cachet</div>
            <div class="signature-date-box">
              <span>Date : </span>
              <div class="signature-date-line"></div>
            </div>
          </div>

          <!-- Signature Responsable RH -->
          <div class="signature-box">
            <div class="signature-header">
              <i class="bi bi-building me-2"></i>
              <strong>Responsable RH</strong>
            </div>
            <div class="signature-name">
              <span style="color: #6c757d; font-style: italic;">Nom et Prénom</span>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Signature et cachet</div>
            <div class="signature-date-box">
              <span>Date : </span>
              <div class="signature-date-line"></div>
            </div>
          </div>
        </div>

        <div class="signatures-footer">
          <p class="small text-muted mb-1">
            <i class="bi bi-info-circle me-1"></i>
            Ce document constitue la fiche d'évaluation officielle et doit être signé par toutes les parties concernées.
          </p>
          <p class="small text-muted mb-0">
            Les signatures attestent de la prise de connaissance et de la validation des évaluations et recommandations contenues dans ce document.
          </p>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Styles d'impression pour synthèse complète -->
<style>
/* Photos de profil pour affichage écran */
.profile-photo-complete {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border: 2px solid #3D74B9;
  flex-shrink: 0;
}

@media print {
  /* Photos de profil pour impression */
  .profile-photo-complete {
    width: 50px !important;
    height: 50px !important;
    object-fit: cover;
    border: 1px solid #333 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  
  /* Masquer navigation et boutons */
  .btn, button, .sidebar, nav, footer, .btn-group {
    display: none !important;
  }
  
  .col-md-3 {
    display: none !important;
  }
  
  .col-md-9 {
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* En-tête d'impression */
  .print-header-complete {
    display: block !important;
    text-align: center;
    margin-bottom: 20px;
    padding: 15px;
    border: 2px solid #3D74B9;
    background: #f8f9fa;
    color: #333;
    -webkit-print-color-adjust: exact !important;
  }
  
  .print-logo-complete {
    max-width: 150px;
    height: auto;
    margin-bottom: 12px;
    display: block;
    margin-left: auto;
    margin-right: auto;
    margin-top: 0;
  }
  
  .print-header-complete h2 {
    font-size: 18pt !important;
    margin: 10px 0 !important;
    color: #3D74B9 !important;
  }
  
  /* Préserver couleurs */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }
  
  /* Cards avec couleurs */
  .card {
    border: 1px solid #dee2e6 !important;
    page-break-inside: avoid;
    margin-bottom: 12px !important;
  }
  
  .card-header {
    -webkit-print-color-adjust: exact !important;
    padding: 8px 12px !important;
  }
  
  .card-header h5 {
    font-size: 13pt !important;
    margin: 0 !important;
  }
  
  h6 {
    font-size: 11pt !important;
  }
  
  .card-body {
    font-size: 10pt !important;
  }
  
  .table {
    font-size: 9pt !important;
  }
  
  .small, small {
    font-size: 8pt !important;
  }
  
  .bg-primary {
    background-color: #3D74B9 !important;
    color: white !important;
  }
  
  .bg-light {
    background-color: #f8f9fa !important;
  }
  
  .bg-secondary {
    background-color: #6c757d !important;
    color: white !important;
  }
  
  /* Badges avec couleurs */
  .badge {
    border: 1px solid #333 !important;
    padding: 3px 8px !important;
    -webkit-print-color-adjust: exact !important;
  }
  
  /* Tables */
  .table {
    page-break-inside: auto;
  }
  
  .table tr {
    page-break-inside: avoid;
    page-break-after: auto;
  }
  
  /* Éviter coupures */
  h3, h4, h5, h6 {
    page-break-after: avoid;
  }
  
  .list-group-item, .alert {
    page-break-inside: avoid;
  }
  
  /* Bordures colorées */
  [style*="border-left: 4px solid"] {
    -webkit-print-color-adjust: exact !important;
  }
  
  /* Marges de page */
  @page {
    margin: 1.5cm;
    size: A4;
  }
  
  body {
    margin: 0;
    font-size: 10pt;
  }
  
  /* Réduire espaces pour tenir sur pages */
  .mb-4 {
    margin-bottom: 15px !important;
  }
  
  .mb-3 {
    margin-bottom: 10px !important;
  }
  
  /* Section signatures - cachée à l'écran */
  .signatures-section {
    display: none;
  }

  .no-print {
    display: block;
  }

  .print-only {
    display: none;
  }

  @media print {
    /* Afficher la section signatures uniquement à l'impression */
    .signatures-section {
      display: block !important;
      margin-top: 3rem;
      page-break-before: always;
      padding: 2rem;
    }
    
    .no-print {
      display: none !important;
    }
    
    .print-only {
      display: block !important;
    }
    
    .signatures-header h5 {
      font-size: 14pt !important;
      color: #3D74B9 !important;
    }
    
    .signatures-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 2rem;
      margin-top: 2rem;
    }
    
    .signature-box {
      border: 1px solid #dee2e6;
      padding: 1.5rem;
      border-radius: 8px;
      background: #ffffff;
      page-break-inside: avoid;
    }
    
    .signature-header {
      font-size: 11pt !important;
      color: #3D74B9 !important;
      margin-bottom: 0.75rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #e9ecef;
    }
    
    .signature-name {
      font-size: 10pt !important;
      color: #212529 !important;
      font-weight: 600;
      margin-bottom: 2rem;
      min-height: 1.5rem;
    }
    
    .signature-line {
      border-top: 1px solid #000;
      margin: 3rem 0 0.5rem 0;
    }
    
    .signature-label {
      font-size: 9pt !important;
      color: #6c757d !important;
      text-align: center;
      font-style: italic;
    }
    
    .signature-date-box {
      display: flex;
      align-items: center;
      margin-top: 1rem;
      font-size: 9pt !important;
    }
    
    .signature-date-box span {
      color: #6c757d !important;
      margin-right: 0.5rem;
    }
    
    .signature-date-line {
      flex: 1;
      border-bottom: 1px solid #000;
      height: 1px;
    }
    
    .signatures-footer {
      margin-top: 2rem;
      padding-top: 1rem;
      border-top: 1px solid #dee2e6;
    }
    
    .signatures-footer p {
      font-size: 8pt !important;
      color: #6c757d !important;
      line-height: 1.4;
    }
  }
}

/* En-tête caché à l'écran */
.print-header-complete {
  display: none;
}

/* Padding à gauche uniquement à l'écran */
.fiche-padding-gauche {
  padding-left: 8rem;
}

/* À l'impression, annule le padding */
@media print {
  .fiche-padding-gauche {
    padding-left: 0 !important;
  }
}
