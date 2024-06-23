<?php
/** @var callable $trans */
/** @var int|string $articles_num */
/** @var int|string $comments_num */
?>
<div class="stat-item">
    <h3><?= $trans('Already published') ?></h3>
    <?= $trans('Articles num', ['{{ articles }}' => $articles_num, '%count%' => $articles_num]) ?><br>
    <?= $trans('Comments num', ['{{ comments }}' => $comments_num, '%count%' => $comments_num]) ?>
</div>
