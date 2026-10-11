<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeImmutable;

/** Tâche : données validées à la construction, cycle de vie via start/complete/reopen. */
final class Task
{
    public const TITLE_MAX_LENGTH = 255;

    private function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly Category $category,
        public readonly ?DateTimeImmutable $dueDate,
        public readonly ?Recurrence $recurrence,
        public readonly ?int $durationMinutes,
        public readonly ?string $description,
        public readonly Status $status,
        public readonly ?DateTimeImmutable $completedAt,
    ) {
    }

    /**
     * Crée une nouvelle tâche (statut « à faire ») à partir de données brutes (API, formulaire).
     *
     * @param array<string,mixed> $data
     * @throws ValidationException
     */
    public static function create(array $data): self
    {
        $fields = self::validate($data);
        return new self(null, ...$fields, status: Status::AFaire, completedAt: null);
    }

    /**
     * Reconstitue une tâche depuis une ligne de base (sans re-validation métier stricte).
     *
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $status = Status::from((string) $row['status']);
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            Category::from((string) $row['category']),
            isset($row['due_date']) ? new DateTimeImmutable((string) $row['due_date']) : null,
            isset($row['recurrence']) ? Recurrence::from((string) $row['recurrence']) : null,
            isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
            $row['description'] ?? null,
            $status,
            isset($row['completed_at']) ? new DateTimeImmutable((string) $row['completed_at']) : null,
        );
    }

    /**
     * Retourne une copie modifiée (champs métier) ; le statut n'est pas modifiable ici.
     *
     * @param array<string,mixed> $changes
     * @throws ValidationException
     */
    public function with(array $changes): self
    {
        $fields = self::validate(array_replace($this->toArray(), $changes));
        return new self($this->id, ...$fields, status: $this->status, completedAt: $this->completedAt);
    }

    public function start(): self
    {
        return $this->transition(Status::EnCours);
    }

    public function complete(?DateTimeImmutable $now = null): self
    {
        return $this->transition(Status::Terminee, $now ?? new DateTimeImmutable());
    }

    /** Remet la tâche « à faire » (abandon d'une tâche en cours ou réouverture d'une tâche terminée). */
    public function reopen(): self
    {
        return $this->transition(Status::AFaire);
    }

    /**
     * Occurrence suivante d'une tâche périodique : nouvelle tâche « à faire » dont la date butée
     * est décalée d'une période. Null si la tâche n'est pas périodique.
     */
    public function nextOccurrence(): ?self
    {
        if ($this->recurrence === null || $this->dueDate === null) {
            return null;
        }
        return new self(
            null,
            $this->title,
            $this->category,
            $this->recurrence->next($this->dueDate),
            $this->recurrence,
            $this->durationMinutes,
            $this->description,
            Status::AFaire,
            null,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category->value,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'recurrence' => $this->recurrence?->value,
            'duration_minutes' => $this->durationMinutes,
            'description' => $this->description,
            'status' => $this->status->value,
            'completed_at' => $this->completedAt?->format(DATE_ATOM),
        ];
    }

    private function transition(Status $target, ?DateTimeImmutable $completedAt = null): self
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new InvalidTransitionException($this->status, $target);
        }
        return new self(
            $this->id,
            $this->title,
            $this->category,
            $this->dueDate,
            $this->recurrence,
            $this->durationMinutes,
            $this->description,
            $target,
            $completedAt,
        );
    }

    /**
     * @param array<string,mixed> $data
     * @return array{title:string,category:Category,dueDate:?DateTimeImmutable,recurrence:?Recurrence,durationMinutes:?int,description:?string}
     * @throws ValidationException
     */
    private static function validate(array $data): array
    {
        $errors = [];

        $title = $data['title'] ?? null;
        $title = is_string($title) ? trim($title) : '';
        if ($title === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $errors['title'] = 'Le titre ne doit pas dépasser ' . self::TITLE_MAX_LENGTH . ' caractères.';
        }

        $category = is_string($data['category'] ?? null) ? Category::tryFrom($data['category']) : null;
        if ($category === null) {
            $errors['category'] = 'La catégorie doit être « Pro » ou « Perso ».';
        }

        $dueDate = null;
        $rawDue = $data['due_date'] ?? null;
        if ($rawDue !== null && $rawDue !== '') {
            $dueDate = is_string($rawDue) ? DateTimeImmutable::createFromFormat('!Y-m-d', $rawDue) : false;
            if ($dueDate === false || $dueDate->format('Y-m-d') !== $rawDue) {
                $dueDate = null;
                $errors['due_date'] = 'La date butée doit être une date valide au format AAAA-MM-JJ.';
            }
        }

        $recurrence = null;
        $rawRecurrence = $data['recurrence'] ?? null;
        if ($rawRecurrence !== null && $rawRecurrence !== '') {
            $recurrence = is_string($rawRecurrence) ? Recurrence::tryFrom($rawRecurrence) : null;
            if ($recurrence === null) {
                $errors['recurrence'] = 'La périodicité doit être : quotidienne, hebdomadaire, mensuelle ou annuelle.';
            } elseif (!isset($errors['due_date']) && $dueDate === null) {
                $errors['recurrence'] = 'Une tâche périodique doit avoir une date butée.';
            }
        }

        $duration = null;
        $rawDuration = $data['duration_minutes'] ?? null;
        if ($rawDuration !== null && $rawDuration !== '') {
            if (is_int($rawDuration) || (is_string($rawDuration) && ctype_digit($rawDuration))) {
                $duration = (int) $rawDuration;
            }
            if ($duration === null || $duration < 1) {
                $duration = null;
                $errors['duration_minutes'] = 'La durée doit être un nombre entier de minutes strictement positif.';
            }
        }

        $description = $data['description'] ?? null;
        if ($description !== null && !is_string($description)) {
            $errors['description'] = 'La description doit être un texte.';
            $description = null;
        } elseif ($description !== null && trim($description) === '') {
            $description = null;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'title' => $title,
            'category' => $category,
            'dueDate' => $dueDate,
            'recurrence' => $recurrence,
            'durationMinutes' => $duration,
            'description' => $description,
        ];
    }
}
