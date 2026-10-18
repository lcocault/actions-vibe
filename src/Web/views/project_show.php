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
<p class="muted">Actions non terminées, selon l'importance et l'échéance (retards inclus dans « moins d'une semaine », sans date butée dans « au-delà »).</p>
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
                <td class="cell cell-<?= View::h($importance === 'Critique' && $urgency === 'week' ? 'hot' : 'std') ?>">
                    <?php foreach ($cell as $t): ?>
                        <a class="req" href="/tasks/<?= (int) $t['id'] ?>"><?= View::h($t['title']) ?>
                            <?php if ($t['due_date']): ?><small class="<?= View::isOverdue($t) ? 'overdue' : '' ?>"><?= View::h(View::date($t['due_date'])) ?></small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
