<?php

declare(strict_types=1);

namespace App\Task;

use RuntimeException;

final class ProjectInUseException extends RuntimeException
{
    public function __construct(public readonly int $taskCount)
    {
        parent::__construct("Projet utilisé par $taskCount tâche(s) : réaffectez ou supprimez-les d'abord.");
    }
}
