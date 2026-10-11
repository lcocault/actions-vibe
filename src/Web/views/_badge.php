<?php use App\Web\View; ?>
<span class="badge st-<?= View::h($task['status']) ?>"><?= View::h(View::STATUS_LABELS[$task['status']]) ?></span><?php if (View::isBlocked($task)): ?> <span class="badge st-bloquee" title="Des prérequis ne sont pas terminés">Bloquée</span><?php endif; ?>
