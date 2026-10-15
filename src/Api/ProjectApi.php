<?php

declare(strict_types=1);

namespace App\Api;

use App\Task\Category;
use App\Task\Project;
use App\Task\ProjectInUseException;
use App\Task\ProjectRepository;
use App\Task\ValidationException;
use JsonException;

/** API REST JSON de la liste des projets (`/api/projects`), même contrat que `TaskApi`. */
final class ProjectApi
{
    public function __construct(private readonly ProjectRepository $projects)
    {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{status:int,headers:array<string,string>,body:?array<string,mixed>}
     */
    public function handle(string $method, string $path, array $query = [], string $rawBody = ''): array
    {
        if (!preg_match('#^/api/projects(?:/([^/]+))?/?$#', $path, $m)) {
            return $this->error(404, 'not_found', 'Ressource inconnue.');
        }
        $idSegment = $m[1] ?? null;
        if ($idSegment !== null && !ctype_digit($idSegment)) {
            return $this->notFound();
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
        } catch (ProjectInUseException $e) {
            return $this->error(409, 'project_in_use', $e->getMessage(), ['task_count' => $e->taskCount]);
        }
    }

    /** @param array<string,mixed> $query */
    private function list(array $query): array
    {
        $category = null;
        if (isset($query['category'])) {
            $category = is_string($query['category']) ? Category::tryFrom($query['category']) : null;
            if ($category === null) {
                throw new ValidationException(['category' => 'Filtre invalide : « Pro » ou « Perso ».']);
            }
        }
        return $this->ok(200, ['data' => array_map(
            static fn(Project $p): array => $p->toArray(),
            $this->projects->findAll($category),
        )]);
    }

    private function show(int $id): array
    {
        $project = $this->projects->find($id);
        return $project === null ? $this->notFound() : $this->ok(200, ['data' => $project->toArray()]);
    }

    /** @param array<string,mixed> $data */
    private function create(array $data): array
    {
        $project = $this->projects->insert(Project::create($data));
        $response = $this->ok(201, ['data' => $project->toArray()]);
        $response['headers']['Location'] = '/api/projects/' . $project->id;
        return $response;
    }

    /**
     * PUT remplace tous les champs, PATCH ne modifie que ceux fournis. Changer la catégorie d'un projet
     * l'applique à toutes ses tâches ; renommer un projet met à jour ses tâches.
     *
     * @param array<string,mixed> $data
     */
    private function update(int $id, array $data, bool $replace): array
    {
        $project = $this->projects->find($id);
        if ($project === null) {
            return $this->notFound();
        }
        $changes = array_intersect_key($data, ['name' => 1, 'category' => 1]);
        if ($replace) {
            $changes += ['name' => null, 'category' => null];
        }
        $updated = $project->with($changes);
        $this->projects->update($updated);
        return $this->ok(200, ['data' => $this->projects->find($id)?->toArray()]);
    }

    private function delete(int $id): array
    {
        if (!$this->projects->delete($id)) {
            return $this->notFound();
        }
        return ['status' => 204, 'headers' => [], 'body' => null];
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
        return $this->error(404, 'not_found', 'Projet introuvable.');
    }

    /** @param list<string> $allowed */
    private function methodNotAllowed(array $allowed): array
    {
        $response = $this->error(405, 'method_not_allowed', 'Méthode non autorisée.');
        $response['headers']['Allow'] = implode(', ', $allowed);
        return $response;
    }
}
