<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chargement - Staff Performance Suite</title>
  <link rel="icon" type="image/png" href="assets/img/logocircular.png">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #3D74B9 0%, #5a8fd4 50%, #F5C7A5 100%); /* Gradient bleu FOSIP vers jaune FOSIP */
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }
    
    /* Particules d'arrière-plan animées */
    .bg-animation {
      position: absolute;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    
    .bg-animation span {
      position: absolute;
      display: block;
      width: 20px;
      height: 20px;
      background: rgba(255, 255, 255, 0.1);
      animation: floatParticles 15s infinite;
      bottom: -150px;
    }
    
    .bg-animation span:nth-child(1) { left: 10%; animation-delay: 0s; width: 80px; height: 80px; }
    .bg-animation span:nth-child(2) { left: 20%; animation-delay: 2s; width: 20px; height: 20px; }
    .bg-animation span:nth-child(3) { left: 35%; animation-delay: 4s; width: 60px; height: 60px; }
    .bg-animation span:nth-child(4) { left: 50%; animation-delay: 0s; width: 30px; height: 30px; }
    .bg-animation span:nth-child(5) { left: 65%; animation-delay: 3s; width: 70px; height: 70px; }
    .bg-animation span:nth-child(6) { left: 80%; animation-delay: 5s; width: 40px; height: 40px; }
    .bg-animation span:nth-child(7) { left: 90%; animation-delay: 1s; width: 50px; height: 50px; }
    
    @keyframes floatParticles {
      0% {
        transform: translateY(0) rotate(0deg);
        opacity: 1;
        border-radius: 0;
      }
      100% {
        transform: translateY(-1000px) rotate(720deg);
        opacity: 0;
        border-radius: 50%;
      }
    }
    
    /* Container principal du loader */
    .loader-wrapper {
      position: relative;
      z-index: 10;
      text-align: center;
      animation: fadeInScale 0.8s ease-out;
    }
    
    @keyframes fadeInScale {
      0% {
        opacity: 0;
        transform: scale(0.9);
      }
      100% {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    /* Triple cercle concentrique animé */
    .spinner-container {
      position: relative;
      width: 180px;
      height: 180px;
      margin: 0 auto 2rem;
    }
    
    .spinner-ring {
      position: absolute;
      border-radius: 50%;
      border: 5px solid transparent;
    }
    
    .spinner-ring-1 {
      width: 180px;
      height: 180px;
      border-top-color: #3D74B9;
      border-right-color: rgba(61, 116, 185, 0.3);
      animation: spinClockwise 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
    }
    
    .spinner-ring-2 {
      width: 140px;
      height: 140px;
      top: 20px;
      left: 20px;
      border-bottom-color: #F5C7A5;
      border-left-color: rgba(245, 199, 165, 0.3);
      animation: spinCounterClockwise 2s ease-in-out infinite;
    }
    
    .spinner-ring-3 {
      width: 100px;
      height: 100px;
      top: 40px;
      left: 40px;
      border-top-color: rgba(255, 255, 255, 0.6);
      border-right-color: rgba(255, 255, 255, 0.2);
      animation: spinClockwise 2.5s linear infinite;
    }
    
    @keyframes spinClockwise {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    @keyframes spinCounterClockwise {
      0% { transform: rotate(360deg); }
      100% { transform: rotate(0deg); }
    }
    
    /* Logo central avec effet de glow pulsant */
    .logo-center {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: white;
      padding: 12px;
      box-shadow: 
        0 0 30px rgba(255, 255, 255, 0.5),
        0 0 60px rgba(61, 116, 185, 0.4),
        0 15px 40px rgba(0, 0, 0, 0.2);
      animation: logoGlow 2s ease-in-out infinite, logoRotate 20s linear infinite;
    }
    
    .logo-center img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    
    @keyframes logoGlow {
      0%, 100% {
        box-shadow: 
          0 0 30px rgba(255, 255, 255, 0.5),
          0 0 60px rgba(61, 116, 185, 0.4),
          0 15px 40px rgba(0, 0, 0, 0.2);
      }
      50% {
        box-shadow: 
          0 0 50px rgba(255, 255, 255, 0.8),
          0 0 100px rgba(61, 116, 185, 0.6),
          0 20px 50px rgba(0, 0, 0, 0.3);
      }
    }
    
    @keyframes logoRotate {
      0% { transform: translate(-50%, -50%) rotate(0deg); }
      100% { transform: translate(-50%, -50%) rotate(360deg); }
    }
    
    /* Texte et animations */
    .loading-text {
      color: white;
      font-size: 1.8rem;
      font-weight: 700;
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      animation: textPulse 2s ease-in-out infinite;
    }
    
    @keyframes textPulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.8; transform: scale(1.05); }
    }
    
    /* Points de chargement animés */
    .loading-dots {
      display: inline-flex;
      gap: 12px;
      margin-left: 10px;
    }
    
    .loading-dots span {
      width: 12px;
      height: 12px;
      background: white;
      border-radius: 50%;
      animation: dotBounce 1.4s ease-in-out infinite;
      box-shadow: 0 4px 15px rgba(255, 255, 255, 0.5);
    }
    
    .loading-dots span:nth-child(1) { animation-delay: 0s; }
    .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
    .loading-dots span:nth-child(3) { animation-delay: 0.4s; }
    
    @keyframes dotBounce {
      0%, 80%, 100% {
        transform: translateY(0) scale(1);
        opacity: 0.7;
      }
      40% {
        transform: translateY(-20px) scale(1.3);
        opacity: 1;
      }
    }
    
    /* Barre de progression stylée */
    .progress-container {
      max-width: 400px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.2);
      height: 8px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(10px);
    }
    
    .progress-bar {
      height: 100%;
      background: linear-gradient(90deg, 
        rgba(255, 255, 255, 0.8) 0%, 
        white 50%, 
        rgba(255, 255, 255, 0.8) 100%);
      width: 0%;
      border-radius: 20px;
      box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
      animation: progressGrow 2.5s ease-out forwards;
      position: relative;
      overflow: hidden;
    }
    
    .progress-bar::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, 
        transparent, 
        rgba(255, 255, 255, 0.6), 
        transparent);
      animation: shimmer 1.5s infinite;
    }
    
    @keyframes progressGrow {
      0% { width: 0%; }
      100% { width: 100%; }
    }
    
    @keyframes shimmer {
      0% { left: -100%; }
      100% { left: 200%; }
    }
    
    /* Message de chargement */
    .loading-message {
      color: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
      margin-top: 1.5rem;
      font-weight: 500;
      animation: messageFade 3s ease-in-out infinite;
    }
    
    @keyframes messageFade {
      0%, 100% { opacity: 0.6; }
      50% { opacity: 1; }
    }
    
    /* Badge version */
    .version-badge {
      position: absolute;
      bottom: 30px;
      right: 30px;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      animation: fadeInUp 1s ease-out 0.5s both;
    }
    
    @keyframes fadeInUp {
      0% {
        opacity: 0;
        transform: translateY(20px);
      }
      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .spinner-container {
        width: 140px;
        height: 140px;
      }
      
      .spinner-ring-1 { width: 140px; height: 140px; }
      .spinner-ring-2 { width: 100px; height: 100px; top: 20px; left: 20px; }
      .spinner-ring-3 { width: 60px; height: 60px; top: 40px; left: 40px; }
      
      .logo-center {
        width: 70px;
        height: 70px;
      }
      
      .loading-text {
        font-size: 1.4rem;
        letter-spacing: 2px;
      }
      
      .progress-container {
        max-width: 300px;
      }
      
      .version-badge {
        bottom: 20px;
        right: 20px;
        font-size: 0.75rem;
        padding: 6px 12px;
      }
    }
  </style>
