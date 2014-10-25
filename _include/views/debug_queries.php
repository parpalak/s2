<?php

/**
 * @var array $saved_queries
 */

?>
		<div id="debug">
			<table>
				<thead>
					<tr>
						<th class="tcl" scope="col">Time, ms</th>
						<th class="tcr" scope="col">Query</th>
					</tr>
				</thead>
				<tbody>
<?php

$query_time_total = 0.0;
foreach ($saved_queries as $cur_query)
{
	$query_time_total += $cur_query[1];

?>
					<tr>
						<td class="tcl"><?php echo (($cur_query[1] != 0) ? Lang::number_format($cur_query[1]*1000, false) : '&#160;') ?></td>
						<td class="tcr"><?php echo s2_htmlencode($cur_query[0]) ?></td>
					</tr>
<?php

}

?>
					<tr class="totals">
						<td class="tcl"><em><?php echo Lang::number_format($query_time_total*1000, false) ?></em></td>
						<td class="tcr"><em>Total query time</em></td>
					</tr>
				</tbody>
			</table>
			Peak memory = <?php echo Lang::number_format(memory_get_peak_usage()); ?>, memory = <?php echo Lang::number_format(memory_get_usage()); ?>
		</div>
<?php
