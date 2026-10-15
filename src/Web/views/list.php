<?php use App\Web\View; ?>
<h1>Actions</h1>
<form method="get" action="/" class="filters">
    <label>Catégorie
        <select name="category">
            <option value="">Toutes</option>
            <?php foreach (['Pro', 'Perso'] as $c): ?>
                <option value="<?= $c ?>"<?= ($filters['category'] ?? '') === $c ? ' selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Statut
        <select name="status">
            <option value="">Tous</option>
            <?php foreach (View::STATUS_LABELS as $value => $label): ?>
                <option value="<?= $value ?>"<?= ($filters['status'] ?? '') === $value ? ' selected' : '' ?>><?= View::h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filtrer</button>
    <?php if ($filters): ?><a href="/">Réinitialiser</a><?php endif; ?>
</form>

<?php if ($tasks === []): ?>
    <p class="empty">Aucune tâche<?= $filters ? ' pour ces filtres' : '' ?>. <a href="/tasks/new">Créer la première</a>.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Titre</th><th>Projet</th><th>Catégorie</th><th>Date butée</th><th>Durée</th><th>Statut</th><th>Prérequis</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($tasks as $task): ?>
        <tr>
            <td><a href="/tasks/<?= (int) $task['id'] ?>"><?= View::h($task['title']) ?></a></td>
            <td><?= View::h($task['project'] ?? '') ?></td>
            <td><span class="cat cat-<?= View::h(strtolower($task['category'])) ?>"><?= View::h($task['category']) ?></span></td>
            <td class="<?= View::isOverdue($task) ? 'overdue' : '' ?>">
                <?= View::h(View::date($task['due_date'])) ?>
                <?php if ($task['recurrence']): ?><small title="Périodicité">↻ <?= View::h(View::RECURRENCE_LABELS[$task['recurrence']]) ?></small><?php endif; ?>
            </td>
            <td><?= View::h(View::duration($task['duration_minutes'])) ?></td>
            <td><?php require __DIR__ . '/_badge.php'; ?></td>
            <td>
                <?php foreach ($task['requirements'] as $req): ?>
                    <a class="req st-<?= View::h($req['status']) ?>" href="/tasks/<?= (int) $req['id'] ?>"><?= View::h($req['title']) ?></a>
                <?php endforeach; ?>
            </td>
            <td class="actions">
                <?php $back = 'list'; require __DIR__ . '/_status_form.php'; ?>
                <a class="btn-small" href="/tasks/<?= (int) $task['id'] ?>/edit">Modifier</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
