<?php
/**
 * Hook fn_output_tag_form_pre_get_tags_qr
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 *
 * @var \S2\Cms\Pdo\DbLayer $s2_db
 */

 if (!defined('S2_ROOT')) {
     die;
}

$subquery = array(
	'SELECT'	=> 'count(*)',
	'FROM'		=> 's2_blog_post_tag AS pt',
	'WHERE'		=> 't.tag_id = pt.tag_id'
);
$raw_query = $s2_db->build($subquery);
$query['SELECT'] .= ', ('.$raw_query.') AS post_count';
