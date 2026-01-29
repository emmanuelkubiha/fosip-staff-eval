<?php
// imprimer-fiche-evaluation.php
// Vue imprimable d'une fiche d'évaluation — destinées à l'impression/PDF.
// Récupère fiche_id en GET, charge les mêmes données que fiche-evaluation.php et affiche un document prêt à imprimer.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

// Sécurité basique
if (empty($_GET['fiche_id']) || empty($_SESSION['user_id'])) {
  http_response_code(400);
  echo "Fiche non spécifiée ou accès non autorisé.";
  exit;
}
$fiche_id = (int)$_GET['fiche_id'];
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Charger la fiche avec informations utilisateur
$stmt = $pdo->prepare("
  SELECT o.*, 
         u.nom AS sup_nom, u.post_nom AS sup_post_nom, u.fonction AS sup_fonction,
         agent.nom AS user_nom, agent.post_nom AS user_postnom, agent.fonction AS user_fonction, agent.email AS user_email
  FROM objectifs o
  LEFT JOIN users u ON o.superviseur_id = u.id
  LEFT JOIN users agent ON o.user_id = agent.id
  WHERE o.id = ?
  LIMIT 1
");
$stmt->execute([$fiche_id]);
$fiche = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fiche) {
  echo "Fiche introuvable.";
  exit;
}

// Vérification des droits d'accès
$agent_id = (int)$fiche['user_id'];
$superviseur_id = (int)$fiche['superviseur_id'];

// Autoriser l'accès si :
// - L'utilisateur est l'agent lui-même
// - L'utilisateur est le superviseur de la fiche
// - L'utilisateur a le rôle coordination ou admin
$access_granted = false;
if ($user_id === $agent_id) {
  $access_granted = true; // L'agent peut imprimer sa propre fiche
} elseif ($user_id === $superviseur_id) {
  $access_granted = true; // Le superviseur peut imprimer
} elseif (in_array($role, ['coordination', 'admin'])) {
  $access_granted = true; // Coordination et admin ont accès à tout
}

if (!$access_granted) {
  http_response_code(403);
  echo "Vous n'êtes pas autorisé à accéder à cette fiche.";
  exit;
}

