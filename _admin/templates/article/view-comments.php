<?php

declare(strict_types=1);

/** @var string $value From database, normalized and converted to view format */
/** @var string $label Calculated SQL expression for the label */
/** @var array $linkParams Additional parameters for the link when $linkToAction is set */
?>
<?php if ($linkParams !== null): ?>
    <a href="?<?= http_build_query($linkParams) ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
<?php elseif ($value === null): ?>
    <span class="null">null</span>
<?php else: ?>
    <?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>
<?php endif; ?>
<?php if ($row['column_commented'] === false): ?>
    <i class="icon icon-comments-disabled"></i>
<?php endif; ?>
