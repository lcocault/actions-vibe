<?php use App\Web\View; ?>
<p><a href="/">← Retour à la liste</a></p>
<h1><?= View::h($task['title']) ?> <?php require __DIR__ . '/_badge.php'; ?></h1>
<dl class="details">
    <dt>Catégorie</dt><dd><span class="cat cat-<?= View::h(strtolower($task['category'])) ?>"><?= View::h($task['category']) ?></span></dd>
    <dt>Date butée</dt><dd class="<?= View::isOverdue($task) ? 'overdue' : '' ?>"><?= $task['due_date'] ? View::h(View::date($task['due_date'])) : '—' ?></dd>
    <dt>Périodicité</dt><dd><?= $task['recurrence'] ? View::h(View::RECURRENCE_LABELS[$task['recurrence']]) : '—' ?></dd>
    <dt>Durée estimée</dt><dd><?= $task['duration_minutes'] ? View::h(View::duration($task['duration_minutes'])) : '—' ?></dd>
    <?php if ($task['completed_at']): ?>
        <dt>Terminée le</dt><dd><?= View::h(View::date($task['completed_at'])) ?></dd>
    <?php endif; ?>
    <dt>Description</dt><dd><?= $task['description'] !== null ? nl2br(View::h($task['description'])) : '—' ?></dd>
    <dt>Actions requises</dt>
    <dd>
        <?php if ($task['requirements'] === []): ?>
            Aucune
        <?php else: ?>
            <ul>
            <?php foreach ($task['requirements'] as $req): ?>
                <li><a href="/tasks/<?= (int) $req['id'] ?>"><?= View::h($req['title']) ?></a>
                    <span class="badge st-<?= View::h($req['status']) ?>"><?= View::h(View::STATUS_LABELS[$req['status']]) ?></span></li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </dd>
</dl>
<div class="actions">
    <?php $back = 'show'; require __DIR__ . '/_status_form.php'; ?>
    <a class="btn-small" href="/tasks/<?= (int) $task['id'] ?>/edit">Modifier</a>
    <form method="post" action="/tasks/<?= (int) $task['id'] ?>/delete" class="inline" onsubmit="return confirm('Supprimer cette tâche ?');">
        <input type="hidden" name="_csrf" value="<?= View::h($csrf) ?>">
        <button type="submit" class="btn-small danger">Supprimer</button>
    </form>
</div>
