<?php
use App\Web\View;
$v = static fn(string $key): string => (string) ($values[$key] ?? '');
$selected = array_map('intval', is_array($values['requirements'] ?? null) ? $values['requirements'] : []);
$action = $task === null ? '/tasks' : '/tasks/' . (int) $task['id'];
?>
<p><a href="/">← Retour à la liste</a></p>
<h1><?= View::h($title) ?></h1>
<?php if (isset($errors['_'])): ?><p class="flash flash-error"><?= View::h($errors['_']) ?></p><?php endif; ?>
<form method="post" action="<?= View::h($action) ?>" class="task-form" novalidate>
    <input type="hidden" name="_csrf" value="<?= View::h($csrf) ?>">

    <label>Titre *
        <input type="text" name="title" maxlength="255" required value="<?= View::h($v('title')) ?>">
        <?php if (isset($errors['title'])): ?><span class="error"><?= View::h($errors['title']) ?></span><?php endif; ?>
    </label>

    <label>Projet <small>(<a href="/projects">gérer la liste</a>)</small>
        <select name="project">
            <option value="">Aucun</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= View::h($p['name']) ?>"<?= $v('project') === $p['name'] ? ' selected' : '' ?>><?= View::h($p['name']) ?> (<?= View::h($p['category']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['project'])): ?><span class="error"><?= View::h($errors['project']) ?></span><?php endif; ?>
    </label>

    <label>Catégorie * <small>(ignorée si un projet est choisi : c'est celle du projet)</small>
        <select name="category" required>
            <?php foreach (['Pro', 'Perso'] as $c): ?>
                <option value="<?= $c ?>"<?= $v('category') === $c ? ' selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category'])): ?><span class="error"><?= View::h($errors['category']) ?></span><?php endif; ?>
    </label>

    <label>Importance
        <select name="importance">
            <?php foreach (['Mineure', 'Majeure', 'Critique'] as $i): ?>
                <option value="<?= $i ?>"<?= ($v('importance') ?: 'Mineure') === $i ? ' selected' : '' ?>><?= $i ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['importance'])): ?><span class="error"><?= View::h($errors['importance']) ?></span><?php endif; ?>
    </label>

    <label>Date butée
        <input type="date" name="due_date" value="<?= View::h($v('due_date')) ?>">
        <?php if (isset($errors['due_date'])): ?><span class="error"><?= View::h($errors['due_date']) ?></span><?php endif; ?>
    </label>

    <label>Périodicité <small>(exige une date butée)</small>
        <select name="recurrence">
            <option value="">Aucune</option>
            <?php foreach (View::RECURRENCE_LABELS as $value => $label): ?>
                <option value="<?= $value ?>"<?= $v('recurrence') === $value ? ' selected' : '' ?>><?= View::h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['recurrence'])): ?><span class="error"><?= View::h($errors['recurrence']) ?></span><?php endif; ?>
    </label>

    <label>Durée estimée (minutes)
        <input type="number" name="duration_minutes" min="1" step="1" value="<?= View::h($v('duration_minutes')) ?>">
        <?php if (isset($errors['duration_minutes'])): ?><span class="error"><?= View::h($errors['duration_minutes']) ?></span><?php endif; ?>
    </label>

    <label>Description
        <textarea name="description" rows="5"><?= View::h($v('description')) ?></textarea>
        <?php if (isset($errors['description'])): ?><span class="error"><?= View::h($errors['description']) ?></span><?php endif; ?>
    </label>

    <label>Conclusions
        <textarea name="conclusions" rows="5"><?= View::h($v('conclusions')) ?></textarea>
        <?php if (isset($errors['conclusions'])): ?><span class="error"><?= View::h($errors['conclusions']) ?></span><?php endif; ?>
    </label>

    <fieldset>
        <legend>Actions requises</legend>
        <?php if ($candidates === []): ?>
            <p class="muted">Aucune autre tâche disponible.</p>
        <?php endif; ?>
        <?php foreach ($candidates as $c): ?>
            <label class="check">
                <input type="checkbox" name="requirements[]" value="<?= (int) $c['id'] ?>"<?= in_array($c['id'], $selected, true) ? ' checked' : '' ?>>
                <?= View::h($c['title']) ?> <small class="muted">(<?= View::h(View::STATUS_LABELS[$c['status']]) ?>)</small>
            </label>
        <?php endforeach; ?>
        <?php if (isset($errors['requirements'])): ?><span class="error"><?= View::h($errors['requirements']) ?></span><?php endif; ?>
    </fieldset>

    <div class="actions">
        <button type="submit"><?= $task === null ? 'Créer' : 'Enregistrer' ?></button>
        <a href="<?= $task === null ? '/' : '/tasks/' . (int) $task['id'] ?>">Annuler</a>
    </div>
</form>
