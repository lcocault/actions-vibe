<?php use App\Web\View; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::h($title) ?> — Gestionnaire d'actions</title>
    <link rel="stylesheet" href="/assets/portal.css">
</head>
<body>
<header class="top">
    <a class="brand" href="/">Gestionnaire d'actions</a>
    <nav>
        <a href="/">Actions</a>
        <a href="/?status=en_cours">En cours</a>
        <a href="/projects">Projets</a>
        <a class="btn" href="/tasks/new">+ Nouvelle tâche</a>
    </nav>
</header>
<main>
    <?php foreach ($flashes as $f): ?>
        <p class="flash <?= $f['type'] === 'error' ? 'flash-error' : 'flash-ok' ?>" role="status"><?= $f['html'] ? $f['message'] : View::h($f['message']) ?></p>
    <?php endforeach; ?>
    <?= $content ?>
</main>
</body>
</html>
