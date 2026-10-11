<?php

declare(strict_types=1);

namespace App\Api;

use App\Task\Category;
use App\Task\CycleException;
use App\Task\DependencyRepository;
use App\Task\InvalidTransitionException;
use App\Task\Status;
use App\Task\Task;
use App\Task\TaskRepository;
use App\Task\ValidationException;
use JsonException;

/**
 * API REST JSON des tâches (`/api/tasks`). Indépendante de HTTP/SAPI : `handle()` reçoit
 * la méthode, le chemin, la query string décodée et le corps brut, et retourne la réponse.
 */
final class TaskApi
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly DependencyRepository $dependencies,
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{status:int,headers:array<string,string>,body:?array<string,mixed>}
     */
    public function handle(string $method, string $path, array $query = [], string $rawBody = ''): array
    {
        if (!preg_match('#^/api/tasks(?:/([^/]+))?/?$#', $path, $m)) {
            return $this->error(404, 'not_found', 'Ressource inconnue.');
        }
        $idSegment = $m[1] ?? null;
        if ($idSegment !== null && !ctype_digit($idSegment)) {
            return $this->error(404, 'not_found', 'Tâche introuvable.');
        }

        $method = strtoupper($method);
        try {
            if ($idSegment === null) {
                return match ($method) {
                    'GET' => $this->list($query),
                    'POST' => $this->create($this->decode($rawBody)),
                    default => $this->methodNotAllowed(['GET', 'POST']),
                };
            }
            $id = (int) $idSegment;
            return match ($method) {
                'GET' => $this->show($id),
                'PUT' => $this->update($id, $this->decode($rawBody), true),
                'PATCH' => $this->update($id, $this->decode($rawBody), false),
                'DELETE' => $this->delete($id),
                default => $this->methodNotAllowed(['GET', 'PUT', 'PATCH', 'DELETE']),
            };
        } catch (BadRequestException $e) {
            return $this->error(400, 'bad_request', $e->getMessage());
        } catch (ValidationException $e) {
            return $this->error(422, 'validation_failed', 'Données invalides.', $e->errors);
        } catch (InvalidTransitionException $e) {
            return $this->error(409, 'invalid_transition', $e->getMessage());
        } catch (CycleException $e) {
            return $this->error(409, 'dependency_cycle', $e->getMessage(), ['path' => $e->path]);
        }
    }

    /** @param array<string,mixed> $query */
    private function list(array $query): array
    {
        $errors = [];
        $category = null;
        if (isset($query['category'])) {
            $category = is_string($query['category']) ? Category::tryFrom($query['category']) : null;
            if ($category === null) {
                $errors['category'] = 'Filtre invalide : « Pro » ou « Perso ».';
            }
        }
        $status = null;
        if (isset($query['status'])) {
            $status = is_string($query['status']) ? Status::tryFrom($query['status']) : null;
            if ($status === null) {
                $errors['status'] = 'Filtre invalide : a_faire, en_cours ou terminee.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $tasks = $this->tasks->findAll($category, $status);
        $requirements = $this->tasks->requirementsFor(array_map(static fn(Task $t): int => (int) $t->id, $tasks));
        return $this->ok(200, [
            'data' => array_map(
                fn(Task $t): array => $this->present($t, $requirements[$t->id] ?? []),
                $tasks,
            ),
        ]);
    }

    private function show(int $id): array
    {
        $task = $this->tasks->find($id);
        return $task === null ? $this->notFound() : $this->ok(200, ['data' => $this->presentWithRequirements($task)]);
    }

    /** @param array<string,mixed> $data */
    private function create(array $data): array
    {
        $task = Task::create($data);
        $requiredIds = $this->requiredIds($data);

        $pdo = $this->tasks->pdo();
        $pdo->beginTransaction();
        try {
            $task = $this->tasks->insert($task);
            if ($requiredIds !== null) {
                $this->dependencies->replaceRequirements((int) $task->id, $requiredIds);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }

        $response = $this->ok(201, ['data' => $this->presentWithRequirements($task)]);
        $response['headers']['Location'] = '/api/tasks/' . $task->id;
        return $response;
    }

    /**
     * PUT remplace tous les champs (ceux omis sont effacés) ; PATCH ne modifie que ceux fournis.
     * Dans les deux cas, `status` déclenche une transition et `requirements` (si présent) remplace les prérequis.
     *
     * @param array<string,mixed> $data
     */
    private function update(int $id, array $data, bool $replace): array
    {
        $task = $this->tasks->find($id);
        if ($task === null) {
            return $this->notFound();
        }

        $fields = ['title', 'category', 'due_date', 'recurrence', 'duration_minutes', 'description'];
        $changes = array_intersect_key($data, array_flip($fields));
        if ($replace) {
            $changes += array_fill_keys($fields, null);
        }
        $updated = $task->with($changes);

        $target = null;
        if (array_key_exists('status', $data)) {
            $target = is_string($data['status']) ? Status::tryFrom($data['status']) : null;
            if ($target === null) {
                throw new ValidationException(['status' => 'Le statut doit être : a_faire, en_cours ou terminee.']);
            }
        }
        $requiredIds = $this->requiredIds($data);

        $next = null;
        if ($target !== null && $target !== $task->status) {
            $updated = match ($target) {
                Status::EnCours => $updated->start(),
                Status::Terminee => $updated->complete(),
                Status::AFaire => $updated->reopen(),
            };
            $next = $target === Status::Terminee ? $updated->nextOccurrence() : null;
        }

        $pdo = $this->tasks->pdo();
        $pdo->beginTransaction();
        try {
            $this->tasks->update($updated);
            if ($requiredIds !== null) {
                $this->dependencies->replaceRequirements($id, $requiredIds);
            }
            $next = $next === null ? null : $this->tasks->insert($next);
            $pdo->commit();
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }

        $body = ['data' => $this->presentWithRequirements($updated)];
        if ($next !== null) {
            $body['next_occurrence'] = $this->presentWithRequirements($next);
        }
        return $this->ok(200, $body);
    }

    private function delete(int $id): array
    {
        if (!$this->tasks->delete($id)) {
            return $this->notFound();
        }
        return ['status' => 204, 'headers' => [], 'body' => null];
    }

    /**
     * @param array<string,mixed> $data
     * @return list<int>|null null si `requirements` est absent
     * @throws ValidationException
     */
    private function requiredIds(array $data): ?array
    {
        if (!array_key_exists('requirements', $data)) {
            return null;
        }
        $raw = $data['requirements'];
        $message = 'Les prérequis doivent être une liste d\'identifiants de tâches.';
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new ValidationException(['requirements' => $message]);
        }
        foreach ($raw as $value) {
            if (!is_int($value) || $value < 1) {
                throw new ValidationException(['requirements' => $message]);
            }
        }
        $ids = array_values(array_unique($raw));
        $missing = array_diff($ids, $this->tasks->existingIds($ids));
        if ($missing !== []) {
            throw new ValidationException(['requirements' => 'Tâches inexistantes : ' . implode(', ', $missing) . '.']);
        }
        return $ids;
    }

    /** @return array<string,mixed> */
    private function decode(string $rawBody): array
    {
        try {
            $data = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestException('Le corps de la requête doit être du JSON valide.');
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new BadRequestException('Le corps de la requête doit être un objet JSON.');
        }
        return $data;
    }

    /** @return array<string,mixed> */
    private function presentWithRequirements(Task $task): array
    {
        return $this->present($task, $this->tasks->requirementsFor([(int) $task->id])[$task->id] ?? []);
    }

    /**
     * @param list<array{id:int,title:string,status:string}> $requirements
     * @return array<string,mixed>
     */
    private function present(Task $task, array $requirements): array
    {
        return $task->toArray() + ['requirements' => $requirements];
    }

    private function rollBack(): void
    {
        $pdo = $this->tasks->pdo();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    /** @param array<string,mixed> $body */
    private function ok(int $status, array $body): array
    {
        return ['status' => $status, 'headers' => [], 'body' => $body];
    }

    /** @param array<string,mixed> $details */
    private function error(int $status, string $code, string $message, array $details = []): array
    {
        $error = ['code' => $code, 'message' => $message] + ($details ? ['details' => $details] : []);
        return ['status' => $status, 'headers' => [], 'body' => ['error' => $error]];
    }

    private function notFound(): array
    {
        return $this->error(404, 'not_found', 'Tâche introuvable.');
    }

    /** @param list<string> $allowed */
    private function methodNotAllowed(array $allowed): array
    {
        $response = $this->error(405, 'method_not_allowed', 'Méthode non autorisée.');
        $response['headers']['Allow'] = implode(', ', $allowed);
        return $response;
    }
}
