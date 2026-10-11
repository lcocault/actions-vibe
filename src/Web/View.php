<?php

declare(strict_types=1);

namespace App\Web;

use DateTimeImmutable;

/** Rendu des gabarits `views/*.php` dans la mise en page commune. */
final class View
{
    public const STATUS_LABELS = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'terminee' => 'Terminée'];
    public const RECURRENCE_LABELS = [
        'quotidienne' => 'Quotidienne',
        'hebdomadaire' => 'Hebdomadaire',
        'mensuelle' => 'Mensuelle',
        'annuelle' => 'Annuelle',
    ];

    public static function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function duration(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }
        return $minutes < 60 ? "$minutes min" : intdiv($minutes, 60) . ' h' . ($minutes % 60 ? sprintf(' %02d', $minutes % 60) : '');
    }

    public static function date(?string $iso): string
    {
        return $iso === null ? '' : (new DateTimeImmutable($iso))->format('d/m/Y');
    }

    /** Une tâche non terminée est bloquée tant qu'un de ses prérequis n'est pas terminé. */
    public static function isBlocked(array $task): bool
    {
        if ($task['status'] === 'terminee') {
            return false;
        }
        foreach ($task['requirements'] as $req) {
            if ($req['status'] !== 'terminee') {
                return true;
            }
        }
        return false;
    }

    public static function isOverdue(array $task): bool
    {
        return $task['status'] !== 'terminee' && $task['due_date'] !== null && $task['due_date'] < date('Y-m-d');
    }

    /** @param array<string,mixed> $data */
    public static function render(string $view, string $title, array $data): string
    {
        $content = (static function () use ($view, $title, $data): string {
            extract($data + ['title' => $title], EXTR_SKIP);
            ob_start();
            require __DIR__ . '/views/' . $view . '.php';
            return (string) ob_get_clean();
        })();
        $flashes = $data['flashes'] ?? [];
        ob_start();
        require __DIR__ . '/views/layout.php';
        return (string) ob_get_clean();
    }
}
