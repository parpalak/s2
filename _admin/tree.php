<?php
/**
 * Displays and manipulates tree structure in the admin panel
 *
 * @copyright (C) 2007-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

//
// Articles tree managing
//

function s2_create_article ($id, $title)
{
	global $s2_db, $s2_user, $lang_admin;

	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_create_article_pre_check_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$s2_db->fetch_assoc($result))
		die('Item not found!');

	$query = array(
		'SELECT'	=> 'MAX(priority + 1)',
		'FROM'		=> 'articles',
		'WHERE'		=> 'parent_id = '.$id
	);
	($hook = s2_hook('fn_create_article_pre_get_maxpr_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$max_priority = (int) $s2_db->result($result);

	$query = array(
		'INSERT'	=> 'parent_id, title, priority, url, user_id',
		'INTO'		=> 'articles',
		'VALUES'	=> $id.', \''.$s2_db->escape($title).'\', '.($max_priority).', \'new\', '.$s2_user['id']
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

function s2_move_branch ($source_id, $dest_id, $far)
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
		else
		{
			$dest_priority = $row['priority'];
			$dest_parent_id = $row['parent_id'];
			$dest_user_id = $row['user_id'];
		}
		$item_num++;
	}

	if ($item_num != 2)
		die('Items not found!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($source_user_id == $s2_user['id']);

	if ($far)
	{
		// Dragging into different folder
		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = priority - 1',
			'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority > '.$source_priority
		);
		($hook = s2_hook('fn_move_branch_pre_src_pr_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = priority + 1',
			'WHERE'		=> 'parent_id = '.$dest_id
		);
		($hook = s2_hook('fn_move_branch_pre_dest_priority_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = 0, parent_id = '.$dest_id,
			'WHERE'		=> 'id = '.$source_id
		);
		($hook = s2_hook('fn_move_branch_pre_parent_id_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		// Moving inside a folder

		if ($source_priority < $dest_priority)
		{
			$query = array(
				'UPDATE'	=> 'articles',
				'SET'		=> 'priority = priority - 1',
				'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority > '.$source_priority.' AND priority <= '.$dest_priority
			);
			($hook = s2_hook('fn_move_branch_pre_shift_pr_dn_upd_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);
		}
		else
		{
			$query = array(
				'UPDATE'	=> 'articles',
				'SET'		=> 'priority = priority + 1',
				'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority < '.$source_priority.' AND priority >= '.$dest_priority
			);
			($hook = s2_hook('fn_move_branch_pre_shift_pr_up_upd_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);
		}

		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = '.$dest_priority,
			'WHERE'		=> 'id = '.$source_id
		);
		($hook = s2_hook('fn_move_branch_pre_src_pr_dn_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}

	return $source_parent_id;
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
// Article-tag links managing
//

function s2_add_article_to_tag ($article_id, $tag_id)
{
	global $s2_db, $s2_user;

	$query = array(
		'SELECT'	=> 'user_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$article_id
	);
	($hook = s2_hook('fn_add_article_to_tag_pre_sl_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_assoc($result))
		$user_id = $row['user_id'];
	else
		die('Unknown article');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($user_id == $s2_user['id']);

	$query = array(
		'INSERT'	=> 'article_id, tag_id',
		'INTO'		=> 'article_tag',
		'VALUES'	=> $article_id.', '.$tag_id
	);
	($hook = s2_hook('fn_add_article_to_tag_pre_ins_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_delete_article_from_tag ($id)
{
	global $s2_db, $s2_user;

	$query = array(
		'SELECT'	=> 'tag_id, article_id',
		'FROM'		=> 'article_tag',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_article_from_tag_pre_get_tagid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_row($result))
		list($tag_id, $article_id) = $row;
	else
		die('Can\'t find the article-tag link.');

	if (!$s2_user['edit_site'])
	{
		// Extra permission check
		$query = array(
			'SELECT'	=> 'user_id',
			'FROM'		=> 'articles',
			'WHERE'		=> 'id = '.$article_id
		);
		($hook = s2_hook('fn_delete_article_from_tag_pre_get_uid_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		if ($row = $s2_db->fetch_assoc($result))
			s2_test_user_rights($row['user_id'] == $s2_user['id']);
	}

	$query = array(
		'DELETE'	=> 'article_tag',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_article_from_tag_pre_del_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	return $tag_id;
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
				$condition[] = '(title LIKE \'%'.$s2_db->escape($word).'%\' OR pagetext LIKE \'%'.$s2_db->escape($word).'%\')';
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

	$rows = array();
	while ($article = $s2_db->fetch_assoc($result))
		$rows[] = $article;

	$output = array();
	for ($i = 0; $i < count($rows); $i++)
	{
		$article = $rows[$i];

		// This element will have onclick event handler and proper styles
		$expand = '<div></div>';
		$strike = $article['published'] ? '' : ' style="text-decoration: line-through;"';
		// Custom attribute
		$comments = $article['comment_num'] ? ' comments="'.$article['comment_num'].'"' : '';
		$span = '<div><span class="additional">'.s2_date($article['create_time']).'</span><span id="'.$article['id'].'"'.$strike.$comments.'>'.s2_htmlencode($article['title']).'</span></div>';

		$children = (!$search || $article['child_num']) ? s2_get_child_branches($article['id'], false, $search) : '';

		// File or folder
		$item_type = $search ? ($article['child_num'] ? 'ExpandOpen' : 'ExpandLeaf') : ($children ? ($id != S2_ROOT_ID ? 'ExpandClosed' : 'ExpandOpen') : 'ExpandLeaf');

		($hook = s2_hook('fn_get_child_branches_after_get_branch')) ? eval($hook) : null;

		if ($search && (!$children && !$article['found']))
			continue;

		//$output .= '<li class="'.$item_type.($i == count($rows) ? ' IsLast' : '' ).($search ? ' Search'.($article['found'] ? ' Match' : '') : '').'">'.$expand.$span.$children.'</li>';
		$item = array(
			'data'		=> array(
				'title'		=> $article['title'],
//				'icon'		=> $children ? 'folder' : 'file'
			),
			'attr'		=> array('id' => 'node_'.$article['id']),
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
			$item['state'] = $search && $children ? 'open' : 'closed';
			$item['children'] = $children;
		}
		$output[] = $item;
	}

	($hook = s2_hook('fn_get_child_branches_end')) ? eval($hook) : null;
	//return $output && !$root ? '<ul>'.$output.'</ul>' : $output;
	return $output;
}

//
// Tags and articles lists in the tree tab
//

function s2_get_tag_names ()
{
	global $s2_db;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'article_tag AS at',
		'WHERE'		=> 't.tag_id = at.tag_id'
	);
	$art_num_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'tag_id, name, ('.$art_num_query.') as article_count',
		'FROM'		=> 'tags AS t',
		'ORDER BY'	=> 'name'
	);
	($hook = s2_hook('fn_get_tag_names_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$list = '';
	while ($row = $s2_db->fetch_assoc($result))
		$list .= '<li data-tagid="'.$row['tag_id'].'" onclick="ChooseTag(this);">'.s2_htmlencode($row['name']).' (<span>'.$row['article_count'].'</span>)</li>';

	return $list;
}

function s2_get_tag_articles ($tag_id)
{
	global $s2_db, $lang_admin;

	$query = array(
		'SELECT'	=> 'art.id, art.title, atg.id as link_id',
		'FROM'		=> 'articles AS art',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'article_tag as atg',
				'ON'			=> 'art.id = atg.article_id'
			)
		),
		'WHERE'		=> 'atg.tag_id = '.$tag_id,
		'ORDER BY'	=> 'atg.id'
	);
	($hook = s2_hook('fn_get_tag_articles_pre_select_query')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$list = '';
	while ($row = $s2_db->fetch_assoc($result))
		$list .= '<li onclick="OpenById('.$row['id'].');"><img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete from list'].'" onclick="DeleteArticleFromTag('.$row['link_id'].', event);" />'.s2_htmlencode($row['title']).'</li>';

	return $list ? '<ul>' . $list . '</ul>' : '';
}
