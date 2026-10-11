<?php

declare(strict_types=1);

namespace App\Task;

use PDO;

/** Persistance des tâches (table `task`). */
final class TaskRepository
{
    private const COLUMNS = 'id, title, category, due_date, recurrence, duration_minutes, description, status, completed_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $id): ?Task
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM task WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : Task::fromRow($row);
    }

    /** @return list<Task> triées par date butée (sans date en dernier), puis par identifiant */
    public function findAll(?Category $category = null, ?Status $status = null): array
    {
        $where = [];
        $params = [];
        if ($category !== null) {
            $where[] = 'category = ?';
            $params[] = $category->value;
        }
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status->value;
        }
        $sql = 'SELECT ' . self::COLUMNS . ' FROM task'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY due_date ASC NULLS LAST, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn(array $row): Task => Task::fromRow($row), $stmt->fetchAll());
    }

    /** Insère la tâche et retourne sa version persistée (avec identifiant). */
    public function insert(Task $task): Task
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO task (title, category, due_date, recurrence, duration_minutes, description, status, completed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING ' . self::COLUMNS
        );
        $stmt->execute($this->values($task));
        return Task::fromRow($stmt->fetch());
    }

    /** Enregistre l'état de la tâche ; retourne false si elle n'existe pas. */
    public function update(Task $task): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE task SET title = ?, category = ?, due_date = ?, recurrence = ?, duration_minutes = ?,
                 description = ?, status = ?, completed_at = ?, updated_at = now()
             WHERE id = ?'
        );
        $stmt->execute([...$this->values($task), $task->id]);
        return $stmt->rowCount() > 0;
    }

    /** Supprime la tâche ; les liens de dépendance associés sont supprimés en cascade. */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM task WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<int> $ids
     * @return list<int> ceux des identifiants qui existent
     */
    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM task WHERE id IN ($in)");
        $stmt->execute(array_values($ids));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Prérequis directs de plusieurs tâches.
     *
     * @param list<int> $taskIds
     * @return array<int,list<array{id:int,title:string,status:string}>> tâche => prérequis
     */
    public function requirementsFor(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT d.task_id, t.id, t.title, t.status
             FROM task_dependency d JOIN task t ON t.id = d.required_task_id
             WHERE d.task_id IN ($in) ORDER BY t.id"
        );
        $stmt->execute(array_values($taskIds));
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['task_id']][] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
            ];
        }
        return $result;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @return list<mixed> */
    private function values(Task $task): array
    {
        $a = $task->toArray();
        return [
            $a['title'], $a['category'], $a['due_date'], $a['recurrence'], $a['duration_minutes'],
            $a['description'], $a['status'], $a['completed_at'],
        ];
    }
}
