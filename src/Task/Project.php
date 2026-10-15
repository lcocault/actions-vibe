<?php

declare(strict_types=1);

namespace App\Task;

/** Projet : nom unique et catégorie Pro/Perso héritée par ses tâches. */
final class Project
{
    private function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly Category $category,
        public readonly int $taskCount = 0,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @throws ValidationException
     */
    public static function create(array $data): self
    {
        [$name, $category] = self::validate($data);
        return new self(null, $name, $category);
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            Category::from((string) $row['category']),
            (int) ($row['task_count'] ?? 0),
        );
    }

    /**
     * @param array<string,mixed> $changes
     * @throws ValidationException
     */
    public function with(array $changes): self
    {
        [$name, $category] = self::validate(array_replace($this->toArray(), $changes));
        return new self($this->id, $name, $category, $this->taskCount);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'category' => $this->category->value, 'task_count' => $this->taskCount];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{0:string,1:Category}
     * @throws ValidationException
     */
    private static function validate(array $data): array
    {
        $errors = [];

        $name = $data['name'] ?? null;
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($name) > Task::PROJECT_MAX_LENGTH) {
            $errors['name'] = 'Le nom ne doit pas dépasser ' . Task::PROJECT_MAX_LENGTH . ' caractères.';
        }

        $category = is_string($data['category'] ?? null) ? Category::tryFrom($data['category']) : null;
        if ($category === null) {
            $errors['category'] = 'La catégorie doit être « Pro » ou « Perso ».';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [$name, $category];
    }
}
