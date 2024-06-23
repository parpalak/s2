<?php
/**
 * @var callable $trans
 * @var callable $friendlyFilesize
 * @var ?string  $dbSize
 * @var ?string  $dbRecords
 * @var string   $dbType
 * @var string   $dbVersion
 */
?>
<div class="stat-item">
    <h3><?php echo $trans('Database'); ?></h3>
    <?php echo $dbType; ?>:
    <?php echo $dbVersion; ?><br>
    <?php if ($dbSize !== null): ?>
        <?php echo $trans('Size', ['{{ value }}' => $friendlyFilesize($dbSize)]); ?><br>
    <?php endif; ?>
    <?php if ($dbRecords !== null): ?>
        <?php echo $trans('Rows', ['{{ rows }}' => $dbRecords, '%count%' => $dbRecords]); ?><br>
    <?php endif; ?>
</div>
