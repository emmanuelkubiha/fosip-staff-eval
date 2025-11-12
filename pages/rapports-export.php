<?php
// pages/rapports-export.php
// Export des rapports en PDF ou Excel

if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');

// --- Auth rôle coordination et admin ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['coordination', 'admin'])) {
  header('Location: unauthorized.php');
  exit;
}

// CSRF check
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  die('CSRF validation failed');
}

$format = $_POST['format'] ?? 'excel'; // 'pdf' ou 'excel'
$periode_debut = $_POST['periode_debut'] ?? '';
$periode_fin = $_POST['periode_fin'] ?? '';
$superviseur = $_POST['superviseur'] ?? '';
$statut = $_POST['statut'] ?? '';

// Construction de la requête SQL avec filtres
$sql = "SELECT 
    o.id AS fiche_id,
    o.periode,
    o.nom_projet,
    o.poste,
    o.statut,
    o.created_at,
    u.nom AS agent_nom,
    u.post_nom AS agent_post_nom,
    u.email AS agent_email,
    u.fonction AS agent_fonction,
    sup.nom AS sup_nom,
    sup.post_nom AS sup_post_nom,
    sup.email AS sup_email,
    c.commentaire AS coord_commentaire,
    c.date_commentaire
  FROM objectifs o
  JOIN users u ON o.user_id = u.id
  LEFT JOIN users sup ON o.superviseur_id = sup.id
  LEFT JOIN coordination_commentaires c ON c.fiche_id = o.id
  WHERE 1=1";

$params = [];

if ($periode_debut !== '') {
  $sql .= " AND o.periode >= :periode_debut";
  $params[':periode_debut'] = $periode_debut;
}

if ($periode_fin !== '') {
  $sql .= " AND o.periode <= :periode_fin";
  $params[':periode_fin'] = $periode_fin;
}

if ($superviseur !== '') {
  $sql .= " AND o.superviseur_id = :superviseur";
  $params[':superviseur'] = (int)$superviseur;
}

if ($statut !== '') {
  $sql .= " AND o.statut = :statut";
  $params[':statut'] = $statut;
}

$sql .= " ORDER BY o.periode DESC, o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fiches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques d'objectifs pour chaque fiche
$objectifsStats = [];
if (!empty($fiches)) {
  $ficheIds = array_map(fn($f) => (int)$f['fiche_id'], $fiches);
  $in = implode(',', $ficheIds);
  
  try {
    // Total d'items par fiche
    $stTotal = $pdo->query("SELECT fiche_id, COUNT(*) AS total FROM objectifs_items WHERE fiche_id IN ($in) GROUP BY fiche_id");
    $totaux = [];
    foreach ($stTotal->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $totaux[(int)$row['fiche_id']] = (int)$row['total'];
    }
    
    // Items atteints ou dépassés (note IN car c'est 'note' et non 'statut_atteinte')
    $stAtteint = $pdo->query("SELECT fiche_id, COUNT(*) AS atteints FROM auto_evaluation WHERE fiche_id IN ($in) AND note IN ('atteint','depasse') GROUP BY fiche_id");
    foreach ($stAtteint->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $fid = (int)$row['fiche_id'];
      $objectifsStats[$fid] = [
        'atteints' => (int)$row['atteints'],
        'total' => $totaux[$fid] ?? 0
      ];
    }
    
    // Compléter avec les fiches sans évaluation
    foreach ($totaux as $fid => $tot) {
      if (!isset($objectifsStats[$fid])) {
        $objectifsStats[$fid] = ['atteints' => 0, 'total' => $tot];
      }
    }
  } catch (Exception $e) {
    // Ignorer les erreurs
  }
}

// Format de la date pour le nom de fichier
$date_export = date('Y-m-d_His');
$filename_base = "rapport_evaluations_$date_export";

if ($format === 'pdf') {
  // Export PDF (version simple avec HTML)
  exportPDF($fiches, $objectifsStats, $filename_base, $periode_debut, $periode_fin, $superviseur, $statut);
} else {
  // Export Excel (CSV)
  exportExcel($fiches, $objectifsStats, $filename_base);
}

