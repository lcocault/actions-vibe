<?php

declare(strict_types=1);

namespace App\Task;

use InvalidArgumentException;

/** Données de tâche invalides ; `errors` associe chaque champ fautif à son message. */
final class ValidationException extends InvalidArgumentException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Tâche invalide : ' . implode(' ', $errors));
    }
}
