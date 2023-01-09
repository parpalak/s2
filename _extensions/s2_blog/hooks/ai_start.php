<?php
/**
 * Hook ai_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

define('S2_BLOG_PATH', s2_link(str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).'/'));
define('S2_BLOG_TAGS_PATH', S2_BLOG_PATH.urlencode(S2_TAGS_URL).'/');
