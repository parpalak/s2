<?php
/**
 * Hook cmnt_pre_path_check
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($class == 's2_blog')
	$path = str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).date('/Y/m/d', $row['create_time']);
