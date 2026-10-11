<?php

declare(strict_types=1);

namespace App\Web;

use App\Api\TaskApi;

/**
 * Portail web : pages HTML rendues côté serveur, s'appuyant sur `TaskApi` en mémoire
 * (mêmes règles de validation, transitions, périodicité et dépendances que l'API REST).
 * Indépendant de la SAPI : `handle()` retourne la réponse, `public/index.php` l'émet.
 */
final class Portal
{
    private const FIELDS = ['title', 'category', 'due_date', 'recurrence', 'duration_minutes', 'description'];

    public function __construct(private readonly TaskApi $api, private readonly Session $session)
    {
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public function handle(string $method, string $path, array $query = [], array $post = []): array
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/') ?: '/';

        if ($method === 'POST' && !$this->session->checkCsrf($post['_csrf'] ?? null)) {
            return $this->page(403, 'Requête refusée', 'error', ['message' => 'Jeton CSRF invalide ou expiré. Rechargez la page et réessayez.']);
        }

        if ($path === '/') {
            return $method === 'GET' ? $this->index($query) : $this->notAllowed();
        }
        if ($path === '/tasks/new') {
            return $method === 'GET' ? $this->form(null, [], []) : $this->notAllowed();
        }
        if ($path === '/tasks') {
            return $method === 'POST' ? $this->save(null, $post) : $this->notAllowed();
        }
        if (preg_match('#^/tasks/(\d+)(?:/(edit|status|delete))?$#', $path, $m)) {
            $id = (int) $m[1];
            return match ([$method, $m[2] ?? '']) {
                ['GET', ''] => $this->show($id),
                ['GET', 'edit'] => $this->edit($id),
                ['POST', ''] => $this->save($id, $post),
                ['POST', 'status'] => $this->changeStatus($id, $post),
                ['POST', 'delete'] => $this->delete($id),
                default => $this->notAllowed(),
            };
        }
        return $this->page(404, 'Page introuvable', 'error', ['message' => 'Cette page n\'existe pas.']);
    }

    /** @param array<string,mixed> $query */
    private function index(array $query): array
    {
        $filters = [];
        foreach (['category', 'status'] as $key) {
            if (isset($query[$key]) && is_string($query[$key]) && $query[$key] !== '') {
                $filters[$key] = $query[$key];
            }
        }
        $r = $this->api->handle('GET', '/api/tasks', $filters);
        if ($r['status'] !== 200) {
            $filters = [];
            $r = $this->api->handle('GET', '/api/tasks');
        }
        $tasks = $r['body']['data'] ?? [];
        return $this->page(200, 'Actions', 'list', ['tasks' => $tasks, 'filters' => $filters]);
    }

    private function show(int $id): array
    {
        $task = $this->load($id);
        return $task === null ? $this->missing() : $this->page(200, $task['title'], 'show', ['task' => $task]);
    }

    private function edit(int $id): array
    {
        $task = $this->load($id);
        return $task === null ? $this->missing() : $this->form($task, $task, []);
    }

    /** @param array<string,mixed> $post */
    private function save(?int $id, array $post): array
    {
        $body = array_intersect_key($post, array_flip(self::FIELDS));
        foreach ($body as $key => $value) {
            $body[$key] = is_string($value) ? trim($value) : $value;
            if ($body[$key] === '') {
                $body[$key] = null;
            }
        }
        $requirements = $post['requirements'] ?? [];
        $body['requirements'] = is_array($requirements)
            ? array_map('intval', array_values(array_filter($requirements, 'ctype_digit')))
            : [];
        if (isset($body['duration_minutes']) && ctype_digit((string) $body['duration_minutes'])) {
            $body['duration_minutes'] = (int) $body['duration_minutes'];
        }

        $r = $id === null ? $this->call('POST', '/api/tasks', $body) : $this->call('PUT', "/api/tasks/$id", $body);
        if ($r['status'] === 404) {
            return $this->missing();
        }
        if ($r['status'] >= 400) {
            return $this->form($id === null ? null : $this->load($id), $post + ['requirements' => $body['requirements']], $this->errors($r));
        }
        $this->session->flash($id === null ? 'Tâche créée.' : 'Tâche modifiée.');
        return $this->redirect('/tasks/' . $r['body']['data']['id']);
    }

