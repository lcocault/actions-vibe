<?php
use App\Web\View;
$v = static fn(string $key): string => (string) ($values[$key] ?? '');
$action = $project === null ? '/projects' : '/projects/' . (int) $project['id'];
?>
<p><a href="/projects">← Retour aux projets</a></p>
<h1><?= View::h($title) ?></h1>
<?php if (isset($errors['_'])): ?><p class="flash flash-error"><?= View::h($errors['_']) ?></p><?php endif; ?>
<form method="post" action="<?= View::h($action) ?>" class="task-form" novalidate>
    <input type="hidden" name="_csrf" value="<?= View::h($csrf) ?>">

    <label>Nom *
        <input type="text" name="name" maxlength="255" required value="<?= View::h($v('name')) ?>">
        <?php if (isset($errors['name'])): ?><span class="error"><?= View::h($errors['name']) ?></span><?php endif; ?>
    </label>

    <label>Catégorie *
        <select name="category" required>
            <?php foreach (['Pro', 'Perso'] as $c): ?>
                <option value="<?= $c ?>"<?= $v('category') === $c ? ' selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category'])): ?><span class="error"><?= View::h($errors['category']) ?></span><?php endif; ?>
    </label>

    <div class="actions">
        <button type="submit"><?= $project === null ? 'Créer' : 'Enregistrer' ?></button>
        <a href="/projects">Annuler</a>
    </div>
</form>
