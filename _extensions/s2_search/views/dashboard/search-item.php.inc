<?php
/** @var callable $trans */
/** @var callable $friendlyFilesize */
/** @var int|string $rows */
/** @var int|string $bytes */
/** @var string $basePath */
?>
<div class="stat-item">
    <h3><?= $trans('Search index') ?></h3>
    <?php if ($rows === 0): ?>
        <span class="icon icon-warning"></span> <?= $trans('Indexing required') ?>
    <?php else: ?>
        <?php echo $trans('Size', ['{{ value }}' => $friendlyFilesize($bytes)]); ?><br>
        <?php echo $trans('Rows', ['{{ rows }}' => $rows, '%count%' => $rows]); ?><br>
    <?php endif; ?>
    <a href="#" onclick="return s2_search.reindex();" class="js" title="<?= $trans('Reindex title') ?>">
        <?= $trans('Reindex') ?></a><span id="s2_search_progress"></span>
</div>
<script src="<?php echo $basePath; ?>/_admin/js/ajax.js"></script>
<script>
    var sUrl = '<?php echo $basePath; ?>/_admin/ajax.php?';
</script>
<script src="<?= $basePath ?>/_extensions/s2_search/index-manager.js"></script>
