<?php

declare(strict_types=1);

namespace App\Task;

/**
 * Cycle de vie : à faire -> en cours -> terminée.
 * Retours autorisés : en cours -> à faire (abandon), terminée -> à faire (réouverture).
 */
enum Status: string
{
    case AFaire = 'a_faire';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AFaire => [self::EnCours, self::Terminee],
            self::EnCours => [self::AFaire, self::Terminee],
            self::Terminee => [self::AFaire],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
