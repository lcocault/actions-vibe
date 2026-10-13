<?php

declare(strict_types=1);

// Tests sans dépendance : php tests/TaskTest.php (code de sortie 1 en cas d'échec).

require __DIR__ . '/../src/autoload.php';

use App\Task\InvalidTransitionException;
use App\Task\Recurrence;
use App\Task\Status;
use App\Task\Task;
use App\Task\ValidationException;

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
}
/** @param callable():mixed $fn */
function throws(callable $fn, string $class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

$valid = ['title' => '  Écrire le rapport ', 'category' => 'Pro'];

$t = Task::create($valid);
check('création minimale', $t->title === 'Écrire le rapport' && $t->status === Status::AFaire && $t->completedAt === null);
check('titre vide refusé', throws(fn() => Task::create(['title' => ' ', 'category' => 'Pro']), ValidationException::class));
check('catégorie invalide refusée', throws(fn() => Task::create(['title' => 'x', 'category' => 'Autre']), ValidationException::class));
check('date invalide refusée', throws(fn() => Task::create($valid + ['due_date' => '2026-02-30']), ValidationException::class));
check('durée nulle refusée', throws(fn() => Task::create($valid + ['duration_minutes' => 0]), ValidationException::class));
check('durée texte acceptée', Task::create($valid + ['duration_minutes' => '45'])->durationMinutes === 45);
check('périodicité sans date refusée', throws(fn() => Task::create($valid + ['recurrence' => 'mensuelle']), ValidationException::class));
check('périodicité inconnue refusée', throws(fn() => Task::create($valid + ['due_date' => '2026-01-01', 'recurrence' => 'x']), ValidationException::class));

try {
    Task::create(['title' => '', 'category' => 'X', 'duration_minutes' => -1]);
} catch (ValidationException $e) {
    check('toutes les erreurs remontées', array_keys($e->errors) === ['title', 'category', 'duration_minutes']);
}

// Cycle de vie
$started = $t->start();
check('à faire -> en cours', $started->status === Status::EnCours);
$done = $started->complete(new DateTimeImmutable('2026-10-11 10:00:00'));
check('en cours -> terminée avec date de clôture', $done->status === Status::Terminee && $done->completedAt !== null);
check('terminée -> en cours interdit', throws(fn() => $done->start(), InvalidTransitionException::class));
check('terminée -> terminée interdit', throws(fn() => $done->complete(), InvalidTransitionException::class));
$reopened = $done->reopen();
check('réouverture efface la clôture', $reopened->status === Status::AFaire && $reopened->completedAt === null);
check('à faire -> terminée direct', $t->complete()->status === Status::Terminee);
check('immutabilité', $t->status === Status::AFaire);

// Modification
$edited = $done->with(['title' => 'Nouveau']);
check('with conserve le statut', $edited->title === 'Nouveau' && $edited->status === Status::Terminee);
check('with revalide', throws(fn() => $t->with(['title' => '']), ValidationException::class));

// Périodicité
$rec = Task::create($valid + ['due_date' => '2026-01-31', 'recurrence' => 'mensuelle', 'duration_minutes' => 30]);
$next = $rec->complete()->nextOccurrence();
check('occurrence suivante : fin de mois', $next?->dueDate?->format('Y-m-d') === '2026-02-28' && $next->status === Status::AFaire);
check('occurrence conserve les champs', $next?->durationMinutes === 30 && $next->id === null);
check('pas d\'occurrence sans périodicité', $t->nextOccurrence() === null);
check('quotidienne', Recurrence::Quotidienne->next(new DateTimeImmutable('2026-12-31'))->format('Y-m-d') === '2027-01-01');
check('hebdomadaire', Recurrence::Hebdomadaire->next(new DateTimeImmutable('2026-10-11'))->format('Y-m-d') === '2026-10-18');
check('annuelle bissextile', Recurrence::Annuelle->next(new DateTimeImmutable('2024-02-29'))->format('Y-m-d') === '2025-02-28');

// Projet et conclusions
$pc = Task::create($valid + ['project' => '  Alpha ', 'conclusions' => 'Livré']);
check('projet et conclusions', $pc->project === 'Alpha' && $pc->conclusions === 'Livré');
check('projet vide -> null', Task::create($valid + ['project' => ' '])->project === null);
check('projet trop long refusé', throws(fn() => Task::create($valid + ['project' => str_repeat('x', 256)]), ValidationException::class));
check('conclusions non texte refusées', throws(fn() => Task::create($valid + ['conclusions' => 12]), ValidationException::class));
check('conclusions conservées par complete()', $pc->start()->complete()->conclusions === 'Livré');

// Hydratation
$row = Task::fromRow(['id' => '7', 'title' => 'A', 'category' => 'Perso', 'due_date' => '2026-05-01', 'recurrence' => null,
    'duration_minutes' => null, 'description' => null, 'project' => 'Alpha', 'conclusions' => null, 'status' => 'en_cours', 'completed_at' => null]);
check('fromRow', $row->id === 7 && $row->project === 'Alpha' && $row->status === Status::EnCours && $row->dueDate?->format('Y-m-d') === '2026-05-01');

exit($failures === 0 ? 0 : 1);
