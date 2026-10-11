<?php

declare(strict_types=1);

namespace App\Task;

use InvalidArgumentException;

/**
 * Graphe des dépendances : l'arête (tâche → prérequis) signifie « la tâche requiert le prérequis ».
 * Structure en mémoire, indépendante de la base.
 */
final class DependencyGraph
{
    /** @var array<int,array<int,true>> tâche => prérequis directs */
    private array $requires = [];

    /** @param iterable<array{0:int,1:int}> $edges paires [tâche, prérequis] */
    public function __construct(iterable $edges = [])
    {
        foreach ($edges as [$taskId, $requiredId]) {
            $this->requires[$taskId][$requiredId] = true;
        }
    }

    /**
     * Ajoute une dépendance.
     *
     * @throws InvalidArgumentException auto-dépendance
     * @throws CycleException si l'arête créerait un cycle (le graphe reste inchangé)
     */
    public function add(int $taskId, int $requiredId): void
    {
        $cycle = $this->cycleIfAdded($taskId, $requiredId);
        if ($cycle !== null) {
            throw new CycleException($cycle);
        }
        $this->requires[$taskId][$requiredId] = true;
    }

    public function remove(int $taskId, int $requiredId): void
    {
        unset($this->requires[$taskId][$requiredId]);
    }

    /** @return list<int> prérequis directs */
    public function requirementsOf(int $taskId): array
    {
        return array_keys($this->requires[$taskId] ?? []);
    }

    /** @return list<int> prérequis directs et indirects */
    public function allRequirementsOf(int $taskId): array
    {
        $seen = [];
        $stack = $this->requirementsOf($taskId);
        while ($stack) {
            $id = array_pop($stack);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            array_push($stack, ...$this->requirementsOf($id));
        }
        return array_keys($seen);
    }

    /**
     * Cycle qu'introduirait la dépendance, sous forme de chemin fermé [tâche, prérequis, ..., tâche],
     * ou null si elle est sans danger.
     *
     * @return list<int>|null
     * @throws InvalidArgumentException auto-dépendance
     */
    public function cycleIfAdded(int $taskId, int $requiredId): ?array
    {
        if ($taskId === $requiredId) {
            throw new InvalidArgumentException('Une tâche ne peut pas dépendre d\'elle-même.');
        }
        // Cycle ssi `taskId` est déjà atteignable depuis `requiredId`.
        $path = $this->pathTo($requiredId, $taskId);
        return $path === null ? null : [$taskId, ...$path];
    }

    /**
     * Premier cycle trouvé dans le graphe (chemin fermé), ou null s'il est acyclique.
     *
     * @return list<int>|null
     */
    public function findCycle(): ?array
    {
        $state = []; // 1 = en cours de visite, 2 = terminé
        $nodes = array_keys($this->requires);
        foreach ($nodes as $start) {
            if (isset($state[$start])) {
                continue;
            }
            $stack = [$start];
            $path = [$start];
            $iters = [$this->requirementsOf($start)];
            $state[$start] = 1;
            while ($stack) {
                $top = count($stack) - 1;
                $next = array_pop($iters[$top]);
                if ($next === null) {
                    $state[$stack[$top]] = 2;
                    array_pop($stack);
                    array_pop($path);
                    array_pop($iters);
                    continue;
                }
                $s = $state[$next] ?? 0;
                if ($s === 1) {
                    $from = array_search($next, $path, true);
                    return [...array_slice($path, (int) $from), $next];
                }
                if ($s === 0) {
                    $state[$next] = 1;
                    $stack[] = $next;
                    $path[] = $next;
                    $iters[] = $this->requirementsOf($next);
                }
            }
        }
        return null;
    }

    /**
     * Ordre d'exécution : chaque tâche apparaît après ses prérequis.
     *
     * @return list<int>
     * @throws CycleException si le graphe est cyclique
     */
    public function executionOrder(): array
    {
        $cycle = $this->findCycle();
        if ($cycle !== null) {
            throw new CycleException($cycle);
        }
        $order = [];
        $done = [];
        $nodes = [];
        foreach ($this->requires as $task => $reqs) {
            $nodes[$task] = true;
            foreach ($reqs as $r => $_) {
                $nodes[$r] = true;
            }
        }
        foreach (array_keys($nodes) as $start) {
            $stack = [[$start, false]];
            while ($stack) {
                [$id, $expanded] = array_pop($stack);
                if ($expanded) {
                    $order[] = $id;
                    continue;
                }
                if (isset($done[$id])) {
                    continue;
                }
                $done[$id] = true;
                $stack[] = [$id, true];
                foreach ($this->requirementsOf($id) as $r) {
                    if (!isset($done[$r])) {
                        $stack[] = [$r, false];
                    }
                }
            }
        }
        return $order;
    }

    /**
     * Chemin [from, ..., to] en suivant les prérequis, ou null.
     *
     * @return list<int>|null
     */
    private function pathTo(int $from, int $to): ?array
    {
        $parent = [$from => null];
        $queue = [$from];
        for ($i = 0; $i < count($queue); $i++) {
            $id = $queue[$i];
            if ($id === $to) {
                $path = [];
                for ($n = $id; $n !== null; $n = $parent[$n]) {
                    $path[] = $n;
                }
                return array_reverse($path);
            }
            foreach ($this->requirementsOf($id) as $r) {
                if (!array_key_exists($r, $parent)) {
                    $parent[$r] = $id;
                    $queue[] = $r;
                }
            }
        }
        return null;
    }
}
