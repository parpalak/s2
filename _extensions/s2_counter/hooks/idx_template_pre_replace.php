<?php
/**
 * Hook idx_template_pre_replace
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (!isset($s2_counter_skip) || !$s2_counter_skip)
{
	if (!defined('S2_COUNTER_FUNCTIONS_LOADED'))
		include S2_ROOT.'/_extensions/s2_counter/functions.php';

	s2_counter_process(S2_ROOT.'/_extensions/s2_counter');
}
$replace['<!-- s2_counter_img -->'] = '<img class="s2_counter" src="'.S2_PATH.'/_extensions/s2_counter/counter.php" width="88" height="31" />';
