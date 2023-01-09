<?php
/**
 * Hook fn_show_comments_pre_table_row_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (strpos($mode, 's2_blog') === 0)
{
	$s2_blog_replace = array(
		'DeleteComment' => 'DeleteBlogComment',
		'edit_comment' => 'edit_blog_comment',
		'mark_comment' => 'mark_blog_comment',
		'hide_comment' => 'hide_blog_comment',
		'\'s2_blog_' => '\'',
	);
	$buttons = strtr($buttons, $s2_blog_replace);
}