// Charger items
$stmtItems = $pdo->prepare("SELECT * FROM objectifs_items WHERE fiche_id = ? ORDER BY ordre ASC");
$stmtItems->execute([$fiche_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Charger auto_evaluations si table existe (utiliser agent_id, pas user_id connecté)
$auto_map_by_item = [];
try {
  $st = $pdo->prepare("SHOW TABLES LIKE 'auto_evaluation'"); $st->execute();
  if ($st->fetchColumn()) {
    $stm = $pdo->prepare("SELECT * FROM auto_evaluation WHERE fiche_id = ? AND user_id = ?");
    $stm->execute([$fiche_id, $agent_id]); // Utiliser agent_id de la fiche
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $r) $auto_map_by_item[(int)$r['item_id']] = $r;
  }
} catch (Throwable $e) { /* ignore */ }

// Informations utilisateur (agent de la fiche)
$userInfo = [
  'nom' => $fiche['user_nom'] ?? '',
  'post_nom' => $fiche['user_postnom'] ?? '',
  'email' => $fiche['user_email'] ?? '',
  'fonction' => $fiche['user_fonction'] ?? ''
];

// Charger les photos de profil
$agent_photo = 'default.png';
$superviseur_photo = 'default.png';

try {
  // Photo de l'agent
  $stAgent = $pdo->prepare('SELECT photo FROM users WHERE id = ?');
  $stAgent->execute([$agent_id]);
  $agentData = $stAgent->fetch(PDO::FETCH_ASSOC);
  if ($agentData && !empty($agentData['photo'])) {
    $agent_photo = $agentData['photo'];
  }
  
  // Photo du superviseur
  if (!empty($superviseur_id)) {
    $stSup = $pdo->prepare('SELECT photo FROM users WHERE id = ?');
    $stSup->execute([$superviseur_id]);
    $supData = $stSup->fetch(PDO::FETCH_ASSOC);
    if ($supData && !empty($supData['photo'])) {
      $superviseur_photo = $supData['photo'];
    }
  }
} catch (Throwable $e) {
  // En cas d'erreur, on garde les photos par défaut
}

$profile_base = __DIR__ . '/../assets/img/profiles/';
$agent_photo_path = $profile_base . $agent_photo;
$superviseur_photo_path = $profile_base . $superviseur_photo;

// Vérifier l'existence des fichiers
if (!file_exists($agent_photo_path)) {
  $agent_photo_path = $profile_base . 'default.png';
}
if (!file_exists($superviseur_photo_path)) {
  $superviseur_photo_path = $profile_base . 'default.png';
}

// Pour l'affichage web (relatif)
$agent_photo_web = '../assets/img/profiles/' . $agent_photo;
$superviseur_photo_web = '../assets/img/profiles/' . $superviseur_photo;

$total_items = is_array($items) ? count($items) : 0;
$counts = ['depasse' => 0, 'atteint' => 0, 'non_atteint' => 0, 'vide' => 0];

foreach ($items as $it) {
  $iid = (int)($it['id'] ?? 0);
  $ae = $auto_map_by_item[$iid] ?? null;
  if (!$ae || empty($ae['note'])) { $counts['vide']++; continue; }
  if ($ae['note'] === 'depasse') $counts['depasse']++;
  elseif ($ae['note'] === 'atteint') $counts['atteint']++;
  else $counts['non_atteint']++;
}


// Calculer la cote du superviseur (moyenne des notes/20)
$fiche_cote = null;
$fiche_cote_pct = null;
try {
  $stAvg = $pdo->prepare('SELECT AVG(note) FROM cote_des_objectifs WHERE fiche_id = :fid AND superviseur_id = :sid');
  $stAvg->execute([':fid'=>(int)$fiche_id, ':sid'=>$superviseur_id]);
  $avg20 = (float)$stAvg->fetchColumn();
  if ($avg20 > 0) {
    $fiche_cote = number_format($avg20,1);
    $fiche_cote_pct = round($avg20 * 5); // /20 vers %
  }
} catch (Throwable $e) {}


// Correction : vérifier s'il existe au moins une note du superviseur pour cette fiche
$supervise_eval = 0;
$coord_comments = 0;
try {
  $stS = $pdo->prepare("SELECT COUNT(*) FROM cote_des_objectifs WHERE fiche_id = ? AND superviseur_id = ? AND note IS NOT NULL");
  $stS->execute([$fiche_id, $superviseur_id]);
  if ((int)$stS->fetchColumn() > 0) {
    $supervise_eval = 1;
  }
  // coordination_commentaires lié à la fiche ?
  try {
    $stC = $pdo->prepare("SELECT COUNT(*) FROM coordination_commentaires WHERE fiche_id = ?");
    $stC->execute([ $fiche_id ]);
    if ((int)$stC->fetchColumn() > 0) $coord_comments = 1;
  } catch (Throwable $e2) { /* table coordination_commentaires peut ne pas exister */ }
} catch (Throwable $e) {
  $supervise_eval = 0;
  $coord_comments = 0;
}

// Ensure resume fields exist (avoid undefined variable / null in foreach)
if (!isset($resume_fields) || !is_array($resume_fields)) {
  $resume_fields = [
    'resume_reussite',
    'resume_amelioration',
    'resume_problemes',
    'resume_competence_a_developper',
    'resume_competence_a_utiliser',
    'resume_soutien'
  ];
}

// logo path (ajustez si nécessaire)
$logo_path = __DIR__ . '/../assets/img/logocolored.png';
$logo_web = '../assets/img/logocolored.png';
if (!file_exists($logo_path)) {
  // fallback to bootstrap icon if logo absent
  $logo_web = null;
}

// entête HTTP pour éviter cache et permettre impression propre
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Fiche d'évaluation — <?= htmlspecialchars($fiche['periode']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="../assets/css/sidebar.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --fosip:#3D74B9; --muted:#6c757d; }
    @page { size: A4; margin: 20mm 15mm; }
    body { font-family: "Segoe UI", Roboto, Arial, sans-serif; color:#222; background:white; font-size: 10pt; }
    .doc-header { display:flex; align-items:center; gap:18px; margin-bottom:14px; }
    .doc-logo { width:100px; }
    .doc-title h2 { margin:0; color:var(--fosip); font-size:14pt; }

    .metric-card { display:flex; gap:12px; align-items:center; justify-content:space-between; padding:10px; border-radius:6px; border:1px solid #e3eef8; background:#fff; font-size: 9pt; }
    .metric { text-align:center; min-width:110px; }
    .metric .value { font-size:1.1rem; font-weight:700; color:var(--fosip); }
    .section-title { display:flex; align-items:center; gap:8px; margin-bottom:8px; color:var(--fosip); font-weight:700; font-size: 11pt; }

    /* Table styles — utiliser couleurs pleines et !important pour impression fiable */
    .items-table { width:100%; border-collapse:collapse; font-size: 9pt; }
    .items-table th { background: #3D74B9 !important; color:#ffffff !important; padding:8px; text-align:left; font-size: 10pt; }
    .items-table td { padding:8px; vertical-align:top; border:1px solid #eef2f7; }

    .badge-stat { padding:.3rem .5rem; border-radius:6px; font-weight:600; color:var(--fosip); background:#eaf3ff; font-size: 9pt; }
    .note-badge.depasse { background:#e6f4ef; color:#1f7a3f; border-radius:6px; padding:3px 6px; font-weight:600; font-size: 8pt; }
    .notice-badge.atteint { background:#fff7e6; color:#8a5b00; border-radius:6px; padding:3px 6px; font-weight:600; font-size: 8pt; }
    .note-badge.non_atteint { background:#fff0f0; color:#a71d2a; border-radius:6px; padding:3px 6px; font-weight:600; font-size: 8pt; }

    .summary-row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .print-controls { position:fixed; top:12px; right:12px; z-index:9999; }

    /* signature styling */
    .sign-box .sign-line { border-top:1px solid #000; margin-top:40px; padding-top:5px; text-align:center; font-weight:600; font-size: 9pt; }
    
    .small-muted { font-size: 9pt; }
    h3 { font-size: 12pt; }
    h4 { font-size: 11pt; }
    h5 { font-size: 10pt; }
    p, div { font-size: 10pt; }

    /* Force printing of background colors in user agents qui le supportent */
    @media print {
      .print-controls { display:none; }
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
      .items-table th { background: #3D74B9 !important; color: #ffffff !important; }
    }

    /* petits ajustements visuels pour écran */
    @media screen {
      .metric-card { background:linear-gradient(180deg, rgba(61,116,185,0.03), #fff); border:1px solid rgba(61,116,185,0.08); }
    }

    /* Styles pour les photos de profil */
    .profile-photo-print {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #3D74B9;
      margin-right: 10px;
      vertical-align: middle;
    }
    
    .info-with-photo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }
    
    @media print {
      .profile-photo-print {
        width: 40px;
        height: 40px;
        border: 1px solid #333;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>
  <div class="print-controls">
    <button class="btn btn-primary btn-sm" onclick="window.print();"><i class="bi bi-printer"></i> Imprimer / Enregistrer PDF</button>
    <a href="fiche-evaluation.php?id=<?= $fiche_id ?>" class="btn btn-outline-secondary btn-sm">Fermer</a>
  </div>

  <header class="doc-header">
    <?php if ($logo_web): ?>
      <img src="<?= htmlspecialchars($logo_web) ?>" alt="Fosip" class="doc-logo">
    <?php else: ?>
      <div style="width:120px;height:60px;background:#3D74B9;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;border-radius:6px;">
        FOSIP
      </div>
    <?php endif; ?>
    <div class="doc-title">
      <h2>FICHE D'ÉVALUATION — <?= htmlspecialchars($fiche['periode']) ?></h2>
      <div class="small-muted"><?= htmlspecialchars($fiche['nom_projet'] ?? '') ?> — <?= htmlspecialchars($fiche['poste'] ?? '') ?></div>
    </div>
  </header>

  <!-- Synthèse metrics -->
  <div class="metric-card mb-3">
    <div style="flex:1">
      <div class="section-title"><span class="notch" style="background:var(--fosip)"></span> Informations générales</div>
      <div class="small-muted">
        <div class="info-with-photo">
          <img src="<?= htmlspecialchars($agent_photo_web) ?>" 
               alt="Photo agent" 
               class="profile-photo-print"
               onerror="this.onerror=null;this.src='../assets/img/profiles/default.png';">
          <div>
            <strong>Employé :</strong> <?= htmlspecialchars(($userInfo['nom'] ?? $fiche['user_nom'] ?? '') . ' ' . ($userInfo['post_nom'] ?? $fiche['user_postnom'] ?? '')) ?> — <?= htmlspecialchars($userInfo['fonction'] ?? '') ?>
          </div>
        </div>
        
        <div class="info-with-photo">
          <img src="<?= htmlspecialchars($superviseur_photo_web) ?>" 
               alt="Photo superviseur" 
               class="profile-photo-print"
               onerror="this.onerror=null;this.src='../assets/img/profiles/default.png';">
          <div>
            <strong>Superviseur :</strong> <?= htmlspecialchars($fiche['sup_nom'].' '.$fiche['sup_post_nom']) ?> — <?= htmlspecialchars($fiche['sup_fonction']) ?>
          </div>
        </div>
        
        <strong>Date de commencement :</strong> <?= htmlspecialchars($fiche['date_commencement']) ?><br>
        <strong>Statut fiche :</strong> 
        <?php
          $statutClass = 'secondary';
          $statutLabel = ucfirst($fiche['statut']);
          switch($fiche['statut']) {
            case 'encours': $statutClass = 'info'; $statutLabel = 'En cours'; break;
            case 'attente': $statutClass = 'warning'; $statutLabel = 'En attente'; break;
            case 'evalue': $statutClass = 'primary'; $statutLabel = 'Évalué'; break;
            case 'termine': $statutClass = 'success'; $statutLabel = 'Terminé'; break;
          }
        ?>
        <span class="badge bg-<?= $statutClass ?>"><?= $statutLabel ?></span>
      </div>
    </div>

    <div style="width:320px">
      <div class="summary-row mb-2">
        <div class="metric">
          <div class="small-muted">Objectifs</div>
          <div class="value"><?= $total_items ?></div>
        </div>
        <div class="metric">
          <div class="small-muted">Dépasse</div>
          <div class="value"><?= $counts['depasse'] ?></div>
        </div>
        <div class="metric">
          <div class="small-muted">Atteint</div>
          <div class="value"><?= $counts['atteint'] ?></div>
        </div>
        <div class="metric">
          <div class="small-muted">Non atteint</div>
          <div class="value"><?= $counts['non_atteint'] ?></div>
        </div>
      </div>
      <div>
        <div class="small-muted">Cote du superviseur</div>
        <?php if ($fiche_cote !== null): ?>
          <div style="font-size:1.15rem;font-weight:700;color:var(--fosip)"><?= $fiche_cote ?>/20 <span class="text-muted">(<?= $fiche_cote_pct ?>%)</span></div>
        <?php else: ?>
          <div style="font-size:1.15rem;font-weight:700;color:var(--fosip)"><span class="text-muted">Non évalué</span></div>
        <?php endif; ?>
      </div>
      <div class="mt-2 small-muted">Superviseur évalué : <?= $supervise_eval ? 'Oui' : 'Non' ?> — Commentaires coordination : <?= $coord_comments ? 'Oui' : 'Non' ?></div>
    </div>
  </div>

  <!-- Objectifs détaillés -->
  <section class="section mb-3">
    <div class="section-title"><span class="notch"></span> Objectifs détaillés</div>
    <?php if (empty($items)): ?>
      <div class="small-muted">Aucun objectif défini.</div>
    <?php else: ?>
      <table class="items-table mt-2">
        <thead>
          <tr>
            <th style="width:40px">#</th>
            <th>Objectif & résumé</th>
            <th style="width:180px">Auto‑évaluation</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Charger les commentaires de cote_des_objectifs pour chaque objectif (clé item_id)
          $supervision_comments = [];
          try {
            $stCoteCom = $pdo->prepare('SELECT item_id, commentaire FROM cote_des_objectifs WHERE fiche_id = ? AND superviseur_id = ?');
            $stCoteCom->execute([$fiche_id, $superviseur_id]);
            foreach ($stCoteCom->fetchAll(PDO::FETCH_ASSOC) as $row) {
              if (!empty($row['commentaire'])) $supervision_comments[(int)$row['item_id']] = $row['commentaire'];
            }
          } catch (Throwable $e) {}
          ?>
          <?php foreach ($items as $i => $it):
            $itemId = (int)$it['id'];
            $ae = $auto_map_by_item[$itemId] ?? null;
            $note = $ae['note'] ?? '';
            $comm = $ae['commentaire'] ?? '';
            $note_label = $note === 'depasse' ? 'Dépasse' : ($note === 'atteint' ? 'Atteint' : ($note === 'non_atteint' ? 'Non atteint' : '—'));
            $note_class = $note === 'depasse' ? 'depasse' : ($note === 'atteint' ? 'atteint' : ($note === 'non_atteint' ? 'non_atteint' : ''));
            $justif = $supervision_comments[$itemId] ?? '';
          ?>
          <tr>
            <td class="small-muted"><?= $i+1 ?></td>
            <td>
              <div style="font-weight:600;\"><?= nl2br(htmlspecialchars($it['contenu'])) ?></div>
              <?php if (!empty($it['resume'])): ?><div class="small-muted mt-2"><?= nl2br(htmlspecialchars($it['resume'])) ?></div><?php endif; ?>
              <?php if ($justif): ?>
                <div class="mt-2 p-2 bg-light border rounded small"><strong>Commentaire du superviseur :</strong><br><?= nl2br(htmlspecialchars($justif)) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div><span class="note-badge <?= $note_class ?>"><?= $note_label ?></span></div>
              <div class="small-muted mt-2"><strong>Commentaire :</strong><br><?= $comm ? nl2br(htmlspecialchars($comm)) : '<span class="small-muted">—</span>' ?></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <!-- Résumés -->
  <section class="section mb-4">
    <div class="section-title"><span class="notch"></span> Résumés & besoins</div>
    <div class="mt-2">
      <?php foreach ($resume_fields as $field):
        $label = ucwords(str_replace('_',' ', str_replace('resume_','', $field)));
        $label = str_replace('reussite','réussite',$label);
        $label = str_replace('amelioration','amélioration',$label);
        $label = str_replace('problemes','problèmes',$label);
        $val = nl2br(htmlspecialchars($fiche[$field] ?? ''));
      ?>
        <div style="margin-bottom:12px;">
          <div style="font-weight:600; color:var(--fosip)"><?= $label ?></div>
          <div class="small-muted"><?= $val ? $val : '<em>— Non renseigné —</em>' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Signatures area -->
  <div class="signatures">
    <div class="sign-box">
      <div>Responsable RH</div>
      <div class="sign-line">Signature &amp; Nom</div>
      <div class="small-muted mt-2">Date : ____________</div>
    </div>
    <div class="sign-box">
      <div>Coordination</div>
      <div class="sign-line">Signature &amp; Nom</div>
      <div class="small-muted mt-2">Date : ____________</div>
    </div>
    <div class="sign-box">
      <div>Employé / Staff</div>
      <div class="sign-line">Signature &amp; Nom</div>
      <div class="small-muted mt-2">Date : ____________</div>
    </div>
    <div class="sign-box">
      <div>Superviseur</div>
      <div class="sign-line">Signature &amp; Nom</div>
      <div class="small-muted mt-2">Date : ____________</div>
    </div>
  </div>

  <footer style="margin-top:30px; font-size:0.85rem; color:#6c757d;">
    Document généré le <?= date('d/m/Y H:i') ?> — FOSIP
  </footer>

  <script>
    // Lance la boîte d'impression automatiquement (utile pour "Enregistrer en PDF")
    window.addEventListener('load', function(){
      // small delay pour s'assurer que tout est rendu
      setTimeout(function(){ window.print(); }, 400);
    });
  </script>
</body>
</html>