    /** @param array<string,mixed> $post */
    private function changeStatus(int $id, array $post): array
    {
        $r = $this->call('PATCH', "/api/tasks/$id", ['status' => $post['status'] ?? null]);
        if ($r['status'] === 404) {
            return $this->missing();
        }
        if ($r['status'] >= 400) {
            $this->session->flash(implode(' ', $this->errors($r)), 'error');
        } else {
            if (isset($r['body']['next_occurrence'])) {
                $next = $r['body']['next_occurrence'];
                $this->session->flash(
                    'Statut mis à jour. Prochaine occurrence créée : <a href="/tasks/' . (int) $next['id'] . '">'
                    . View::h($next['title']) . '</a>.',
                    'ok',
                    true,
                );
            } else {
                $this->session->flash('Statut mis à jour.');
            }
        }
        $back = $post['back'] ?? '';
        return $this->redirect($back === 'list' ? '/' : "/tasks/$id");
    }

    private function delete(int $id): array
    {
        $r = $this->call('DELETE', "/api/tasks/$id");
        if ($r['status'] === 404) {
            return $this->missing();
        }
        $this->session->flash('Tâche supprimée.');
        return $this->redirect('/');
    }

    /**
     * @param ?array<string,mixed> $task tâche éditée (null en création)
     * @param array<string,mixed> $values
     * @param array<string,string> $errors
     */
    private function form(?array $task, array $values, array $errors): array
    {
        $r = $this->api->handle('GET', '/api/tasks');
        $candidates = array_values(array_filter(
            $r['body']['data'] ?? [],
            static fn(array $t): bool => $task === null || $t['id'] !== $task['id'],
        ));
        if (isset($values['requirements']) && is_array($values['requirements']) && $values['requirements'] !== []) {
            $first = reset($values['requirements']);
            if (is_array($first)) {
                $values['requirements'] = array_column($values['requirements'], 'id');
            }
        }
        return $this->page($errors === [] ? 200 : 422, $task === null ? 'Nouvelle tâche' : 'Modifier la tâche', 'form', [
            'task' => $task,
            'values' => $values,
            'errors' => $errors,
            'candidates' => $candidates,
        ]);
    }

    /** @return ?array<string,mixed> */
    private function load(int $id): ?array
    {
        $r = $this->api->handle('GET', "/api/tasks/$id");
        return $r['status'] === 200 ? $r['body']['data'] : null;
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array{status:int,headers:array<string,string>,body:?array<string,mixed>}
     */
    private function call(string $method, string $path, ?array $body = null): array
    {
        return $this->api->handle($method, $path, [], $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array{status:int,body:?array<string,mixed>} $response
     * @return array<string,string>
     */
    private function errors(array $response): array
    {
        $error = $response['body']['error'] ?? [];
        $details = $error['details'] ?? [];
        if (($error['code'] ?? '') === 'dependency_cycle') {
            return ['requirements' => $error['message'] ?? 'Cycle de dépendances.'];
        }
        if (($error['code'] ?? '') === 'validation_failed' && $details !== []) {
            return $details;
        }
        return ['_' => $error['message'] ?? 'Erreur inattendue.'];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function page(int $status, string $title, string $view, array $data = []): array
    {
        $data += ['csrf' => $this->session->csrfToken(), 'flashes' => $this->session->takeFlashes()];
        return ['status' => $status, 'headers' => [], 'body' => View::render($view, $title, $data)];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private function redirect(string $to): array
    {
        return ['status' => 303, 'headers' => ['Location' => $to], 'body' => ''];
    }

    private function missing(): array
    {
        return $this->page(404, 'Tâche introuvable', 'error', ['message' => 'Cette tâche n\'existe pas (ou plus).']);
    }

    private function notAllowed(): array
    {
        return $this->page(405, 'Méthode non autorisée', 'error', ['message' => 'Méthode non autorisée.']);
    }
}
