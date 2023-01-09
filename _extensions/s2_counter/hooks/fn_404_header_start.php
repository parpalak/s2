<?php
/**
 * Hook fn_404_header_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

 if (!defined('S2_ROOT')) {
     die;
}

global $s2_counter_skip;
$s2_counter_skip = true;
