<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../includes/version.php');
include('../includes/header.php');
?>

<div class="row">
  <div class="col-md-3">
    <?php include('../includes/sidebar.php'); ?>
  </div>
  <div class="col-md-9">
    <div class="container mt-4 mb-5">
      <div class="d-flex align-items-center mb-3 py-2 px-3 rounded-3 shadow-sm" style="background:#f8f9fa;">
        <div class="me-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:#e9ecef;border-radius:50%;">
          <i class="bi bi-life-preserver" style="font-size:2rem;color:#6c757d;"></i>
        </div>
        <div class="flex-grow-1">
          <h4 class="mb-1 fw-bold" style="color:#495057;">Centre d'aide de Performance Staff Suite <i class="bi bi-shield-check"></i></h4>
          <div class="small text-muted">Version <?= FOSIP_VERSION ?> — Guide rapide des rôles, du cycle d'évaluation, des fonctionnalités et des bonnes pratiques.</div>
        </div>
        <div class="ms-auto d-none d-md-block">
         
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button class="btn btn-sm btn-outline-secondary" id="expandAll"><i class="bi bi-arrows-expand me-1"></i> Tout ouvrir</button>
        <button class="btn btn-sm btn-outline-secondary" id="collapseAll"><i class="bi bi-arrows-collapse me-1"></i> Tout fermer</button>
      </div>

      <div class="accordion" id="helpAccordion">
        <!-- Introduction -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-intro">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#c-intro" aria-expanded="true" aria-controls="c-intro">
              <i class="bi bi-clipboard-data me-2 text-primary"></i> Introduction & philosophie du système
            </button>
          </h2>
          <div id="c-intro" class="accordion-collapse collapse show" aria-labelledby="h-intro" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <p>Performance Staff Suite est une application interne destinée à planifier, suivre et clôturer les évaluations du personnel. Elle valorise la clarté des objectifs, la transparence des évaluations et la simplicité d’usage.</p>
              <ul class="mb-0">
                <li>Objectifs mesurables définis par l’agent</li>
                <li>Évaluation structurée par le superviseur (compétences + cotation 1–20)</li>
                <li>Commentaire final de la coordination qui clôture la fiche</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Rôles & permissions -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-roles">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-roles" aria-expanded="false" aria-controls="c-roles">
              <i class="bi bi-people-fill me-2 text-primary"></i> Rôles et permissions
            </button>
          </h2>
          <div id="c-roles" class="accordion-collapse collapse" aria-labelledby="h-roles" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <ul>
                <li><strong>Agent/Staff</strong> — Crée ses objectifs mensuels, saisit l'auto-évaluation, consulte sa fiche complète et l'imprime. Peut modifier son profil (mot de passe, photo).</li>
                <li><strong>Superviseur</strong> — Évalue les objectifs (1–20 avec %), renseigne les compétences, ajoute le plan d'action, valide la supervision; sa validation place la fiche en <span class="badge bg-primary">évalué</span>.</li>
                <li><strong>Coordination</strong> — Ajoute/modifie/supprime le <em>commentaire final</em>. L'ajout place la fiche en <span class="badge bg-success">clôturé</span>; la suppression ramène en <span class="badge bg-primary">évalué</span>. <strong>Peut désormais superviser</strong> également (accès aux menus, compteurs et pages de supervision).</li>
                <li><strong>Admin</strong> — Gère les utilisateurs (page modernisée en cartes avec recherche/filtres améliorés), paramètres et configurations système. Peut réinitialiser les données (commentaires, supervisions, fiches, utilisateurs) via mot de passe développeur. Peut modifier son profil complet (nom, post-nom, email, fonction) avec déconnexion forcée. Accès global en lecture/écriture.</li>
              </ul>
              <div class="small text-muted mt-2"><i class="bi bi-shield-lock me-1 text-primary"></i> <strong>Sécurité:</strong> Accès protégés par sessions et rôles (admin, coordination, superviseur, staff/agent). Actions sensibles protégées par CSRF et mot de passe développeur. Modales de confirmation pour opérations destructives avec suppressions en cascade.</div>
            </div>
          </div>
        </div>

        <!-- Cycle d'évaluation -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-cycle">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-cycle" aria-expanded="false" aria-controls="c-cycle">
              <i class="bi bi-diagram-3 me-2 text-primary"></i> Cycle d’évaluation (étapes)
            </button>
          </h2>
          <div id="c-cycle" class="accordion-collapse collapse" aria-labelledby="h-cycle" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <ol class="mb-3">
                <li><strong>Objectifs (Agent)</strong> — L’agent crée la fiche de la période (mois), ajoute les items d’objectifs.</li>
                <li><strong>Auto-évaluation</strong> — L’agent indique le niveau de réalisation et commentaires par item.</li>
                <li><strong>Supervision</strong> — Le superviseur cote chaque objectif (1–20, % affiché), évalue les compétences (individuelles/gestion/leader/profil), ajoute un commentaire général et un plan d’action.</li>
                <li><strong>Coordination</strong> — Ajoute le commentaire final; le statut passe à <span class="badge bg-success">termine</span> (clôturé).</li>
                <li><strong>Fiche complète & Impression</strong> — La synthèse affiche toutes les sections avec en-tête imprimable.</li>
              </ol>
              <div class="alert alert-light border mb-0">
                <i class="bi bi-traffic-light me-1"></i> Statuts utilisés: <span class="badge bg-info">encours</span>, <span class="badge bg-warning text-dark">attente</span>, <span class="badge bg-primary">evalue</span>, <span class="badge bg-success">termine</span>.
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation & fonctionnalités -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-fonct">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-fonct" aria-expanded="false" aria-controls="c-fonct">
              <i class="bi bi-compass me-2 text-primary"></i> Navigation et fonctionnalités clés
            </button>
          </h2>
          <div id="c-fonct" class="accordion-collapse collapse" aria-labelledby="h-fonct" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <ul>
                <li><strong>Tableau de bord</strong> — Vue synthèse par rôle, accès rapide aux fiches à traiter.</li>
                <li><strong>Mes objectifs</strong> — Création/édition des objectifs de la période, auto-évaluation.</li>
                <li><strong>Supervision</strong> — Liste des agents à évaluer, filtres par statut, saisie des notes et compétences.</li>
                <li><strong>Coordination</strong> — Cartes des fiches <em>à commenter</em> ou <em>clôturées</em>, ajout/modif/suppression du commentaire final avec confirmation.</li>
                <li><strong>Fiche complète</strong> — Synthèse lisible et imprimable (logo en haut, couleurs préservées).</li>
                <li><strong>Profil</strong> — Changer mot de passe et photo; les admins peuvent modifier nom/post-nom/email/fonction (déconnexion forcée après modification).</li>
                <li><strong>Utilisateurs (Admin)</strong> — Page modernisée en cartes responsive avec recherche/filtre améliorés; suppression en cascade.</li>
                <li><strong>Configurations (Admin)</strong> — Réinitialiser commentaires, supervisions, fiches, utilisateurs ou tout (mot de passe développeur + CSRF; admin par défaut recréé).</li>
              </ul>
              <div class="small text-muted">UI: Bootstrap 5 + Bootstrap Icons, toasts de feedback et modales de confirmation sans emojis.</div>
            </div>
          </div>
        </div>

        <!-- Statuts & règles -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-statuts">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-statuts" aria-expanded="false" aria-controls="c-statuts">
              <i class="bi bi-badge-ad me-2 text-primary"></i> Statuts et règles métiers
            </button>
          </h2>
          <div id="c-statuts" class="accordion-collapse collapse" aria-labelledby="h-statuts" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <ul>
                <li><strong>encours</strong> — Fiche en construction par l’agent.</li>
                <li><strong>attente</strong> — Fiche soumise, en attente de supervision.</li>
                <li><strong>evalue</strong> — Supervision validée (sauvegarde superviseur).</li>
                <li><strong>termine</strong> — Commentaire final coordonné; fiche clôturée.</li>
              </ul>
              <div class="alert alert-info mb-0">
                <i class="bi bi-arrow-repeat me-1"></i> Supprimer le commentaire de coordination fait revenir la fiche à <strong>evalue</strong>.
              </div>
            </div>
          </div>
        </div>

        <!-- Bonnes pratiques & erreurs à éviter -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-erreurs">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-erreurs" aria-expanded="false" aria-controls="c-erreurs">
              <i class="bi bi-shield-check me-2 text-primary"></i> Bonnes pratiques et erreurs à éviter
            </button>
          </h2>
          <div id="c-erreurs" class="accordion-collapse collapse" aria-labelledby="h-erreurs" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <ul class="mb-0">
                <li>Ne changez pas la <strong>période</strong> d’une fiche après le démarrage du cycle.</li>
                <li>Effectuez l’<strong>auto-évaluation</strong> avant la supervision pour un meilleur suivi.</li>
                <li>En supervision, utilisez la <strong>cotation 1–20</strong>; le pourcentage s’affiche automatiquement.</li>
                <li>La <strong>suppression</strong> du commentaire coordination rouvre la fiche (<em>évalué</em>).</li>
                <li>Respectez les <strong>rôles</strong> et ne partagez pas vos identifiants.</li>
                <li>En cas d’erreur d’impression (marges), préférez le format A4 et l’option “Couleurs d’arrière-plan”.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Impression & export -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-print">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-print" aria-expanded="false" aria-controls="c-print">
              <i class="bi bi-printer me-2 text-primary"></i> Impression de la fiche complète
            </button>
          </h2>
          <div id="c-print" class="accordion-collapse collapse" aria-labelledby="h-print" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <ul>
                <li>Le <strong>logo</strong> s’affiche en haut de la première page; couleurs préservées.</li>
                <li>Les éléments de navigation et boutons ne s’impriment pas.</li>
                <li>Conseil: activez “<em>Imprimer les couleurs d’arrière-plan</em>” dans votre navigateur.</li>
              </ul>
              <div class="small text-muted">En cas de première page blanche: le correctif est déjà appliqué (en-tête sans saut de page imposé).</div>
            </div>
          </div>
        </div>

        <!-- Support & contact -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-support">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-support" aria-expanded="false" aria-controls="c-support">
              <i class="bi bi-envelope-at me-2 text-primary"></i> Support & contact
            </button>
          </h2>
          <div id="c-support" class="accordion-collapse collapse" aria-labelledby="h-support" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <p>Pour toute question, suggestion ou assistance technique:</p>
              <ul class="mb-0">
                <li><strong>Email:</strong> <a href="mailto:emmabaraka@outlook.com">emmabaraka@outlook.com</a></li>
                <li><strong>Téléphone:</strong> <a href="tel:+243974051239">+243 974 051 239</a></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Licence & utilisation -->
        <div class="accordion-item shadow-sm mb-2">
          <h2 class="accordion-header" id="h-licence">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-licence" aria-expanded="false" aria-controls="c-licence">
              <i class="bi bi-shield-lock me-2 text-primary"></i> Licence & conditions d’utilisation
            </button>
          </h2>
          <div id="c-licence" class="accordion-collapse collapse" aria-labelledby="h-licence" data-bs-parent="#helpAccordion">
            <div class="accordion-body">
              <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Ce système est destiné <strong>exclusivement</strong> à un usage interne chez <strong>FOSIP</strong>.
              </div>
              <ul>
                <li>La <strong>reproduction</strong>, le <strong>partage</strong> ou la <strong>vente</strong> du système, en tout ou partie, sont interdits.</li>
                <li>Toute utilisation hors FOSIP nécessite une <strong>autorisation écrite</strong> préalable et/ou un accord spécifique.</li>
                <li>Le code et les contenus sont fournis sans licence de distribution publique.</li>
              </ul>
              <div class="small text-muted mb-0">En cas de besoin d’extension ou de déploiement dans un autre contexte, merci de prendre contact avec FOSIP pour discuter des modalités.</div>
            </div>
          </div>
        </div>


      <div class="text-center mt-4">
        <a href="dashboard.php" class="btn btn-secondary mt-4">
          Retour
        </a>
      </div>

      <div class="text-center text-muted small mt-3">
        <a href="https://cd.linkedin.com/in/emmanuel-baraka?trk=people-guest_people_search-card" target="_blank" rel="noopener" class="text-decoration-none" style="color:inherit;">
          <img src="../assets/img/Emmanuel-image.jfif" alt="Emmanuel Baraka" style="width:26px;height:26px;border-radius:50%;opacity:.85;vertical-align:middle;margin-right:6px;">
          Emmanuel Baraka
        </a>
        — © 2025 Tous droits réservés.
      </div>
    </div>
  </div>
</div>

<script>
  // Ouvrir/fermer tous les panneaux
  document.getElementById('expandAll').addEventListener('click', function(){
    document.querySelectorAll('#helpAccordion .accordion-collapse').forEach(c => new bootstrap.Collapse(c, { show: true }));
  });
  document.getElementById('collapseAll').addEventListener('click', function(){
    document.querySelectorAll('#helpAccordion .accordion-collapse.show').forEach(c => new bootstrap.Collapse(c, { toggle: true }));
  });
</script>

<?php include('../includes/footer.php'); ?>