// Fonction d'export PDF (HTML to PDF simple)
function exportPDF($fiches, $objectifsStats, $filename, $periode_debut, $periode_fin, $superviseur, $statut) {
  // Pour une vraie génération PDF, il faudrait utiliser une librairie comme TCPDF, FPDF ou mPDF
  // Ici, nous générons un HTML imprimable qui peut être converti en PDF par le navigateur
  
  header('Content-Type: text/html; charset=UTF-8');
  
  $titre = "Rapport d'évaluation du personnel FOSIP";
  $sousTitre = "Généré le " . date('d/m/Y à H:i');
  
  if ($periode_debut && $periode_fin) {
    $sousTitre .= " - Période: " . formatPeriode($periode_debut) . " à " . formatPeriode($periode_fin);
  } elseif ($periode_debut) {
    $sousTitre .= " - À partir de " . formatPeriode($periode_debut);
  } elseif ($periode_fin) {
    $sousTitre .= " - Jusqu'à " . formatPeriode($periode_fin);
  }
  
  ?>
  <!DOCTYPE html>
  <html lang="fr">
  <head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($filename) ?></title>
    <style>
      @media print {
        @page { 
          margin: 1.5cm; 
          size: A4;
        }
        body { margin: 0; }
        .no-print { display: none; }
        .page-break { page-break-before: always; }
      }
      
      body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.6;
        color: #2c3e50;
        max-width: 100%;
        margin: 0;
        padding: 15px;
        background: #ffffff;
      }
      
      .header {
        text-align: center;
        margin-bottom: 35px;
        padding: 25px 20px;
        background: linear-gradient(135deg, #3D74B9 0%, #2a5a94 100%);
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(61, 116, 185, 0.2);
        position: relative;
      }
      
      .header::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80%;
        height: 3px;
        background: linear-gradient(90deg, transparent, #3D74B9, transparent);
      }
      
      .logo-container {
        margin-bottom: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
      }
      
      .logo {
        max-width: 100px;
        max-height: 100px;
      }
      
      .header h1 {
        color: white;
        margin: 15px 0 10px 0;
        font-size: 26pt;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        letter-spacing: 0.5px;
      }
      
      .header p {
        color: rgba(255,255,255,0.95);
        margin: 5px 0;
        font-size: 11pt;
        font-weight: 500;
      }
      
      .header .subtitle {
        background: rgba(255,255,255,0.15);
        padding: 8px 20px;
        border-radius: 20px;
        display: inline-block;
        margin-top: 10px;
      }
      
      .summary-box {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 6px solid #3D74B9;
        border-radius: 8px;
        padding: 20px 25px;
        margin: 25px 0;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
      }
      
      .summary-box h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #3D74B9;
        font-size: 16pt;
        font-weight: 700;
        border-bottom: 2px solid #3D74B9;
        padding-bottom: 10px;
      }
      
      .summary-box p {
        margin: 12px 0;
        font-size: 11pt;
        line-height: 1.8;
      }
      
      .summary-box strong {
        color: #2c3e50;
        font-weight: 700;
      }
      
      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
      }
      
      .stat-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        text-align: center;
      }
      
      .stat-card .stat-value {
        font-size: 24pt;
        font-weight: bold;
        color: #3D74B9;
        margin: 5px 0;
      }
      
      .stat-card .stat-label {
        font-size: 9pt;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 25px 0;
        font-size: 10pt;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
      }
      
      table thead {
        background: linear-gradient(135deg, #3D74B9 0%, #2a5a94 100%);
        color: white;
      }
      
      table th {
        padding: 14px 12px;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 9pt;
        letter-spacing: 0.5px;
        border-bottom: 3px solid #F5C7A5;
      }
      
      table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e9ecef;
      }
      
      table tbody tr {
        transition: all 0.2s ease;
      }
      
      table tbody tr:nth-child(even) {
        background: #f8f9fa;
      }
      
      table tbody tr:hover {
        background: #e3f2fd;
      }
      
      table tbody tr:last-child td {
        border-bottom: none;
      }
      
      .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 9pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
      }
      
      .badge-success { 
        background: linear-gradient(135deg, #28a745, #20c997); 
        color: white;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
      }
      .badge-warning { 
        background: linear-gradient(135deg, #ffc107, #fd7e14); 
        color: #333;
        box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
      }
      .badge-secondary { 
        background: linear-gradient(135deg, #6c757d, #495057); 
        color: white;
        box-shadow: 0 2px 4px rgba(108, 117, 125, 0.3);
      }
      .badge-danger { 
        background: linear-gradient(135deg, #dc3545, #c82333); 
        color: white;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
      }
      
      .print-button {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 30px;
        background: linear-gradient(135deg, #3D74B9, #2a5a94);
        color: white;
        border: none;
        border-radius: 30px;
        cursor: pointer;
        font-size: 12pt;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(61, 116, 185, 0.4);
        transition: all 0.3s ease;
        z-index: 1000;
      }
      
      .print-button:hover {
        background: linear-gradient(135deg, #2a5a94, #1e4470);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(61, 116, 185, 0.5);
      }
      
      .footer {
        margin-top: 50px;
        padding: 25px 20px;
        border-top: 3px solid #3D74B9;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 8px;
        text-align: center;
        color: #2c3e50;
        font-size: 9pt;
      }
      
      .footer p {
        margin: 8px 0;
      }
      
      .footer strong {
        color: #3D74B9;
        font-weight: 700;
      }
      
      .section-title {
        color: #3D74B9;
        font-size: 14pt;
        font-weight: 700;
        margin: 30px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #3D74B9;
      }
      
      .info-row {
        display: flex;
        justify-content: space-between;
        margin: 10px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
      }
      
      .info-label {
        font-weight: 600;
        color: #495057;
      }
      
      .info-value {
        color: #3D74B9;
        font-weight: 700;
      }
    </style>
  </head>
  <body>
    <button class="print-button no-print" onclick="window.print()">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
      <i class="bi bi-printer"></i> Imprimer / Sauver PDF
    </button>
    
    <div class="header">
      <div class="logo-container">
        <?php
        // Chemin absolu pour l'affichage dans le PDF
        $logoPath = dirname(__DIR__) . '/assets/img/logocolored.png';
        if (file_exists($logoPath)) {
          $logoData = base64_encode(file_get_contents($logoPath));
          $logoSrc = 'data:image/png;base64,' . $logoData;
        } else {
          $logoSrc = '../assets/img/logocolored.png';
        }
        ?>
        <img src="<?= $logoSrc ?>" alt="Logo FOSIP" class="logo">
      </div>
      <h1><?= htmlspecialchars($titre) ?></h1>
      <div class="subtitle">
        <p><?= htmlspecialchars($sousTitre) ?></p>
      </div>
    </div>
    
    <div class="summary-box">
      <h3><i class="bi bi-bar-chart-fill"></i> Résumé du Rapport</h3>
      <?php 
        $totalFiches = count($fiches);
        $fichesTerminees = count(array_filter($fiches, fn($f) => $f['statut'] === 'termine'));
        $fichesEvalue = count(array_filter($fiches, fn($f) => $f['statut'] === 'evalue'));
        $fichesEncours = count(array_filter($fiches, fn($f) => $f['statut'] === 'encours'));
        $tauxCompletion = $totalFiches > 0 ? round(($fichesTerminees / $totalFiches) * 100, 1) : 0;
        
        $totalObjectifs = 0;
        $objectifsAtteints = 0;
        foreach ($fiches as $f) {
          $fid = (int)$f['fiche_id'];
          $totalObjectifs += $objectifsStats[$fid]['total'] ?? 0;
          $objectifsAtteints += $objectifsStats[$fid]['atteints'] ?? 0;
        }
        $tauxAtteinte = $totalObjectifs > 0 ? round(($objectifsAtteints / $totalObjectifs) * 100, 1) : 0;
      ?>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?= $totalFiches ?></div>
          <div class="stat-label"><i class="bi bi-clipboard-data"></i> Fiches d'évaluation</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= count(array_unique(array_column($fiches, 'agent_email'))) ?></div>
          <div class="stat-label"><i class="bi bi-people"></i> Agents concernés</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= count(array_unique(array_column($fiches, 'sup_email'))) ?></div>
          <div class="stat-label"><i class="bi bi-person-badge"></i> Superviseurs</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $fichesTerminees ?></div>
          <div class="stat-label"><i class="bi bi-check-circle"></i> Fiches terminées</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $tauxCompletion ?>%</div>
          <div class="stat-label"><i class="bi bi-percent"></i> Taux de complétion</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $objectifsAtteints ?>/<?= $totalObjectifs ?></div>
          <div class="stat-label"><i class="bi bi-bullseye"></i> Objectifs atteints</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $tauxAtteinte ?>%</div>
          <div class="stat-label"><i class="bi bi-graph-up"></i> Taux d'atteinte</div>
        </div>
      </div>
      
      <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 8px;">
        <p style="margin: 5px 0;"><strong><i class="bi bi-calendar-range"></i> Période:</strong> <?= $periode_debut ? formatPeriode($periode_debut) : 'Toutes' ?> → <?= $periode_fin ? formatPeriode($periode_fin) : 'Toutes' ?></p>
        <p style="margin: 5px 0;"><strong><i class="bi bi-funnel"></i> Filtres appliqués:</strong> 
          <?php if ($superviseur): ?>
            Superviseur #<?= $superviseur ?>
          <?php endif; ?>
          <?php if ($statut): ?>
            | Statut: <?= ucfirst($statut) ?>
          <?php endif; ?>
          <?php if (!$superviseur && !$statut): ?>
            Aucun filtre supplémentaire
          <?php endif; ?>
        </p>
      </div>
    </div>
    
    <h2 class="section-title"><i class="bi bi-list-check"></i> Détail des Évaluations</h2>
    
    <table>
      <thead>
        <tr>
          <th>Agent</th>
          <th>Période</th>
          <th>Projet</th>
          <th>Poste</th>
          <th>Superviseur</th>
          <th>Objectifs</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($fiches)): ?>
          <tr>
            <td colspan="7" style="text-align:center;color:#999;">Aucune fiche ne correspond aux critères sélectionnés</td>
          </tr>
        <?php else: ?>
          <?php foreach ($fiches as $f): 
            $agentNom = trim($f['agent_nom'] . ' ' . $f['agent_post_nom']);
            $supNom = trim($f['sup_nom'] . ' ' . $f['sup_post_nom']);
            $objAtteints = $objectifsStats[(int)$f['fiche_id']]['atteints'] ?? 0;
            $objTotal = $objectifsStats[(int)$f['fiche_id']]['total'] ?? 0;
            
            $badgeClass = 'badge-secondary';
            $statutText = ucfirst($f['statut']);
            if ($f['statut'] === 'termine') {
              $badgeClass = 'badge-success';
            } elseif ($f['statut'] === 'evalue') {
              $badgeClass = 'badge-warning';
            }
          ?>
            <tr>
              <td><?= htmlspecialchars($agentNom) ?></td>
              <td><?= htmlspecialchars(formatPeriode($f['periode'])) ?></td>
              <td><?= htmlspecialchars($f['nom_projet']) ?></td>
              <td><?= htmlspecialchars($f['poste']) ?></td>
              <td><?= htmlspecialchars($supNom ?: '—') ?></td>
              <td style="text-align:center;"><?= $objAtteints ?>/<?= $objTotal ?></td>
              <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statutText) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    
    <div class="footer">
      <p><strong>FOSIP - DRC</strong></p>
      <p>Femmes Organisées dans le Système Intégré du Progrès</p>
      <p>Staff Performance Suite - Rapport généré automatiquement le <?= date('d/m/Y à H:i') ?></p>
      <p style="margin-top: 15px; font-size: 8pt; color: #6c757d;">
        Ce document est confidentiel et destiné uniquement à un usage interne.
      </p>
    </div>
    
    <script>
      // Auto-print dialog after page load
      window.addEventListener('load', function() {
        setTimeout(function() {
          // Suggestion d'impression automatique désactivée pour laisser l'utilisateur décider
          // window.print();
        }, 500);
      });
    </script>
  </body>
  </html>
  <?php
  exit;
}

// Fonction d'export Excel (CSV)
function exportExcel($fiches, $objectifsStats, $filename) {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');
  
  // BOM UTF-8 pour Excel
  echo "\xEF\xBB\xBF";
  
  $output = fopen('php://output', 'w');
  
  // En-têtes
  fputcsv($output, [
    'ID Fiche',
    'Période',
    'Agent - Nom',
    'Agent - Prénom',
    'Agent - Email',
    'Agent - Fonction',
    'Projet',
    'Poste',
    'Superviseur - Nom',
    'Superviseur - Email',
    'Objectifs atteints',
    'Total objectifs',
    'Taux atteinte (%)',
    'Statut',
    'Date création',
    'Commentaire coordination',
    'Date commentaire'
  ], ';');
  
  // Données
  foreach ($fiches as $f) {
    $objAtteints = $objectifsStats[(int)$f['fiche_id']]['atteints'] ?? 0;
    $objTotal = $objectifsStats[(int)$f['fiche_id']]['total'] ?? 0;
    $tauxAtteinte = $objTotal > 0 ? round(($objAtteints / $objTotal) * 100, 1) : 0;
    
    fputcsv($output, [
      $f['fiche_id'],
      formatPeriode($f['periode']),
      $f['agent_nom'],
      $f['agent_post_nom'],
      $f['agent_email'],
      $f['agent_fonction'],
      $f['nom_projet'],
      $f['poste'],
      trim($f['sup_nom'] . ' ' . $f['sup_post_nom']),
      $f['sup_email'] ?? '',
      $objAtteints,
      $objTotal,
      $tauxAtteinte,
      ucfirst($f['statut']),
      $f['created_at'] ? date('d/m/Y', strtotime($f['created_at'])) : '',
      $f['coord_commentaire'] ?? '',
      $f['date_commentaire'] ? date('d/m/Y', strtotime($f['date_commentaire'])) : ''
    ], ';');
  }
  
  fclose($output);
  exit;
}

// Fonction helper pour formater les périodes
function formatPeriode($periode) {
  if (empty($periode)) return '';
  
  $date = DateTime::createFromFormat('Y-m', $periode);
  if (!$date) return $periode;
  
  $moisFr = [
    'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars', 
    'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
    'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
    'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
  ];
  
  $formatted = $date->format('F Y');
  foreach ($moisFr as $en => $fr) {
    $formatted = str_replace($en, $fr, $formatted);
  }
  
  return $formatted;
}
?>
