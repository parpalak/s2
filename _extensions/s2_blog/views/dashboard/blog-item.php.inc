<?php
/** @var callable $trans */
/** @var int|string $posts_num */
/** @var int|string $comments_num */
?>
<div class="stat-item">
    <h3><?= $trans('Published in the blog') ?></h3>
    <?= $trans('Posts num', ['{{ posts }}' => $posts_num, '%count%' => $posts_num]) ?><br>
    <?= $trans('Comments num', ['{{ comments }}' => $comments_num, '%count%' => $comments_num]) ?>
</div>
