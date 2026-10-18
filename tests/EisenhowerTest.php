<?php

declare(strict_types=1);

// Tests sans dépendance : php tests/EisenhowerTest.php (code de sortie 1 en cas d'échec).

require __DIR__ . '/../src/autoload.php';

use App\Task\Eisenhower;

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
}

$today = new DateTimeImmutable('2026-10-18 15:30');
$t = static fn(?string $due, string $imp = 'Majeure', string $st = 'a_faire'): array => ['due_date' => $due, 'importance' => $imp, 'status' => $st];

check('retard => semaine', Eisenhower::urgency($t('2026-10-01'), $today) === 'week');
check('aujourd\'hui => semaine', Eisenhower::urgency($t('2026-10-18'), $today) === 'week');
check('+6 jours => semaine', Eisenhower::urgency($t('2026-10-24'), $today) === 'week');
check('+7 jours => mois', Eisenhower::urgency($t('2026-10-25'), $today) === 'month');
check('+29 jours => mois', Eisenhower::urgency($t('2026-11-16'), $today) === 'month');
check('+30 jours => au-delà', Eisenhower::urgency($t('2026-11-17'), $today) === 'later');
check('sans date => au-delà', Eisenhower::urgency($t(null), $today) === 'later');

$m = Eisenhower::matrix([$t('2026-10-20', 'Critique'), $t(null, 'Mineure'), $t('2026-10-20', 'Critique', 'terminee')], $today);
check('9 cellules', count($m) === 3 && count($m['Critique']) === 3);
check('critique/semaine', count($m['Critique']['week']) === 1);
check('mineure/au-delà', count($m['Mineure']['later']) === 1);
check('terminées exclues', count($m['Critique']['week']) === 1 && $m['Majeure']['week'] === []);

exit($failures === 0 ? 0 : 1);
