<?php
/**
 * Hook ai_after_js_include
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

 if (!defined('S2_ROOT')) {
     die;
}

echo '<script type="text/javascript" src="'.S2_PATH.'/_extensions/s2_counter'.'/highcharts/highstock.js"></script>'."\n";
echo '<script type="text/javascript" src="'.S2_PATH.'/_extensions/s2_counter'.'/draw_chart.js"></script>'."\n";
