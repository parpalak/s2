<?php
/**
 * @var callable $trans
 * @var string   $basePath
 * @var bool     $dirIsWritable
 */

if (!$dirIsWritable) {
    echo '<div class="info-box"><p>' . $trans('Data folder not writable', ['{{ dir }}' => $basePath . '/_extensions/s2_counter/data/']) . '</p></div>';
}
?>
<script src="<?php echo $basePath; ?>/_extensions/s2_counter/highcharts/highstock.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_counter/add-charts.js"></script>
<p id="s2_counter_hits" style="height: 400px;"></p>
<p id="s2_counter_rss" style="height: 400px;"></p>
