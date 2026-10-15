<?php

declare(strict_types=1);

namespace App\Task;

use PDO;
use PDOException;

/** Persistance des projets (table `project`). */
final class ProjectRepository
{
    private const SELECT = 'SELECT p.id, p.name, p.category, (SELECT count(*) FROM task t WHERE t.project = p.name) AS task_count FROM project p';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $id): ?Project
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE p.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : Project::fromRow($row);
    }

    public function findByName(string $name): ?Project
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE p.name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row === false ? null : Project::fromRow($row);
    }

    /** @return list<Project> triés par nom */
    public function findAll(?Category $category = null): array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ($category ? ' WHERE p.category = ?' : '') . ' ORDER BY lower(p.name), p.id');
        $stmt->execute($category ? [$category->value] : []);
        return array_map(static fn(array $row): Project => Project::fromRow($row), $stmt->fetchAll());
    }

    /** @throws ValidationException nom déjà utilisé */
    public function insert(Project $project): Project
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO project (name, category) VALUES (?, ?) RETURNING id');
            $stmt->execute([$project->name, $project->category->value]);
        } catch (PDOException $e) {
            throw $this->duplicate($e);
        }
        return $this->find((int) $stmt->fetchColumn());
    }

    /**
     * Enregistre le projet ; un renommage est propagé aux tâches (clé étrangère) et un changement
     * de catégorie à toutes ses tâches. Retourne false si le projet n'existe pas.
     *
     * @throws ValidationException nom déjà utilisé
     */
    public function update(Project $project): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE project SET name = ?, category = ?, updated_at = now() WHERE id = ?');
            $stmt->execute([$project->name, $project->category->value, $project->id]);
            $found = $stmt->rowCount() > 0;
            if ($found) {
                $this->pdo->prepare('UPDATE task SET category = ?, updated_at = now() WHERE project = ? AND category <> ?')
                    ->execute([$project->category->value, $project->name, $project->category->value]);
            }
            $this->pdo->commit();
            return $found;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $this->duplicate($e);
        }
    }

    /**
     * @throws ProjectInUseException des tâches y sont encore rattachées
     * @return bool false si le projet n'existe pas
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM project WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if ($e->getCode() === '23503') {
                throw new ProjectInUseException($this->find($id)?->taskCount ?? 0);
            }
            throw $e;
        }
    }

    private function duplicate(PDOException $e): \Throwable
    {
        return $e->getCode() === '23505' ? new ValidationException(['name' => 'Un projet porte déjà ce nom.']) : $e;
    }
}
