<?php
/**
 * pages/supervision-agent.php
 * -------------------------------------------------------------
 * OBJECTIF
 *   Vue "catalogue" pour un superviseur afin de parcourir, par personne,
 *   toutes les fiches d'objectifs existantes, avec un aperçu synthétique
 *   des auto‑évaluations et du statut de supervision.
 *
 * POINTS CLÉS
 *   - Groupement par agent (affichage par blocs).
 *   - Cartes par fiche avec indicateurs: nb objectifs, distribution auto‑éval.
 *   - Lien "Voir" menant à la page détail (lecture seule) de la fiche.
 *   - Filtres: recherche texte et période.
 *   - Affiche uniquement les agents dont l'utilisateur est superviseur.
 * -------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'supervision-agent.php';
include('../includes/header.php');

// Accès restreint au rôle superviseur ou coordination
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) {
  header('Location: unauthorized.php'); exit;
}
$superviseur_id = (int)$_SESSION['user_id'];

// Helper: tester l'existence d'une table
function tableExists(PDO $pdo, string $table): bool {
  try{ $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}

$search = trim((string)($_GET['q'] ?? ''));
$periode = trim((string)($_GET['periode'] ?? ''));

// Requête corrigée: affiche uniquement les agents dont le superviseur est l'utilisateur connecté
$sql = "SELECT 
          o.id AS fiche_id, o.periode, o.nom_projet, o.poste, o.statut AS fiche_statut,
          a.id AS agent_id, a.nom AS agent_nom, a.post_nom AS agent_post_nom, a.photo AS agent_photo,
          s.id AS sup_id, s.statut AS sup_statut, s.note AS sup_note
        FROM objectifs o
        JOIN users a ON a.id = o.user_id AND a.superviseur_id = :sid1
        LEFT JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.superviseur_id = :sid2
        WHERE 1 = 1";

$params = [':sid1' => $superviseur_id, ':sid2' => $superviseur_id];
if ($search !== '') {
  $sql .= " AND (a.nom LIKE :s OR a.post_nom LIKE :s OR o.nom_projet LIKE :s OR o.poste LIKE :s OR o.periode LIKE :s)";
  $params[':s'] = "%$search%";
}
if ($periode !== '') {
  $sql .= " AND o.periode = :p";
  $params[':p'] = $periode;
}
$sql .= " ORDER BY a.nom ASC, a.post_nom ASC, o.periode DESC, o.id DESC";

// Vérification stricte des paramètres :
// On ne garde dans $params que les clés présentes dans la requête
$finalParams = [];
foreach ($params as $k => $v) {
  if (strpos($sql, $k) !== false) {
    $finalParams[$k] = $v;
  }
}

$st = $pdo->prepare($sql);
$st->execute($finalParams);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Préparer agrégations: nombre d'items par fiche, distribution auto‑évaluation
$ficheIds = array_map(fn($r)=> (int)$r['fiche_id'], $rows);
$itemCount = []; $autoDist = [];
if (!empty($ficheIds)) {
  $in = implode(',', array_map('intval',$ficheIds));
  try {
    $q1 = $pdo->query("SELECT fiche_id, COUNT(*) cnt FROM objectifs_items WHERE fiche_id IN ($in) GROUP BY fiche_id");
    foreach ($q1->fetchAll(PDO::FETCH_ASSOC) as $r) $itemCount[(int)$r['fiche_id']] = (int)$r['cnt'];
  } catch (Throwable $e) {}
  if (tableExists($pdo,'auto_evaluation')) {
    try {
      $q2 = $pdo->query("SELECT fiche_id, note, COUNT(*) cnt FROM auto_evaluation WHERE fiche_id IN ($in) GROUP BY fiche_id, note");
      foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $fid=(int)$r['fiche_id']; $note=$r['note']; $cnt=(int)$r['cnt'];
        if (!isset($autoDist[$fid])) $autoDist[$fid]=['depasse'=>0,'atteint'=>0,'non_atteint'=>0];
        if (isset($autoDist[$fid][$note])) $autoDist[$fid][$note] = $cnt;
      }
    } catch (Throwable $e) {}
  }
}
?>
<style>
.page-header-supervision {
  background: linear-gradient(135deg, #3D74B9 0%, #5a8fd4 100%);
  color: white;
  padding: 2rem;
  border-radius: 15px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 15px rgba(61, 116, 185, 0.2);
}

.search-box-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  margin-bottom: 2rem;
  border: 2px solid #e9ecef;
}

.agent-section-header {
  background: linear-gradient(135deg, rgba(61, 116, 185, 0.08) 0%, rgba(61, 116, 185, 0.03) 100%);
  border-radius: 12px;
  padding: 1rem 1.25rem;
  margin-top: 2rem;
  margin-bottom: 1rem;
  border-left: 4px solid #3D74B9;
}

.fiche-card-supervision {
  background: white;
  border-radius: 12px;
  border: 2px solid #e9ecef;
  transition: all 0.3s ease;
  height: 100%;
}

.fiche-card-supervision:hover {
  border-color: #3D74B9;
  box-shadow: 0 6px 20px rgba(61, 116, 185, 0.15);
  transform: translateY(-4px);
}

.fiche-card-supervision .card-body {
  padding: 1.25rem;
}

.badge-supervision {
  padding: 0.4rem 0.875rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.75rem;
}
</style>

<div class="row">
  <div class="col-md-3 sidebar-col">
    <div class="sidebar-wrapper">
      <?php include('../includes/sidebar.php'); ?>
    </div>
  </div>
  <div class="col-md-9 content-col">
    <div class="container mt-4">

      <!-- En-tête avec gradient -->
      <div class="page-header-supervision">
        <div class="d-flex align-items-start justify-content-between">
          <div class="flex-grow-1">
            <h4 class="mb-2"><i class="bi bi-people me-2"></i> Supervision — Vue par Agent</h4>
            <p class="mb-0 opacity-90">Consultez et gérez les fiches d'évaluation de vos agents, organisées par personne</p>
          </div>
          <a href="supervision.php?statut=encours" class="btn btn-light px-4" style="border-radius: 25px; font-weight: 600;">
            <i class="bi bi-list-task me-2"></i> À évaluer
          </a>
        </div>
      </div>

      <!-- Carte de recherche -->
      <div class="search-box-card">
        <h6 class="mb-3" style="color: #3D74B9;"><i class="bi bi-funnel me-2"></i>Filtres de recherche</h6>
        <form class="row g-3" method="get" novalidate>
          <div class="col-md-6">
            <label class="form-label small fw-semibold"><i class="bi bi-search me-1"></i>Recherche</label>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Agent, projet, poste, période..." style="border-radius: 8px;">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold"><i class="bi bi-calendar-range me-1"></i>Période</label>
            <input type="month" name="periode" value="<?= htmlspecialchars($periode) ?>" class="form-control" style="border-radius: 8px;">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="d-flex gap-2 w-100">
              <button class="btn btn-primary flex-grow-1" style="border-radius: 8px;">
                <i class="bi bi-funnel-fill me-1"></i>Filtrer
              </button>
              <a href="supervision-agent.php" class="btn btn-outline-secondary" style="border-radius: 8px;" title="Réinitialiser">
                <i class="bi bi-arrow-clockwise"></i>
              </a>
            </div>
          </div>
        </form>
      </div>

      <?php if (empty($rows)): ?>
        <div class="alert alert-info d-flex align-items-center" style="border-radius: 12px; border-left: 4px solid #0dcaf0;">
          <i class="bi bi-info-circle me-3" style="font-size: 1.5rem;"></i>
          <div>
            <strong>Aucune fiche trouvée</strong>
            <p class="mb-0 small">Aucun agent ou fiche ne correspond à vos critères de recherche.</p>
          </div>
        </div>
      <?php else: ?>
        <?php
          $currentAgent = null;
          foreach ($rows as $r):
            $agentKey = $r['agent_id'];
            if ($currentAgent !== $agentKey):
              if ($currentAgent !== null) echo '</div>'; // close previous grid
              $currentAgent = $agentKey;
        ?>
          <div class="agent-section-header">
            <div class="d-flex align-items-center">
              <?php
                $agentPhoto = $r['agent_photo'] ?? 'default.PNG';
                $photoPath = '../assets/img/profiles/' . htmlspecialchars($agentPhoto);
              ?>
              <img src="<?= $photoPath ?>" 
                   alt="Photo" 
                   class="rounded-circle me-3"
                   style="width: 48px; height: 48px; object-fit: cover; border: 3px solid #3D74B9; box-shadow: 0 2px 8px rgba(61, 116, 185, 0.2);"
                   onerror="this.src='../assets/img/profiles/default.PNG'">
              <div>
                <h5 class="mb-0" style="color: #3D74B9;"><?= htmlspecialchars(trim(($r['agent_nom'] ?? '').' '.($r['agent_post_nom'] ?? ''))) ?></h5>
                <small class="text-muted"><i class="bi bi-person-badge me-1"></i>Agent supervisé</small>
              </div>
            </div>
          </div>
          <div class="row g-3">
        <?php endif; ?>
            <?php
              $fid = (int)$r['fiche_id'];
              $itCnt = $itemCount[$fid] ?? 0;
              $ad = $autoDist[$fid] ?? ['depasse'=>0,'atteint'=>0,'non_atteint'=>0];
              $ficheStatut = $r['fiche_statut'] ?? '';
              $supStatut = $r['sup_statut'] ?? null;
              
              // Système de badges basé sur le statut de la fiche dans objectifs.statut
              $badge = 'secondary'; $label = 'Non évalué';
              
              // Priorité au statut de la fiche objectif
              if ($ficheStatut === 'termine') {
                // Fiche commentée par la coordination
                $badge = 'success'; 
                $label = 'Terminé';
              } elseif ($ficheStatut === 'evalue') {
                // Fiche évaluée par le superviseur, en attente coordination
                $badge = 'info'; 
                $label = 'Évalué';
              } elseif ($ficheStatut === 'encours' || $ficheStatut === 'attente') {
                // Fiche en cours d'évaluation par l'agent ou en attente d'évaluation superviseur
                if ($supStatut === 'encours') {
                  $badge = 'warning'; 
                  $label = 'En cours d\'évaluation';
                } else {
                  $badge = 'secondary'; 
                  $label = 'Non évalué';
                }
              }
            ?>
            <div class="col-md-6 col-xl-4">
              <div class="fiche-card-supervision">
                <div class="card-body d-flex flex-column">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center flex-grow-1">
                      <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                        <i class="bi bi-clipboard2-data" style="color:#3D74B9; font-size: 1.25rem;"></i>
                      </div>
                      <div>
                        <div class="small text-muted">Période</div>
                        <div class="fw-semibold" style="color: #3D74B9;"><?= htmlspecialchars($r['periode'] ?? '') ?></div>
                      </div>
                    </div>
                    <span class="badge badge-supervision bg-<?= $badge ?>"><?= $label ?></span>
                  </div>

                  <div class="mb-3">
                    <div class="fw-bold text-truncate mb-1" title="<?= htmlspecialchars($r['nom_projet'] ?? '') ?>" style="color: #2c3e50;">
                      <i class="bi bi-kanban me-1" style="color: #3D74B9;"></i>
                      <?= htmlspecialchars($r['nom_projet'] ?? '') ?>
                    </div>
                    <div class="small text-muted text-truncate" title="<?= htmlspecialchars($r['poste'] ?? '') ?>">
                      <i class="bi bi-briefcase me-1"></i>
                      <?= htmlspecialchars($r['poste'] ?? '') ?>
                    </div>
                  </div>

                  <div class="small mb-3">
                    <div class="d-flex align-items-center mb-2 p-2" style="background: rgba(61, 116, 185, 0.05); border-radius: 8px;">
                      <i class="bi bi-list-check me-2" style="color:#3D74B9; font-size: 1.1rem;"></i>
                      <span class="fw-semibold"><?= $itCnt ?> objectif<?= $itCnt>1?'s':'' ?></span>
                    </div>
                    <div class="p-2" style="background: rgba(108, 117, 125, 0.05); border-radius: 8px;">
                      <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-speedometer2 me-2" style="color:#3D74B9;"></i>
                        <span class="fw-semibold small">Auto-évaluation:</span>
                      </div>
                      <div class="d-flex gap-2 flex-wrap ms-4">
                        <span class="badge bg-success bg-opacity-10 text-success" style="border: 1px solid rgba(25, 135, 84, 0.3);">
                          <i class="bi bi-arrow-up-circle-fill me-1"></i><?= (int)$ad['depasse'] ?>
                        </span>
                        <span class="badge bg-warning bg-opacity-10 text-warning" style="border: 1px solid rgba(255, 193, 7, 0.3);">
                          <i class="bi bi-dash-circle-fill me-1"></i><?= (int)$ad['atteint'] ?>
                        </span>
                        <span class="badge bg-danger bg-opacity-10 text-danger" style="border: 1px solid rgba(220, 53, 69, 0.3);">
                          <i class="bi bi-arrow-down-circle-fill me-1"></i><?= (int)$ad['non_atteint'] ?>
                        </span>
                      </div>
                    </div>
                  </div>

                  <div class="mt-auto">
                    <a class="btn btn-primary w-100" href="supervision-fiche.php?fiche_id=<?= $fid ?>" style="border-radius: 8px; font-weight: 600;">
                      <i class="bi bi-eye me-2"></i> Voir la fiche
                    </a>
                  </div>
                </div>
              </div>
            </div>
        <?php endforeach; if ($currentAgent !== null) echo '</div>'; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
