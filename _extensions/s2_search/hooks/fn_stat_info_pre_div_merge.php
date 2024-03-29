<?php
/**
 * Hook fn_stat_info_pre_div_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

use S2\Rose\Storage\Database\PdoStorage;

if (!defined('S2_ROOT')) {
    die;
}

Lang::load('s2_search', static function () {
    if (file_exists(S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php')) {
        return require S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php';
    }

    return require S2_ROOT . '/_extensions/s2_search' . '/lang/English.php';
});

$s2_search_reindex = '<a href="#" onclick="return s2_search.reindex();" class="js" title="' . Lang::get('Reindex title', 's2_search') . '">' . Lang::get('Reindex', 's2_search') . '</a><span id="s2_search_progress"></span>';
try {
    $stat = \Container::get(PdoStorage::class)->getIndexStat();
    $size = $stat['bytes'];
    $rows = $stat['rows'];
} catch (\Exception $e) {
    $size = $rows = 0;
}

/** @var $output */
$output .= '<div class="stat-item"><h3>' . Lang::get('Search index', 's2_search') . '</h3>'
    . sprintf(Lang::get('Search index rows', 's2_search'), Lang::number_format($rows)) . '<br>'
    . sprintf(Lang::get('Search index size', 's2_search'), Lang::friendly_filesize($size)) . '<br>'
    . $s2_search_reindex
    . '</div>';
