<?php
use App\Task\Eisenhower;
use App\Web\View;
?>
<p><a href="/projects">← Retour aux projets</a></p>
<h1><?= View::h($project['name']) ?> <span class="cat cat-<?= View::h(strtolower($project['category'])) ?>"><?= View::h($project['category']) ?></span></h1>
<div class="actions">
    <a class="btn-small" href="/projects/<?= (int) $project['id'] ?>/edit">Renommer / modifier</a>
</div>

<h2>Synthèse des actions</h2>
<?php if ($tasks === []): ?>
    <p class="empty">Aucune action dans ce projet. <a href="/tasks/new">Créer une tâche</a>.</p>
<?php else: ?>
<div class="summary">
    <?php foreach (View::STATUS_LABELS as $status => $label): ?>
        <div class="stat st-<?= View::h($status) ?>"><strong><?= (int) $summary[$status] ?></strong><span><?= View::h($label) ?></span></div>
    <?php endforeach; ?>
    <div class="stat st-bloquee"><strong><?= (int) $blocked ?></strong><span>Bloquées</span></div>
    <div class="stat"><strong><?= count($tasks) ?></strong><span>Total</span></div>
</div>

<h2>Matrice d'Eisenhower</h2>
<p class="muted">Actions non terminées, selon l'importance et l'échéance (les retards sont affichés séparément, sans date butée dans « au-delà »).</p>
<div class="table-wrap">
<table class="matrix">
    <thead>
        <tr><th></th><?php foreach (Eisenhower::URGENCIES as $label): ?><th><?= View::h($label) ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($matrix as $importance => $cells): ?>
        <tr>
            <th><span class="imp imp-<?= View::h(strtolower($importance)) ?>"><?= View::h($importance) ?></span></th>
            <?php foreach ($cells as $urgency => $cell): ?>
                <td class="cell cell-<?= View::h($importance === 'Critique' && in_array($urgency, ['late', 'week'], true) ? 'hot' : 'std') ?>">
                    <?php if ($cell !== []): ?>
                        <a class="count" href="/projects/<?= (int) $project['id'] ?>?importance=<?= urlencode($importance) ?>&amp;urgency=<?= urlencode($urgency) ?>#selection"
                           title="Voir les actions"><?= count($cell) ?></a>
                    <?php else: ?>
                        <span class="muted">0</span>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($selection !== null): ?>
<h2 id="selection">
    <span class="imp imp-<?= View::h(strtolower($selection['importance'])) ?>"><?= View::h($selection['importance']) ?></span>
    · <?= View::h(Eisenhower::URGENCIES[$selection['urgency']]) ?>
    <a class="btn-small" href="/projects/<?= (int) $project['id'] ?>">Effacer le filtre</a>
</h2>
<div class="table-wrap">
<table class="task-list">
    <thead><tr><th>Titre</th><th class="opt">Date butée</th><th class="opt">Statut</th></tr></thead>
    <tbody>
    <?php foreach ($selection['tasks'] as $task): ?>
        <tr>
            <td><a href="/tasks/<?= (int) $task['id'] ?>"><?= View::h($task['title']) ?></a></td>
            <td class="opt <?= View::isOverdue($task) ? 'overdue' : '' ?>"><?= View::h(View::date($task['due_date'])) ?></td>
            <td class="opt"><?php require __DIR__ . '/_badge.php'; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
