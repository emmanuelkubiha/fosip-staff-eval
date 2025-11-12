<?php
/**
 * pages/supervision-evaluer.php
 * =====================================================================
 * PAGE D'ÉVALUATION COMPLÈTE DU SUPERVISEUR
 * =====================================================================
 * 
 * Cette page permet au superviseur d'effectuer une évaluation complète
 * d'un agent sur 3 sections principales :
 * 
 * SECTION 1 : ÉVALUATION DES COMPÉTENCES
 * - Compétences Individuelles (persévérance, qualité, gestion du temps...)
 * - Compétences de Gestion (planification, communication, procédures...)
 * - Qualités de Leader (travail d'équipe, écoute, compassion...)
 * - Compétences Profil (compétences spécifiques définies pour l'agent)
 * Pour chaque compétence, 4 options disponibles :
 *   • Point Fort Avéré
 *   • Point Fort
 *   • Domaine à Développer
 *   • Non-Applicable
 * + Zone de commentaire optionnel par compétence
 * 
 * SECTION 2 : COTATION DES OBJECTIFS
 * - Note sur 20 pour chaque objectif item de la fiche
 * - Commentaire pour justifier la note
 * - Calcul automatique du pourcentage (note × 5%)
 * 
 * SECTION 3 : ACTIONS ET RECOMMANDATIONS
 * - Besoins de développement identifiés
 * - Nécessité et justification
 * - Plan d'action (comment atteindre l'objectif)
 * - Échéancier (quand)
 * - Autres actions ou suivis convenus
 * 
 * FONCTIONNEMENT :
 * - Le formulaire charge automatiquement les données déjà enregistrées
 * - Toutes les sections sont sauvegardées en un seul clic
 * - Boutons d'action en bas : Enregistrer / Voir l'évaluation / Supprimer
 * 
 * SÉCURITÉ :
 * - Accès réservé au rôle 'superviseur'
 * - Vérification du rattachement agent-superviseur
 * - Protection CSRF sur toutes les soumissions
 * 
 * STOCKAGE :
 * - competence_evaluation : une ligne par compétence évaluée
 * - cote_des_objectifs : notes et commentaires des objectifs
 * - actions_recommandations : plan d'action et recommandations
 * =====================================================================
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/db.php');
include('../includes/header.php');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['superviseur', 'coordination'])) {
  echo '<div class="alert alert-danger m-4">Accès refusé.</div>'; include('../includes/footer.php'); exit;
}
$superviseur_id = (int)$_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf_token = $_SESSION['csrf_token'];

$fiche_id = isset($_GET['fiche_id']) ? (int)$_GET['fiche_id'] : 0;
if ($fiche_id <= 0) { echo '<div class="alert alert-warning m-4">Fiche non spécifiée.</div>'; include('../includes/footer.php'); exit; }

// Charger la fiche et vérifier rattachement superviseur
$stF = $pdo->prepare('SELECT o.*, u.id AS agent_id, u.nom AS agent_nom, u.post_nom AS agent_post_nom, u.fonction AS agent_fonction, u.superviseur_id AS agent_superviseur FROM objectifs o JOIN users u ON u.id=o.user_id WHERE o.id=:fid LIMIT 1');
$stF->execute([':fid'=>$fiche_id]);
$fiche = $stF->fetch(PDO::FETCH_ASSOC);
if (!$fiche) { echo '<div class="alert alert-danger m-4">Fiche introuvable.</div>'; include('../includes/footer.php'); exit; }
$agent_id = (int)$fiche['agent_id'];
if ((int)$fiche['agent_superviseur'] !== $superviseur_id && (int)$fiche['superviseur_id'] !== $superviseur_id) {
  echo '<div class="alert alert-danger m-4">Vous ne pouvez pas évaluer cette fiche.</div>'; include('../includes/footer.php'); exit;
}

// Cycle (optionnel)
$periode = trim((string)$fiche['periode']);
$cycle_id = null;
if (preg_match('/^(\d{4})-(\d{2})$/', $periode, $m)) {
  $annee = (int)$m[1]; $mois = (int)$m[2];
  try {
    $stC = $pdo->prepare('SELECT id FROM evaluation_cycles WHERE annee = :a AND mois = :m LIMIT 1');
    $stC->execute([':a'=>$annee, ':m'=>$mois]);
    $rowC = $stC->fetch(PDO::FETCH_ASSOC); if ($rowC) $cycle_id = (int)$rowC['id'];
  } catch(Throwable $e){}
}

// Définition des compétences statiques (liste de comportements)
$competences_individuelles = [
  'Persévérance', 'Qualité de travail', 'Gestion du temps', 'Flexibilité', 'Auto-développement', 'Ponctualité'
];
$competences_gestion = [
  'Planification & Organisation', 'Communication verbale', 'Communication écrite', 'Respect des procédures', 'Respect des délais'
];
$competences_leader = [
  'Travail en équipe', 'Capacité d\'écoute', 'Compassion', 'Accessible', 'Qualités interpersonnelles', 'Compréhension des autres'
];

// Compétences du profil utilisateur (table competence_votre_profil)
$profil_competences = [];
try {
  $stP = $pdo->prepare('SELECT id, competence FROM competence_votre_profil WHERE user_id = :uid ORDER BY id ASC');
  $stP->execute([':uid'=>$agent_id]);
  $profil_competences = $stP->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

// Chargement des évaluations existantes (competence_evaluation)
$eval_map = []; // key = categorie|competence (normalisée) => row
try {
  $stE = $pdo->prepare('SELECT * FROM competence_evaluation WHERE superviseur_id = :sup AND supervise_id = :agent AND categorie IN ("individuelle","gestion","leader","profil")');
  $stE->execute([':sup'=>$superviseur_id, ':agent'=>$agent_id]);
  foreach($stE->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = strtolower($r['categorie'].'|'.trim($r['competence']));
    $eval_map[$key] = $r;
  }
} catch(Throwable $e) {}

// Objectifs items
$items = [];
try {
  $stI = $pdo->prepare('SELECT id, contenu, ordre FROM objectifs_items WHERE fiche_id = :fid ORDER BY ordre ASC');
  $stI->execute([':fid'=>$fiche_id]);
  $items = $stI->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

// Cotations existantes cote_des_objectifs
$cotes_map = []; // key = item_id => row
try {
  $stCote = $pdo->prepare('SELECT * FROM cote_des_objectifs WHERE fiche_id = :fid AND superviseur_id = :sup');
  $stCote->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);
  foreach($stCote->fetchAll(PDO::FETCH_ASSOC) as $r){ $cotes_map[(int)$r['item_id']] = $r; }
} catch(Throwable $e) {}

function renderRadioOptions($namePrefix, $existing){
  // $existing contient la ligne competence_evaluation éventuelle
  $checked = [
    'avere' => ($existing && (int)$existing['point_avere'] === 1),
    'fort' => ($existing && (int)$existing['point_fort'] === 1),
    'developper' => ($existing && (int)$existing['point_a_developper'] === 1),
    'na' => ($existing && (int)$existing['non_applicable'] === 1),
  ];
  $nameChoix = htmlspecialchars($namePrefix.'[choix]');
  $html = '<div class="d-flex flex-wrap gap-3 competence-options">';
  $html .= '<label class="form-check-label small">'
         . '<input type="radio" class="form-check-input" name="'.$nameChoix.'" value="avere" '.($checked['avere']?'checked':'').'>'
         . ' Point Fort Avéré</label>';
  $html .= '<label class="form-check-label small">'
         . '<input type="radio" class="form-check-input" name="'.$nameChoix.'" value="fort" '.($checked['fort']?'checked':'').'>'
         . ' Point Fort</label>';
  $html .= '<label class="form-check-label small">'
         . '<input type="radio" class="form-check-input" name="'.$nameChoix.'" value="developper" '.($checked['developper']?'checked':'').'>'
         . ' Domaine à Développer</label>';
  $html .= '<label class="form-check-label small">'
         . '<input type="radio" class="form-check-input" name="'.$nameChoix.'" value="na" '.($checked['na']?'checked':'').'>'
         . ' Non-Applicable</label>';
  $html .= '</div>';
  return $html;
}
?>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container-fluid mt-4">
      <!-- En-tête avec retour -->
      <div class="d-flex align-items-center mb-3">
        <h4 class="mb-0" style="color:#3D74B9"><i class="bi bi-clipboard-check me-2"></i> Évaluation Superviseur</h4>
        <div class="ms-auto">
          <a href="supervision-fiche.php?fiche_id=<?= (int)$fiche_id ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Retour
          </a>
        </div>
      </div>

      <!-- CARTE D'INFORMATION DE LA FICHE ÉVALUÉE -->
      <div class="card shadow-sm mb-4" style="border-left: 4px solid #3D74B9;">
        <div class="card-body">
          <h5 class="card-title mb-3">
            <i class="bi bi-file-earmark-text me-2 text-primary"></i> Informations de la Fiche
          </h5>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="d-flex align-items-start">
                <i class="bi bi-person-circle me-2 text-primary" style="font-size:1.5rem;"></i>
                <div>
                  <small class="text-muted d-block">Agent évalué</small>
                  <strong><?= htmlspecialchars(trim($fiche['agent_nom'].' '.$fiche['agent_post_nom'])) ?></strong>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="d-flex align-items-start">
                <i class="bi bi-briefcase me-2 text-success" style="font-size:1.5rem;"></i>
                <div>
                  <small class="text-muted d-block">Fonction</small>
                  <strong><?= htmlspecialchars($fiche['agent_fonction'] ?? 'Non définie') ?></strong>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="d-flex align-items-start">
                <i class="bi bi-calendar-event me-2 text-info" style="font-size:1.5rem;"></i>
                <div>
                  <small class="text-muted d-block">Période d'évaluation</small>
                  <strong><?= htmlspecialchars($periode) ?></strong>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="d-flex align-items-start">
                <i class="bi bi-hash me-2 text-warning" style="font-size:1.5rem;"></i>
                <div>
                  <small class="text-muted d-block">Fiche N°</small>
                  <strong><?= (int)$fiche_id ?></strong>
                </div>
              </div>
            </div>
          </div>
          <hr class="my-3">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-kanban me-2 text-secondary"></i>
                <div>
                  <small class="text-muted d-block">Projet</small>
                  <span><?= htmlspecialchars($fiche['nom_projet'] ?? 'Non défini') ?></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-flag me-2 text-secondary"></i>
                <div>
                  <small class="text-muted d-block">Poste</small>
                  <span><?= htmlspecialchars($fiche['poste'] ?? 'Non défini') ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mode d'emploi -->
      <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Mode d'emploi :</strong> Complétez les 3 sections ci-dessous. Sélectionnez une option pour chaque compétence, donnez une note sur 20 pour chaque objectif, puis détaillez le plan d'action. Cliquez sur "Enregistrer tout" en bas de page.
      </div>

      <form id="formEvaluation" class="mb-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>" />
        <input type="hidden" name="fiche_id" value="<?= (int)$fiche_id ?>" />
        <input type="hidden" name="agent_id" value="<?= (int)$agent_id ?>" />
        <?php if ($cycle_id !== null): ?><input type="hidden" name="cycle_id" value="<?= (int)$cycle_id ?>" /><?php endif; ?>

        <!-- ========================= SECTION 1 : ÉVALUATION DES COMPÉTENCES ========================= -->
        <div class="mb-4">
          <h5 class="text-primary mb-2">
            <i class="bi bi-stars me-2"></i> Section 1 : Évaluation des Compétences
          </h5>
          <p class="text-muted small mb-3">
            <i class="bi bi-arrow-right-circle me-1"></i> <strong>À faire :</strong> Pour chaque compétence listée ci-dessous, cochez l'option qui correspond le mieux au niveau de l'agent. Ajoutez un commentaire si nécessaire pour justifier votre évaluation.
          </p>
        </div>

        <!-- Layout en 2 colonnes pour Compétences Individuelles et Gestion -->
        <div class="row mb-4">
          <!-- Colonne Gauche : Compétences Individuelles -->
          <div class="col-lg-6 mb-3">
            <div class="card h-100 section-card shadow-sm">
              <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h6 class="mb-0"><i class="bi bi-person-check-fill me-2"></i> Compétences Individuelles</h6>
                <small>Évaluez les qualités personnelles de l'agent</small>
              </div>
              <div class="card-body">
                <?php foreach ($competences_individuelles as $i => $comp):
                  $key = strtolower('individuelle|'.trim($comp));
                  $existing = $eval_map[$key] ?? null;
                  $prefix = 'competences[individuelle_'.$i.']';
                ?>
                <div class="mb-3 p-2 rounded border competence-row">
                  <div class="fw-semibold mb-2 small"><?= htmlspecialchars($comp) ?></div>
                  <?= renderRadioOptions($prefix, $existing) ?>
                  <div class="mt-2">
                    <textarea name="<?= $prefix ?>[commentaire]" rows="2" class="form-control form-control-sm" placeholder="Commentaire..."><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>
                    <input type="hidden" name="<?= $prefix ?>[categorie]" value="individuelle" />
                    <input type="hidden" name="<?= $prefix ?>[competence]" value="<?= htmlspecialchars($comp) ?>" />
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Colonne Droite : Compétences de Gestion -->
          <div class="col-lg-6 mb-3">
            <div class="card h-100 section-card shadow-sm">
              <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <h6 class="mb-0"><i class="bi bi-diagram-3-fill me-2"></i> Compétences de Gestion</h6>
                <small>Évaluez les capacités organisationnelles de l'agent</small>
              </div>
              <div class="card-body">
                <?php foreach ($competences_gestion as $i => $comp):
                  $key = strtolower('gestion|'.trim($comp));
                  $existing = $eval_map[$key] ?? null;
                  $prefix = 'competences[gestion_'.$i.']';
                ?>
                <div class="mb-3 p-2 rounded border competence-row">
                  <div class="fw-semibold mb-2 small"><?= htmlspecialchars($comp) ?></div>
                  <?= renderRadioOptions($prefix, $existing) ?>
                  <div class="mt-2">
                    <textarea name="<?= $prefix ?>[commentaire]" rows="2" class="form-control form-control-sm" placeholder="Commentaire..."><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>
                    <input type="hidden" name="<?= $prefix ?>[categorie]" value="gestion" />
                    <input type="hidden" name="<?= $prefix ?>[competence]" value="<?= htmlspecialchars($comp) ?>" />
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Layout en 2 colonnes pour Leader et Profil -->
        <div class="row mb-5">
          <!-- Colonne Gauche : Qualités de Leader -->
          <div class="col-lg-6 mb-3">
            <div class="card h-100 section-card shadow-sm">
              <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <h6 class="mb-0"><i class="bi bi-people-fill me-2"></i> Qualités de Leader</h6>
                <small>Évaluez les compétences relationnelles et de leadership</small>
              </div>
              <div class="card-body">
                <?php foreach ($competences_leader as $i => $comp):
                  $key = strtolower('leader|'.trim($comp));
                  $existing = $eval_map[$key] ?? null;
                  $prefix = 'competences[leader_'.$i.']';
                ?>
                <div class="mb-3 p-2 rounded border competence-row">
                  <div class="fw-semibold mb-2 small"><?= htmlspecialchars($comp) ?></div>
                  <?= renderRadioOptions($prefix, $existing) ?>
                  <div class="mt-2">
                    <textarea name="<?= $prefix ?>[commentaire]" rows="2" class="form-control form-control-sm" placeholder="Commentaire..."><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>
                    <input type="hidden" name="<?= $prefix ?>[categorie]" value="leader" />
                    <input type="hidden" name="<?= $prefix ?>[competence]" value="<?= htmlspecialchars($comp) ?>" />
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Colonne Droite : Compétences Profil Utilisateur -->
          <div class="col-lg-6 mb-3">
            <div class="card h-100 section-card shadow-sm">
              <div class="card-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <h6 class="mb-0"><i class="bi bi-person-gear me-2"></i> Compétences Profil</h6>
                <small>Évaluez les compétences spécifiques du poste</small>
              </div>
              <div class="card-body">
                <?php if (empty($profil_competences)): ?>
                  <div class="alert alert-secondary small">Aucune compétence de profil définie pour cet agent.</div>
                <?php else: foreach ($profil_competences as $i => $pc):
                  $comp = $pc['competence'];
                  $key = strtolower('profil|'.trim($comp));
                  $existing = $eval_map[$key] ?? null;
                  $prefix = 'competences[profil_'.$i.']';
                ?>
                  <div class="mb-3 p-2 rounded border competence-row">
                    <div class="fw-semibold mb-2 small"><?= htmlspecialchars($comp) ?></div>
                    <?= renderRadioOptions($prefix, $existing) ?>
                    <div class="mt-2">
                      <textarea name="<?= $prefix ?>[commentaire]" rows="2" class="form-control form-control-sm" placeholder="Commentaire..."><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>
                      <input type="hidden" name="<?= $prefix ?>[categorie]" value="profil" />
                      <input type="hidden" name="<?= $prefix ?>[competence]" value="<?= htmlspecialchars($comp) ?>" />
                    </div>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ========================= SECTION 2 : COTATION DES OBJECTIFS ========================= -->
        <div class="mb-4 mt-5">
          <h5 class="text-success mb-2">
            <i class="bi bi-check2-circle me-2"></i> Section 2 : Cotation des Objectifs
          </h5>
          <p class="text-muted small mb-3">
            <i class="bi bi-arrow-right-circle me-1"></i> <strong>À faire :</strong> Donnez une note de 1 à 20 pour chaque objectif selon le niveau d'atteinte. Ajoutez un commentaire pour justifier votre notation. Le pourcentage sera calculé automatiquement.
          </p>
        </div>

        <div class="card mb-5 section-card shadow-sm">
          <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h6 class="mb-0"><i class="bi bi-clipboard-data me-2"></i> Attribution des Notes</h6>
            <small>Notez chaque objectif de la fiche sur une échelle de 1 à 20</small>
          </div>
          <div class="card-body">
            <?php if (empty($items)): ?>
              <div class="alert alert-warning">Aucun objectif item dans cette fiche.</div>
            <?php else: foreach ($items as $i => $it):
              $item_id = (int)$it['id'];
              $cote = $cotes_map[$item_id] ?? null;
              $prefix = 'objectifs['.$i.']';
            ?>
            <div class="mb-3 p-3 rounded border objectif-row">
              <div class="fw-semibold mb-2">#<?= $i+1 ?> — <?= htmlspecialchars($it['contenu']) ?></div>
              <div class="row g-2 align-items-center">
                <div class="col-md-3">
                  <label class="form-label small">Note (1-20)</label>
                  <select class="form-select" name="<?= $prefix ?>[note]">
                    <option value="">—</option>
                    <?php for($n=1;$n<=20;$n++): $sel = ($cote && (int)$cote['note']===$n)?'selected':''; ?>
                      <option value="<?= $n ?>" <?= $sel ?>><?= $n ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-9">
                  <label class="form-label small">Commentaire</label>
                  <textarea name="<?= $prefix ?>[commentaire]" rows="2" class="form-control" placeholder="Justification..."><?= htmlspecialchars($cote['commentaire'] ?? '') ?></textarea>
                </div>
              </div>
              <input type="hidden" name="<?= $prefix ?>[item_id]" value="<?= $item_id ?>" />
              <?php if ($cote && $cote['note'] !== null): $pct = round(((int)$cote['note']) * 5); ?>
                <div class="small text-muted mt-1">Moyenne item: <strong><?= (int)$cote['note'] ?>/20</strong> (<?= $pct ?>%)</div>
              <?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- ========================= SECTION 3 : ACTIONS ET RECOMMANDATIONS ========================= -->
        <?php
        // Chargement des actions/recommandations existantes
        $actionsReco = null;
        try {
          $stAR = $pdo->prepare('SELECT * FROM actions_recommandations WHERE fiche_id = :fid AND superviseur_id = :sup LIMIT 1');
          $stAR->execute([':fid'=>$fiche_id, ':sup'=>$superviseur_id]);
          $actionsReco = $stAR->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        ?>
        <div class="mb-4 mt-5">
          <h5 class="text-warning mb-2">
            <i class="bi bi-clipboard-check me-2"></i> Section 3 : Actions et Recommandations
          </h5>
          <p class="text-muted small mb-3">
            <i class="bi bi-arrow-right-circle me-1"></i> <strong>À faire :</strong> Documentez le plan de développement de l'agent. Identifiez les besoins de formation ou d'accompagnement, expliquez leur importance, détaillez le plan d'action et fixez les échéances. Cette section engage le superviseur et l'agent dans un processus de suivi.
          </p>
        </div>

        <div class="card mb-5 section-card shadow-sm">
          <div class="card-header" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
            <h6 class="mb-0"><i class="bi bi-compass me-2"></i> Plan de Développement et Suivi</h6>
            <small class="text-muted">Remplissez les 5 champs du plan d'action</small>
          </div>
          <div class="card-body">
            <div class="alert alert-warning border-warning mb-4">
              <i class="bi bi-lightbulb me-2"></i>
              <strong>Conseil :</strong> Soyez précis et concret. Cette section servira de feuille de route pour le développement professionnel de l'agent et sera consultée lors des prochains entretiens.
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-book me-2 text-primary"></i>1. Besoins de développement identifiés
                <small class="text-muted">(Formation, coaching, mentorat, nouvel emploi...)</small>
              </label>
              <textarea name="actions[besoins_developpement]" rows="3" class="form-control" placeholder="Ex: Formation en gestion de projet, coaching en leadership..."><?= htmlspecialchars($actionsReco['besoins_developpement'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-question-circle me-2 text-info"></i>2. Nécessité et justification
                <small class="text-muted">(Pourquoi ce développement est-il important ?)</small>
              </label>
              <textarea name="actions[necessite_developpement]" rows="3" class="form-control" placeholder="Ex: Pour améliorer l'efficacité dans la gestion d'équipe, combler une lacune technique..."><?= htmlspecialchars($actionsReco['necessite_developpement'] ?? '') ?></textarea>
            </div>
            
            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">
                  <i class="bi bi-signpost me-2 text-success"></i>3. Comment cet objectif sera atteint
                  <small class="text-muted">(Modalités et plan d'action concret)</small>
                </label>
                <textarea name="actions[comment_atteindre]" rows="3" class="form-control" placeholder="Ex: Inscription à une formation certifiante, accompagnement par un mentor senior, rotation de poste..."><?= htmlspecialchars($actionsReco['comment_atteindre'] ?? '') ?></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">
                  <i class="bi bi-calendar-event me-2 text-danger"></i>4. Échéancier
                  <small class="text-muted">(Date cible)</small>
                </label>
                <input type="text" name="actions[quand_atteindre]" class="form-control" placeholder="Ex: Décembre 2025" value="<?= htmlspecialchars($actionsReco['quand_atteindre'] ?? '') ?>">
                <small class="form-text text-muted">Date ou période visée</small>
              </div>
            </div>
            
            <div class="mb-0">
              <label class="form-label fw-semibold">
                <i class="bi bi-chat-left-dots me-2 text-secondary"></i>5. Autres actions ou suivis convenus
                <small class="text-muted">(Engagements mutuels, points de suivi...)</small>
              </label>
              <textarea name="actions[autres_actions]" rows="4" class="form-control" placeholder="Ex: Rendez-vous mensuel de suivi, évaluation à mi-parcours, ressources à mettre à disposition..."><?= htmlspecialchars($actionsReco['autres_actions'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <!-- ========================= BOUTONS D'ACTION ========================= -->
        <div class="card shadow-sm mb-4" style="border-left: 4px solid #0d6efd;">
          <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <button type="button" id="btnSaveAll" class="btn btn-primary">
                  <i class="bi bi-save me-1"></i> Enregistrer
                </button>
                <a href="fiche-evaluation-complete.php?fiche_id=<?= (int)$fiche_id ?>" class="btn btn-outline-info">
                  <i class="bi bi-eye me-1"></i> Voir
                </a>
                <button type="button" id="btnDelete" class="btn btn-outline-danger">
                  <i class="bi bi-trash me-1"></i> Supprimer
                </button>
              </div>
              <div>
                <a href="supervision.php" class="btn btn-outline-secondary" id="btnRetour">
                  <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
<script>
// Variable pour tracker les modifications
let formModified = false;
const form = document.getElementById('formEvaluation');

// Détecter les modifications du formulaire
if (form) {
  form.addEventListener('input', function() {
    formModified = true;
  });
  form.addEventListener('change', function() {
    formModified = true;
  });
}

// Avertissement avant de quitter si modifications non sauvegardées
window.addEventListener('beforeunload', function(e) {
  if (formModified) {
    const message = 'Vous avez des modifications non enregistrées. Êtes-vous sûr de vouloir quitter cette page ?';
    e.preventDefault();
    e.returnValue = message;
    return message;
  }
});

// Avertissement sur le bouton Retour
document.getElementById('btnRetour')?.addEventListener('click', function(e) {
  if (formModified) {
    if (!confirm('ATTENTION : Modifications non enregistrées\n\nVous avez effectué des modifications qui n\'ont pas été sauvegardées.\n\nSi vous quittez maintenant, toutes vos modifications seront perdues.\n\nÊtes-vous sûr de vouloir quitter sans enregistrer ?')) {
      e.preventDefault();
      return false;
    }
  }
});

// Gestion de la sauvegarde globale avec confirmation et récapitulatif
document.getElementById('btnSaveAll')?.addEventListener('click', function() {
  // Analyser le formulaire pour créer un récapitulatif
  const fd = new FormData(form);
  
  // Compter les compétences évaluées
  let nbCompetencesIndiv = 0, nbCompetencesGestion = 0, nbCompetencesLeader = 0, nbCompetencesProfil = 0;
  for (let [key, value] of fd.entries()) {
    if (key.includes('comp_individuelle') && key.includes('[choix]') && value) nbCompetencesIndiv++;
    if (key.includes('comp_gestion') && key.includes('[choix]') && value) nbCompetencesGestion++;
    if (key.includes('comp_leader') && key.includes('[choix]') && value) nbCompetencesLeader++;
    if (key.includes('comp_profil') && key.includes('[choix]') && value) nbCompetencesProfil++;
  }
  const totalCompetences = nbCompetencesIndiv + nbCompetencesGestion + nbCompetencesLeader + nbCompetencesProfil;
  
  // Compter les notes d'objectifs
  let nbNotesObjectifs = 0;
  for (let [key, value] of fd.entries()) {
    if (key.includes('cote[') && key.includes('][note]') && value && value !== '') nbNotesObjectifs++;
  }
  
  // Vérifier les champs du plan d'action
  const besoins = fd.get('actions[besoins_developpement]') || '';
  const necessite = fd.get('actions[necessite_developpement]') || '';
  const comment = fd.get('actions[comment_atteindre]') || '';
  const quand = fd.get('actions[quand_atteindre]') || '';
  const autres = fd.get('actions[autres_actions]') || '';
  
  const champsActionsRemplis = [besoins, necessite, comment, quand, autres].filter(v => v.trim() !== '').length;
  
  // Créer le message de récapitulatif
  let recapHTML = '<div class="text-start">';
  recapHTML += '<h6 class="mb-3"><i class="bi bi-clipboard-check me-2"></i>Récapitulatif de votre évaluation</h6>';
  
  recapHTML += '<ul class="list-unstyled mb-3">';
  recapHTML += '<li><i class="bi bi-star me-2"></i><strong>Compétences évaluées :</strong> ' + totalCompetences + '</li>';
  recapHTML += '<li class="ms-4 small">- Individuelles : ' + nbCompetencesIndiv + '</li>';
  recapHTML += '<li class="ms-4 small">- Gestion : ' + nbCompetencesGestion + '</li>';
  recapHTML += '<li class="ms-4 small">- Leader : ' + nbCompetencesLeader + '</li>';
  recapHTML += '<li class="ms-4 small">- Profil : ' + nbCompetencesProfil + '</li>';
  recapHTML += '<li class="mt-2"><i class="bi bi-bullseye me-2"></i><strong>Objectifs notés :</strong> ' + nbNotesObjectifs + '</li>';
  recapHTML += '<li class="mt-2"><i class="bi bi-clipboard-check me-2"></i><strong>Plan d\'action :</strong> ' + champsActionsRemplis + '/5 champs remplis</li>';
  recapHTML += '</ul>';
  
  // Avertir des champs vides
  const champsVidesAction = [];
  if (!besoins.trim()) champsVidesAction.push('Besoins de développement');
  if (!necessite.trim()) champsVidesAction.push('Nécessité et justification');
  if (!comment.trim()) champsVidesAction.push('Comment atteindre l\'objectif');
  if (!quand.trim()) champsVidesAction.push('Échéancier');
  if (!autres.trim()) champsVidesAction.push('Autres actions');
  
  if (champsVidesAction.length > 0) {
    recapHTML += '<div class="alert alert-warning py-2 px-3 mb-2">';
    recapHTML += '<strong><i class="bi bi-exclamation-triangle me-2"></i>Champs vides :</strong><br>';
    recapHTML += '<small>' + champsVidesAction.join(', ') + '</small>';
    recapHTML += '</div>';
  }
  
  if (totalCompetences === 0) {
    recapHTML += '<div class="alert alert-danger py-2 px-3 mb-2">';
    recapHTML += '<i class="bi bi-x-circle me-2"></i><strong>Aucune compétence évaluée !</strong>';
    recapHTML += '</div>';
  }
  
  if (nbNotesObjectifs === 0) {
    recapHTML += '<div class="alert alert-danger py-2 px-3 mb-2">';
    recapHTML += '<i class="bi bi-x-circle me-2"></i><strong>Aucun objectif noté !</strong>';
    recapHTML += '</div>';
  }
  
  recapHTML += '<hr class="my-3">';
  recapHTML += '<p class="mb-0 text-center"><strong>Voulez-vous confirmer l\'enregistrement malgré tout ?</strong></p>';
  recapHTML += '</div>';
  
  // Afficher le toast de récapitulatif
  showRecapToast(recapHTML, function(confirmed) {
    if (!confirmed) return;
    
    // Procéder à l'enregistrement
    const btn = document.getElementById('btnSaveAll');
    btn.disabled = true;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enregistrement...';
    
    fetch('supervision-evaluation-save.php', { method:'POST', body: fd })
      .then(r=>r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        if(data.ok){
          formModified = false; // Réinitialiser le flag
          showToast('success', 'Évaluation enregistrée avec succès', 'bi-check-circle-fill');
          setTimeout(() => { window.location.reload(); }, 1500);
        } else {
          showToast('danger', 'Erreur : '+ (data.error || 'Enregistrement échoué'), 'bi-exclamation-triangle-fill');
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        showToast('danger', 'Erreur réseau lors de l\'enregistrement', 'bi-wifi-off');
      });
  });
});

// Gestion de la suppression avec confirmation renforcée
document.getElementById('btnDelete')?.addEventListener('click', function() {
  if(!confirm('SUPPRESSION DÉFINITIVE\n\n' +
              'Vous allez supprimer TOUTE l\'évaluation de cette fiche :\n\n' +
              '- Toutes les compétences évaluées\n' +
              '- Toutes les notes d\'objectifs\n' +
              '- Le plan d\'action et recommandations\n\n' +
              'CETTE ACTION EST IRRÉVERSIBLE !\n\n' +
              'Êtes-vous absolument certain de vouloir continuer ?')) return;
  
  const btn = this;
  btn.disabled = true;
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression...';
  
  const fd = new FormData();
  fd.append('fiche_id', '<?= (int)$fiche_id ?>');
  fd.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
  
  fetch('supervision-evaluation-delete.php', { method:'POST', body: fd })
    .then(r=>r.json())
    .then(data => {
      if(data.ok){
        showToast('success', 'Évaluation supprimée avec succès', 'bi-check-circle-fill');
        setTimeout(() => { window.location.href = 'supervision-fiche.php?fiche_id=<?= (int)$fiche_id ?>'; }, 1000);
      } else {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        showToast('danger', 'Erreur : '+ (data.error || 'Suppression échouée'), 'bi-exclamation-triangle-fill');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = originalHTML;
      showToast('danger', 'Erreur réseau lors de la suppression', 'bi-wifi-off');
    });
});

// Fonction toast avec icônes Bootstrap
function showToast(type, message, icon = '', delay = 4500) {
  var c = document.getElementById('toast-container'); 
  if (!c) { 
    c = document.createElement('div'); 
    c.id='toast-container'; 
    c.className='position-fixed top-0 end-0 p-3'; 
    c.style.zIndex=1080; 
    document.body.appendChild(c); 
  }
  var id = 't'+Date.now(); 
  var bg='bg-primary text-white'; 
  var closeClass = 'btn-close-white';
  var defaultIcon = 'bi-info-circle-fill';
  
  if (type==='success') {
    bg='bg-success text-white'; 
    defaultIcon = 'bi-check-circle-fill';
  }
  if (type==='danger') {
    bg='bg-danger text-white'; 
    defaultIcon = 'bi-exclamation-triangle-fill';
  }
  if (type==='warning') {
    bg='bg-warning text-dark';
    closeClass = '';
    defaultIcon = 'bi-exclamation-circle-fill';
  }
  if (type==='info') {
    bg='bg-info text-white';
    defaultIcon = 'bi-info-circle-fill';
  }
  
  var iconClass = icon || defaultIcon;
  
  var html = `<div id="${id}" class="toast ${bg}" role="alert" data-bs-delay="${delay}">
    <div class="d-flex align-items-center">
      <div class="toast-body">
        <i class="bi ${iconClass} me-2"></i>${message}
      </div>
      <button type="button" class="btn-close ${closeClass} me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>`;
  c.insertAdjacentHTML('beforeend', html); 
  var el=document.getElementById(id); 
  var bs=new bootstrap.Toast(el); 
  bs.show(); 
  el.addEventListener('hidden.bs.toast', ()=> el.remove());
}

// Fonction toast de récapitulatif avec confirmation
function showRecapToast(htmlContent, callback) {
  // Créer un modal Bootstrap pour le récapitulatif
  const modalId = 'recapModal' + Date.now();
  const modalHTML = `
    <div class="modal fade" id="${modalId}" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-clipboard-data me-2"></i>Confirmation d'Enregistrement</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            ${htmlContent}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-lg me-1"></i>Annuler
            </button>
            <button type="button" class="btn btn-primary" id="${modalId}_confirm">
              <i class="bi bi-check-lg me-1"></i>Confirmer l'enregistrement
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', modalHTML);
  const modalEl = document.getElementById(modalId);
  const modal = new bootstrap.Modal(modalEl);
  
  // Bouton confirmer
  document.getElementById(modalId + '_confirm').addEventListener('click', function() {
    modal.hide();
    callback(true);
  });
  
  // Nettoyage après fermeture
  modalEl.addEventListener('hidden.bs.modal', function() {
    modalEl.remove();
  });
  
  modal.show();
}
</script>