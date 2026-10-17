<?php

declare(strict_types=1);

// Test d'intégration de l'API : nécessite l'application et PostgreSQL en marche.
// php tests/ApiTest.php [URL de base, défaut http://localhost:8080]
// Ex. : docker compose exec app php tests/ApiTest.php http://localhost

$base = rtrim($argv[1] ?? 'http://localhost:8080', '/');

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
}

/** @return array{0:int,1:?array<string,mixed>,2:array<string,string>} statut, corps JSON, en-têtes */
function call(string $method, string $path, mixed $body = null, ?string $raw = null): array
{
    global $base;
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => "Content-Type: application/json\r\n",
        'content' => $raw ?? ($body === null ? '' : json_encode($body)),
        'ignore_errors' => true,
    ]]);
    $text = file_get_contents($base . $path, false, $ctx);
    $headers = [];
    foreach ($http_response_header ?? [] as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower($k)] = trim($v);
        }
    }
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), $text === '' || $text === false ? null : json_decode($text, true), $headers];
}

[$s, $b, $h] = call('POST', '/api/tasks', ['title' => 'Prérequis', 'category' => 'Pro']);
$a = $b['data']['id'] ?? 0;
check('POST crée (201, Location)', $s === 201 && ($h['location'] ?? '') === "/api/tasks/$a" && $b['data']['status'] === 'a_faire');
check('importance par défaut : Mineure', ($b['data']['importance'] ?? '') === 'Mineure');
[$s, $b] = call('POST', '/api/tasks', ['title' => 'Urgente', 'category' => 'Pro', 'importance' => 'Critique']);
check('POST avec importance Critique', $s === 201 && $b['data']['importance'] === 'Critique');
$imp = $b['data']['id'] ?? 0;
[$s, $b] = call('PATCH', "/api/tasks/$imp", ['importance' => 'Majeure']);
check('PATCH importance', $s === 200 && $b['data']['importance'] === 'Majeure');
[$s, $b] = call('PATCH', "/api/tasks/$imp", ['importance' => 'Haute']);
check('importance invalide -> 422', $s === 422 && isset($b['error']['details']['importance']));
call('DELETE', "/api/tasks/$imp");

[$s, $b] = call('POST', '/api/tasks', ['title' => 'Principale', 'category' => 'Perso', 'due_date' => '2026-01-31', 'recurrence' => 'mensuelle', 'requirements' => [$a]]);
$t = $b['data']['id'] ?? 0;
check('POST avec prérequis', $s === 201 && ($b['data']['requirements'][0]['id'] ?? 0) === $a);

[$s, $b] = call('POST', '/api/tasks', ['title' => '', 'category' => 'X']);
check('POST invalide -> 422 par champ', $s === 422 && isset($b['error']['details']['title'], $b['error']['details']['category']));
[$s] = call('POST', '/api/tasks', null, '{pas du json');
check('JSON invalide -> 400', $s === 400);
[$s, $b] = call('POST', '/api/tasks', ['title' => 'x', 'category' => 'Pro', 'requirements' => [999999]]);
check('prérequis inexistant -> 422', $s === 422 && isset($b['error']['details']['requirements']));
[, $b] = call('GET', '/api/tasks?category=Pro');
check('POST en échec n\'a rien créé', count(array_filter($b['data'], fn($x) => $x['title'] === 'x')) === 0);

[$s, $b] = call('GET', "/api/tasks/$t");
check('GET détail avec prérequis', $s === 200 && $b['data']['title'] === 'Principale' && $b['data']['requirements'][0]['title'] === 'Prérequis');
[$s] = call('GET', '/api/tasks/999999');
check('GET inconnu -> 404', $s === 404);
[$s] = call('GET', '/api/tasks/abc');
check('GET id non numérique -> 404', $s === 404);

[$s, $b] = call('GET', '/api/tasks?category=Perso');
check('liste filtrée par catégorie', $s === 200 && array_column($b['data'], 'id') === [$t] && count($b['data'][0]['requirements']) === 1);
[$s] = call('GET', '/api/tasks?status=nimporte');
check('filtre invalide -> 422', $s === 422);

[$s, $b] = call('PATCH', "/api/tasks/$t", ['description' => 'Détail']);
check('PATCH partiel conserve le reste', $s === 200 && $b['data']['description'] === 'Détail' && $b['data']['title'] === 'Principale' && count($b['data']['requirements']) === 1);
[$s, $b] = call('PUT', "/api/tasks/$t", ['title' => 'Remplacée', 'category' => 'Pro']);
check('PUT remplace (champs omis effacés, prérequis conservés)', $s === 200 && $b['data']['due_date'] === null && $b['data']['recurrence'] === null && count($b['data']['requirements']) === 1);
[$s] = call('PUT', "/api/tasks/$t", ['title' => 'Sans catégorie']);
check('PUT incomplet -> 422', $s === 422);
[$s, $b] = call('PATCH', "/api/tasks/$t", ['requirements' => []]);
check('PATCH requirements=[] retire les prérequis', $s === 200 && $b['data']['requirements'] === []);

