<?php
// dashboard.php
// Tableau de bord principal — compatible avec les rôles : admin, staff, superviseur, coordination

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once('../includes/db.php');
include('../includes/header.php');

// Récupération des infos utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nom, post_nom, email, role, fonction, photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$nom = $user['nom'] ?? 'Utilisateur';
$postnom = $user['post_nom'] ?? '';
$email = $user['email'] ?? '';
$role = $user['role'] ?? 'invité';
$fonction = $user['fonction'] ?? 'Non défini';
$photo = $user['photo'] ?? 'default.PNG';
$profile_base = '../assets/img/profiles/';
$photo_path = $profile_base . htmlspecialchars($photo);

// Périodes et stats personnelles (6 derniers mois)
$mois = [];
for ($i = 5; $i >= 0; $i--) { $mois[] = date('Y-m', strtotime("-$i months")); }
$stats = [];
foreach ($mois as $periode) {
  $st = $pdo->prepare("SELECT
      SUM(CASE WHEN statut='termine' THEN 1 ELSE 0 END) AS complet,
      SUM(CASE WHEN statut='encours' THEN 1 ELSE 0 END) AS encours,
      SUM(CASE WHEN statut='attente' THEN 1 ELSE 0 END) AS attente
    FROM objectifs WHERE user_id=? AND periode=?");
  $st->execute([$user_id, $periode]);
  $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $stats[$periode] = [
    'complet' => (int)($r['complet'] ?? 0),
    'encours' => (int)($r['encours'] ?? 0),
    'attente' => (int)($r['attente'] ?? 0),
  ];
}
$score_total = 0; $score_max = 0;
foreach ($stats as $d) { $score_total += $d['complet']*2 + $d['encours']; $score_max += ($d['complet']+$d['encours']+$d['attente'])*2; }
$performance_ratio = $score_max > 0 ? round($score_total*100/$score_max) : 0;
$contenu = '';

// Calculs complémentaires pour la synthèse (mois en difficulté, meilleur mois, dernière cote)
// 1) Auto-évaluations "non atteints" par période (pour le graphique et le pire mois)
$autoNonAtteints = array_fill_keys($mois, 0);
try {
  if (!empty($mois)) {
    $placeholders = implode(',', array_fill(0, count($mois), '?'));
    $sqlNA = "SELECT o.periode, COUNT(*) AS na
              FROM auto_evaluation ae
              JOIN objectifs o ON o.id = ae.fiche_id AND o.user_id = ae.user_id
              WHERE o.user_id = ? AND o.periode IN ($placeholders) AND ae.note = 'non_atteint'
              GROUP BY o.periode";
    $stmtNA = $pdo->prepare($sqlNA);
    $paramsNA = array_merge([$user_id], $mois);
    $stmtNA->execute($paramsNA);
    foreach ($stmtNA->fetchAll(PDO::FETCH_ASSOC) as $rowNA) {
      $p = $rowNA['periode'] ?? null;
      if ($p !== null && isset($autoNonAtteints[$p])) {
        $autoNonAtteints[$p] = (int)($rowNA['na'] ?? 0);
      }
    }
  }
} catch (Throwable $e) { /* silencieux: on garde des zéros */ }

// 2) Mois en difficulté (max de non atteints)
$worstMonth = '';
$worstValue = -1;
foreach ($mois as $p) {
  $val = (int)($autoNonAtteints[$p] ?? 0);
  if ($val > $worstValue) { $worstValue = $val; $worstMonth = $p; }
}
if ($worstMonth === '') { $worstMonth = $mois[count($mois)-1] ?? ''; $worstValue = max(0, $worstValue); }

// 3) Meilleur mois (max d'objectifs complétés)
$bestMonth = '';
$bestValue = -1;
foreach ($mois as $p) {
  $val = (int)($stats[$p]['complet'] ?? 0);
  if ($val > $bestValue) { $bestValue = $val; $bestMonth = $p; }
}
if ($bestMonth === '') { $bestMonth = $mois[count($mois)-1] ?? ''; $bestValue = max(0, $bestValue); }

// 4) Cote dernière évaluation (période la plus récente)
$lastScore = 0;
$lastPeriode = $mois[count($mois)-1] ?? null;
if ($lastPeriode && isset($stats[$lastPeriode])) {
  $d = $stats[$lastPeriode];
  $maxPot = (($d['complet'] ?? 0) + ($d['encours'] ?? 0) + ($d['attente'] ?? 0)) * 2;
  if ($maxPot > 0) {
    $lastScore = (int)round(((($d['complet'] ?? 0) * 2) + ($d['encours'] ?? 0)) * 100 / $maxPot);
  }
}

// 5) Sécuriser d'éventuelles références hors contexte (ex.: $priorites dans la vue Performance)
if (!isset($priorites)) { $priorites = []; }

// ===== STATISTIQUES GLOBALES POUR COORDINATION ET ADMIN =====
if (in_array($role, ['coordination', 'admin'])) {
  // Recalculer les stats globales pour tous les utilisateurs sur 6 derniers mois
  
  // 1) Mois en difficulté : période avec le plus de non_atteints
  $worstMonth = '';
  $worstValue = '';
  $worstCount = -1;
  
  foreach ($mois as $p) {
    try {
      // Total objectifs de la période
      $stTotal = $pdo->prepare("SELECT COUNT(*) FROM objectifs_items oi 
        JOIN objectifs o ON o.id = oi.fiche_id 
        WHERE o.periode = ?");
      $stTotal->execute([$p]);
      $totalObj = (int)$stTotal->fetchColumn();
      
      // Objectifs non atteints de la période (note = 'non_atteint')
      $stNA = $pdo->prepare("SELECT COUNT(*) FROM auto_evaluation ae 
        JOIN objectifs o ON o.id = ae.fiche_id 
        WHERE o.periode = ? AND ae.note = 'non_atteint'");
      $stNA->execute([$p]);
      $nonAtteints = (int)$stNA->fetchColumn();
      
      if ($totalObj > 0 && $nonAtteints > $worstCount) {
        $worstCount = $nonAtteints;
        $worstMonth = $p;
        $worstValue = $nonAtteints . '/' . $totalObj;
      }
    } catch (Throwable $e) { /* silent */ }
  }
  
  // 2) Meilleur mois : période avec le plus d'objectifs atteints
  $bestMonth = '';
  $bestValue = '';
  $bestCount = -1;
  
  foreach ($mois as $p) {
    try {
      // Total objectifs de la période
      $stTotal = $pdo->prepare("SELECT COUNT(*) FROM objectifs_items oi 
        JOIN objectifs o ON o.id = oi.fiche_id 
        WHERE o.periode = ?");
      $stTotal->execute([$p]);
      $totalObj = (int)$stTotal->fetchColumn();
      
      // Objectifs atteints (note IN ('atteint', 'depasse'))
      $stAtteints = $pdo->prepare("SELECT COUNT(*) FROM auto_evaluation ae 
        JOIN objectifs o ON o.id = ae.fiche_id 
        WHERE o.periode = ? AND ae.note IN ('atteint', 'depasse')");
      $stAtteints->execute([$p]);
      $atteints = (int)$stAtteints->fetchColumn();
      
      if ($totalObj > 0 && $atteints > $bestCount) {
        $bestCount = $atteints;
        $bestMonth = $p;
        $bestValue = $atteints . '/' . $totalObj;
      }
    } catch (Throwable $e) { /* silent */ }
  }
  
  // 3) Cote moyenne dernière évaluation : moyenne globale de tous les utilisateurs
  $lastScore = 0;
  $lastPeriode = $mois[count($mois)-1] ?? null;
  
  if ($lastPeriode) {
    try {
      // Total objectifs items de la dernière période
      $stTotal = $pdo->prepare("SELECT COUNT(*) FROM objectifs_items oi 
        JOIN objectifs o ON o.id = oi.fiche_id 
        WHERE o.periode = ?");
      $stTotal->execute([$lastPeriode]);
      $totalObjLast = (int)$stTotal->fetchColumn();
      
      // Objectifs atteints + dépassés (note IN ('atteint', 'depasse'))
      $stAtteints = $pdo->prepare("SELECT COUNT(*) FROM auto_evaluation ae 
        JOIN objectifs o ON o.id = ae.fiche_id 
        WHERE o.periode = ? AND ae.note IN ('atteint', 'depasse')");
      $stAtteints->execute([$lastPeriode]);
      $atteintsLast = (int)$stAtteints->fetchColumn();
      
      if ($totalObjLast > 0) {
        $lastScore = round(($atteintsLast / $totalObjLast) * 100);
      }
    } catch (Throwable $e) { /* silent */ }
  }
}

// Coordination : tableau de bord orienté décisions
if ($role === 'coordination') {
  // KPIs principaux utiles à la coordination
  $kpi = [ 'pending'=>0, 'commented'=>0, 'median_delay'=>0, 'overdue'=>0, 'closed_30'=>0, 'non_atteints'=>0 ];

  // 1) Backlog en attente (statut = evalue) et fiches clôturées (statut = termine)
  $q = $pdo->query("SELECT\n      SUM(CASE WHEN o.statut = 'evalue' THEN 1 ELSE 0 END) AS pending,\n      SUM(CASE WHEN o.statut = 'termine' THEN 1 ELSE 0 END) AS commented\n    FROM objectifs o\n    JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut = 'complet'");
  $r = $q ? ($q->fetch(PDO::FETCH_ASSOC) ?: []) : [];
  $kpi['pending'] = (int)($r['pending'] ?? 0);
  $kpi['commented'] = (int)($r['commented'] ?? 0);

  // 2) Délai médian entre validation supervision et commentaire coordination (sur 6 derniers mois)
  $delays = [];
  try {
    $q = $pdo->query("SELECT GREATEST(DATEDIFF(c.date_commentaire, s.date_validation),0) AS d\n      FROM objectifs o\n      JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut = 'complet'\n      JOIN coordination_commentaires c ON c.fiche_id = o.id\n      WHERE o.statut='termine'\n        AND s.date_validation IS NOT NULL\n        AND c.date_commentaire IS NOT NULL\n        AND c.date_commentaire >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) { $delays[] = (int)max(0, (int)($row['d'] ?? 0)); }
  } catch (Throwable $e) { $delays = []; }
  if (!empty($delays)) { sort($delays); $mid = (int)floor(count($delays)/2); $kpi['median_delay'] = (count($delays)%2===0) ? (int)round(($delays[$mid-1]+$delays[$mid])/2) : $delays[$mid]; }

  // 3) En retard (>7 jours depuis validation supervision, toujours en attente de commentaire)
  try {
    $q = $pdo->query("SELECT COUNT(*) FROM objectifs o\n      JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut='complet'\n      WHERE o.statut='evalue'\n        AND s.date_validation IS NOT NULL\n        AND DATEDIFF(CURDATE(), s.date_validation) > 7");
    $kpi['overdue'] = (int)($q->fetchColumn() ?: 0);
  } catch (Throwable $e) { $kpi['overdue'] = 0; }

  // 4) Fiches clôturées dans les 30 derniers jours (débit/throughput)
  try {
    $q = $pdo->query("SELECT COUNT(*) FROM objectifs o\n      JOIN coordination_commentaires c ON c.fiche_id = o.id\n      WHERE o.statut='termine' AND c.date_commentaire >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $kpi['closed_30'] = (int)($q->fetchColumn() ?: 0);
  } catch (Throwable $e) { $kpi['closed_30'] = 0; }

  // 4b) Nombre d'objectifs (items) non atteints sur la dernière période
  try {
    $latestPeriode = $pdo->query("SELECT MAX(periode) FROM objectifs")->fetchColumn();
    if ($latestPeriode) {
      $stNA = $pdo->prepare("SELECT COUNT(*)
        FROM auto_evaluation ae
        JOIN objectifs o ON o.id = ae.fiche_id AND o.user_id = ae.user_id
        WHERE o.periode = ? AND ae.note = 'non_atteint'");
      $stNA->execute([$latestPeriode]);
      $kpi['non_atteints'] = (int)($stNA->fetchColumn() ?: 0);
    }
  } catch (Throwable $e) { $kpi['non_atteints'] = 0; }

  // 5) Top 5 superviseurs avec fiches en attente de commentaire
  $topSup = [];
  try {
    $q = $pdo->query("SELECT o.superviseur_id, u.nom, u.post_nom, COUNT(*) AS en_attente\n      FROM objectifs o\n      JOIN users u ON u.id = o.superviseur_id\n      JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut = 'complet'\n      LEFT JOIN coordination_commentaires c ON c.fiche_id = o.id\n      WHERE o.statut='evalue'\n      GROUP BY o.superviseur_id\n      ORDER BY en_attente DESC\n      LIMIT 5");
    $topSup = $q->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $topSup = []; }

  // 6) Répartition par période (6 derniers mois)
  $periods = [];
  for ($i=5;$i>=0;$i--) $periods[] = date('Y-m', strtotime("-$i months"));
  $byPeriod = array_fill_keys($periods, ['pending'=>0,'commented'=>0]);
  $in = "('" . implode("','", array_map('addslashes', $periods)) . "')";
  try {
    $qr = $pdo->query("SELECT o.periode,\n        SUM(CASE WHEN o.statut = 'evalue' THEN 1 ELSE 0 END) AS pending,\n        SUM(CASE WHEN o.statut = 'termine' THEN 1 ELSE 0 END) AS commented\n      FROM objectifs o\n      JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut = 'complet'\n      WHERE o.periode IN $in\n      GROUP BY o.periode");
    foreach ($qr->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $p = $row['periode']; if (!isset($byPeriod[$p])) continue;
      $byPeriod[$p]['pending'] = (int)$row['pending'];
      $byPeriod[$p]['commented'] = (int)$row['commented'];
    }
  } catch (Throwable $e) { /* silent */ }

  // 7) Backlog par ancienneté et priorités (top 10 les plus anciens)
  $ageBuckets = ['b0_7'=>0,'b8_14'=>0,'b15_30'=>0,'b30'=>0];
  try {
    $q = $pdo->query("SELECT\n        SUM(CASE WHEN DATEDIFF(CURDATE(), s.date_validation) BETWEEN 0 AND 7 THEN 1 ELSE 0 END) AS b0_7,\n        SUM(CASE WHEN DATEDIFF(CURDATE(), s.date_validation) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) AS b8_14,\n        SUM(CASE WHEN DATEDIFF(CURDATE(), s.date_validation) BETWEEN 15 AND 30 THEN 1 ELSE 0 END) AS b15_30,\n        SUM(CASE WHEN DATEDIFF(CURDATE(), s.date_validation) > 30 THEN 1 ELSE 0 END) AS b30\n      FROM objectifs o\n      JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut='complet'\n      WHERE o.statut='evalue' AND s.date_validation IS NOT NULL");
    $ageBuckets = $q->fetch(PDO::FETCH_ASSOC) ?: $ageBuckets;
  } catch (Throwable $e) { /* ignore */ }

  $priorites = [];
  try {
    $st = $pdo->query("SELECT o.id, o.periode, u.nom, u.post_nom, sup.nom AS sup_nom, sup.post_nom AS sup_post,\n        GREATEST(DATEDIFF(CURDATE(), s.date_validation),0) AS age\n      FROM objectifs o\n      JOIN users u ON u.id = o.user_id\n      LEFT JOIN users sup ON sup.id = o.superviseur_id\n      JOIN supervisions s ON s.agent_id = o.user_id AND s.periode = o.periode AND s.statut='complet'\n      WHERE o.statut='evalue' AND s.date_validation IS NOT NULL\n      ORDER BY age DESC\n      LIMIT 10");
    $priorites = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $priorites = []; }

  // Rendu UI pour coordination
  ob_start();
  ?>
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10"><i class="bi bi-hourglass-split text-warning" style="font-size:1.4rem;"></i></div>
          <div>
            <div class="small text-muted d-flex align-items-center">En attente <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Fiches validées par la supervision en attente du commentaire de coordination (backlog actuel)."></i></div>
            <div class="h4 mb-0 text-warning"><?= (int)$kpi['pending'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-success bg-opacity-10"><i class="bi bi-flag text-success" style="font-size:1.4rem;"></i></div>
          <div>
            <div class="small text-muted d-flex align-items-center">Clôturées <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Fiches avec commentaire final enregistré (terminées)."></i></div>
            <div class="h4 mb-0 text-success"><?= (int)$kpi['commented'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10"><i class="bi bi-stopwatch text-primary" style="font-size:1.4rem;"></i></div>
          <div>
            <div class="small text-muted d-flex align-items-center">Délai médian <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Nombre de jours (médiane) entre la validation de la supervision et le commentaire de coordination (6 derniers mois)."></i></div>
            <div class="h4 mb-0" style="color:#3D74B9;">
              <?= (int)$kpi['median_delay'] ?> j
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-danger bg-opacity-10"><i class="bi bi-exclamation-triangle text-danger" style="font-size:1.4rem;"></i></div>
          <div>
            <div class="small text-muted d-flex align-items-center">En retard > 7 j <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Fiches en attente depuis plus de 7 jours après validation de la supervision (priorité de traitement)."></i></div>
            <div class="h4 mb-0 text-danger"><?= (int)$kpi['overdue'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-danger bg-opacity-10"><i class="bi bi-x-octagon text-danger" style="font-size:1.4rem;"></i></div>
          <div>
            <div class="small text-muted d-flex align-items-center">Objectifs non atteints <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Nombre d'objectifs (items) auto-évalués en 'non atteint' sur la dernière période."></i></div>
            <div class="h4 mb-0 text-danger"><?= (int)$kpi['non_atteints'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-bottom-0">
          <h6 class="mb-0" style="color:#3D74B9;"><i class="bi bi-layers-half me-2"></i> Backlog par ancienneté <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Répartition des fiches en attente selon l'ancienneté depuis la validation de la supervision."></i></h6>
        </div>
        <div class="card-body">
          <?php
            $totalBacklog = max(1, (int)$kpi['pending']);
            $b0_7 = (int)($ageBuckets['b0_7'] ?? 0); $p0_7 = (int)round($b0_7*100/$totalBacklog);
            $b8_14 = (int)($ageBuckets['b8_14'] ?? 0); $p8_14 = (int)round($b8_14*100/$totalBacklog);
            $b15_30 = (int)($ageBuckets['b15_30'] ?? 0); $p15_30 = (int)round($b15_30*100/$totalBacklog);
            $b30 = (int)($ageBuckets['b30'] ?? 0); $p30 = (int)round($b30*100/$totalBacklog);
          ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span>0–7 jours</span><span><?= $b0_7 ?> (<?= $p0_7 ?>%)</span></div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-success" style="width: <?= $p0_7 ?>%"></div>
            </div>
          </div>
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span>8–14 jours</span><span><?= $b8_14 ?> (<?= $p8_14 ?>%)</span></div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-warning text-dark" style="width: <?= $p8_14 ?>%"></div>
            </div>
          </div>
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span>15–30 jours</span><span><?= $b15_30 ?> (<?= $p15_30 ?>%)</span></div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-orange" style="background-color:#fd7e14;width: <?= $p15_30 ?>%"></div>
            </div>
          </div>
          <div>
            <div class="d-flex justify-content-between small"><span>> 30 jours</span><span><?= $b30 ?> (<?= $p30 ?>%)</span></div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-danger" style="width: <?= $p30 ?>%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-bottom-0 d-flex align-items-center justify-content-between">
          <h6 class="mb-0" style="color:#3D74B9;"><i class="bi bi-lightning-charge me-2"></i> Clôturées (30 derniers jours) <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Nombre de fiches clôturées au cours des 30 derniers jours (débit de traitement)."></i></h6>
          <span class="badge bg-light text-dark"><?= (int)$kpi['closed_30'] ?></span>
        </div>
        <div class="card-body small text-muted">
          Poursuivez un rythme régulier pour réduire le backlog et respecter le SLA interne.
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-secondary small border-0">
    <strong>Glossaire.</strong>
    <strong>Backlog</strong> = ensemble des fiches déjà validées par la supervision mais encore en attente du commentaire de coordination.
    <strong>SLA</strong> = délai cible interne pour traiter une fiche (ex.: 7 jours après la validation de la supervision).
  </div>

  <div class="row mb-4 g-3">
    <div class="col-md-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 d-flex align-items-center justify-content-between">
          <h6 class="mb-0" style="color:#3D74B9;"><i class="bi bi-list-stars me-2"></i> À traiter en priorité <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Top 10 des fiches en attente depuis le plus longtemps."></i></h6>
          <a href="coordination.php?filter=encours" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right-circle me-1"></i> Ouvrir la liste</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($priorites)): ?>
            <div class="p-3 small text-muted">Pas de backlog prioritaire.</div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr><th>ID</th><th>Employé</th><th>Superviseur</th><th>Période</th><th class="text-end">Âge (jours)</th></tr>
              </thead>
              <tbody>
                <?php foreach ($priorites as $pr): $nm = trim(($pr['nom']??'').' '.($pr['post_nom']??'')); $snm = trim(($pr['sup_nom']??'').' '.($pr['sup_post']??'')); ?>
                <tr>
                  <td><?= (int)$pr['id'] ?></td>
                  <td><?= htmlspecialchars($nm) ?></td>
                  <td><?= htmlspecialchars($snm ?: '—') ?></td>
                  <td><?= htmlspecialchars($pr['periode'] ?? '') ?></td>
                  <td class="text-end"><span class="badge bg-danger"><?= (int)$pr['age'] ?> j</span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php
  $contenu = ob_get_clean();
}

// Superviseur : agents supervisés à faible performance
elseif ($role === 'superviseur') {
  $stmt = $pdo->prepare("
    SELECT u.nom, u.post_nom, COUNT(o.id) AS total,
      SUM(CASE WHEN o.statut = 'termine' THEN 1 ELSE 0 END) AS complet
    FROM users u
    LEFT JOIN objectifs o ON o.user_id = u.id
    WHERE u.superviseur_id = ?
    GROUP BY u.id
    HAVING complet < total / 2
    LIMIT 50
  ");
  $stmt->execute([$user_id]);
  $faibles = $stmt->fetchAll();

  $contenu = "
    <div class='card border-danger mb-4'>
      <div class='card-body'>
        <h5 class='card-title text-danger'><i class='bi bi-people me-2'></i> Agents à faible performance</h5>
        <ul class='mb-0'>";
  foreach ($faibles as $f) {
    $nomf = htmlspecialchars($f['nom'] . ' ' . $f['post_nom']);
    $contenu .= "<li>{$nomf} — " . (int)$f['complet'] . " sur " . (int)$f['total'] . " objectifs</li>";
  }
  $contenu .= "</ul></div></div>";
}

// Staff : vue personnelle simple (peut être étendue)
elseif ($role === 'staff') {
  $contenu = "
    <div class='card border-secondary mb-4'>
      <div class='card-body'>
        <h5 class='card-title text-secondary'><i class='bi bi-person-lines-fill me-2'></i> Vue personnelle</h5>
        <p class='mb-0'>Suivez vos objectifs, validations et commentaires. Consultez l'historique pour améliorer votre performance.</p>
      </div>
    </div>";
}

// Admin : vue de gestion améliorée
elseif ($role === 'admin') {
  // KPIs administration de base
  $total_users = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0);
  $no_email = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE email IS NULL OR TRIM(email) = ''")->fetchColumn() ?: 0);
  $no_fonction = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE fonction IS NULL OR TRIM(fonction) = ''")->fetchColumn() ?: 0);
  $agents_no_superv = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('staff','agent') AND (superviseur_id IS NULL OR superviseur_id = 0)")->fetchColumn() ?: 0);
  
  // Statistiques supplémentaires
  $total_fiches = (int)($pdo->query("SELECT COUNT(*) FROM objectifs")->fetchColumn() ?: 0);
  $fiches_terminees = (int)($pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut = 'termine'")->fetchColumn() ?: 0);
  $fiches_encours = (int)($pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut IN ('encours', 'attente')")->fetchColumn() ?: 0);
  
  // Répartition par rôle
  $roles = ['admin','coordination','superviseur','staff','agent'];
  $role_counts = array_fill_keys($roles, 0);
  foreach ($pdo->query("SELECT role, COUNT(*) c FROM users GROUP BY role") as $r) { 
    $role_counts[$r['role']] = (int)$r['c']; 
  }
  
  // Derniers utilisateurs créés (triés par ID car pas de colonne created_at)
  $last_users = $pdo->query("SELECT nom, post_nom, email, role FROM users ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

  ob_start();
  ?>
  <!-- Statistiques globales -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10">
            <i class="bi bi-people text-primary" style="font-size:1.4rem;"></i>
          </div>
          <div>
            <div class="small text-muted">Utilisateurs</div>
            <div class="h4 mb-0 text-primary"><?= $total_users ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-success bg-opacity-10">
            <i class="bi bi-clipboard-check text-success" style="font-size:1.4rem;"></i>
          </div>
          <div>
            <div class="small text-muted">Fiches terminées</div>
            <div class="h4 mb-0 text-success"><?= $fiches_terminees ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10">
            <i class="bi bi-hourglass-split text-warning" style="font-size:1.4rem;"></i>
          </div>
          <div>
            <div class="small text-muted">Fiches en cours</div>
            <div class="h4 mb-0 text-warning"><?= $fiches_encours ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-3 bg-danger bg-opacity-10">
            <i class="bi bi-exclamation-triangle text-danger" style="font-size:1.4rem;"></i>
          </div>
          <div>
            <div class="small text-muted">Profils incomplets</div>
            <div class="h4 mb-0 text-danger"><?= $no_email + $no_fonction + $agents_no_superv ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Répartition par rôle et derniers utilisateurs -->
  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-bottom-0">
          <h6 class="mb-0 text-primary"><i class="bi bi-pie-chart me-2"></i> Répartition par rôle</h6>
        </div>
        <div class="card-body">
          <?php foreach ($role_counts as $r => $c): if ($c > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-capitalize"><?= htmlspecialchars($r) ?></span>
              <div class="d-flex align-items-center gap-2">
                <div class="progress" style="width: 100px; height: 8px;">
                  <div class="progress-bar bg-primary" style="width: <?= round(($c / max(1, $total_users)) * 100) ?>%"></div>
                </div>
                <span class="badge bg-primary"><?= $c ?></span>
              </div>
            </div>
          <?php endif; endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-bottom-0">
          <h6 class="mb-0 text-primary"><i class="bi bi-clock-history me-2"></i> Derniers utilisateurs</h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($last_users as $u): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-bold"><?= htmlspecialchars($u['nom'] . ' ' . $u['post_nom']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                </div>
                <span class="badge bg-primary"><?= htmlspecialchars(ucfirst($u['role'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions rapides -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom-0">
      <h6 class="mb-0 text-primary"><i class="bi bi-lightning me-2"></i> Actions rapides</h6>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <a href="user_add.php" class="btn btn-outline-primary w-100">
            <i class="bi bi-person-plus me-2"></i> Nouvel utilisateur
          </a>
        </div>
        <div class="col-md-3">
          <a href="users.php" class="btn btn-outline-primary w-100">
            <i class="bi bi-people me-2"></i> Gérer les utilisateurs
          </a>
        </div>
        <div class="col-md-3">
          <a href="admin-fiches.php" class="btn btn-outline-primary w-100">
            <i class="bi bi-clipboard-data me-2"></i> Gérer les fiches
          </a>
        </div>
        <div class="col-md-3">
          <a href="admin-config.php" class="btn btn-outline-primary w-100">
            <i class="bi bi-gear me-2"></i> Configurations
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php
  $contenu = ob_get_clean();
}

?>
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar desktop et offcanvas mobile intégrés dans la colonne -->
    <div class="col-lg-3 col-xl-3 sidebar-zone">
      <?php include('../includes/sidebar.php'); ?>
    </div>
    <div class="col-12 col-lg-9 col-xl-9 p-4 dashboard-main">

      <!-- Synthèse nouvelle -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 p-3 bg-danger bg-opacity-10">
                <i class="bi bi-thermometer-high text-danger fs-4"></i>
              </div>
              <div class="flex-grow-1">
                <div class="small text-muted d-flex align-items-center">
                  Mois en difficulté
                  <i class="bi bi-info-circle ms-1 text-muted" 
                     data-bs-toggle="tooltip" 
                     data-bs-placement="top"
                     title="<?= in_array($role, ['coordination', 'admin']) ? 'Période où l\'ensemble du staff a eu le plus d\'objectifs non atteints par rapport au total. Indicateur global de performance organisationnelle.' : 'Période où vous avez eu le plus d\'objectifs non atteints. Identifiez les obstacles rencontrés.' ?>"></i>
                </div>
                <div class="h5 mb-0">
                  <?= htmlspecialchars($worstMonth ?: 'N/A') ?>
                  <?php if (in_array($role, ['coordination', 'admin'])): ?>
                    <span class="badge bg-danger text-white ms-2" style="font-size: 0.75rem;"><?= htmlspecialchars($worstValue) ?></span>
                  <?php else: ?>
                    <span class="badge bg-danger text-white ms-2"><?= $worstValue >=0 ? $worstValue : 0 ?> NA</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 p-3 bg-success bg-opacity-10">
                <i class="bi bi-trophy text-success fs-4"></i>
              </div>
              <div class="flex-grow-1">
                <div class="small text-muted d-flex align-items-center">
                  Meilleur mois
                  <i class="bi bi-info-circle ms-1 text-muted" 
                     data-bs-toggle="tooltip" 
                     data-bs-placement="top"
                     title="<?= in_array($role, ['coordination', 'admin']) ? 'Période où l\'ensemble du staff a atteint le plus d\'objectifs par rapport au total. Indicateur de performance optimale globale.' : 'Période où vous avez complété le plus d\'objectifs. Votre mois le plus performant.' ?>"></i>
                </div>
                <div class="h5 mb-0">
                  <?= htmlspecialchars($bestMonth ?: 'N/A') ?>
                  <?php if (in_array($role, ['coordination', 'admin'])): ?>
                    <span class="badge bg-success text-white ms-2" style="font-size: 0.75rem;"><?= htmlspecialchars($bestValue) ?></span>
                  <?php else: ?>
                    <span class="badge bg-success text-white ms-2"><?= $bestValue >=0 ? $bestValue : 0 ?> Complété(s)</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-3 p-3 bg-primary bg-opacity-10">
                <i class="bi bi-award text-primary fs-4"></i>
              </div>
              <div class="flex-grow-1">
                <div class="small text-muted d-flex align-items-center">
                  Cote dernière évaluation
                  <i class="bi bi-info-circle ms-1 text-muted" 
                     data-bs-toggle="tooltip" 
                     data-bs-placement="top"
                     title="<?= in_array($role, ['coordination', 'admin']) ? 'Pourcentage moyen d\'objectifs atteints par l\'ensemble du staff lors de la dernière période d\'évaluation. Indicateur de performance collective actuelle.' : 'Votre taux d\'atteinte des objectifs lors de la dernière période d\'évaluation. Score de performance personnel.' ?>"></i>
                </div>
                <div class="h5 mb-0">
                  <span class="badge text-white ms-2" style="background-color: #3D74B9;"><?= $lastScore ?>%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section d'en-tête -->
      <div class="row mb-4">
        <div class="col-md-12">
          <div class="card shadow-sm border-0" style="background: linear-gradient(45deg, #3D74B9, #4d8cd6);">
            <div class="card-body p-4">
              <div class="row align-items-center">
                <!-- Avatar centré sur mobile -->
                <div class="col-12 col-sm-auto d-flex justify-content-center mb-2 mb-sm-0">
                  <div class="d-flex align-items-center justify-content-center bg-white rounded-circle"
                       style="width:80px;height:80px;">
                    <i class="bi bi-person-fill" style="font-size:32px;color: #3D74B9;"></i>
                  </div>
                </div>
                <!-- Texte principal centré sur mobile -->
                <div class="col-12 col-sm flex-grow-1 text-white text-center text-sm-start">
                  <h2 class="display-6 fw-bold mb-2">Bienvenue, <?= htmlspecialchars($nom . ' ' . $postnom) ?></h2>
                  <div class="d-flex flex-column flex-sm-row gap-2 mt-2 align-items-center align-items-sm-start justify-content-center justify-content-sm-start">
                    <div class="d-flex align-items-center">
                      <div class="rounded-pill px-3 py-1" style="background: rgba(255, 255, 255, 0.1);">
                        <i class="bi bi-person-badge-fill me-2"></i>
                        <?= htmlspecialchars(ucfirst($role)) ?>
                      </div>
                    </div>
                    <div class="d-flex align-items-center">
                      <div class="rounded-pill px-3 py-1" style="background: rgba(255, 255, 255, 0.1);">
                        <i class="bi bi-briefcase-fill me-2"></i>
                        <?= htmlspecialchars($fonction) ?>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Infos date/heure centrées sur mobile -->
                <div class="col-12 col-sm-auto text-white text-center text-sm-end mt-3 mt-sm-0">
                  <p class="mb-1"><?= date('d/m/Y') ?></p>
                  <p class="small mb-0">Dernière connexion : <?= date('H:i') ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cartes de résumé rapide -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
              <div class="d-flex align-items-start">
                <div class="flex-shrink-0 rounded-3 p-3" style="background-color: rgba(61, 116, 185, 0.1);">
                  <i class="bi bi-check-circle text-primary" style="color: #3D74B9 !important; font-size: 1.5rem;"></i>
                </div>
                <div class="ms-3">
                  <div class="d-flex align-items-center">
                    <h6 class="fw-bold mb-1">Objectifs Complétés</h6>
                    <button type="button" class="btn btn-link text-muted p-0 ms-1" 
                            data-bs-toggle="tooltip" 
                            data-bs-placement="top" 
                            title="Nombre total d'objectifs validés et complétés sur les 6 derniers mois">
                      <i class="bi bi-info-circle small"></i>
                    </button>
                  </div>
                  <h3 class="mb-0" style="color: #3D74B9;"><?= array_sum(array_column($stats, 'complet')) ?></h3>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
              <div class="d-flex align-items-start">
                <div class="flex-shrink-0 rounded-3 p-3" style="background-color: rgba(245, 199, 165, 0.2);">
                  <i class="bi bi-hourglass-split" style="color: #F5C7A5; font-size: 1.5rem;"></i>
                </div>
                <div class="ms-3">
                  <h6 class="fw-bold mb-1">En Cours</h6>
                  <h3 class="mb-0" style="color: #F5C7A5;"><?= array_sum(array_column($stats, 'encours')) ?></h3>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
              <div class="d-flex align-items-start">
                <div class="flex-shrink-0 rounded-3 p-3" style="background-color: rgba(108, 117, 125, 0.1);">
                  <i class="bi bi-clock-history text-secondary" style="font-size: 1.5rem;"></i>
                </div>
                <div class="ms-3">
                  <h6 class="fw-bold mb-1">En Attente</h6>
                  <h3 class="mb-0" style="color: #6c757d;"><?= array_sum(array_column($stats, 'attente')) ?></h3>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
              <div class="d-flex align-items-start">
                <div class="flex-shrink-0 rounded-3 p-3" style="background-color: rgba(25, 135, 84, 0.1);">
                  <i class="bi bi-graph-up text-success" style="font-size: 1.5rem;"></i>
                </div>
                <div class="ms-3">
                  <h6 class="fw-bold mb-1">Performance</h6>
                  <h3 class="mb-0 text-success"><?= $performance_ratio ?>%</h3>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Graphiques et analyses détaillées -->
      <?php if (in_array($role, ['staff', 'superviseur', 'agent'])): ?>
      <div class="row mb-4">
        <div class="col-md-8">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <div>
                  <h5 class="mb-1" style="color: #3D74B9;"><i class="bi bi-bar-chart-fill me-2"></i> Progression des objectifs</h5>
                  <p class="text-muted small mb-0">Suivi détaillé de vos objectifs sur les 6 derniers mois</p>
                </div>
                <button type="button" class="btn btn-link text-muted p-0 ms-2" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="right"
                        title="Le graphique montre la répartition de vos objectifs mois par mois">
                  <i class="bi bi-question-circle"></i>
                </button>
              </div>
            </div>
            <div class="card-body" style="height: 400px; overflow: hidden;">
              <div style="position: relative; height: 300px;">
                <canvas id="objectifChart"></canvas>
              </div>
              <div class="row mb-4 g-3 mt-2">
                <div class="col-md-3 text-center">
                  <div class="d-inline-flex align-items-center">
                    <span class="badge p-2 me-2" style="background-color: #3D74B9;">•</span>
                    <span class="text-muted small">Complétés</span>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="d-inline-flex align-items-center">
                    <span class="badge p-2 me-2" style="background-color: #F5C7A5;">•</span>
                    <span class="text-muted small">En cours</span>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="d-inline-flex align-items-center">
                    <span class="badge p-2 me-2" style="background-color: #e9ecef;">•</span>
                    <span class="text-muted small">En attente</span>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="d-inline-flex align-items-center">
                    <span class="badge p-2 me-2" style="background-color: #dc3545;">•</span>
                    <span class="text-muted small">Non atteints</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 d-flex align-items-center">
              <div>
                <h5 class="mb-1" style="color: #3D74B9;"><i class="bi bi-pie-chart-fill me-2"></i> Performance globale</h5>
                <p class="text-muted small mb-0">Analyse de votre performance actuelle</p>
              </div>
            </div>
            <div class="card-body position-relative" style="height: 400px;">
              <div class="position-absolute top-50 start-50 translate-middle" style="width: 100%;">
                <div style="height: 200px;">
                  <canvas id="performanceChart"></canvas>
                </div>
                <div class="mt-4 text-center">
                  <?php if ($performance_ratio >= 80): ?>
                    <div class="mb-2"><span class="badge bg-success fs-6"><i class="bi bi-award me-1"></i> Performance Excellente</span></div>
                    <p class="text-muted small mb-0">Continuez on cette lancée !</p>
                  <?php elseif ($performance_ratio >= 50): ?>
                    <div class="mb-2"><span class="badge fs-6" style="background-color: #3D74B9; color: white;"><i class="bi bi-graph-up-arrow me-1"></i> En Progression</span></div>
                    <p class="text-muted small mb-0">Vous progressez bien.</p>
                  <?php else: ?>
                    <div class="mb-2"><span class="badge bg-danger fs-6"><i class="bi bi-exclamation-triangle me-1"></i> À Améliorer</span></div>
                    <p class="text-muted small mb-0">Identifiez les obstacles.</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Contenu spécifique au rôle -->
      <?= $contenu ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Initialisation des tooltips Bootstrap
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
      html: true
    })
  })
  const ctx = document.getElementById('objectifChart');
  if (ctx) {
    // Données PHP -> JS pour le graphique en barres
    const dataStats = <?= json_encode($stats, JSON_HEX_TAG) ?>;
    const nonAtteintsData = <?= json_encode($autoNonAtteints, JSON_HEX_TAG) ?>;
    const labels = Object.keys(dataStats);
    const complet = labels.map(m => dataStats[m].complet);
    const encours = labels.map(m => dataStats[m].encours);
    const attente = labels.map(m => dataStats[m].attente);
    const nonAtteints = labels.map(m => nonAtteintsData[m] ?? 0);

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Complet', data: complet, backgroundColor: '#3D74B9', stack: 'S', borderRadius: 4 },
          { label: 'En cours', data: encours, backgroundColor: '#F5C7A5', stack: 'S', borderRadius: 4 },
          { label: 'En attente', data: attente, backgroundColor: '#e9ecef', stack: 'S', borderRadius: 4 },
          { label: 'Non atteints', data: nonAtteints, backgroundColor: '#dc3545', stack: 'S', borderRadius: 4 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
      }
    });
  }

  // Graphique de performance
  const ctxPerf = document.getElementById('performanceChart');
  if (ctxPerf) {
    const performance = <?= $performance_ratio ?>;
    
    new Chart(ctxPerf, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [performance, 100 - performance],
          backgroundColor: [
            '#3D74B9',
            '#f0f0f0'
          ],
          borderWidth: 0,
          cutout: '80%'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            enabled: false
          },
          title: {
            display: false
          }
        },
        onComplete: function(chart) {
          const ctx = chart.ctx;
          const width = chart.width;
          const height = chart.height;

          ctx.restore();
          ctx.font = "bold 20px 'Segoe UI'";
          ctx.textBaseline = "middle";
          ctx.textAlign = "center";
          ctx.fillStyle = "#3D74B9";
          
          const text = performance + "%";
          const textX = Math.round((width - ctx.measureText(text).width) / 2);
          const textY = height / 2;

          ctx.fillText(text, textX, textY);
          ctx.save();
        }
      }
    });
  }
});
</script>

<style>
/* Fond dégradé sur la zone dashboard */
.dashboard-main {
  background: linear-gradient(135deg, #e8f2f9 0%, #fef5ed 100%);
  min-height: 100vh;
  padding-bottom: 2rem;
  /* NE PAS METTRE de position, top, height, z-index */
}

/* Responsive padding */
@media (max-width: 991px) {
  .dashboard-main {
    background: #fff !important;
    min-height: auto !important;
    padding-bottom: 2rem;
  }
  .offcanvas {
    z-index: 1080 !important;
  }
}

/* Desktop : aucune règle de position */
@media (min-width: 992px) {
  .dashboard-main {
    background: #fff;
    min-height: 100vh;
    padding-bottom: 2rem;
    /* pas de position sticky/fixed/relative/top/z-index/height */
  }
}

/* Cartes modernisées */
.card {
  border-radius: 18px !important;
  box-shadow: 0 6px 32px rgba(61, 116, 185, 0.10), 0 1.5px 8px rgba(245, 199, 165, 0.08);
  transition: box-shadow 0.3s, transform 0.3s;
}
.card:hover {
  box-shadow: 0 12px 40px rgba(61, 116, 185, 0.18), 0 2px 12px rgba(245, 199, 165, 0.12);
  transform: translateY(-2px) scale(1.01);
}

/* Titres modernisés */
.card-title, h5, h6 {
  font-family: 'Segoe UI', 'Montserrat', Arial, sans-serif;
  font-weight: 700;
  letter-spacing: 0.5px;
}

/* Badges stylés */
.badge {
  border-radius: 16px !important;
  font-size: 0.95rem;
  font-weight: 600;
  padding: 0.5em 1em;
  box-shadow: 0 2px 8px rgba(61, 116, 185, 0.08);
}

/* Animation chiffres clés */
.h4, .h5, .h3, .display-6 {
  animation: keyPulse 1.2s cubic-bezier(0.4,0,0.2,1) 1;
}
@keyframes keyPulse {
  0% { opacity: 0.5; transform: scale(0.95);}
  60% { opacity: 1; transform: scale(1.08);}
  100% { opacity: 1; transform: scale(1);}
}

/* Boutons modernisés */
.btn, .btn-outline-primary, .btn-primary {
  border-radius: 12px !important;
  font-weight: 600;
  transition: box-shadow 0.2s, transform 0.2s;
}
.btn:hover, .btn-outline-primary:hover, .btn-primary:hover {
  box-shadow: 0 4px 16px rgba(61, 116, 185, 0.12);
  transform: translateY(-2px) scale(1.03);
}

/* Progress bars stylées */
.progress-bar {
  border-radius: 8px !important;
  box-shadow: 0 1px 6px rgba(61, 116, 185, 0.08);
}

/* Section d'en-tête dashboard */
.card[style*="linear-gradient"] {
  border-radius: 24px !important;
  box-shadow: 0 8px 32px rgba(61, 116, 185, 0.18), 0 2px 12px rgba(245, 199, 165, 0.12);
}

/* Section bienvenue vraiment responsive */
@media (max-width: 575.98px) {
  .card[style*="linear-gradient"] .rounded-circle {
    width: 54px !important;
    height: 54px !important;
  }
  .card[style*="linear-gradient"] .display-6 {
    font-size: 1.1rem !important;
    margin-bottom: 0.7rem !important;
  }
  .card[style*="linear-gradient"] .d-flex.flex-column {
    gap: 0.6rem !important;
  }
  .card[style*="linear-gradient"] .text-sm-end {
    text-align: center !important;
    margin-top: 1rem !important;
  }
}

/* TODO: ISSUE-XXXXX -> Sticky dashboard provoque un espace vide en haut sur desktop (inspecter top/navbar height).
     Signaler sur GitHub et appliquer fix : soit retirer position:sticky/fixed, soit définir top égal à la hauteur réelle de la navbar. */
</style>

<?php include('../includes/footer.php'); ?>
