<?php
/** @var array $row */
/** @var string $value From database, normalized and converted to view format */
/** @var string $label Calculated SQL expression for the label */
/** @var array $linkParams Additional parameters for the link when $linkToAction is set */

$escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

if (!$row['column_published']) {
    $escapedLabel = '<s>' . $escapedLabel . '</s>';
}

if ($linkParams !== null): ?>
    <a href="?<?= http_build_query($linkParams) ?>"><?= $escapedLabel ?></a>
<?php elseif ($value === null): ?>
    <span class="null">null</span>
<?php else: ?>
    <?= $escapedLabel ?>
<?php
endif;
