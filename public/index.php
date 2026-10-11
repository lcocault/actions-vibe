<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Api\TaskApi;
use App\Database;
use App\Task\DependencyRepository;
use App\Task\TaskRepository;

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/api' || str_starts_with($path, '/api/')) {
    try {
        $pdo = Database::connection();
        $api = new TaskApi(new TaskRepository($pdo), new DependencyRepository($pdo));
        $response = $api->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, (string) file_get_contents('php://input'));
    } catch (Throwable $e) {
        error_log((string) $e);
        $response = ['status' => 500, 'headers' => [], 'body' => ['error' => ['code' => 'server_error', 'message' => 'Erreur interne.']]];
    }
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) {
        header("$name: $value");
    }
    if ($response['body'] !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    exit;
}

// Le portail web (CRUD) et /mcp seront ajoutés aux étapes suivantes.
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
    <p>API REST disponible sur <code>/api/tasks</code>.</p>
</body>
</html>
