<?php use App\Web\View; ?>
<h1>Projets</h1>
<p class="muted">La catégorie d'un projet (Pro ou Perso) s'applique à toutes ses tâches.</p>
<p><a class="btn" href="/projects/new">+ Nouveau projet</a></p>
<?php if ($projects === []): ?>
    <p class="empty">Aucun projet configuré.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Nom</th><th>Catégorie</th><th>Tâches</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
        <tr>
            <td><?= View::h($p['name']) ?></td>
            <td><span class="cat cat-<?= View::h(strtolower($p['category'])) ?>"><?= View::h($p['category']) ?></span></td>
            <td><?= (int) $p['task_count'] ?></td>
            <td class="actions">
                <a class="btn-small" href="/projects/<?= (int) $p['id'] ?>/edit">Modifier</a>
                <form method="post" action="/projects/<?= (int) $p['id'] ?>/delete" class="inline" onsubmit="return confirm('Supprimer ce projet ?');">
                    <input type="hidden" name="_csrf" value="<?= View::h($csrf) ?>">
                    <button type="submit" class="btn-small danger">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
