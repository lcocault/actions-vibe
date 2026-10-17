<?php

declare(strict_types=1);

namespace App\Task;

enum Importance: string
{
    case Mineure = 'Mineure';
    case Majeure = 'Majeure';
    case Critique = 'Critique';
}
