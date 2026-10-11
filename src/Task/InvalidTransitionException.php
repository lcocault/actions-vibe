<?php

declare(strict_types=1);

namespace App\Task;

use LogicException;

final class InvalidTransitionException extends LogicException
{
    public function __construct(Status $from, Status $to)
    {
        parent::__construct("Transition interdite : {$from->value} -> {$to->value}.");
    }
}
