<?php
/**
 * @var array $saved_queries
 * @var array $saved_queries2
 */

?>
		<div id="debug">
			<table>
				<thead>
					<tr>
						<th class="tcl" scope="col">Time,&nbsp;ms</th>
						<th class="tcr" scope="col">Query</th>
					</tr>
				</thead>
				<tbody>
<?php

foreach ($saved_queries2 as $cur_query) {
    $saved_queries[] = [$cur_query['statement'], $cur_query['time']];
}

$query_time_total = 0.0;
$maximumTime = 0.0;
foreach ($saved_queries as [,$time]) {
    $query_time_total += $time;
    $maximumTime = max($maximumTime, $time);
}

foreach ($saved_queries as $cur_query) {

?>
					<tr>
						<td class="tcl" style="vertical-align: top; user-select: none; background: 0 0 /auto 24px linear-gradient(to right, rgba(0,0,0,0.13) <?= 100.0*$cur_query[1]/$maximumTime ?>%, rgba(0,0,0,0) <?= 100.0*$cur_query[1]/$maximumTime ?>%) no-repeat;"><?php echo (($cur_query[1] != 0) ? Lang::number_format($cur_query[1]*1000, true) : '&#160;') ?></td>
                        <td valign="top" class="tcr"><code><?php echo s2_htmlencode($cur_query[0]) ?></code></td>
					</tr>
<?php

}

?>
					<tr class="totals">
						<td class="tcl"><em><?php echo Lang::number_format($query_time_total*1000, true) ?></em></td>
						<td class="tcr"><em>Total query time</em></td>
					</tr>
				</tbody>
			</table>
			Peak memory = <?php echo Lang::number_format(memory_get_peak_usage()); ?>, memory = <?php echo Lang::number_format(memory_get_usage()); ?>
		</div>
<?php
