<?php
/**
 * Hook idx_get_content
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($request_uri == '/search' || isset($_GET['search']) && isset($_GET['q']))
	return new \s2_extensions\s2_search\Page(array());