[$s, $b] = call('PATCH', "/api/tasks/$a", ['requirements' => [$t]]);
check('cycle évité', $s === 200);
[$s, $b] = call('PATCH', "/api/tasks/$t", ['requirements' => [$a]]);
check('cycle refusé -> 409', $s === 409 && $b['error']['code'] === 'dependency_cycle');
call('PATCH', "/api/tasks/$a", ['requirements' => []]);

[$s, $b] = call('PATCH', "/api/tasks/$t", ['status' => 'en_cours']);
check('transition en cours', $s === 200 && $b['data']['status'] === 'en_cours');
[$s, $b] = call('PATCH', "/api/tasks/$t", ['status' => 'bof']);
check('statut invalide -> 422', $s === 422);

call('PATCH', "/api/tasks/$t", ['due_date' => '2026-01-31', 'recurrence' => 'mensuelle']);
[$s, $b] = call('PATCH', "/api/tasks/$t", ['status' => 'terminee']);
check('clôture périodique crée l\'occurrence suivante',
    $s === 200 && $b['data']['completed_at'] !== null
    && ($b['next_occurrence']['due_date'] ?? '') === '2026-02-28' && $b['next_occurrence']['status'] === 'a_faire');
$next = $b['next_occurrence']['id'] ?? 0;
[$s, $b] = call('PATCH', "/api/tasks/$t", ['status' => 'en_cours']);
check('transition interdite -> 409', $s === 409 && $b['error']['code'] === 'invalid_transition');

[$s, $b, $h] = call('PUT', '/api/tasks');
check('méthode non permise -> 405 + Allow', $s === 405 && str_contains($h['allow'] ?? '', 'POST'));

[$s, $b] = call('DELETE', "/api/tasks/$a");
check('DELETE -> 204', $s === 204 && $b === null);
[$s] = call('GET', "/api/tasks/$a");
check('tâche supprimée -> 404', $s === 404);
[$s] = call('DELETE', "/api/tasks/$a");
check('DELETE inconnu -> 404', $s === 404);

// Projets : catégorie portée par le projet
$pname = 'Projet test ' . bin2hex(random_bytes(3));
[$s, $b, $h] = call('POST', '/api/projects', ['name' => $pname, 'category' => 'Perso']);
$pid = $b['data']['id'] ?? 0;
check('POST projet (201, Location)', $s === 201 && ($h['location'] ?? '') === "/api/projects/$pid" && $b['data']['task_count'] === 0);
[$s, $b] = call('POST', '/api/projects', ['name' => $pname, 'category' => 'Pro']);
check('nom de projet en double -> 422', $s === 422 && isset($b['error']['details']['name']));
[$s, $b] = call('POST', '/api/projects', ['name' => '', 'category' => 'X']);
check('projet invalide -> 422', $s === 422 && isset($b['error']['details']['name'], $b['error']['details']['category']));
[$s, $b] = call('GET', '/api/projects?category=Perso');
check('GET projets filtrés', $s === 200 && in_array($pname, array_column($b['data'], 'name'), true));

[$s, $b] = call('POST', '/api/tasks', ['title' => 'Tâche projet', 'project' => $pname]);
$pt = $b['data']['id'] ?? 0;
check('tâche : catégorie déduite du projet', $s === 201 && $b['data']['category'] === 'Perso' && $b['data']['project'] === $pname);
[$s, $b] = call('POST', '/api/tasks', ['title' => 'Conflit', 'category' => 'Pro', 'project' => $pname]);
check('catégorie contredisant le projet -> 422', $s === 422 && isset($b['error']['details']['category']));
[$s, $b] = call('POST', '/api/tasks', ['title' => 'Inconnu', 'category' => 'Pro', 'project' => 'Projet inexistant']);
check('projet inconnu -> 422', $s === 422 && isset($b['error']['details']['project']));

[$s, $b] = call('PATCH', "/api/projects/$pid", ['category' => 'Pro']);
check('changer la catégorie du projet', $s === 200 && $b['data']['category'] === 'Pro' && $b['data']['task_count'] === 1);
[$s, $b] = call('GET', "/api/tasks/$pt");
check('catégorie propagée aux tâches', $b['data']['category'] === 'Pro');
$renamed = $pname . ' bis';
[$s, $b] = call('PATCH', "/api/projects/$pid", ['name' => $renamed]);
[$s, $b] = call('GET', "/api/tasks/$pt");
check('renommage propagé aux tâches', $b['data']['project'] === $renamed);

[$s, $b] = call('DELETE', "/api/projects/$pid");
check('suppression d\'un projet utilisé -> 409', $s === 409 && $b['error']['code'] === 'project_in_use');
call('DELETE', "/api/tasks/$pt");
[$s] = call('DELETE', "/api/projects/$pid");
check('suppression d\'un projet libre -> 204', $s === 204);
[$s] = call('GET', "/api/projects/$pid");
check('projet supprimé -> 404', $s === 404);

call('DELETE', "/api/tasks/$t");
call('DELETE', "/api/tasks/$next");
exit($failures > 0 ? 1 : 0);
