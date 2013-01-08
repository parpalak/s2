<?php
/**
 * Displays and manipulates tree structure in the admin panel
 *
 * @copyright (C) 2007-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

//
// Articles tree managing
//

function s2_create_article ($id, $title)
{
	global $s2_db, $s2_user;

	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_create_article_pre_check_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$s2_db->fetch_assoc($result))
		die('Item not found!');

	if (S2_ADMIN_NEW_POS)
	{
		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = priority + 1',
			'WHERE'		=> 'parent_id = '.$id
		);
		($hook = s2_hook('fn_create_article_pre_upd_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		$new_priority = 0;
	}
	else
	{
		$query = array(
			'SELECT'	=> 'MAX(priority + 1)',
			'FROM'		=> 'articles',
			'WHERE'		=> 'parent_id = '.$id
		);
		($hook = s2_hook('fn_create_article_pre_get_maxpr_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		$new_priority = (int) $s2_db->result($result);
	}

	$query = array(
		'INSERT'	=> 'parent_id, title, priority, url, user_id',
		'INTO'		=> 'articles',
		'VALUES'	=> $id.', \''.$s2_db->escape($title).'\', '.($new_priority).', \'new\', '.$s2_user['id']
	);
	($hook = s2_hook('fn_create_article_pre_ins_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
	$new_id = $s2_db->insert_id();

	($hook = s2_hook('fn_create_article_end')) ? eval($hook) : null;

	return $new_id;
}

function s2_rename_article ($id, $title)
{
	global $s2_db, $s2_user;

	$query = array(
		'SELECT'	=> 'user_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	($hook = s2_hook('fn_rename_article_pre_get_uid_qr')) ? eval($hook) : null;

	if ($row = $s2_db->fetch_row($result))
		list($user_id) = $row;
	else
		die('Item not found!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($user_id == $s2_user['id']);

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'title = \''.$s2_db->escape($title).'\'',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_rename_article_pre_upd_title_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_move_branch ($source_id, $dest_id, $position)
{
	global $s2_db, $s2_user;

	$query = array(
		'SELECT'	=> 'priority, parent_id, user_id, id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id IN ('.$source_id.', '.$dest_id.')'
	);
	($hook = s2_hook('fn_move_branch_pre_get_art_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$item_num = 0;
	while ($row = $s2_db->fetch_assoc($result))
	{
		if ($row['id'] == $source_id)
		{
			$source_priority = $row['priority'];
			$source_parent_id = $row['parent_id'];
			$source_user_id = $row['user_id'];
		}
		$item_num++;
	}

	if ($item_num != 2)
		die('Items not found!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($source_user_id == $s2_user['id']);

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'priority = priority + 1',
		'WHERE'		=> 'priority >= '.$position.' AND parent_id = '.$dest_id
	);
	($hook = s2_hook('fn_move_branch_pre_dest_priority_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'priority = '.$position.', parent_id = '.$dest_id,
		'WHERE'		=> 'id = '.$source_id
	);
	($hook = s2_hook('fn_move_branch_pre_parent_id_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'priority = priority - 1',
		'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority > '.$source_priority
	);
	($hook = s2_hook('fn_move_branch_pre_src_pr_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_delete_item_and_children ($id)
{
	global $s2_db;

	$return = ($hook = s2_hook('fn_delete_item_and_children_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	$query = array(
		'SELECT'	=> 'id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'parent_id = '.$id
	);
	($hook = s2_hook('fn_delete_item_and_children_pre_get_ids_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_row($result))
		s2_delete_item_and_children($row[0]);

	$query = array(
		'DELETE'	=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_item_and_children_pre_del_art_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'DELETE'	=> 'article_tag',
		'WHERE'		=> 'article_id = '.$id
	);
	($hook = s2_hook('fn_delete_item_and_children_pre_del_tags_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'DELETE'	=> 'art_comments',
		'WHERE'		=> 'article_id = '.$id
	);
	($hook = s2_hook('fn_delete_item_and_children_pre_del_comments_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_delete_branch ($id)
{
	global $s2_db, $s2_user;

	$query = array(
		'SELECT'	=> 'priority, parent_id, user_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_branch_pre_get_art_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_row($result))
		list($priority, $parent_id, $user_id) = $row;
	else
		die('Item not found!');

	if ($parent_id == S2_ROOT_ID)
		die('Can\'t delete root item!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($user_id == $s2_user['id']);

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'priority = priority - 1',
		'WHERE'		=> 'parent_id = '.$parent_id.' AND  priority > '.$priority
	);
	($hook = s2_hook('fn_delete_branch_pre_upd_pr_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	s2_delete_item_and_children($id);
}

//
// Builds HTML tree for the admin panel
//

function s2_get_child_branches ($id, $root = true, $search = false)
{
	global $s2_db;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'a.id = c.article_id'
	);
	$comment_num_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'title, id, create_time, priority, published, ('.$comment_num_query.') as comment_num, parent_id',
		'FROM'		=> 'articles AS a',
		'WHERE'		=> 'parent_id = '.$id,
		'ORDER BY'	=> 'priority'
	);
	if ($search)
	{
		// This function also can search through the site :)
		$condition = array();
		foreach (explode(' ', $search) as $word)
			if ($word != '')
			{
				if ($word{0} != ':' && strlen($word) > 1)
					$condition[] = '(title LIKE \'%'.$s2_db->escape($word).'%\' OR pagetext LIKE \'%'.$s2_db->escape($word).'%\')';
				else
				{
					$subquery = array(
						'SELECT'	=> 'count(*)',
						'FROM'		=> 'article_tag AS at',
						'JOINS'		=> array(
							array(
								'INNER JOIN'	=> 'tags AS t',
								'ON'			=> 't.tag_id = at.tag_id'
							)
						),
						'WHERE'		=> 'a.id = at.article_id AND t.name LIKE \'%'.$s2_db->escape(substr($word, 1)).'%\'',
						'LIMIT'		=> '1'
					);
					$tag_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);
					$condition[] = '('.$tag_query.')';
				}
			}

		if (count($condition))
		{
			$query['SELECT'] .= ', ('.implode(' AND ', $condition).') as found';

			$subquery = array(
				'SELECT'	=> 'count(*)',
				'FROM'		=> 'articles AS a2',
				'WHERE'		=> 'a2.parent_id = a.id'
			);
			$child_num_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

			$query['SELECT'] .= ', ('.$child_num_query.') as child_num';
		}
	}
	($hook = s2_hook('fn_get_child_branches_pre_get_art_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($article = $s2_db->fetch_assoc($result))
	{
		$children = (!$search || $article['child_num']) ? s2_get_child_branches($article['id'], false, $search) : '';

		($hook = s2_hook('fn_get_child_branches_after_get_branch')) ? eval($hook) : null;

		if ($search && (!$children && !$article['found']))
			continue;

		$item = array(
			'data'		=> array(
				'title'		=> $article['title'],
			),
			'attr'		=> array(
				'id'		=> 'node_'.$article['id'],
			),
		);

		$class = array();
		if ($search)
		{
			$class[] = 'Search';
			if ($article['found'])
				$class[] = 'Match';
		}
		if (!$article['published'])
			$class[] = 'Hidden';
		if (count($class))
			$item['data']['attr']['class'] = implode(' ', $class);

		if ($article['comment_num'])
		{
			$item['attr']['data-comments'] = $article['comment_num'];
		}

		if ($children)
		{
			if ($search)
				$item['state'] = 'open';
			$item['children'] = $children;
		}
		$output[] = $item;
	}

	($hook = s2_hook('fn_get_child_branches_end')) ? eval($hook) : null;

	return $output;
}
