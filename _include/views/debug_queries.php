<?php
/**
 * @var array $saved_queries
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

$query_time_total = 0.0;
$maximumTime = 0.0;
foreach ($saved_queries as $cur_query) {
    $query_time_total += $cur_query['time'];
    $maximumTime = max($maximumTime, $cur_query['time']);
}

foreach ($saved_queries as $cur_query) {

?>
					<tr>
						<td class="tcl" style="vertical-align: top; user-select: none; background: 0 0 /auto 100% linear-gradient(to right, rgba(0,0,0,0.13) <?= 100.0*$cur_query['time']/$maximumTime ?>%, rgba(0,0,0,0) <?= 100.0*$cur_query['time']/$maximumTime ?>%) no-repeat;"><?php echo (($cur_query['time'] != 0) ? Lang::number_format($cur_query['time']*1000, true) : '&#160;') ?></td>
                        <td valign="top" class="tcr"><code><?php echo s2_htmlencode($cur_query['statement']) ?></code></td>
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
			Peak memory = <?php echo Lang::number_format(memory_get_peak_usage()); ?>,
            memory = <?php echo Lang::number_format(memory_get_usage()); ?>,
            total queries = <?php echo count($saved_queries); ?>
		</div>
<?php
