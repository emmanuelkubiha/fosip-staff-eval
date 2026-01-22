<?php
/**
 * pages/supervision-periode.php
 * -------------------------------------------------------------
 * OBJECTIF
 *   Vue alternative pour un superviseur : regrouper les fiches par période
 *   (mois/année), permettre de voir rapidement combien d'agents sont évalués
 *   ou en attente pour chaque période et accéder aux fiches.
 *
 * CARACTÉRISTIQUES
 *   - Regroupement par o.periode.
 *   - Compteur d'agents total vs évalués (statut supervision complet).
 *   - Tables détaillées pliables (accordion) pour chaque période.
 * -------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
$current_page = 'supervision-periode.php';
include('../includes/header.php');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) { header('Location: unauthorized.php'); exit; }
$superviseur_id = (int)$_SESSION['user_id'];

function tableExists(PDO $pdo, string $table): bool { try { $st=$pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetchColumn(); } catch(Throwable $e){ return false; } }

$search = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT 
          o.id AS fiche_id, o.periode, o.nom_projet, o.poste,
          a.id AS agent_id, a.nom AS agent_nom, a.post_nom AS agent_post_nom,
          s.id AS sup_id, s.statut AS sup_statut
        FROM objectifs o
        JOIN users a ON a.id = o.user_id AND a.superviseur_id = :sid
        LEFT JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.superviseur_id = :sid
        WHERE 1=1";
$params = [':sid'=>$superviseur_id];
if ($search !== '') {
  $sql .= " AND (o.periode LIKE :s OR a.nom LIKE :s OR a.post_nom LIKE :s OR o.nom_projet LIKE :s OR o.poste LIKE :s)";
  $params[':s'] = "%$search%";
}
$sql .= " ORDER BY o.periode DESC, a.nom ASC";

$st = $pdo->prepare($sql); $st->execute($params); $rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Regroupement par période
$grouped = [];
foreach ($rows as $r) { $p = $r['periode'] ?? '—'; if (!isset($grouped[$p])) $grouped[$p] = []; $grouped[$p][] = $r; }

?>
<div class="row">
  <div class="col-md-3"><?php include('../includes/sidebar.php'); ?></div>
  <div class="col-md-9">
    <div class="container mt-4">
      <div class="d-flex align-items-start mb-3">
        <h4 class="mb-0" style="color:#3D74B9;"><i class="bi bi-calendar3 me-2"></i> Supervision — Vue par période</h4>
        <div class="ms-auto"><a href="supervision.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-task me-1"></i> Vue fiches</a></div>
      </div>

      <p class="text-muted">Regroupe les fiches de vos agents par période (mois). Dépliez une période pour voir les détails et accéder aux évaluations.</p>

      <form class="row g-2 align-items-end mb-3" method="get" novalidate>
        <div class="col-auto">
          <label class="form-label mb-0 small">Recherche</label>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Période, agent, projet...">
        </div>
        <div class="col-auto">
          <button class="btn btn-fosip btn-sm">Filtrer</button>
          <a href="supervision-periode.php" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
        </div>
      </form>

      <?php if (empty($grouped)): ?>
        <div class="alert alert-info">Aucune fiche trouvée.</div>
      <?php else: ?>
        <div class="accordion" id="accPeriodes">
          <?php $idx=0; foreach ($grouped as $periode => $list):
            $totalAgents = count($list);
            $evalDone = 0; foreach ($list as $x) if (($x['sup_statut'] ?? '') === 'complet') $evalDone++;
            $percent = $totalAgents ? round($evalDone*100/$totalAgents) : 0;
            $collapseId = 'p'.$idx; $idx++;
          ?>
          <div class="accordion-item mb-2 shadow-sm">
            <h2 class="accordion-header" id="h<?= $collapseId ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?= $collapseId ?>" aria-expanded="false" aria-controls="c<?= $collapseId ?>">
                <div class="d-flex flex-column flex-md-row w-100">
                  <div class="me-3"><strong><?= htmlspecialchars($periode) ?></strong></div>
                  <div class="small text-muted">Agents: <?= $totalAgents ?> • Évalués: <?= $evalDone ?> (<?= $percent ?>%)</div>
                </div>
              </button>
            </h2>
            <div id="c<?= $collapseId ?>" class="accordion-collapse collapse" aria-labelledby="h<?= $collapseId ?>" data-bs-parent="#accPeriodes">
              <div class="accordion-body">
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Agent</th>
                        <th>Projet / Poste</th>
                        <th>Statut supervision</th>
                        <th style="width:120px">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($list as $r):
                        $agentNom = trim(($r['agent_nom'] ?? '').' '.($r['agent_post_nom'] ?? ''));
                        $supStatut = $r['sup_statut'] ?? null;
                        $badge = 'secondary'; $label='Non commencé';
                        if ($supStatut === 'complet') { $badge='success'; $label='Complet'; }
                        elseif ($supStatut === 'encours') { $badge='warning'; $label='En cours'; }
                      ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($agentNom) ?></strong></td>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars($r['nom_projet'] ?? '') ?></div>
                          <div class="small text-muted"><?= htmlspecialchars($r['poste'] ?? '') ?></div>
                        </td>
                        <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
                        <td>
                          <a class="btn btn-sm btn-outline-primary" href="supervision-fiche.php?fiche_id=<?= (int)$r['fiche_id'] ?>">
                            <i class="bi bi-eye me-1"></i> Voir
                          </a>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
