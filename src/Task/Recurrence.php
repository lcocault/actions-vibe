<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeImmutable;

enum Recurrence: string
{
    case Quotidienne = 'quotidienne';
    case Hebdomadaire = 'hebdomadaire';
    case Mensuelle = 'mensuelle';
    case Annuelle = 'annuelle';

    /** Date de l'occurrence suivante (fin de mois conservée : 31 janvier -> 28/29 février). */
    public function next(DateTimeImmutable $from): DateTimeImmutable
    {
        return match ($this) {
            self::Quotidienne => $from->modify('+1 day'),
            self::Hebdomadaire => $from->modify('+1 week'),
            self::Mensuelle => self::addMonths($from, 1),
            self::Annuelle => self::addMonths($from, 12),
        };
    }

    private static function addMonths(DateTimeImmutable $from, int $months): DateTimeImmutable
    {
        $first = $from->modify('first day of this month')->modify("+$months months");
        $day = min((int) $from->format('j'), (int) $first->format('t'));
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
    }
}
