<?php
/**
 * Hook fn_get_counters_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_search', function ()
{
	if (file_exists(S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_search'.'/lang/English.php';
});
$s2_search_reindex = '<a href="#" onclick="return s2_search.reindex();" class="js" title="'.Lang::get('Reindex title', 's2_search').'">'.Lang::get('Reindex', 's2_search').'</a><span id="s2_search_progress"></span>';
try {
	$stat = \Container::get(\S2\Rose\Storage\Database\PdoStorage::class)->getIndexStat();
	$size = $stat['bytes'];
	$rows = $stat['rows'];
} catch (\Exception $e) {
	$size = $rows = 0;
}
$counters[] = sprintf(Lang::get('Info link', 's2_search'), Lang::friendly_filesize($size), Lang::number_format($rows), $s2_search_reindex);
