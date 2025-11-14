<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Staff Performance Suite - FOSIP</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/logocircular.png">
  <link rel="shortcut icon" type="image/png" href="../assets/img/logocircular.png">
  
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Style personnalisé -->
  <link rel="stylesheet" href="../assets/css/style.css">
  
  <style>
    /* Loader amélioré - couleurs FOSIP */
    #page-loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #e8f2f9 0%, #ffffff 40%, #fef5ed 100%); /* Gradient bleu clair vers jaune clair FOSIP */
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    
    #page-loader.fade-out {
      opacity: 0;
      visibility: hidden;
    }
    
    .loader-container {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2rem;
      animation: loaderFloat 3s ease-in-out infinite;
    }
    
    @keyframes loaderFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    
    /* Double cercle tournant avec couleurs FOSIP */
    .spinner-ring {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      border: 5px solid transparent;
      border-top-color: #3D74B9; /* Bleu FOSIP */
      border-right-color: rgba(61, 116, 185, 0.3);
      animation: spinRotate 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
      position: relative;
      box-shadow: 0 0 30px rgba(61, 116, 185, 0.3);
    }
    
    .spinner-ring::before {
      content: '';
      position: absolute;
      top: -5px;
      left: -5px;
      right: -5px;
      bottom: -5px;
      border-radius: 50%;
      border: 5px solid transparent;
      border-bottom-color: #F5C7A5; /* Jaune FOSIP */
      border-left-color: rgba(245, 199, 165, 0.4);
      animation: spinRotate 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite reverse;
    }
    
    .spinner-ring::after {
      content: '';
      position: absolute;
      top: 15px;
      left: 15px;
      right: 15px;
      bottom: 15px;
      border-radius: 50%;
      border: 3px solid transparent;
      border-top-color: rgba(61, 116, 185, 0.5);
      animation: spinRotate 2s linear infinite;
    }
    
    @keyframes spinRotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Logo avec effet pulsant FOSIP */
    .logo-center {
      position: absolute;
      width: 75px;
      height: 75px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border-radius: 50%;
      object-fit: cover;
      background: white;
      padding: 10px;
      box-shadow: 
        0 0 20px rgba(61, 116, 185, 0.4),
        0 0 40px rgba(245, 199, 165, 0.3),
        0 10px 30px rgba(0, 0, 0, 0.1);
      animation: logoGlow 2s ease-in-out infinite;
    }
    
    @keyframes logoGlow {
      0%, 100% { 
        transform: translate(-50%, -50%) scale(1);
        box-shadow: 
          0 0 20px rgba(61, 116, 185, 0.4),
          0 0 40px rgba(245, 199, 165, 0.3),
          0 10px 30px rgba(0, 0, 0, 0.1);
      }
      50% { 
        transform: translate(-50%, -50%) scale(1.08);
        box-shadow: 
          0 0 30px rgba(61, 116, 185, 0.6),
          0 0 60px rgba(245, 199, 165, 0.4),
          0 15px 40px rgba(0, 0, 0, 0.15);
      }
    }
    
    /* Texte avec couleur FOSIP */
    .loading-text {
      font-size: 1.25rem;
      font-weight: 700;
      color: #3D74B9; /* Bleu FOSIP */
      letter-spacing: 2px;
      position: relative;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .loading-text::after {
      content: '';
      position: absolute;
      right: 0;
      bottom: 0;
      width: 30px;
      height: 3px;
      background: #3D74B9;
      border-radius: 3px;
      animation: loadingBar 1.5s ease-in-out infinite;
    }
    
    @keyframes loadingBar {
      0%, 100% { 
        width: 0;
        opacity: 0;
      }
      50% { 
        width: 30px;
        opacity: 1;
      }
    }
    
    /* Points animés dégradé FOSIP */
    .loading-dots {
      display: inline-flex;
      gap: 8px;
    }
    
    .loading-dots span {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      animation: dotJump 1.4s ease-in-out infinite;
    }
    
    .loading-dots span:nth-child(1) { 
      background: #3D74B9; /* Bleu FOSIP */
      box-shadow: 0 2px 8px rgba(61, 116, 185, 0.4);
      animation-delay: 0s; 
    }
    
    .loading-dots span:nth-child(2) { 
      background: linear-gradient(135deg, #3D74B9, #F5C7A5); /* Gradient bleu vers jaune */
      box-shadow: 0 2px 8px rgba(61, 116, 185, 0.3);
      animation-delay: 0.2s; 
    }
    
    .loading-dots span:nth-child(3) { 
      background: #F5C7A5; /* Jaune FOSIP */
      box-shadow: 0 2px 8px rgba(245, 199, 165, 0.4);
      animation-delay: 0.4s; 
    }
    
    @keyframes dotJump {
      0%, 60%, 100% { 
        transform: translateY(0) scale(1);
        opacity: 0.7;
      }
      30% { 
        transform: translateY(-15px) scale(1.2);
        opacity: 1;
      }
    }
    
    /* Barre de progression avec couleurs FOSIP */
    .progress-bar-loader {
      width: 240px;
      height: 6px;
      background: rgba(61, 116, 185, 0.15);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .progress-bar-loader::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(
        90deg,
        transparent,
        rgba(61, 116, 185, 0.5) 10%,
        #3D74B9 40%,
        #F5C7A5 60%,
        rgba(245, 199, 165, 0.5) 90%,
        transparent
      );
      animation: progressSlide 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      box-shadow: 0 0 15px rgba(61, 116, 185, 0.5);
    }
    
    @keyframes progressSlide {
      0% { left: -100%; }
      100% { left: 200%; }
    }
    
    /* Texte de sous-titre */
    .loading-subtitle {
      font-size: 0.9rem;
      color: #8a6e5a; /* Couleur entre bleu et jaune */
      font-weight: 500;
      margin-top: -0.5rem;
      animation: subtitleFade 2s ease-in-out infinite;
    }
    
    @keyframes subtitleFade {
      0%, 100% { opacity: 0.5; color: #3D74B9; }
      50% { opacity: 1; color: #d4a574; }
    }
    
    /* Particules décoratives FOSIP */
    .loader-container::before,
    .loader-container::after {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      animation: particleFloat 4s ease-in-out infinite;
    }
    
    .loader-container::before {
      top: -100px;
      left: -100px;
      background: radial-gradient(circle, rgba(61, 116, 185, 0.08) 0%, transparent 70%); /* Bleu */
      animation-delay: 0s;
    }
    
    .loader-container::after {
      bottom: -100px;
      right: -100px;
      background: radial-gradient(circle, rgba(245, 199, 165, 0.08) 0%, transparent 70%); /* Jaune */
      animation-delay: 2s;
    }
    
    @keyframes particleFloat {
      0%, 100% { 
        transform: scale(1) translateY(0);
        opacity: 0.4;
      }
      50% { 
        transform: scale(1.2) translateY(-20px);
        opacity: 0.7;
      }
    }
    
    /* Header moderne et stylé */
    .navbar-fosip {
      background: linear-gradient(135deg, #3D74B9 0%, #2a5a94 100%);
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      padding: 0.75rem 1rem;
    }
    
    .navbar-brand-fosip {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 700;
      font-size: 1.1rem;
      color: white !important;
      text-decoration: none;
      transition: transform 0.2s ease;
    }
    
    .navbar-brand-fosip:hover {
      transform: translateY(-2px);
      text-decoration: none;
    }
    
    /* Branding modernisé et encore plus léger */
    .logo-fosip {
      height: 28px !important;
      width: auto;
      box-shadow: 0 0 8px #3D74B955;
      transition: box-shadow 0.2s;
    }
    
    .logo-fosip:hover {
      box-shadow: 0 0 16px #3D74B9aa;
    }
    
    /* --- Remplacement : titre en blanc, lisible et moderne --- */
.brand-text-container { min-width: 0; max-width: 320px; }

.brand-main-title {
  display: inline-block;
  font-family: 'Montserrat', 'Segoe UI', Arial, sans-serif;
  font-weight: 700;
  font-size: 1.06rem;
  line-height: 1;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 260px;

  /* Texte blanc propre (remplace le gradient) */
  color: #ffffff;
  -webkit-text-fill-color: #ffffff;
  background: none;
  -webkit-background-clip: initial;
  background-clip: initial;

  /* Profondeur subtile pour lisibilité */
  text-shadow: 0 2px 10px rgba(0,0,0,0.18);
  -webkit-font-smoothing: antialiased;
  position: relative;
}

/* Barre d'accent discrète, blanche translucide, qui s'élargit au survol */
.brand-main-title::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: -6px;
  width: 32px;
  height: 3px;
  border-radius: 3px;
  background: rgba(255,255,255,0.28);
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  transition: width .22s ease;
}
.navbar-brand-fosip:hover .brand-main-title::after { width: 64px; }

/* Sous-titre léger en blanc semi-translucide */
.brand-subtitle {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.88);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
  max-width: 300px;
}

/* Ajustements responsive */
@media (max-width: 576px) {
  .brand-main-title { max-width: 140px; font-size: 0.95rem; }
  .brand-subtitle { display: none; }
}
    /* --- fin remplacement --- */
    
    /* Profil utilisateur dans le header */
    .user-profile-dropdown {
      position: relative;
    }
    
    .profile-trigger {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.5rem 1rem;
      background: rgba(255,255,255,0.1);
      border: 2px solid rgba(255,255,255,0.2);
      border-radius: 50px;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .profile-trigger:hover {
      background: rgba(255,255,255,0.2);
      border-color: rgba(255,255,255,0.4);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    
    .profile-avatar-header {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .profile-info-header {
      display: flex;
      flex-direction: column;
      gap: 0;
      text-align: left;
    }
    
    .profile-name-header {
      font-size: 0.9rem;
      font-weight: 600;
      color: white;
      line-height: 1.2;
      margin: 0;
    }
    
    .profile-role-header {
      font-size: 0.7rem;
      color: rgba(255,255,255,0.8);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 500;
      line-height: 1;
    }
    
    .profile-chevron {
      color: white;
      transition: transform 0.3s ease;
      font-size: 0.9rem;
    }
    
    .profile-trigger:hover .profile-chevron {
      transform: rotate(180deg);
    }
    
    
    /* Conteneur du texte de branding */
    .brand-text-container {
      display: flex;
      flex-direction: column;
      gap: 0.15rem;
    }
    
    .brand-main-title {
      font-size: 1.3rem;
      font-weight: 800;
      color: white;
      line-height: 1.2;
      letter-spacing: 0.3px;
      text-shadow: 0 2px 8px rgba(0,0,0,0.3);
      background: linear-gradient(135deg, #ffffff 0%, rgba(245,199,165,0.9) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      position: relative;
    }
    
    .brand-subtitle {
      font-size: 0.7rem;
      font-weight: 600;
      color: rgba(245,199,165,0.95);
      text-transform: uppercase;
      letter-spacing: 1.5px;
      text-shadow: 0 1px 3px rgba(0,0,0,0.3);
      line-height: 1;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .brand-subtitle::before {
      content: '';
      width: 20px;
      height: 2px;
      background: linear-gradient(90deg, transparent, rgba(245,199,165,0.8));
      border-radius: 2px;
    }
    
    /* Animation subtile du titre */
    @keyframes titleShine {
      0%, 100% { filter: brightness(1); }
      50% { filter: brightness(1.15); }
    }
    
    .navbar-brand-fosip:hover .brand-main-title {
      animation: titleShine 2s ease-in-out infinite;
    }
    
    /* Dropdown menu stylé */
    .dropdown-menu-fosip {
      min-width: 280px;
      padding: 0.5rem;
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      border-radius: 12px;
      margin-top: 0.75rem;
    }
    
    .dropdown-header-fosip {
      padding: 0.85rem 1rem;
      background: linear-gradient(135deg, rgba(61,116,185,0.15) 0%, rgba(42,90,148,0.15) 100%);
      border-radius: 8px;
      margin-bottom: 0.5rem;
      border-left: 3px solid #3D74B9;
    }
    
    .dropdown-header-fosip .user-info-name {
      font-weight: 600;
      color: #3D74B9;
      font-size: 0.95rem;
      margin-bottom: 0.25rem;
    }
    
    .dropdown-header-fosip .user-info-email {
      font-size: 0.75rem;
      color: #6c757d;
      margin-bottom: 0.25rem;
    }
    
    .dropdown-header-fosip .user-info-fonction {
      font-size: 0.7rem;
      color: #6c757d;
      opacity: 0.8;
    }
    
    .dropdown-item-fosip {
      padding: 0.75rem 1rem;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      transition: all 0.2s ease;
      color: #495057;
      text-decoration: none;
    }
    
    .dropdown-item-fosip:hover {
      background: rgba(61,116,185,0.1);
      color: #3D74B9;
      transform: translateX(4px);
    }
    
    .dropdown-item-fosip i {
      font-size: 1.1rem;
      width: 24px;
      text-align: center;
    }
    
    .dropdown-divider-fosip {
      margin: 0.5rem 0;
      border-top: 1px solid rgba(0,0,0,0.08);
    }
    
    .btn-menu-toggle {
      background: rgba(255,255,255,0.15);
      border: 2px solid rgba(255,255,255,0.3);
      color: white;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-menu-toggle:hover {
      background: rgba(255,255,255,0.25);
      border-color: rgba(255,255,255,0.5);
      transform: translateY(-2px);
    }
    
    /* Loader amélioré et moderne */
    #page-loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #e8f2f9 0%, #ffffff 40%, #fef5ed 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    
    #page-loader.fade-out {
      opacity: 0;
      visibility: hidden;
    }
    
    .loader-container {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2rem;
      animation: loaderFloat 3s ease-in-out infinite;
    }
    
    @keyframes loaderFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    
    /* Double cercle tournant avec effet de profondeur */
    .spinner-ring {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      border: 5px solid transparent;
      border-top-color: #3D74B9;
      border-right-color: rgba(61, 116, 185, 0.3);
      animation: spinRotate 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
      position: relative;
      box-shadow: 0 0 30px rgba(61, 116, 185, 0.3);
    }
    
    .spinner-ring::before {
      content: '';
      position: absolute;
      top: -5px;
      left: -5px;
      right: -5px;
      bottom: -5px;
      border-radius: 50%;
      border: 5px solid transparent;
      border-bottom-color: #F5C7A5;
      border-left-color: rgba(245, 199, 165, 0.4);
      animation: spinRotate 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite reverse;
    }
    
    .spinner-ring::after {
      content: '';
      position: absolute;
      top: 15px;
      left: 15px;
      right: 15px;
      bottom: 15px;
      border-radius: 50%;
      border: 3px solid transparent;
      border-top-color: rgba(61, 116, 185, 0.5);
      animation: spinRotate 2s linear infinite;
    }
    
    /* Animation du cercle tournant */
    @keyframes spinRotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Logo centré avec effet de battement */
    .logo-center {
      position: absolute;
      width: 75px;
      height: 75px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border-radius: 50%;
      object-fit: cover;
      background: white;
      padding: 10px;
      box-shadow: 
        0 0 20px rgba(61, 116, 185, 0.4),
        0 0 40px rgba(245, 199, 165, 0.3),
        0 10px 30px rgba(0, 0, 0, 0.1);
      animation: logoGlow 2s ease-in-out infinite;
    }
    
    @keyframes logoGlow {
      0%, 100% { 
        transform: translate(-50%, -50%) scale(1);
        box-shadow: 
          0 0 20px rgba(61, 116, 185, 0.4),
          0 0 40px rgba(245, 199, 165, 0.3),
          0 10px 30px rgba(0, 0, 0, 0.1);
      }
      50% { 
        transform: translate(-50%, -50%) scale(1.08);
        box-shadow: 
          0 0 30px rgba(61, 116, 185, 0.6),
          0 0 60px rgba(245, 199, 165, 0.4),
          0 15px 40px rgba(0, 0, 0, 0.15);
      }
    }
    
    /* Texte de chargement avec animation */
    .loading-text {
      font-size: 1.25rem;
      font-weight: 700;
      color: #3D74B9;
      letter-spacing: 2px;
      position: relative;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .loading-text::after {
      content: '';
      position: absolute;
      right: 0;
      bottom: 0;
      width: 30px;
      height: 3px;
      background: #3D74B9;
      border-radius: 3px;
      animation: loadingBar 1.5s ease-in-out infinite;
    }
    
    @keyframes loadingBar {
      0%, 100% { 
        width: 0;
        opacity: 0;
      }
      50% { 
        width: 30px;
        opacity: 1;
      }
    }
    
    /* Points animés */
    .loading-dots {
      display: inline-flex;
      gap: 8px;
    }
    
    .loading-dots span {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      animation: dotJump 1.4s ease-in-out infinite;
    }
    
    .loading-dots span:nth-child(1) { animation-delay: 0s; }
    .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
    .loading-dots span:nth-child(3) { animation-delay: 0.4s; }
    
    @keyframes dotJump {
      0%, 60%, 100% { 
        transform: translateY(0) scale(1);
        opacity: 0.7;
      }
      30% { 
        transform: translateY(-15px) scale(1.2);
        opacity: 1;
      }
    }
    
    /* Barre de progression élégante */
    .progress-bar-loader {
      width: 240px;
      height: 6px;
      background: rgba(61, 116, 185, 0.1);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .progress-bar-loader::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(
        90deg,
        transparent,
        rgba(61, 116, 185, 0.5) 10%,
        #3D74B9 40%,
        #F5C7A5 60%,
        rgba(245, 199, 165, 0.5) 90%,
        transparent
      );
      animation: progressSlide 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      box-shadow: 0 0 15px rgba(61, 116, 185, 0.5);
    }
    
    @keyframes progressSlide {
      0% { left: -100%; }
      100% { left: 200%; }
    }
    
    /* Texte de sous-titre */
    .loading-subtitle {
      font-size: 0.9rem;
      color: #8a6e5a; /* Couleur entre bleu et jaune */
      font-weight: 500;
      margin-top: -0.5rem;
      animation: subtitleFade 2s ease-in-out infinite;
    }
    
    @keyframes subtitleFade {
      0%, 100% { opacity: 0.5; color: #3D74B9; }
      50% { opacity: 1; color: #d4a574; }
    }
    
    /* Particules décoratives FOSIP */
    .loader-container::before,
    .loader-container::after {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      animation: particleFloat 4s ease-in-out infinite;
    }
    
    .loader-container::before {
      top: -100px;
      left: -100px;
      background: radial-gradient(circle, rgba(61, 116, 185, 0.08) 0%, transparent 70%); /* Bleu */
      animation-delay: 0s;
    }
    
    .loader-container::after {
      bottom: -100px;
      right: -100px;
      background: radial-gradient(circle, rgba(245, 199, 165, 0.08) 0%, transparent 70%); /* Jaune */
      animation-delay: 2s;
    }
    
    @keyframes particleFloat {
      0%, 100% { 
        transform: scale(1) translateY(0);
        opacity: 0.4;
      }
      50% { 
        transform: scale(1.2) translateY(-20px);
        opacity: 0.7;
      }
    }
    
    /* Header moderne et stylé */
    .navbar-fosip {
      background: linear-gradient(135deg, #3D74B9 0%, #2a5a94 100%);
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      padding: 0.75rem 1rem;
    }
    
    .navbar-brand-fosip {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 700;
      font-size: 1.1rem;
      color: white !important;
      text-decoration: none;
      transition: transform 0.2s ease;
    }
    
    .navbar-brand-fosip:hover {
      transform: translateY(-2px);
      text-decoration: none;
    }
    
    /* Branding modernisé et encore plus léger */
    .logo-fosip {
      height: 28px !important;
      width: auto;
      box-shadow: 0 0 8px #3D74B955;
      transition: box-shadow 0.2s;
    }
    
    .logo-fosip:hover {
      box-shadow: 0 0 16px #3D74B9aa;
    }
    
    /* --- Remplacement : titre en blanc, lisible et moderne --- */
.brand-text-container { min-width: 0; max-width: 320px; }

.brand-main-title {
  display: inline-block;
  font-family: 'Montserrat', 'Segoe UI', Arial, sans-serif;
  font-weight: 700;
  font-size: 1.06rem;
  line-height: 1;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 260px;

  /* Texte blanc propre (remplace le gradient) */
  color: #ffffff;
  -webkit-text-fill-color: #ffffff;
  background: none;
  -webkit-background-clip: initial;
  background-clip: initial;

  /* Profondeur subtile pour lisibilité */
  text-shadow: 0 2px 10px rgba(0,0,0,0.18);
  -webkit-font-smoothing: antialiased;
  position: relative;
}

/* Barre d'accent discrète, blanche translucide, qui s'élargit au survol */
.brand-main-title::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: -6px;
  width: 32px;
  height: 3px;
  border-radius: 3px;
  background: rgba(255,255,255,0.28);
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  transition: width .22s ease;
}
.navbar-brand-fosip:hover .brand-main-title::after { width: 64px; }

/* Sous-titre léger en blanc semi-translucide */
.brand-subtitle {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.88);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
  max-width: 300px;
}

/* Ajustements responsive */
@media (max-width: 576px) {
  .brand-main-title { max-width: 140px; font-size: 0.95rem; }
  .brand-subtitle { display: none; }
}
    /* --- fin remplacement --- */
    
    /* Profil utilisateur dans le header */
    .user-profile-dropdown {
      position: relative;
    }
    
    .profile-trigger {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.5rem 1rem;
      background: rgba(255,255,255,0.1);
      border: 2px solid rgba(255,255,255,0.2);
      border-radius: 50px;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .profile-trigger:hover {
      background: rgba(255,255,255,0.2);
      border-color: rgba(255,255,255,0.4);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    
    .profile-avatar-header {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .profile-info-header {
      display: flex;
      flex-direction: column;
      gap: 0;
      text-align: left;
    }
    
    .profile-name-header {
      font-size: 0.9rem;
      font-weight: 600;
      color: white;
      line-height: 1.2;
      margin: 0;
    }
    
    .profile-role-header {
      font-size: 0.7rem;
      color: rgba(255,255,255,0.8);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 500;
      line-height: 1;
    }
    
    .profile-chevron {
      color: white;
      transition: transform 0.3s ease;
      font-size: 0.9rem;
    }
    
    .profile-trigger:hover .profile-chevron {
      transform: rotate(180deg);
    }
    
    
    /* Conteneur du texte de branding */
    .brand-text-container {
      display: flex;
      flex-direction: column;
      gap: 0.15rem;
    }
    
    .brand-main-title {
      font-size: 1.3rem;
      font-weight: 800;
      color: white;
      line-height: 1.2;
      letter-spacing: 0.3px;
      text-shadow: 0 2px 8px rgba(0,0,0,0.3);
      background: linear-gradient(135deg, #ffffff 0%, rgba(245,199,165,0.9) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      position: relative;
    }
    
    .brand-subtitle {
      font-size: 0.7rem;
      font-weight: 600;
      color: rgba(245,199,165,0.95);
      text-transform: uppercase;
      letter-spacing: 1.5px;
      text-shadow: 0 1px 3px rgba(0,0,0,0.3);
      line-height: 1;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .brand-subtitle::before {
      content: '';
      width: 20px;
      height: 2px;
      background: linear-gradient(90deg, transparent, rgba(245,199,165,0.8));
      border-radius: 2px;
    }
    
    /* Animation subtile du titre */
    @keyframes titleShine {
      0%, 100% { filter: brightness(1); }
      50% { filter: brightness(1.15); }
    }
    
    .navbar-brand-fosip:hover .brand-main-title {
      animation: titleShine 2s ease-in-out infinite;
    }
    
    /* Dropdown menu stylé */
    .dropdown-menu-fosip {
      min-width: 280px;
      padding: 0.5rem;
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      border-radius: 12px;
      margin-top: 0.75rem;
    }
    
    .dropdown-header-fosip {
      padding: 0.85rem 1rem;
      background: linear-gradient(135deg, rgba(61,116,185,0.15) 0%, rgba(42,90,148,0.15) 100%);
      border-radius: 8px;
      margin-bottom: 0.5rem;
      border-left: 3px solid #3D74B9;
    }
    
    .dropdown-header-fosip .user-info-name {
      font-weight: 600;
      color: #3D74B9;
      font-size: 0.95rem;
      margin-bottom: 0.25rem;
    }
    
    .dropdown-header-fosip .user-info-email {
      font-size: 0.75rem;
      color: #6c757d;
      margin-bottom: 0.25rem;
    }
    
    .dropdown-header-fosip .user-info-fonction {
      font-size: 0.7rem;
      color: #6c757d;
      opacity: 0.8;
    }
    
    .dropdown-item-fosip {
      padding: 0.75rem 1rem;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      transition: all 0.2s ease;
      color: #495057;
      text-decoration: none;
    }
    
    .dropdown-item-fosip:hover {
      background: rgba(61,116,185,0.1);
      color: #3D74B9;
      transform: translateX(4px);
    }
    
    .dropdown-item-fosip i {
      font-size: 1.1rem;
      width: 24px;
      text-align: center;
    }
    
    .dropdown-divider-fosip {
      margin: 0.5rem 0;
      border-top: 1px solid rgba(0,0,0,0.08);
    }
    
    .btn-menu-toggle {
      background: rgba(255,255,255,0.15);
      border: 2px solid rgba(255,255,255,0.3);
      color: white;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-menu-toggle:hover {
      background: rgba(255,255,255,0.25);
      border-color: rgba(255,255,255,0.5);
      transform: translateY(-2px);
    }
    
    /* Loader amélioré et moderne */
    #page-loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #e8f2f9 0%, #ffffff 40%, #fef5ed 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    
    #page-loader.fade-out {
      opacity: 0;
      visibility: hidden;
    }
    
    .loader-container {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2rem;
      animation: loaderFloat 3s ease-in-out infinite;
    }
    
    @keyframes loaderFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    
    /* Double cercle tournant avec effet de profondeur */
    .spinner-ring {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      border: 5px solid transparent;
      border-top-color: #3D74B9;
      border-right-color: rgba(61, 116, 185, 0.3);
      animation: spinRotate 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
      position: relative;
      box-shadow: 0 0 30px rgba(61, 116, 185, 0.3);
    }
    
    .spinner-ring::before {
      content: '';
      position: absolute;
      top: -5px;
      left: -5px;
      right: -5px;
      bottom: -5px;
      border-radius: 50%;
      border: 5px solid transparent;
      border-bottom-color: #F5C7A5;
      border-left-color: rgba(245, 199, 165, 0.4);
      animation: spinRotate 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite reverse;
    }
    
    .spinner-ring::after {
      content: '';
      position: absolute;
      top: 15px;
      left: 15px;
      right: 15px;
      bottom: 15px;
      border-radius: 50%;
      border: 3px solid transparent;
      border-top-color: rgba(61, 116, 185, 0.5);
      animation: spinRotate 2s linear infinite;
    }
    
    /* Animation du cercle tournant */
    @keyframes spinRotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Logo centré avec effet de battement */
    .logo-center {
      position: absolute;
      width: 75px;
      height: 75px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border-radius: 50%;
      object-fit: cover;
      background: white;
      padding: 10px;
      box-shadow: 
        0 0 20px rgba(61, 116, 185, 0.4),
        0 0 40px rgba(245, 199, 165, 0.3),
        0 10px 30px rgba(0, 0, 0, 0.1);
      animation: logoGlow 2s ease-in-out infinite;
    }
    
    @keyframes logoGlow {
      0%, 100% { 
        transform: translate(-50%, -50%) scale(1);
        box-shadow: 
          0 0 20px rgba(61, 116, 185, 0.4),
          0 0 40px rgba(245, 199, 165, 0.3),
          0 10px 30px rgba(0, 0, 0, 0.1);
      }
      50% { 
        transform: translate(-50%, -50%) scale(1.08);
        box-shadow: 
          0 0 30px rgba(61, 116, 185, 0.6),
          0 0 60px rgba(245, 199, 165, 0.4),
          0 15px 40px rgba(0, 0, 0, 0.15);
      }
    }
    
    /* Texte de chargement avec animation */
    .loading-text {
      font-size: 1.25rem;
      font-weight: 700;
      color: #3D74B9;
      letter-spacing: 2px;
      position: relative;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .loading-text::after {
      content: '';
      position: absolute;
      right: 0;
      bottom: 0;
      width: 30px;
      height: 3px;
      background: #3D74B9;
      border-radius: 3px;
      animation: loadingBar 1.5s ease-in-out infinite;
    }
    
    @keyframes loadingBar {
      0%, 100% { 
        width: 0;
        opacity: 0;
      }
      50% { 
        width: 30px;
        opacity: 1;
      }
    }
    
    /* Points animés */
    .loading-dots {
      display: inline-flex;
      gap: 8px;
    }
    
    .loading-dots span {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      animation: dotJump 1.4s ease-in-out infinite;
    }
    
    .loading-dots span:nth-child(1) { animation-delay: 0s; }
    .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
    .loading-dots span:nth-child(3) { animation-delay: 0.4s; }
    
    @keyframes dotJump {
      0%, 60%, 100% { 
        transform: translateY(0) scale(1);
        opacity: 0.7;
      }
      30% { 
        transform: translateY(-15px) scale(1.2);
        opacity: 1;
      }
    }
    
    /* Barre de progression élégante */
    .progress-bar-loader {
      width: 240px;
      height: 6px;
      background: rgba(61, 116, 185, 0.1);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .progress-bar-loader::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(
        90deg,
        transparent,
        rgba(61, 116, 185, 0.5) 10%,
        #3D74B9 40%,
        #F5C7A5 60%,
        rgba(245, 199, 165, 0.5) 90%,
        transparent
      );
      animation: progressSlide 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      box-shadow: 0 0 15px rgba(61, 116, 185, 0.5);
    }
    
    @keyframes progressSlide {
      0% { left: -100%; }
      100% { left: 200%; }
    }
    
    /* Texte de sous-titre */
    .loading-subtitle {
      font-size: 0.9rem;
      color: #8a6e5a; /* Couleur entre bleu et jaune */
      font-weight: 500;
      margin-top: -0.5rem;
      animation: subtitleFade 2s ease-in-out infinite;
    }
    
    @keyframes subtitleFade {
      0%, 100% { opacity: 0.5; color: #3D74B9; }
      50% { opacity: 1; color: #d4a574; }
    }
    
    /* Particules décoratives FOSIP */
    .loader-container::before,
    .loader-container::after {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      animation: particleFloat 4s ease-in-out infinite;
    }
    
    .loader-container::before {
      top: -100px;
      left: -100px;
      background: radial-gradient(circle, rgba(61, 116, 185, 0.08) 0%, transparent 70%); /* Bleu */
      animation-delay: 0s;
    }
    
    .loader-container::after {
      bottom: -100px;
      right: -100px;
      background: radial-gradient(circle, rgba(245, 199, 165, 0.08) 0%, transparent 70%); /* Jaune */
      animation-delay: 2s;
    }
    
    @keyframes particleFloat {
      0%, 100% { 
        transform: scale(1) translateY(0);
        opacity: 0.4;
      }
      50% { 
        transform: scale(1.2) translateY(-20px);
        opacity: 0.7;
      }
    }
    
    /* Responsive */
    @media (max-width: 576px) {
      .spinner-ring {
        width: 110px;
        height: 110px;
      }
      
      .logo-center {
        width: 60px;
        height: 60px;
        padding: 8px;
      }
      
      .loading-text {
        font-size: 1rem;
        letter-spacing: 1px;
      }
      
      .loading-dots span {
        width: 8px;
        height: 8px;
      }
      
      .progress-bar-loader {
        width: 180px;
        height: 5px;
      }
      
      .loading-subtitle {
        font-size: 0.8rem;
      }
    }
    
    /* --- Override mobile: titres très petits pour petits écrans --- */
@media (max-width: 576px) {
  .brand-main-title {
    font-size: 0.72rem !important; /* très petit */
    max-width: 120px !important;
    line-height: 1 !important;
  }
  .brand-subtitle {
    font-size: 0.52rem !important; /* encore plus petit */
    max-width: 140px !important;
    display: block !important;
    opacity: 0.9 !important;
  }
  .logo-fosip { height: 18px !important; } /* optionnel : réduit le logo pour compenser */
}
    /* --- fin override --- */
  </style>
</head>
<body class="d-flex flex-column min-vh-100">

  <!-- Loader animé moderne et professionnel -->
  <div id="page-loader">
    <div class="loader-container">
      <div class="spinner-ring">
        <img src="../assets/img/logocircular.png" alt="Logo FOSIP" class="logo-center">
      </div>
      
      <div class="loading-text">
        Chargement
        <span class="loading-dots">
          <span></span>
          <span></span>
          <span></span>
        </span>
      </div>
      
      <div class="progress-bar-loader"></div>
      
      <div class="loading-subtitle">
        Préparation de votre espace de travail...
      </div>
    </div>
  </div>

  <script>
  (function(){
    // Masque le loader puis le supprime après transition
    function hideLoader() {
      try {
        var loader = document.getElementById('page-loader');
        if (!loader) return;
        if (!loader.classList.contains('fade-out')) {
          loader.classList.add('fade-out');
          // nettoyage DOM après la transition
          setTimeout(function(){
            if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
          }, 700);
        }
      } catch (e) { /* silent */ }
    }

    // Si le document est déjà complètement chargé
    if (document.readyState === 'complete') {
      hideLoader();
    } else {
      // Exécute lorsque toutes les ressources sont chargées
      window.addEventListener('load', hideLoader, {passive:true, once:true});
      // Exécute quand le DOM est prêt (plus rapide)
      document.addEventListener('DOMContentLoaded', function(){ setTimeout(hideLoader, 50); }, {once:true});
    }

    // Fallback : en cas de blocage, forcer la disparition au bout de 6s
    setTimeout(hideLoader, 6000);
  })();
  </script>

  <!-- Barre de navigation moderne -->
  <nav class="navbar navbar-expand-lg navbar-fosip sticky-top">
    <div class="container-fluid">
      <?php
      // session_start() supprimé, la session doit être démarrée dans le script principal
      if (!empty($_SESSION['user_id'])):
      ?>
        <!-- Bouton menu mobile (hamburger) visible seulement si connecté -->
        <button class="btn btn-menu-toggle d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
          <i class="bi bi-list fs-4"></i>
        </button>
      <?php endif; ?>
      <!-- Logo et nom -->
      <!-- --- Remplacement HTML branding (remplace l'ancien <a class="navbar-brand-fosip">...)</* --- -->
<a class="navbar-brand-fosip d-flex align-items-center gap-2" href="../index.php" aria-label="FOSIP — Staff Performance Suite" title="FOSIP — Staff Performance Suite" style="text-decoration:none; min-width:0;">
  <img src="../assets/img/logowhite.png" alt="Logo FOSIP" class="logo-fosip" />
  <div class="brand-text-container">
    <span class="brand-main-title" title="Staff Performance Suite">Staff Performance Suite</span>
    <span class="brand-subtitle" title="Système d'évaluation du personnel">Système d'évaluation du personnel</span>
  </div>
</a>
      <!-- Profil utilisateur à droite -->
      <div class="ms-auto">
        <?php
        // session_start() supprimé, la session doit être démarrée dans le script principal
        
        if (!empty($_SESSION['user_id'])):
          // Récupération des infos utilisateur
          require_once(__DIR__ . '/../includes/db.php');
          
          $user_id = (int)$_SESSION['user_id'];
          $stmt = $pdo->prepare("SELECT nom, post_nom, email, fonction, role, photo FROM users WHERE id = ?");
          $stmt->execute([$user_id]);
          $userData = $stmt->fetch(PDO::FETCH_ASSOC);
          
          $nom = trim(($userData['nom'] ?? '') . ' ' . ($userData['post_nom'] ?? ''));
          $email = $userData['email'] ?? '';
          $fonction = $userData['fonction'] ?? 'Non définie';
          $role = $userData['role'] ?? 'invité';
          $photo = $userData['photo'] ?? 'default.png';
          
          // Formattage du nom
          if ($nom !== '') {
            $lower = mb_strtolower($nom, 'UTF-8');
            $display_name = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, null, 'UTF-8');
          } else {
            $display_name = 'Utilisateur';
          }
          
          $profile_base = '../assets/img/profiles/';
          $photo_path = $profile_base . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
        ?>
        
        <div class="dropdown user-profile-dropdown">
          <div class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?= htmlspecialchars($photo_path) ?>" 
                 alt="Photo de profil" 
                 class="profile-avatar-header"
                 onerror="this.onerror=null;this.src='<?= $profile_base ?>default.png';">
            
            <div class="profile-info-header d-none d-sm-flex">
              <span class="profile-name-header"><?= htmlspecialchars($display_name) ?></span>
             
            </div>
            
            <i class="bi bi-chevron-down profile-chevron"></i>
          </div>
          
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-fosip">
            <li class="dropdown-header-fosip">
              <div class="d-flex align-items-center gap-2">
                <img src="<?= htmlspecialchars($photo_path) ?>" 
                     alt="Photo" 
                     width="48" 
                     height="48" 
                     class="rounded-circle"
                     onerror="this.onerror=null;this.src='<?= $profile_base ?>default.png';">
                <div class="flex-grow-1">
                  <div class="user-info-name"><?= htmlspecialchars($display_name) ?></div>
                  <div class="user-info-email"><?= htmlspecialchars($email) ?></div>
                  <div class="user-info-fonction">
                    <i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($fonction) ?>
                  </div>
                </div>
              </div>
            </li>
            
            <li><div class="dropdown-divider-fosip"></div></li>
            
            <li>
              <a class="dropdown-item-fosip" href="profile.php">
                <i class="bi bi-person-circle"></i>
                <span>Mon profil</span>
              </a>
            </li>
            
            <?php if (in_array($role, ['staff', 'agent', 'superviseur'])): ?>
            <li>
              <a class="dropdown-item-fosip" href="competence-profile.php">
                <i class="bi bi-list-check"></i>
                <span>Mes compétences</span>
              </a>
            </li>
            <?php endif; ?>
            
            <li>
              <a class="dropdown-item-fosip" href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Tableau de bord</span>
              </a>
            </li>
            
            <li>
              <a class="dropdown-item-fosip" href="aide.php">
                <i class="bi bi-question-circle"></i>
                <span>Aide & Support</span>
              </a>
            </li>
            
            <li><div class="dropdown-divider-fosip"></div></li>
            
            <li>
              <a class="dropdown-item-fosip text-danger" href="logout.php" id="logout-link">
                <i class="bi bi-box-arrow-right"></i>
                <span>Déconnexion</span>
              </a>
            </li>
          </ul>
        </div>
        
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- Contenu principal -->
  <div class="container-fluid mt-4 flex-grow-1">
