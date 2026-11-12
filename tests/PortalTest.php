<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use App\Api\ProjectApi;
use App\Api\TaskApi;
use App\Task\DependencyRepository;
use App\Task\ProjectRepository;
use App\Task\TaskRepository;
use App\Web\Portal;
use App\Web\Session;

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
}

$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE task (
    id INTEGER PRIMARY KEY, title TEXT, category TEXT, importance TEXT, due_date TEXT, recurrence TEXT,
    duration_minutes INTEGER, description TEXT, project TEXT, conclusions TEXT, status TEXT, completed_at TEXT
)');
$pdo->exec('CREATE TABLE task_dependency (task_id INTEGER, required_task_id INTEGER)');
$insert = $pdo->prepare(
    'INSERT INTO task (id, title, category, importance, status, completed_at) VALUES (?, ?, ?, ?, ?, ?)',
);
$insert->execute([1, 'À faire', 'Pro', 'Mineure', 'a_faire', null]);
$insert->execute([2, 'En cours', 'Pro', 'Mineure', 'en_cours', null]);
$insert->execute([3, 'Terminée', 'Pro', 'Mineure', 'terminee', '2026-10-30']);
$globalTask = $pdo->prepare(
    'INSERT INTO task (id, title, category, importance, project, status, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
);
$globalTask->execute([4, 'Action Alpha', 'Pro', 'Critique', 'Alpha', 'a_faire', null]);
$globalTask->execute([5, 'Action Beta', 'Perso', 'Critique', 'Beta', 'en_cours', null]);
$globalTask->execute([6, 'Action terminée', 'Pro', 'Critique', 'Gamma', 'terminee', '2026-10-30']);

$projects = new ProjectRepository($pdo);
$api = new TaskApi(new TaskRepository($pdo), new DependencyRepository($pdo), $projects);
$portal = new Portal($api, new ProjectApi($projects), new Session());

$default = $portal->handle('GET', '/')['body'];
check('liste par défaut exclut les tâches terminées', str_contains($default, '<a href="/tasks/1">À faire</a>') && str_contains($default, '<a href="/tasks/2">En cours</a>') && !str_contains($default, '<a href="/tasks/3">Terminée</a>'));
check('le menu propose les tâches en cours', str_contains($default, 'href="/?status=en_cours">En cours</a>'));

$inProgress = $portal->handle('GET', '/', ['status' => 'en_cours'])['body'];
check('la liste en cours ne montre que les tâches en cours', str_contains($inProgress, '<a href="/tasks/2">En cours</a>') && !str_contains($inProgress, '<a href="/tasks/1">À faire</a>') && !str_contains($inProgress, '<a href="/tasks/3">Terminée</a>'));

$all = $portal->handle('GET', '/', ['status' => 'all'])['body'];
check('le filtre toutes affiche aussi les tâches terminées', str_contains($all, '<a href="/tasks/1">À faire</a>') && str_contains($all, '<a href="/tasks/2">En cours</a>') && str_contains($all, '<a href="/tasks/3">Terminée</a>'));

$matrix = $portal->handle('GET', '/matrix')['body'];
check('la matrice globale est accessible depuis le menu', str_contains($matrix, 'href="/matrix">Matrice globale</a>') && str_contains($matrix, "Matrice d'Eisenhower globale"));
$selection = $portal->handle('GET', '/matrix', ['importance' => 'Critique', 'urgency' => 'later'])['body'];
check('la sélection globale agrège les projets et exclut les tâches terminées', str_contains($selection, 'Action Alpha') && str_contains($selection, 'Action Beta') && !str_contains($selection, 'Action terminée'));

exit($failures === 0 ? 0 : 1);
