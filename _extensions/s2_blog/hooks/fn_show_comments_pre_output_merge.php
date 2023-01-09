<?php
/**
 * Hook fn_show_comments_pre_output_merge
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
	$output_header = str_replace('EditArticle', 'EditRecord', $output_header);
	$output_subheader = $mode == 's2_blog' ? '' : str_replace('LoadComments', 'LoadBlogComments', $output_subheader);
}
