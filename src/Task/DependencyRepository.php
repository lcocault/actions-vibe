<?php

declare(strict_types=1);

namespace App\Task;

use PDO;
use Throwable;

/**
 * Persistance des dépendances (table `task_dependency`) avec refus des cycles.
 * Les écritures rejoignent la transaction en cours si l'appelant en a ouvert une.
 */
final class DependencyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Déclare que `$taskId` requiert `$requiredId`. Idempotent.
     *
     * @throws CycleException
     * @throws \InvalidArgumentException auto-dépendance
     */
    public function add(int $taskId, int $requiredId): void
    {
        $owns = !$this->pdo->inTransaction();
        $owns && $this->pdo->beginTransaction();
        try {
            // Verrou de table : sérialise les ajouts pour que deux requêtes concurrentes ne créent pas un cycle.
            $this->pdo->exec('LOCK TABLE task_dependency IN SHARE ROW EXCLUSIVE MODE');
            $this->graph()->add($taskId, $requiredId);
            $this->pdo->prepare(
                'INSERT INTO task_dependency (task_id, required_task_id) VALUES (?, ?) ON CONFLICT DO NOTHING'
            )->execute([$taskId, $requiredId]);
            $owns && $this->pdo->commit();
        } catch (Throwable $e) {
            $owns && $this->pdo->rollBack();
            throw $e;
        }
    }

    public function remove(int $taskId, int $requiredId): void
    {
        $this->pdo->prepare('DELETE FROM task_dependency WHERE task_id = ? AND required_task_id = ?')
            ->execute([$taskId, $requiredId]);
    }

    /**
     * Remplace l'ensemble des prérequis d'une tâche, en tout ou rien.
     *
     * @param list<int> $requiredIds
     * @throws CycleException
     */
    public function replaceRequirements(int $taskId, array $requiredIds): void
    {
        $owns = !$this->pdo->inTransaction();
        $owns && $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('LOCK TABLE task_dependency IN SHARE ROW EXCLUSIVE MODE');
            $this->pdo->prepare('DELETE FROM task_dependency WHERE task_id = ?')->execute([$taskId]);
            $graph = $this->graph();
            $insert = $this->pdo->prepare('INSERT INTO task_dependency (task_id, required_task_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
            foreach (array_unique($requiredIds) as $requiredId) {
                $graph->add($taskId, $requiredId);
                $insert->execute([$taskId, $requiredId]);
            }
            $owns && $this->pdo->commit();
        } catch (Throwable $e) {
            $owns && $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<int> prérequis directs */
    public function requirementsOf(int $taskId): array
    {
        $stmt = $this->pdo->prepare('SELECT required_task_id FROM task_dependency WHERE task_id = ? ORDER BY required_task_id');
        $stmt->execute([$taskId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function graph(): DependencyGraph
    {
        $rows = $this->pdo->query('SELECT task_id, required_task_id FROM task_dependency')->fetchAll(PDO::FETCH_NUM);
        return new DependencyGraph(array_map(static fn(array $r): array => [(int) $r[0], (int) $r[1]], $rows));
    }
}
