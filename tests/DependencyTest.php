<?php

declare(strict_types=1);

// Tests sans dépendance : php tests/DependencyTest.php (code de sortie 1 en cas d'échec).

require __DIR__ . '/../src/autoload.php';

use App\Task\CycleException;
use App\Task\DependencyGraph;

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

$g = new DependencyGraph();
$g->add(1, 2);
$g->add(2, 3);
check('chaîne acceptée', $g->requirementsOf(1) === [2]);
$all = $g->allRequirementsOf(1);
sort($all);
check('prérequis indirects', $all === [2, 3]);
check('auto-dépendance refusée', throws(fn() => $g->add(4, 4), InvalidArgumentException::class));
check('cycle direct refusé', throws(fn() => $g->add(2, 1), CycleException::class));
try {
    $g->add(3, 1);
    check('cycle indirect refusé', false);
} catch (CycleException $e) {
    check('cycle indirect refusé', $e->path === [3, 1, 2, 3]);
}
check('graphe inchangé après refus', $g->requirementsOf(3) === []);
$g->add(1, 3);
check('losange sans cycle', $g->cycleIfAdded(1, 3) === null && $g->findCycle() === null);
$g->remove(2, 3);
$g->add(3, 2);
check('ajout permis après retrait', $g->requirementsOf(3) === [2]);

$order = $g->executionOrder();
check('ordre topologique', array_search(2, $order) < array_search(3, $order) && array_search(3, $order) < array_search(1, $order));

$bad = new DependencyGraph([[1, 2], [2, 3], [3, 1], [5, 1]]);
$cycle = $bad->findCycle();
check('détection de cycle existant', $cycle !== null && $cycle[0] === end($cycle) && count($cycle) === 4);
check('ordre refusé si cycle', throws(fn() => $bad->executionOrder(), CycleException::class));
check('graphe vide acyclique', (new DependencyGraph())->findCycle() === null);

exit($failures === 0 ? 0 : 1);
