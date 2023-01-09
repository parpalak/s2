<?php
/**
 * Hook s2_search_action_makeindex
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($save_action == 'save_blog_' && $id)
	$chapter = 's2_blog_'.$id;
