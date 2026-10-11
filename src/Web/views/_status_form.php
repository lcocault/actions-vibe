<?php
use App\Web\View;
/** Boutons de transition de statut. Variables : $task, $csrf, $back */
$transitions = match ($task['status']) {
    'a_faire' => [['en_cours', 'Démarrer'], ['terminee', 'Terminer']],
    'en_cours' => [['terminee', 'Terminer'], ['a_faire', 'Abandonner']],
    default => [['a_faire', 'Rouvrir']],
};
?>
<?php foreach ($transitions as [$target, $label]): ?>
    <form method="post" action="/tasks/<?= (int) $task['id'] ?>/status" class="inline">
        <input type="hidden" name="_csrf" value="<?= View::h($csrf) ?>">
        <input type="hidden" name="status" value="<?= View::h($target) ?>">
        <input type="hidden" name="back" value="<?= View::h($back) ?>">
        <button type="submit" class="btn-small"><?= View::h($label) ?></button>
    </form>
<?php endforeach; ?>