</head>
<body>
  <!-- Particules d'arrière-plan -->
  <div class="bg-animation">
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    <span></span>
  </div>

  <!-- Loader principal -->
  <div class="loader-wrapper">
    <div class="spinner-container">
      <div class="spinner-ring spinner-ring-1"></div>
      <div class="spinner-ring spinner-ring-2"></div>
      <div class="spinner-ring spinner-ring-3"></div>
      <div class="logo-center">
        <img src="assets/img/logocircular.png" alt="FOSIP Logo" onerror="this.onerror=null; this.src='assets/img/logo-fosip.png';">
      </div>
    </div>

    <div class="loading-text">
      Chargement
      <span class="loading-dots">
        <span></span>
        <span></span>
        <span></span>
      </span>
    </div>

    <div class="progress-container">
      <div class="progress-bar"></div>
    </div>

    <div class="loading-message">
      Préparation de votre espace de travail...
    </div>
  </div>

  <!-- Badge version -->
  <div class="version-badge">
    FOSIP v2.0
  </div>

  <script>
    // Redirection automatique après le chargement
    setTimeout(function() {
      window.location.href = 'pages/login.php';
    }, 2800);
    
    // Animation personnalisée du message
    const messages = [
      "Préparation de votre espace de travail...",
      "Chargement des données...",
      "Initialisation du système...",
      "Presque prêt..."
    ];
    
    let messageIndex = 0;
    const messageElement = document.querySelector('.loading-message');
    
    setInterval(function() {
      messageIndex = (messageIndex + 1) % messages.length;
      messageElement.style.animation = 'none';
      setTimeout(() => {
        messageElement.textContent = messages[messageIndex];
        messageElement.style.animation = 'messageFade 3s ease-in-out infinite';
      }, 10);
    }, 2000);
  </script>
</body>
</html>
