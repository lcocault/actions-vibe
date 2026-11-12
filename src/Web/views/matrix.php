<?php
use App\Task\Eisenhower;
use App\Web\View;
?>
<h1>Matrice d'Eisenhower globale</h1>
<p class="muted">Toutes les actions non terminées, tous projets confondus. Les retards sont affichés séparément ; sans date butée, les actions sont dans « Au-delà ».</p>
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
                        <a class="count" href="/matrix?importance=<?= urlencode($importance) ?>&amp;urgency=<?= urlencode($urgency) ?>#selection"
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
    <a class="btn-small" href="/matrix">Effacer le filtre</a>
</h2>
<div class="table-wrap">
<table class="task-list">
    <thead><tr><th>Titre</th><th class="opt">Projet</th><th class="opt">Date butée</th><th class="opt">Statut</th></tr></thead>
    <tbody>
    <?php foreach ($selection['tasks'] as $task): ?>
        <tr>
            <td><a href="/tasks/<?= (int) $task['id'] ?>"><?= View::h($task['title']) ?></a></td>
            <td class="opt"><?= View::h($task['project'] ?? '') ?></td>
            <td class="opt <?= View::isOverdue($task) ? 'overdue' : '' ?>"><?= View::h(View::date($task['due_date'])) ?></td>
            <td class="opt"><?php require __DIR__ . '/_badge.php'; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
