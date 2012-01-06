<?php
/**
 * Ajax request processing for autosearch
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


header('Content-Type: text/html; charset=utf-8');

define('S2_ROOT', '../../');
define('S2_NO_DB', 1);
require S2_ROOT.'_include/common.php';

header('X-Powered-By: S2/'.S2_VERSION);

$s2_search_query = isset($_GET['q']) ? $_GET['q'] : '';

require 'finder.class.php';

if ($s2_search_query !== '')
{
	$finder = new s2_search_title_finder(S2_CACHE_DIR);
	$toc = $finder->find($s2_search_query);

	foreach ($toc as $chapter => $chapter_info)
		echo '<a href="'.$chapter_info['url'].'">'.
				preg_replace('#('.preg_quote($s2_search_query, '#').')#ui', '<em>\\1</em>', s2_htmlencode($chapter_info['title'])).'</a>';
}