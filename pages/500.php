<?php
// Page d'erreur 500 personnalisée
http_response_code(500);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Erreur 500 - Problème serveur</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { background: #f8d7da; color: #721c24; font-family: sans-serif; text-align: center; padding: 5em 1em; }
        .error-box { background: #fff; border: 1px solid #f5c6cb; border-radius: 8px; display: inline-block; padding: 2em 3em; }
        h1 { font-size: 3em; margin-bottom: 0.5em; }
        p { font-size: 1.2em; }
        a { color: #721c24; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>Erreur 500</h1>
        <p>Le serveur a rencontré un problème.<br>
        Veuillez réessayer plus tard ou contacter l'administrateur.</p>
        <p><a href="/">Retour à l'accueil</a></p>
    </div>
</body>
</html>
