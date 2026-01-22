<?php
// sidebar.php
// Sidebar simplifiée sans profil (déplacé dans le header)

// session_start() supprimé, la session doit être démarrée dans le script principal

require_once dirname(__DIR__) . '/includes/db.php';

// Contexte utilisateur
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'invité';

// Initialisation compteurs
$counts = [
  'objectifs_encours'      => 0,
  'objectifs_anterieur'    => 0,
  'supervision_encours'    => 0,
  'supervision_anterieur'  => 0,
  'coordination_encours'   => 0,
  'coordination_anterieur' => 0,
];

if ($user_id) {
  // Objectifs personnels en cours
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs WHERE user_id = ? AND statut IN ('encours', 'attente')");
  $stmt->execute([$user_id]);
  $counts['objectifs_encours'] = (int)$stmt->fetchColumn();

  // Objectifs antérieurs terminés
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs WHERE user_id = ? AND statut = 'termine'");
  $stmt->execute([$user_id]);
  $counts['objectifs_anterieur'] = (int)$stmt->fetchColumn();

  // Superviseur : compteurs
  if ($role === 'superviseur') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs o JOIN users u ON u.id = o.user_id WHERE u.superviseur_id = ? AND o.statut IN ('encours', 'attente')");
    $stmt->execute([$user_id]);
    $counts['supervision_encours'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs o JOIN users u ON u.id = o.user_id WHERE u.superviseur_id = ? AND o.statut IN ('evalue', 'termine')");
    $stmt->execute([$user_id]);
    $counts['supervision_anterieur'] = (int)$stmt->fetchColumn();
  }

  // Coordination
  if ($role === 'coordination') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs o JOIN users u ON u.id = o.user_id WHERE u.superviseur_id = ? AND o.statut IN ('encours', 'attente')");
    $stmt->execute([$user_id]);
    $counts['supervision_encours'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM objectifs o JOIN users u ON u.id = o.user_id WHERE u.superviseur_id = ? AND o.statut IN ('evalue', 'termine')");
    $stmt->execute([$user_id]);
    $counts['supervision_anterieur'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut = 'evalue'");
    $counts['coordination_encours'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM objectifs WHERE statut = 'termine'");
    $counts['coordination_anterieur'] = (int)$stmt->fetchColumn();
  }
}

// Configuration des menus selon le rôle
$menus = [
  'admin' => [
    ['icon' => 'house-door-fill', 'text' => 'Accueil', 'url' => 'dashboard.php'],
    ['icon' => 'people', 'text' => 'Utilisateurs', 'url' => 'users.php'],
    ['icon' => 'person-plus', 'text' => 'Ajouter utilisateur', 'url' => 'user_add.php'],
    ['icon' => 'clipboard2-data', 'text' => 'Gestion fiches', 'url' => 'admin-fiches.php'],
    ['icon' => 'sliders', 'text' => 'Configurations', 'url' => 'admin-config.php'],
    ['icon' => 'file-earmark-bar-graph', 'text' => 'Rapports', 'url' => 'rapports.php'],
  ],
  'coordination' => [
    ['icon' => 'house-door-fill', 'text' => 'Accueil', 'url' => 'dashboard.php'],
    ['icon' => 'clipboard-check', 'text' => 'Coordination finale', 'url' => 'coordination.php', 'badge' => $counts['coordination_encours'], 'submenu' => [
      ['icon' => 'hourglass-split', 'text' => 'En cours', 'url' => 'coordination.php?filter=encours', 'badge' => $counts['coordination_encours']],
      ['icon' => 'check-circle', 'text' => 'Terminées', 'url' => 'coordination.php?filter=cloture', 'badge' => $counts['coordination_anterieur']]
    ]],
    ['icon' => 'person-badge', 'text' => 'Supervision', 'url' => 'supervision.php', 'badge' => $counts['supervision_encours'], 'submenu' => [
      ['icon' => 'person', 'text' => 'Vue par agent', 'url' => 'supervision-agent.php'],
      ['icon' => 'calendar', 'text' => 'Vue par période', 'url' => 'supervision-periode.php'],
      ['icon' => 'hourglass-split', 'text' => 'À évaluer', 'url' => 'supervision.php?statut=encours', 'badge' => $counts['supervision_encours']],
      ['icon' => 'list', 'text' => 'Voir tout', 'url' => 'supervision.php?statut=all']
    ]],

  ],
  'superviseur' => [
    ['icon' => 'house-door-fill', 'text' => 'Accueil', 'url' => 'dashboard.php'],
    ['icon' => 'clipboard2-check', 'text' => 'Mes objectifs', 'url' => 'fiches-evaluation-voir.php', 'badge' => $counts['objectifs_encours'], 'submenu' => [
      ['icon' => 'plus-circle', 'text' => 'Nouvelle évaluation', 'url' => 'objectifs-ajouter.php'],
      ['icon' => 'hourglass-split', 'text' => 'En cours', 'url' => 'fiches-evaluation-voir.php?statut=encours', 'badge' => $counts['objectifs_encours']],
      ['icon' => 'check-circle', 'text' => 'Terminés', 'url' => 'fiches-evaluation-voir.php?statut=complet', 'badge' => $counts['objectifs_anterieur']],
      ['icon' => 'list', 'text' => 'Voir tout', 'url' => 'fiches-evaluation-voir.php?statut=all']
    ]],
    ['icon' => 'person-badge', 'text' => 'Supervision', 'url' => 'supervision.php', 'badge' => $counts['supervision_encours'], 'submenu' => [
      ['icon' => 'person', 'text' => 'Vue par agent', 'url' => 'supervision-agent.php'],
      ['icon' => 'calendar', 'text' => 'Vue par période', 'url' => 'supervision-periode.php'],
      ['icon' => 'hourglass-split', 'text' => 'À évaluer', 'url' => 'supervision.php?statut=encours', 'badge' => $counts['supervision_encours']],
      ['icon' => 'list', 'text' => 'Voir tout', 'url' => 'supervision.php?statut=all']
    ]],
    ['icon' => 'people', 'text' => 'Mes agents', 'url' => 'mes-agents.php'],
  ],
  'staff' => [
    ['icon' => 'house-door-fill', 'text' => 'Accueil', 'url' => 'dashboard.php'],
    ['icon' => 'clipboard2-check', 'text' => 'Mes objectifs', 'url' => 'fiches-evaluation-voir.php', 'badge' => $counts['objectifs_encours'], 'submenu' => [
      ['icon' => 'plus-circle', 'text' => 'Nouvelle évaluation', 'url' => 'objectifs-ajouter.php'],
      ['icon' => 'hourglass-split', 'text' => 'En cours', 'url' => 'fiches-evaluation-voir.php?statut=encours', 'badge' => $counts['objectifs_encours']],
      ['icon' => 'check-circle', 'text' => 'Terminés', 'url' => 'fiches-evaluation-voir.php?statut=complet', 'badge' => $counts['objectifs_anterieur']],
      ['icon' => 'list', 'text' => 'Voir tout', 'url' => 'fiches-evaluation-voir.php?statut=all']
    ]],
  ],
  'agent' => [
    ['icon' => 'house-door-fill', 'text' => 'Accueil', 'url' => 'dashboard.php'],
    ['icon' => 'clipboard2-check', 'text' => 'Mes objectifs', 'url' => 'fiches-evaluation-voir.php', 'badge' => $counts['objectifs_encours'], 'submenu' => [
      ['icon' => 'plus-circle', 'text' => 'Nouvelle évaluation', 'url' => 'objectifs-ajouter.php'],
      ['icon' => 'hourglass-split', 'text' => 'En cours', 'url' => 'fiches-evaluation-voir.php?statut=encours', 'badge' => $counts['objectifs_encours']],
      ['icon' => 'check-circle', 'text' => 'Terminés', 'url' => 'fiches-evaluation-voir.php?statut=complet', 'badge' => $counts['objectifs_anterieur']],
      ['icon' => 'list', 'text' => 'Voir tout', 'url' => 'fiches-evaluation-voir.php?statut=all']
    ]],
    ['icon' => 'person-check', 'text' => 'Auto-évaluation', 'url' => 'auto-evaluation.php'],
    ['icon' => 'star', 'text' => 'Mes compétences', 'url' => 'competence-profile.php'],
  ],
];

$menuItems = $menus[$role] ?? [];

// Helper pour marquer l'élément actif
if (!function_exists('isActive')) {
function isActive($page) {
  global $current_page;
  
  // Supervision : actif si page commence par "supervision"
  if ($page === 'supervision.php') {
    return (strpos($current_page, 'supervision') === 0) ? 'active text-white bg-primary' : '';
  }
  
  // Fiches évaluation : actif pour toutes les pages liées aux fiches
  if ($page === 'fiches-evaluation-voir.php') {
    return (preg_match('/^(fiche|fiches)-evaluation|^objectifs-ajouter/i', $current_page)) ? 'active text-white bg-primary' : '';
  }
  
  // Comparaison exacte pour les autres pages
  return $current_page === $page ? 'active text-white bg-primary' : '';
}
}
?>

<style>
  /* Sidebar moderne et élégante - Largeur augmentée */
  .sidebar-modern {
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    padding: 1.75rem; /* Augmenté de 1.5rem à 1.75rem */
    position: sticky;
    top: 88px;
    max-height: calc(100vh - 104px);
    overflow-y: auto;
    overflow-x: hidden;
    min-width: 300px; /* Largeur minimale augmentée de 280px à 300px */
    width: 300px;        /* Limite la largeur à gauche */
    max-width: 300px;    /* Empêche le débordement sur desktop */
  }
  
  /* Scrollbar personnalisée */
  .sidebar-modern::-webkit-scrollbar {
    width: 6px;
  }
  
  .sidebar-modern::-webkit-scrollbar-track {
    background: transparent;
  }
  
  .sidebar-modern::-webkit-scrollbar-thumb {
    background: rgba(61, 116, 185, 0.3);
    border-radius: 3px;
  }
  
  .sidebar-modern::-webkit-scrollbar-thumb:hover {
    background: rgba(61, 116, 185, 0.5);
  }
  
  /* En-tête de la sidebar */
  .sidebar-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(61, 116, 185, 0.1);
  }
  
  .sidebar-title {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #3D74B9;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .sidebar-title i {
    font-size: 1rem;
  }
  
  /* Badge rôle */
  .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.875rem; /* Padding réduit */
    background: linear-gradient(135deg, rgba(61, 116, 185, 0.1) 0%, rgba(61, 116, 185, 0.05) 100%);
    border-radius: 20px;
    font-size: 0.75rem; /* Réduit de 0.8rem à 0.75rem */
    font-weight: 600;
    color: #3D74B9;
    margin-bottom: 1rem;
  }
  
  .role-badge i {
    font-size: 0.9rem; /* Réduit de 1rem à 0.9rem */
  }
  
  /* Liste de navigation - Suppression du point noir */
  .nav-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    list-style: none; /* Supprime les puces */
    padding: 0;
    margin: 0;
  }
  
  /* Items de menu */
  .nav-item-modern {
    position: relative;
    list-style: none; /* Supprime les puces */
  }
  
  .nav-link-modern {
    display: flex;
    align-items: center;
    gap: 1rem; /* Augmenté de 0.875rem à 1rem */
    padding: 0.85rem 1rem; /* Augmenté de 0.75rem 0.875rem */
    border-radius: 12px;
    color: #495057;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem; /* Légèrement augmenté de 0.85rem */
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }
  
  /* Effet de fond au survol */
  .nav-link-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(61, 116, 185, 0.08) 0%, rgba(61, 116, 185, 0.04) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 0;
  }
  
  .nav-link-modern:hover::before {
    opacity: 1;
  }
  
  /* Barre latérale active */
  .nav-link-modern::after {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 0;
    background: linear-gradient(180deg, #3D74B9 0%, #2a5a94 100%);
    border-radius: 0 4px 4px 0;
    transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  .nav-link-modern.active::after,
  .nav-link-modern:hover::after {
    height: 70%;
  }
  
  /* Contenu du lien - Espacement augmenté */
  .nav-link-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1rem; /* Augmenté de 0.875rem à 1rem */
    width: 100%;
  }
  
  /* Icône */
  .nav-icon {
    font-size: 1.1rem; /* Réduit de 1.25rem à 1.1rem */
    width: 22px; /* Réduit de 24px à 22px */
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
  }
  
  .nav-link-modern:hover .nav-icon {
    transform: scale(1.1);
    color: #3D74B9;
  }
  
  .nav-link-modern.active .nav-icon {
    color: #3D74B9;
  }
  
  /* Texte */
  .nav-text {
    flex: 1;
    transition: all 0.3s ease;
  }
  
  .nav-link-modern:hover .nav-text {
    color: #3D74B9;
    transform: translateX(4px);
  }
  
  .nav-link-modern.active .nav-text {
    color: #3D74B9;
    font-weight: 600;
  }
  
  /* État actif */
  .nav-link-modern.active {
    background: linear-gradient(135deg, rgba(61, 116, 185, 0.12) 0%, rgba(61, 116, 185, 0.06) 100%);
    box-shadow: 0 2px 8px rgba(61, 116, 185, 0.1);
  }
  
  /* Effet hover */
  .nav-link-modern:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(61, 116, 185, 0.15);
  }
  
  /* Section divider */
  .sidebar-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(61, 116, 185, 0.2), transparent);
    margin: 1rem 0;
  }
  
  /* Footer sidebar avec lien cliquable */
  .sidebar-footer {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(61, 116, 185, 0.1);
  }
  
  .sidebar-footer-text {
    font-size: 0.7rem; /* Réduit de 0.75rem à 0.7rem */
    color: #6c757d;
    text-align: center;
    margin-bottom: 0.5rem;
  }
  
  .footer-link-fosip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.875rem; /* Padding réduit */
    background: linear-gradient(135deg, rgba(61, 116, 185, 0.08) 0%, rgba(61, 116, 185, 0.04) 100%);
    border-radius: 20px;
    color: #3D74B9;
    text-decoration: none;
    font-size: 0.7rem; /* Réduit de 0.75rem à 0.7rem */
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid rgba(61, 116, 185, 0.2);
  }
  
  .footer-link-fosip:hover {
    background: linear-gradient(135deg, rgba(61, 116, 185, 0.15) 0%, rgba(61, 116, 185, 0.08) 100%);
    border-color: rgba(61, 116, 185, 0.4);
    color: #2a5a94;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(61, 116, 185, 0.2);
  }
  
  .footer-link-fosip i {
    font-size: 0.85rem; /* Réduit de 0.9rem à 0.85rem */
    transition: transform 0.3s ease;
  }
  
  .footer-link-fosip:hover i.bi-box-arrow-up-right {
    transform: translate(3px, -3px);
  }
  
  /* Animation d'entrée */
  @keyframes slideInLeft {
    from {
      opacity: 0;
      transform: translateX(-20px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }
  
  .nav-item-modern {
    animation: slideInLeft 0.4s ease forwards;
    opacity: 0;
  }
  
  .nav-item-modern:nth-child(1) { animation-delay: 0.05s; }
  .nav-item-modern:nth-child(2) { animation-delay: 0.1s; }
  .nav-item-modern:nth-child(3) { animation-delay: 0.15s; }
  .nav-item-modern:nth-child(4) { animation-delay: 0.2s; }
  .nav-item-modern:nth-child(5) { animation-delay: 0.25s; }
  
  /* Responsive */
  @media (max-width: 768px) {
    .sidebar-modern {
      position: static;
      margin-bottom: 1.5rem;
    }
  }
  
  /* Sous-menu - Espacement augmenté et collapsible */
  .nav-submenu {
    display: none; /* Caché par défaut */
    flex-direction: column;
    gap: 0.35rem;
    margin-left: 2.75rem;
    margin-top: 0.65rem;
    margin-bottom: 0.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
  }
  
  .nav-submenu.show {
    display: flex;
  }
  
  /* Flèche d'expansion */
  .nav-expand-icon {
    margin-left: auto;
    transition: transform 0.3s ease;
    font-size: 0.8rem;
  }
  
  .nav-link-modern.expanded .nav-expand-icon {
    transform: rotate(180deg);
  }
  
  /* Bouton parent avec sous-menu */
  .nav-link-modern.has-submenu {
    cursor: pointer;
  }
  
  .nav-submenu-link {
    display: flex;
    align-items: center;
    gap: 0.65rem; /* Augmenté de 0.5rem à 0.65rem */
    padding: 0.5rem 0.75rem; /* Augmenté de 0.4rem 0.65rem */
    border-radius: 8px;
    color: #6c757d;
    text-decoration: none;
    font-weight: 400;
    font-size: 0.825rem; /* Augmenté de 0.8rem */
    transition: all 0.2s ease;
    position: relative;
  }
  
  .nav-submenu-link:hover {
    background: rgba(61, 116, 185, 0.08);
    color: #3D74B9;
    transform: translateX(4px);
  }
  
  .nav-submenu-link i {
    font-size: 0.85rem;
  }
  
  /* Badge compteur - Légèrement plus grand */
  .nav-badge {
    margin-left: auto;
    padding: 0.3rem 0.55rem; /* Augmenté de 0.25rem 0.5rem */
    font-size: 0.72rem; /* Augmenté de 0.7rem */
    font-weight: 600;
    border-radius: 20px;
    background: #3D74B9;
    color: white;
    min-width: 22px; /* Augmenté de 20px */
    text-align: center;
    box-shadow: 0 2px 4px rgba(61, 116, 185, 0.3);
  }
  
  /* Badge pour sous-menu - Légèrement plus grand */
  .nav-submenu-badge {
    margin-left: auto;
    padding: 0.2rem 0.45rem; /* Augmenté de 0.15rem 0.4rem */
    font-size: 0.68rem; /* Augmenté de 0.65rem */
    font-weight: 600;
    border-radius: 12px;
    background: #3D74B9;
    color: white;
    min-width: 20px; /* Augmenté de 18px */
    text-align: center;
  }
  
  /* Responsive - Cacher la sidebar sur mobile */
  @media (max-width: 991px) {
    .sidebar-modern {
      display: none; /* Caché par défaut sur mobile */
    }
  }
  
  /* Offcanvas pour mobile */
  .offcanvas-sidebar {
    max-width: 320px;
  }
  
  .offcanvas-sidebar .sidebar-modern {
    position: static;
    top: 0;
    max-height: 100%;
    box-shadow: none;
    border-radius: 0;
    padding: 1.5rem;
    display: block; /* Visible dans l'offcanvas */
  }
  
  .offcanvas.offcanvas-sidebar {
    z-index: 1080 !important;
  }
</style>

<!-- Sidebar Desktop (visible sur écrans larges) -->
<nav class="sidebar-modern d-none d-lg-block">
  <!-- En-tête -->
  <div class="sidebar-header">
    <h6 class="sidebar-title">
      <i class="bi bi-compass"></i>
      Navigation
    </h6>
  </div>
  
  <!-- Badge du rôle -->
  <div class="role-badge">
    <i class="bi bi-shield-check"></i>
    <span><?= htmlspecialchars(ucfirst($role)) ?></span>
  </div>
  
  <!-- Menu -->
  <ul class="nav-modern">
    <?php foreach ($menuItems as $index => $item): 
      $isActive = ($current_page === $item['url']) ? 'active' : '';
      $hasBadge = isset($item['badge']) && $item['badge'] > 0;
      $hasSubmenu = !empty($item['submenu']);
    ?>
      <li class="nav-item-modern">
        <?php if ($hasSubmenu): ?>
          <a href="#" class="nav-link-modern <?= $isActive ?> has-submenu" onclick="toggleSubmenu(event, this)">
            <div class="nav-link-content">
              <i class="bi bi-<?= htmlspecialchars($item['icon']) ?> nav-icon"></i>
              <span class="nav-text"><?= htmlspecialchars($item['text']) ?></span>
              <?php if ($hasBadge): ?>
                <span class="nav-badge"><?= (int)$item['badge'] ?></span>
              <?php endif; ?>
              <i class="bi bi-chevron-down nav-expand-icon"></i>
            </div>
          </a>
        <?php else: ?>
          <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link-modern <?= $isActive ?>">
            <div class="nav-link-content">
              <i class="bi bi-<?= htmlspecialchars($item['icon']) ?> nav-icon"></i>
              <span class="nav-text"><?= htmlspecialchars($item['text']) ?></span>
              <?php if ($hasBadge): ?>
                <span class="nav-badge"><?= (int)$item['badge'] ?></span>
              <?php endif; ?>
            </div>
          </a>
        <?php endif; ?>
        
        <?php if ($hasSubmenu): ?>
        <div class="nav-submenu">
          <?php foreach ($item['submenu'] as $subitem): 
            $hasSubBadge = isset($subitem['badge']) && $subitem['badge'] > 0;
          ?>
          <a href="<?= htmlspecialchars($subitem['url']) ?>" class="nav-submenu-link">
            <i class="bi bi-<?= htmlspecialchars($subitem['icon']) ?>"></i>
            <span><?= htmlspecialchars($subitem['text']) ?></span>
            <?php if ($hasSubBadge): ?>
              <span class="nav-submenu-badge"><?= (int)$subitem['badge'] ?></span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  
  <!-- Divider -->
  <div class="sidebar-divider"></div>
  
  <!-- Liens supplémentaires -->
  <ul class="nav-modern">
    <li class="nav-item-modern">
      <a href="aide.php" class="nav-link-modern <?= $current_page === 'aide.php' ? 'active' : '' ?>">
        <div class="nav-link-content">
          <i class="bi bi-question-circle nav-icon"></i>
          <span class="nav-text">Aide & Support</span>
        </div>
      </a>
    </li>
    
    <li class="nav-item-modern">
      <a href="profile.php" class="nav-link-modern <?= $current_page === 'profile.php' ? 'active' : '' ?>">
        <div class="nav-link-content">
          <i class="bi bi-person-circle nav-icon"></i>
          <span class="nav-text">Mon profil</span>
        </div>
      </a>
    </li>
  </ul>
  
  <!-- Footer -->
  <div class="sidebar-footer">
    <p class="sidebar-footer-text">
      <i class="bi bi-shield-check"></i>
      Performance Staff Suite v1.0
    </p>
    <div class="text-center">
      <a href="https://fosip-drc.org" 
         target="_blank" 
         rel="noopener noreferrer" 
         class="footer-link-fosip"
         title="Visitez le site officiel de FOSIP">
        <i class="bi bi-globe2"></i>
        <span>FOSIP</span>
        <i class="bi bi-box-arrow-up-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- Offcanvas Sidebar Mobile -->
<div class="offcanvas offcanvas-start offcanvas-sidebar" tabindex="-1" id="sidebarMobile" aria-labelledby="sidebarMobileLabel">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="sidebarMobileLabel" style="color: #3D74B9;">
      <i class="bi bi-compass me-2"></i>
      Navigation
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <nav class="sidebar-modern">
      <!-- Badge du rôle -->
      <div class="role-badge">
        <i class="bi bi-shield-check"></i>
        <span><?= htmlspecialchars(ucfirst($role)) ?></span>
      </div>
      
      <!-- Menu -->
      <ul class="nav-modern">
        <?php foreach ($menuItems as $index => $item): 
          $isActive = ($current_page === $item['url']) ? 'active' : '';
          $hasBadge = isset($item['badge']) && $item['badge'] > 0;
          $hasSubmenu = !empty($item['submenu']);
        ?>
          <li class="nav-item-modern">
            <?php if ($hasSubmenu): ?>
              <a href="#" class="nav-link-modern <?= $isActive ?> has-submenu" onclick="toggleSubmenu(event, this)">
                <div class="nav-link-content">
                  <i class="bi bi-<?= htmlspecialchars($item['icon']) ?> nav-icon"></i>
                  <span class="nav-text"><?= htmlspecialchars($item['text']) ?></span>
                  <?php if ($hasBadge): ?>
                    <span class="nav-badge"><?= (int)$item['badge'] ?></span>
                  <?php endif; ?>
                  <i class="bi bi-chevron-down nav-expand-icon"></i>
                </div>
              </a>
            <?php else: ?>
              <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link-modern <?= $isActive ?>" data-bs-dismiss="offcanvas">
                <div class="nav-link-content">
                  <i class="bi bi-<?= htmlspecialchars($item['icon']) ?> nav-icon"></i>
                  <span class="nav-text"><?= htmlspecialchars($item['text']) ?></span>
                  <?php if ($hasBadge): ?>
                    <span class="nav-badge"><?= (int)$item['badge'] ?></span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endif; ?>
            
            <?php if ($hasSubmenu): ?>
            <div class="nav-submenu">
              <?php foreach ($item['submenu'] as $subitem): 
                $hasSubBadge = isset($subitem['badge']) && $subitem['badge'] > 0;
              ?>
              <a href="<?= htmlspecialchars($subitem['url']) ?>" class="nav-submenu-link" data-bs-dismiss="offcanvas">
                <i class="bi bi-<?= htmlspecialchars($subitem['icon']) ?>"></i>
                <span><?= htmlspecialchars($subitem['text']) ?></span>
                <?php if ($hasSubBadge): ?>
                  <span class="nav-submenu-badge"><?= (int)$subitem['badge'] ?></span>
                <?php endif; ?>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      
      <!-- Divider -->
      <div class="sidebar-divider"></div>
      
      <!-- Liens supplémentaires -->
      <ul class="nav-modern">
        <li class="nav-item-modern">
          <a href="aide.php" class="nav-link-modern <?= $current_page === 'aide.php' ? 'active' : '' ?>" data-bs-dismiss="offcanvas">
            <div class="nav-link-content">
              <i class="bi bi-question-circle nav-icon"></i>
              <span class="nav-text">Aide & Support</span>
            </div>
          </a>
        </li>
        
        <li class="nav-item-modern">
          <a href="profile.php" class="nav-link-modern <?= $current_page === 'profile.php' ? 'active' : '' ?>" data-bs-dismiss="offcanvas">
            <div class="nav-link-content">
              <i class="bi bi-person-circle nav-icon"></i>
              <span class="nav-text">Mon profil</span>
            </div>
          </a>
        </li>
      </ul>
      
      <!-- Footer -->
      <div class="sidebar-footer">
        <p class="sidebar-footer-text">
          <i class="bi bi-shield-check"></i>
          Performance Staff Suite v1.0
        </p>
        <div class="text-center">
          <a href="https://fosip-drc.org" 
             target="_blank" 
             rel="noopener noreferrer" 
             class="footer-link-fosip"
             title="Visitez le site officiel de FOSIP">
            <i class="bi bi-globe2"></i>
            <span>FOSIP</span>
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
        </div>
      </div>
    </nav>
  </div>
</div>

<script>
// Fonction pour basculer l'affichage des sous-menus
function toggleSubmenu(event, element) {
  event.preventDefault();
  event.stopPropagation();
  
  // Récupérer le sous-menu
  const submenu = element.parentElement.querySelector('.nav-submenu');
  
  if (submenu) {
    // Toggle la classe 'show'
    submenu.classList.toggle('show');
    
    // Toggle la classe 'expanded' sur le lien parent
    element.classList.toggle('expanded');
  }
}

// Au chargement, ouvrir automatiquement les sous-menus contenant la page active
document.addEventListener('DOMContentLoaded', function() {
  const currentPage = window.location.pathname.split('/').pop();
  const currentSearch = window.location.search;
  const currentFullUrl = currentPage + currentSearch;
  
  let foundActiveSubmenuLink = false;
  
  // Pour chaque sous-menu link
  document.querySelectorAll('.nav-submenu-link').forEach(function(link) {
    const linkHref = link.getAttribute('href');
    const linkPage = linkHref.split('?')[0].split('/').pop();
    const linkSearch = linkHref.includes('?') ? '?' + linkHref.split('?')[1] : '';
    const linkFullUrl = linkPage + linkSearch;
    
    // Vérification exacte : page ET paramètres doivent correspondre
    let isExactMatch = false;
    
    if (linkSearch) {
      // Si le lien a des paramètres, vérifier la correspondance exacte
      isExactMatch = (currentFullUrl === linkFullUrl);
    } else {
      // Si le lien n'a pas de paramètres, vérifier seulement la page
      isExactMatch = (currentPage === linkPage && !currentSearch);
    }
    
    if (isExactMatch) {
      foundActiveSubmenuLink = true;
      
      // Ajouter la classe active au lien
      link.classList.add('active');
      link.style.background = 'rgba(61, 116, 185, 0.15)';
      link.style.color = '#3D74B9';
      link.style.fontWeight = '600';
      
      // Trouver le sous-menu parent et l'afficher
      const submenu = link.closest('.nav-submenu');
      if (submenu) {
        submenu.classList.add('show');
        // Trouver le lien parent et le marquer comme expanded et actif
        const parentLink = submenu.previousElementSibling;
        if (parentLink) {
          parentLink.classList.add('expanded', 'active');
        }
      }
    }
  });
  
  // Pour les liens principaux sans sous-menu
  document.querySelectorAll('.nav-link-modern:not(.has-submenu)').forEach(function(link) {
    const linkHref = link.getAttribute('href');
    const linkPage = linkHref.split('?')[0].split('/').pop();
    
    if (currentPage === linkPage && !currentSearch) {
      link.classList.add('active');
    }
  });
  
  // Pour les liens principaux avec sous-menu, vérifier si une page du sous-menu est active
  document.querySelectorAll('.nav-link-modern.has-submenu').forEach(function(link) {
    const submenu = link.parentElement.querySelector('.nav-submenu');
    if (submenu && submenu.classList.contains('show')) {
      // Si le sous-menu est ouvert (page active dedans), marquer le parent comme actif
      link.classList.add('active');
    }
  });
});

// Correction navigation offcanvas mobile : redirige après fermeture
document.addEventListener('DOMContentLoaded', function() {
  var sidebarOffcanvas = document.getElementById('sidebarMobile');
  if (!sidebarOffcanvas) return;

  sidebarOffcanvas.querySelectorAll('a[data-bs-dismiss="offcanvas"]').forEach(function(link) {
    link.addEventListener('click', function(e) {
      var href = link.getAttribute('href');
      if (href && href !== '#' && href !== 'javascript:void(0)') {
        e.preventDefault();
        var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(sidebarOffcanvas);
        offcanvas.hide();
        setTimeout(function() {
          window.location.href = href;
        }, 350); // délai pour laisser le menu se fermer
      }
    });
  });
});
</script>
