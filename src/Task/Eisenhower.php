<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeImmutable;

/**
 * Matrice d'Eisenhower à 9 cellules : importance (Critique, Majeure, Mineure) × urgence
 * (moins d'une semaine, moins d'un mois, au-delà). Les tâches en retard comptent comme
 * « moins d'une semaine » ; sans date butée, elles sont rangées dans « au-delà ».
 */
final class Eisenhower
{
    public const IMPORTANCES = ['Critique', 'Majeure', 'Mineure'];
    public const URGENCIES = ['week' => 'Moins d\'une semaine', 'month' => 'Moins d\'un mois', 'later' => 'Au-delà'];

    /** @param array{due_date:?string} $task */
    public static function urgency(array $task, DateTimeImmutable $today): string
    {
        if ($task['due_date'] === null) {
            return 'later';
        }
        $days = (int) $today->setTime(0, 0)->diff(new DateTimeImmutable($task['due_date']))->format('%r%a');
        return $days < 7 ? 'week' : ($days < 30 ? 'month' : 'later');
    }

    /**
     * Seules les tâches non terminées sont placées.
     *
     * @param list<array<string,mixed>> $tasks
     * @return array<string,array<string,list<array<string,mixed>>>> [importance][urgence] => tâches
     */
    public static function matrix(array $tasks, DateTimeImmutable $today): array
    {
        $matrix = [];
        foreach (self::IMPORTANCES as $importance) {
            $matrix[$importance] = array_fill_keys(array_keys(self::URGENCIES), []);
        }
        foreach ($tasks as $task) {
            if ($task['status'] !== 'terminee') {
                $matrix[$task['importance']][self::urgency($task, $today)][] = $task;
            }
        }
        return $matrix;
    }
}
