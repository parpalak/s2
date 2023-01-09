<?php
/**
 * Hook fn_show_comments_after_table_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($mode == 's2_blog_new' && count($article_titles))
	$output .= '<div class="info-box"><p>'.$lang_admin['Premoderation info'].'</p></div>';
