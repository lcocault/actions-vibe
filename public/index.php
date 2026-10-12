<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Api\TaskApi;
use App\Database;
use App\Mcp\McpServer;
use App\Task\DependencyRepository;
use App\Task\TaskRepository;
use App\Web\Portal;
use App\Web\Session;

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

if ($path === '/mcp' || $path === '/mcp/') {
    try {
        $pdo = Database::connection();
        $mcp = new McpServer(new TaskApi(new TaskRepository($pdo), new DependencyRepository($pdo)));
        $response = $mcp->handle($_SERVER['REQUEST_METHOD'] ?? 'POST', (string) file_get_contents('php://input'));
    } catch (Throwable $e) {
        error_log((string) $e);
        $response = [
            'status' => 500,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => '{"jsonrpc":"2.0","id":null,"error":{"code":-32603,"message":"Erreur interne."}}',
        ];
    }
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) {
        header("$name: $value");
    }
    if ($response['body'] !== null) {
        echo $response['body'];
    }
    exit;
}

// Portail web (pages HTML, sessions et jetons CSRF).
$pdo = Database::connection();
$portal = new Portal(new TaskApi(new TaskRepository($pdo), new DependencyRepository($pdo)), new Session());
$response = $portal->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $_POST);
http_response_code($response['status']);
foreach ($response['headers'] as $name => $value) {
    header("$name: $value");
}
header('Content-Type: text/html; charset=utf-8');
echo $response['body'];
