<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

// Point d'entrée unique : le routage (portail, /api, /mcp) sera ajouté aux étapes suivantes.
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Gestionnaire d'actions</title>
</head>
<body>
    <h1>Gestionnaire d'actions</h1>
    <p>Squelette en place.</p>
</body>
</html>
