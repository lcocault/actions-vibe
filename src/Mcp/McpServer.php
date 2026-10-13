<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Api\TaskApi;
use JsonException;

/**
 * Serveur MCP (transport « Streamable HTTP », JSON-RPC 2.0) exposant les tâches comme outils.
 * Indépendant de HTTP/SAPI, comme `TaskApi` qu'il réutilise en mémoire : `handle()` reçoit la méthode
 * HTTP et le corps brut, et retourne `{status, headers, body}` (body = chaîne JSON ou null).
 */
final class McpServer
{
    private const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    private const PARSE_ERROR = -32700;
    private const INVALID_REQUEST = -32600;
    private const METHOD_NOT_FOUND = -32601;
    private const INVALID_PARAMS = -32602;

    public function __construct(private readonly TaskApi $api)
    {
    }

    /** @return array{status:int,headers:array<string,string>,body:?string} */
    public function handle(string $method, string $rawBody): array
    {
        $method = strtoupper($method);
        if ($method !== 'POST') {
            return ['status' => 405, 'headers' => ['Allow' => 'POST'], 'body' => null];
        }

        try {
            $message = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json($this->failure(null, self::PARSE_ERROR, 'JSON invalide.'), 400);
        }
        if (!is_array($message) || $message === []) {
            return $this->json($this->failure(null, self::INVALID_REQUEST, 'Requête JSON-RPC invalide.'), 400);
        }

        if (array_is_list($message)) {
            $responses = [];
            foreach ($message as $item) {
                $response = $this->dispatch($item);
                if ($response !== null) {
                    $responses[] = $response;
                }
            }
            return $responses === [] ? $this->accepted() : $this->json($responses);
        }

        $response = $this->dispatch($message);
        return $response === null ? $this->accepted() : $this->json($response);
    }

