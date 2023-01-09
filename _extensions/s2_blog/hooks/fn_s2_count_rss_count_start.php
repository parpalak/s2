<?php
/**
 * Hook fn_s2_count_rss_count_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

global $request_uri;
if ($request_uri == S2_BLOG_URL.'/rss.xml')
	$filename = '/data/rss_s2_blog.txt';
