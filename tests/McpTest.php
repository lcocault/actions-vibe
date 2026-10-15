<?php

declare(strict_types=1);

// Test d'intégration de l'interface MCP : nécessite l'application et PostgreSQL en marche.
// php tests/McpTest.php [URL de base, défaut http://localhost:8080]
// Ex. : docker compose exec app php tests/McpTest.php http://localhost

$base = rtrim($argv[1] ?? 'http://localhost:8080', '/');

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
}

/** @return array{0:int,1:mixed} statut HTTP, corps JSON décodé (null si vide) */
function post(mixed $body, ?string $raw = null, string $method = 'POST'): array
{
    global $base;
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => "Content-Type: application/json\r\nAccept: application/json, text/event-stream\r\n",
        'content' => $raw ?? json_encode($body),
        'ignore_errors' => true,
    ]]);
    $text = file_get_contents($base . '/mcp', false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), $text === '' || $text === false ? null : json_decode($text, true)];
}

/** @return array{0:bool,1:mixed} isError, contenu JSON décodé de l'outil */
function tool(string $name, array $args = []): array
{
    static $n = 100;
    [, $r] = post(['jsonrpc' => '2.0', 'id' => ++$n, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => (object) $args]]);
    $res = $r['result'] ?? [];
    return [(bool) ($res['isError'] ?? true), json_decode($res['content'][0]['text'] ?? 'null', true)];
}

[$s, $r] = post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => new stdClass(), 'clientInfo' => ['name' => 't', 'version' => '1']]]);
check('initialize négocie la version et déclare les outils', $s === 200 && $r['id'] === 1 && $r['result']['protocolVersion'] === '2025-03-26' && isset($r['result']['capabilities']['tools']));
[$s, $r] = post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
check('notification -> 202 sans corps', $s === 202 && $r === null);
[$s, $r] = post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping']);
check('ping', $s === 200 && $r['id'] === 2 && array_key_exists('result', $r));
[$s, $r] = post(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list']);
$names = array_column($r['result']['tools'] ?? [], 'name');
sort($names);
check('tools/list : 10 outils avec schéma', $s === 200 && $names === ['create_project', 'create_task', 'delete_project', 'delete_task', 'get_project', 'get_task', 'list_projects', 'list_tasks', 'update_project', 'update_task'] && isset($r['result']['tools'][0]['inputSchema']['type']));
[$s, $r] = post(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'inconnue']);
check('méthode inconnue -> -32601', $r['error']['code'] === -32601);
[$s, $r] = post(null, '{pas du json');
check('JSON invalide -> -32700', $s === 400 && $r['error']['code'] === -32700);
[$s, $r] = post([['jsonrpc' => '2.0', 'id' => 5, 'method' => 'ping'], ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']]);
check('lot : une réponse pour la seule requête', $s === 200 && count($r) === 1 && $r[0]['id'] === 5);
[$s] = post(null, '', 'GET');
check('GET -> 405', $s === 405);

[$err, $a] = tool('create_task', ['title' => 'Prérequis MCP', 'category' => 'Pro']);
$a = $a['data']['id'] ?? 0;
check('create_task', !$err && $a > 0);
[$err, $b] = tool('create_task', ['title' => 'Principale MCP', 'category' => 'Perso', 'due_date' => '2026-01-31', 'recurrence' => 'mensuelle', 'requirements' => [$a]]);
$t = $b['data']['id'] ?? 0;
check('create_task avec prérequis', !$err && ($b['data']['requirements'][0]['id'] ?? 0) === $a);
[$err, $b] = tool('create_task', ['title' => '', 'category' => 'X']);
check('create_task invalide -> isError + détails', $err && isset($b['error']['details']['title'], $b['error']['details']['category']));

[$err, $b] = tool('list_tasks', ['category' => 'Perso']);
check('list_tasks filtré', !$err && in_array($t, array_column($b['data'], 'id'), true));
[$err] = tool('list_tasks', ['status' => 'bof']);
check('list_tasks filtre invalide -> isError', $err);
[$err, $b] = tool('get_task', ['id' => $t]);
check('get_task', !$err && $b['data']['title'] === 'Principale MCP');
[$err, $b] = tool('get_task', ['id' => 999999]);
check('get_task inconnu -> isError not_found', $err && $b['error']['code'] === 'not_found');
[$err] = tool('get_task');
check('get_task sans id -> isError', $err);

[$err, $b] = tool('update_task', ['id' => $t, 'description' => 'Détail']);
check('update_task partiel', !$err && $b['data']['description'] === 'Détail' && $b['data']['title'] === 'Principale MCP');
[$err, $b] = tool('update_task', ['id' => $a, 'requirements' => [$t]]);
check('update_task cycle -> isError dependency_cycle', $err && $b['error']['code'] === 'dependency_cycle');
[$err, $b] = tool('update_task', ['id' => $t, 'status' => 'terminee']);
$next = $b['next_occurrence']['id'] ?? 0;
check('update_task clôture périodique -> occurrence suivante', !$err && ($b['next_occurrence']['due_date'] ?? '') === '2026-02-28');

[$s, $r] = post(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => ['name' => 'nope', 'arguments' => new stdClass()]]);
check('outil inconnu -> erreur JSON-RPC -32602', $r['error']['code'] === -32602);

[$err, $b] = tool('delete_task', ['id' => $a]);
check('delete_task', !$err && $b['data']['deleted'] === true);
[$err] = tool('delete_task', ['id' => $a]);
check('delete_task inconnu -> isError', $err);
tool('delete_task', ['id' => $t]);
tool('delete_task', ['id' => $next]);
exit($failures > 0 ? 1 : 0);