    /** @return array<string,mixed>|null null pour une notification ou une réponse client (rien à répondre) */
    private function dispatch(mixed $message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return $this->failure(null, self::INVALID_REQUEST, 'Requête JSON-RPC invalide.');
        }
        $hasId = array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        if ($hasId && !is_int($id) && !is_string($id)) {
            return $this->failure(null, self::INVALID_REQUEST, 'Identifiant de requête invalide.');
        }
        if (!isset($message['method'])) {
            return null; // réponse du client à une requête serveur : ignorée
        }
        if (!is_string($message['method'])) {
            return $this->failure($id, self::INVALID_REQUEST, 'Méthode invalide.');
        }
        if (!$hasId) {
            return null; // notification (ex. notifications/initialized)
        }

        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return $this->failure($id, self::INVALID_PARAMS, 'Paramètres invalides.');
        }

        return match ($message['method']) {
            'initialize' => $this->success($id, $this->initialize($params)),
            'ping' => $this->success($id, new \stdClass()),
            'tools/list' => $this->success($id, ['tools' => $this->tools()]),
            'tools/call' => $this->callTool($id, $params),
            default => $this->failure($id, self::METHOD_NOT_FOUND, 'Méthode inconnue : ' . $message['method'] . '.'),
        };
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $version = is_string($requested) && in_array($requested, self::SUPPORTED_VERSIONS, true)
            ? $requested
            : self::SUPPORTED_VERSIONS[0];
        return [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'actions-vibe', 'version' => '1.0.0'],
            'instructions' => 'Gestion de tâches Pro/Perso avec prérequis, statuts et périodicité.',
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function callTool(int|string $id, array $params): array
    {
        $name = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];
        if (!is_string($name) || !is_array($args) || ($args !== [] && array_is_list($args))) {
            return $this->failure($id, self::INVALID_PARAMS, 'Appel d\'outil invalide.');
        }

        $taskId = $args['id'] ?? null;
        $fields = array_diff_key($args, ['id' => true]);
        $needsId = in_array($name, ['get_task', 'update_task', 'delete_task'], true);
        if ($needsId && (!is_int($taskId) || $taskId < 1)) {
            return $this->success($id, $this->toolResult(['error' => [
                'code' => 'validation_failed',
                'message' => 'Données invalides.',
                'details' => ['id' => 'Identifiant de tâche requis (entier positif).'],
            ]], true));
        }

        $response = match ($name) {
            'list_tasks' => $this->api->handle('GET', '/api/tasks', array_intersect_key($args, ['category' => 1, 'status' => 1])),
            'get_task' => $this->api->handle('GET', "/api/tasks/$taskId"),
            'create_task' => $this->api->handle('POST', '/api/tasks', [], $this->encode($fields)),
            'update_task' => $this->api->handle('PATCH', "/api/tasks/$taskId", [], $this->encode($fields)),
            'delete_task' => $this->api->handle('DELETE', "/api/tasks/$taskId"),
            default => null,
        };
        if ($response === null) {
            return $this->failure($id, self::INVALID_PARAMS, 'Outil inconnu : ' . $name . '.');
        }

        $isError = $response['status'] >= 400;
        $body = $response['body'] ?? ['data' => ['deleted' => true, 'id' => $taskId]];
        return $this->success($id, $this->toolResult($body, $isError));
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function toolResult(array $body, bool $isError): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $this->encode($body, true)]],
            'isError' => $isError,
        ];
    }

    /** @param array<string,mixed> $data */
    private function encode(array $data, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($pretty ? JSON_PRETTY_PRINT : 0);
        return $data === [] ? '{}' : json_encode($data, $flags);
    }

    /** @return list<array<string,mixed>> */
    private function tools(): array
    {
        $category = ['type' => 'string', 'enum' => ['Pro', 'Perso'], 'description' => 'Catégorie de la tâche.'];
        $status = ['type' => 'string', 'enum' => ['a_faire', 'en_cours', 'terminee'], 'description' => 'Statut de la tâche.'];
        $idProp = ['type' => 'integer', 'minimum' => 1, 'description' => 'Identifiant de la tâche.'];
        $fields = [
            'title' => ['type' => 'string', 'description' => 'Titre (obligatoire à la création).'],
            'category' => $category,
            'due_date' => ['type' => ['string', 'null'], 'description' => 'Date butée au format AAAA-MM-JJ.'],
            'recurrence' => [
                'type' => ['string', 'null'],
                'enum' => ['quotidienne', 'hebdomadaire', 'mensuelle', 'annuelle', null],
                'description' => 'Périodicité (exige une date butée). À la clôture, la tâche suivante est créée.',
            ],
            'duration_minutes' => ['type' => ['integer', 'null'], 'minimum' => 1, 'description' => 'Durée estimée en minutes.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'Description libre.'],
            'project' => ['type' => ['string', 'null'], 'description' => 'Projet auquel la tâche se rattache (255 caractères max).'],
            'conclusions' => ['type' => ['string', 'null'], 'description' => 'Conclusions, enseignements ou résultat de la tâche.'],
            'requirements' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'minimum' => 1],
                'description' => 'Identifiants des tâches prérequises (remplace la liste existante ; les cycles sont refusés).',
            ],
        ];

        return [
            [
                'name' => 'create_task',
                'description' => 'Crée une tâche (statut initial : a_faire).',
                'inputSchema' => ['type' => 'object', 'properties' => $fields, 'required' => ['title', 'category']],
            ],
            [
                'name' => 'list_tasks',
                'description' => 'Liste les tâches avec leurs prérequis, avec filtres optionnels.',
                'inputSchema' => ['type' => 'object', 'properties' => ['category' => $category, 'status' => $status]],
            ],
            [
                'name' => 'get_task',
                'description' => 'Consulte une tâche et ses prérequis.',
                'inputSchema' => ['type' => 'object', 'properties' => ['id' => $idProp], 'required' => ['id']],
            ],
            [
                'name' => 'update_task',
                'description' => 'Modifie les champs fournis d\'une tâche. `status` déclenche une transition '
                    . '(a_faire → en_cours → terminee) ; terminer une tâche périodique crée l\'occurrence suivante.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => $idProp] + $fields + ['status' => $status],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'delete_task',
                'description' => 'Supprime une tâche (les liens de dépendance associés sont retirés).',
                'inputSchema' => ['type' => 'object', 'properties' => ['id' => $idProp], 'required' => ['id']],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function success(int|string $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /** @return array<string,mixed> */
    private function failure(int|string|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    /**
     * @param array<mixed> $payload
     * @return array{status:int,headers:array<string,string>,body:?string}
     */
    private function json(array $payload, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:?string} */
    private function accepted(): array
    {
        return ['status' => 202, 'headers' => [], 'body' => null];
    }
}
