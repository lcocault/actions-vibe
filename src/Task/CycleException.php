<?php

declare(strict_types=1);

namespace App\Task;

use RuntimeException;

/** Dépendance refusée car elle créerait un cycle ; `path` donne le cycle (ex. [1, 2, 3, 1]). */
final class CycleException extends RuntimeException
{
    /** @param list<int> $path */
    public function __construct(public readonly array $path)
    {
        parent::__construct('Dépendance cyclique : ' . implode(' → ', $path));
    }
}
