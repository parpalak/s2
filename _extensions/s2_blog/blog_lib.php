<?php
/**
 * Helper functions for blog tab in the admin panel
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */


if (!defined('S2_ROOT'))
	die;

//
// Editor tab
//

// Converts a date like 2010/05/09 into the timestamp
function s2_blog_parse_date ($time, $day_shift = 0)
{
	$regex = Lang::get('Date pattern', 's2_blog'); // 'Y/m/d'
	if (!preg_match_all('#[Ymd]#', $regex, $pattern_matches))
		return 'Error in $lang_s2_blog[\'Date pattern\']';

	$replace = array(
		'Y' => '(\d{4})',
		'm' => '(\d{1,2})',
		'd' => '(\d{1,2})',
	);
	$regex = strtr(preg_quote($regex), $replace);
	if (!preg_match('#^' . $regex . '$#', $time, $time_matches))
		return false;

	foreach ($pattern_matches[0] as $i => $interval)
		$time_array[$interval] = $time_matches[$i + 1];

	return checkdate($time_array['m'], $time_array['d'], $time_array['Y']) ?
		mktime(0, 0, 0, $time_array['m'], $time_array['d'] + $day_shift, $time_array['Y']) : false;
}

function s2_blog_save_post ($page, $flags)
{
	global $s2_db, $lang_admin, $s2_user;

	$favorite = (int) isset($flags['favorite']);
	$published = (int) isset($flags['published']);
	$commented = (int) isset($flags['commented']);

	$create_time = isset($page['create_time']) ? s2_time_from_array($page['create_time']) : time();
	$modify_time = isset($page['modify_time']) ? s2_time_from_array($page['modify_time']) : time();

	$id = (int) $page['id'];

	$label = isset($page['label']) ? $s2_db->escape($page['label']) : '';

	$query = array(
		'SELECT'	=> 'user_id, revision, text',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_save_post_pre_get_post_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_row($result))
		list($user_id, $revision, $text) = $row;
	else
		die('Item not found!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($user_id == $s2_user['id']);

	if ($page['text'] != $text)
	{
		// If the page text has been modified, we check if this modification is done by current user
		if ($revision != $page['revision'])
			return array(null, $revision, 'conflict'); // No, it's somebody else

		$revision++;
	}

	$error = false;

	$query = array(
		'UPDATE'	=> 's2_blog_posts',
		'SET'		=> "title = '".$s2_db->escape($page['title'])."', text = '".$s2_db->escape($page['text'])."', url = '".$s2_db->escape($page['url'])."', published = $published, favorite = $favorite, commented = $commented, create_time = $create_time, modify_time = $modify_time, label = '$label', revision = $revision",
		'WHERE'		=> 'id = '.$id
	);

	if ($s2_user['edit_site'])
		$query['SET'] .= ', user_id = '.intval($page['user_id']);

	($hook = s2_hook('fn_s2_blog_save_post_pre_upd_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);
	if ($s2_db->affected_rows() == -1)
		$error = true;

	// Dealing with tags
	$new_tags_str = isset($page['tags']) ? $page['tags'] : '';
	$new_tags = s2_get_tag_ids($new_tags_str);

	$query = array(
		'SELECT'	=> 'tag_id',
		'FROM'		=> 's2_blog_post_tag',
		'WHERE'		=> 'post_id = '.$id,
		'ORDER BY'	=> 'id'
	);
	($hook = s2_hook('fn_s2_blog_save_post_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$old_tags = array();
	while ($row = $s2_db->fetch_row($result))
		$old_tags[] = $row[0];

	// Compare old and new tags
	if (implode(',', $old_tags) != implode(',', $new_tags))
	{
		// Deleting old links
		$query = array(
			'DELETE'	=> 's2_blog_post_tag',
			'WHERE'		=> 'post_id = '.$id
		);
		($hook = s2_hook('fn_s2_blog_save_post_pre_del_tags_qr')) ? eval($hook) : null;
		$s2_db->query_build($query);
		if ($s2_db->affected_rows() == -1)
			$error = true;

		// Inserting new links
		foreach ($new_tags as $tag_id)
		{
			$query = array(
				'INSERT'	=> 'post_id, tag_id',
				'INTO'		=> 's2_blog_post_tag',
				'VALUES'	=> $id.', '.$tag_id
			);
			($hook = s2_hook('fn_s2_blog_save_post_pre_ins_tags_qr')) ? eval($hook) : null;
			$s2_db->query_build($query);
			if ($s2_db->affected_rows() == -1)
				$error = true;
		}
	}

	if ($error)
		die($lang_admin['Not saved correct']);

	return array($create_time, $revision, 'ok');
}

// Check nor unique and empty post urls
function s2_blog_check_url_status ($create_time, $url)
{
	global $s2_db;

	$url_status = 'ok';

	if ($url == '')
		$url_status = 'empty';
	else
	{
		$start_time = strtotime('midnight', $create_time);
		$end_time = $start_time + 86400;

		$query = array(
			'SELECT'	=> 'count(id)',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'url = \''.$url.'\' AND create_time < '.$end_time.' AND create_time >= '.$start_time
		);
		($hook = s2_hook('fn_s2_blog_check_url_status_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		if ($s2_db->result($result) != 1)
			$url_status = 'not_unique';
	}

	return $url_status;
}

//
// Blog tab
//

function s2_blog_create_post ()
{
	global $s2_db, $lang_admin, $s2_user;

	$now = time();

	$query = array(
		'INSERT'	=> 'create_time, modify_time, title, text, published, user_id',
		'INTO'		=> 's2_blog_posts',
		'VALUES'	=> $now.', '.$now.', \''.$lang_admin['New page'].'\', \'\', 0, '.$s2_user['id']
	);
	($hook = s2_hook('fn_s2_blog_create_post_pre_ins_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $s2_db->insert_id();
}

function s2_blog_flip_favorite ($id)
{
	global $s2_db;

	$query = array(
		'UPDATE'	=> 's2_blog_posts',
		'SET'		=> 'favorite = 1 - favorite',
		'WHERE'		=> 'id = '.$id,
	);
	($hook = s2_hook('fn_s2_blog_flip_favorite_pre_upd_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);
}

function s2_blog_delete_post ($id)
{
	global $s2_db, $s2_user;

	if (!$s2_user['edit_site'])
	{
		$query = array(
			'SELECT'	=> 'user_id',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'id = '.$id
		);
		($hook = s2_hook('fn_s2_blog_delete_post_pre_get_uid_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		if ($row = $s2_db->fetch_row($result))
			list($user_id) = $row;
		else
			die('Item not found!');

		s2_test_user_rights($user_id == $s2_user['id']);
	}

	$query = array(
		'DELETE'	=> 's2_blog_posts',
		'WHERE'		=> 'id = '.$id,
		'LIMIT'		=> '1'
	);
	($hook = s2_hook('fn_s2_blog_delete_post_pre_del_post_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	$query = array(
		'DELETE'	=> 's2_blog_post_tag',
		'WHERE'		=> 'post_id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_delete_post_pre_del_tags_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	$query = array(
		'DELETE'	=> 's2_blog_comments',
		'WHERE'		=> 'post_id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_delete_post_pre_del_cmnts_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	($hook = s2_hook('fn_s2_blog_delete_post_end')) ? eval($hook) : null;
}

//
// Comments tab
//

function s2_blog_get_comment ($id)
{
	global $s2_db;

	// Get comment
	$query = array(
		'SELECT'	=> 'id, nick, email, text, show_email, subscribed',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_get_comment_pre_get_cmnnt_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$comment = $s2_db->fetch_assoc($result);

	return $comment;
}

/**
 * @param int  $id
 * @param bool $leaveHidden Set true for spam
 * @return mixed
 */
function s2_blog_hide_comment ($id, bool $leaveHidden = false)
{
	global $s2_db;

	// Does the comment exist?
	// We need post id for displaying comments.
	// Also we need the comment if the premoderation is turned on.
	$query = array(
		'SELECT'	=> 'post_id, sent, shown, nick, email, text',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_hide_comment_pre_get_cmnt_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$comment = $s2_db->fetch_assoc($result);
	if (!$comment)
		die('Comment not found!');

	$sent = 1;
	if (!$comment['shown'] && !$comment['sent'] && !$leaveHidden)
	{
		// Premoderation is enabled and we have to send the comment to be shown
		// to the subscribed commentators
		if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
			require S2_ROOT.'_include/comments.php';

		// Getting some info about the post commented
		$query = array(
			'SELECT'	=> 'title, create_time, url',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'id = '.$comment['post_id'].' AND published = 1 AND commented = 1'
		);
		($hook = s2_hook('fn_s2_blog_hide_comment_pre_get_post_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		if ($post = $s2_db->fetch_assoc($result))
		{
			$link = s2_abs_link(str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).date('/Y/m/d/', $post['create_time']).urlencode($post['url']));

			// Fetching receivers' names and addresses
			$query = array(
				'SELECT'	=> 'id, nick, email, ip, time',
				'FROM'		=> 's2_blog_comments',
				'WHERE'		=> 'post_id = '.$comment['post_id'].' AND subscribed = 1 AND shown = 1 AND email <> \''.$s2_db->escape($comment['email']).'\''
			);
			($hook = s2_hook('fn_s2_blog_toggle_hide_comment_pre_get_rcvs_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query);

			$receivers = array();
			while ($receiver = $s2_db->fetch_assoc($result))
				$receivers[$receiver['email']] = $receiver;

			foreach ($receivers as $receiver)
			{
				$unsubscribe_link = S2_BASE_URL.'/comment.php?mail='.urlencode($receiver['email']).'&id='.$comment['post_id'].'.s2_blog&unsubscribe='.base_convert(substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16), 16, 36);
				s2_mail_comment($receiver['nick'], $receiver['email'], $comment['text'], $post['title'], $link, $comment['nick'], $unsubscribe_link);
			}
		}
		else
			$sent = 0;
	}

	// Toggle comment visibility
	$query = array(
		'UPDATE'	=> 's2_blog_comments',
        'SET'		=> !$leaveHidden ? 'shown = 1 - shown, sent = '.$sent : 'shown = 0, sent = 1',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_hide_comment_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $comment['post_id'];
}

function s2_blog_mark_comment ($id)
{
	global $s2_db;

	// Does the comment exist?
	// We need post id for displaying comments
	$query = array(
		'SELECT'	=> 'post_id',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_mark_comment_pre_get_pid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_row($result))
		$post_id = $row[0];
	else
		die('Comment not found!');

	// Mark comment
	$query = array(
		'UPDATE'	=> 's2_blog_comments',
		'SET'		=> 'good = 1 - good',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_mark_comment_pre_get_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $post_id;
}

function s2_blog_delete_comment ($id)
{
	global $s2_db;

	// Does the comment exist?
	// We need post id for displaying the other comments
	$query = array(
		'SELECT'	=> 'post_id',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_delete_comment_pre_get_pid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_row($result))
		$post_id = $row[0];
	else
		die('Comment not found!');

	$query = array(
		'DELETE'	=> 's2_blog_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_delete_comment_pre_del_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $post_id;
}

//
// HTML building
//

function s2_blog_output_post_list ($criteria)
{
	global $s2_db, $lang_admin, $s2_user;

	$conditions = array();
	$messages = array();

	if (!empty($criteria['start_time']))
	{
		$time = s2_blog_parse_date($criteria['start_time']);
		if ($time === false)
			$messages[] = sprintf(Lang::get('Invalid start date', 's2_blog'), date(Lang::get('Date pattern', 's2_blog'), time() - 86400), date(Lang::get('Date pattern', 's2_blog')));
		elseif ((int) $time != 0)
			$conditions[] = 'p.create_time >= ' . ((int) $time);
		else
			$messages[] = $time;
	}

	if (!empty($criteria['end_time']))
	{
		$time = s2_blog_parse_date($criteria['end_time'], 1);
		if ($time === false)
			$messages[] = sprintf(Lang::get('Invalid end date', 's2_blog'), date(Lang::get('Date pattern', 's2_blog'), time() - 86400), date(Lang::get('Date pattern', 's2_blog')));
		elseif ((int) $time != 0)
			$conditions[] = 'p.create_time <= ' . ((int) $time);
		else
			$messages[] = $time;
	}

	if (!empty($criteria['text']))
	{
		$condition = array();
		foreach (explode(' ', $criteria['text']) as $word)
			if ($word != '')
				$condition[] = 'p.title LIKE \'%'.$s2_db->escape($word).'%\' OR p.text LIKE \'%'.$s2_db->escape($word).'%\'';
		if (count($condition))
			$conditions[] = '('.implode(' OR ', $condition).')';
	}

	if (isset($criteria['hidden']) &&  $criteria['hidden'] == '1')
		$conditions[] = 'p.published = 0';

	if (!$s2_user['view_hidden'])
		$conditions[] = '(p.published = 1 OR p.user_id = '.$s2_user['id'].')';

	if (!empty($criteria['author']) && trim($criteria['author']))
	{
		$sub_query = array(
			'SELECT'    => 'u.id',
			'FROM'      => 'users AS u',
			'WHERE'     => 'u.login LIKE \'%'.$s2_db->escape(trim($criteria['author'])).'%\'',
		);
		$raw_sub_query = $s2_db->query_build($sub_query, true);
		$conditions[] = 'p.user_id in ('.$raw_sub_query.')';
	}

	$key_search = isset($criteria['key']) ? trim($criteria['key']) : '';

	list($tag_names, $tag_urls, $tag_count) = s2_blog_tag_list();

	if ($key_search != '')
	{
		$tag_ids = array();
		foreach ($tag_names as $tag_id => $tag)
			if (stristr($tag, $key_search) !== false && $tag_count[$tag_id])
				$tag_ids[] = $tag_id;

		if (!empty($tag_ids))
		{
			$sub_query = array(
				'SELECT'    => 'pt.post_id',
				'FROM'      => 's2_blog_post_tag AS pt',
				'WHERE'     => 'pt.tag_id IN ('.implode(', ', $tag_ids).')',
			);
			$raw_sub_query = $s2_db->query_build($sub_query, true);
			$conditions[] = 'p.id in ('.$raw_sub_query.')';
		}
		else
			$conditions = array('NULL');
	}

	($hook = s2_hook('fn_s2_blog_output_post_list_pre_crit_mrg')) ? eval($hook) : null;

	$condition = count($conditions) ? implode(' AND ', $conditions) : '1';
	$message = empty($messages) ? '' : '<div class="info-box"><p>'.implode('</p><p>', $messages).'</p></div>';

	$sub_query = array(
		'SELECT'    => 'count(c.post_id)',
		'FROM'      => 's2_blog_comments AS c',
		'WHERE'     => 'c.post_id = p.id',
	);
	$raw_sub_query = $s2_db->query_build($sub_query, true);

	$query = array(
		'SELECT'	=> 'id, title, published, commented, ('.$raw_sub_query.') as comment_count, create_time, label, favorite, user_id',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> $condition,
		'ORDER BY'	=> 'create_time DESC'
	);
	($hook = s2_hook('fn_s2_blog_output_post_list_pre_fetch_posts_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$rows = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$row['tags'] = array();
		($hook = s2_hook('fn_s2_blog_output_post_list_pre_form_row_ar')) ? eval($hook) : null;
		$rows[$row['id']] = $row;
	}

	echo $message;

	if (!empty($rows))
	{
		$query = array(
			'SELECT'	=> 'tag_id, post_id',
			'FROM'		=> 's2_blog_post_tag',
			'ORDER BY'	=> 'id'
		);
		($hook = s2_hook('fn_s2_blog_output_post_list_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);
		while ($row = $s2_db->fetch_assoc($result))
			if (isset($rows[$row['post_id']]))
				$rows[$row['post_id']]['tags'][] = $tag_names[$row['tag_id']];

		$body = '';

		foreach ($rows as $row)
		{
			$class = $row['published'] ? '' : ' class="hidden"';
			$comment = $row['comment_count'] ? '<a href="#" onclick="return LoadBlogComments('.$row['id'].');" ondblclick="return true;">'.$row['comment_count'].'</a>' : ($row['commented'] ? '' : '×'); // commented

			$buttons = array();
			if ($s2_user['edit_site'])
				$buttons['favorite'] = '<img class="'.($row['favorite'] ? 'favorite' : 'notfavorite').'" data-class="'.(!$row['favorite'] ? 'favorite' : 'notfavorite').'" src="i/1.gif" alt="'.($row['favorite'] ? Lang::get('Undo favorite', 's2_blog') : Lang::get('Do favorite', 's2_blog')).'" data-alt="'.(!$row['favorite'] ? Lang::get('Undo favorite', 's2_blog') : Lang::get('Do favorite', 's2_blog')).'" onclick="return ToggleFavBlog(this, '.$row['id'].');">';
			if ($s2_user['edit_site'] || $s2_user['id'] == $row['user_id'])
				$buttons['delete'] = '<img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete'].'" onclick="return DeleteRecord(this, '.$row['id'].', \''.s2_htmlencode(addslashes(sprintf(Lang::get('Delete warning', 's2_blog'), $row['title']))).'\');">';

			($hook = s2_hook('fn_s2_blog_output_post_list_pre_item_mrg')) ? eval($hook) : null;

			$buttons = '<span class="buttons">'.implode('', $buttons).'</span>';
			$tags = implode(', ', $row['tags']);
			$date = date('Y/m/d', $row['create_time']);

			($hook = s2_hook('fn_s2_blog_output_post_list_pre_row_mrg')) ? eval($hook) : null;

			$body .= '<tr'.$class.'><td><a href="#" onclick="return EditRecord('.$row['id'].'); ">'.s2_htmlencode($row['title']).'</a></td><td>'.$date.'</td><td>'.$tags.'</td><td>'.$row['label'].'</td><td>'.$comment.'</td><td>'.$buttons.'</td></tr>';
		}

		echo '<table width="100%" class="sort"><thead><tr><td class="sortable">'.Lang::get('Post', 's2_blog').'</td><td width="10%" class="sortable curcol_up">'.$lang_admin['Date'].'</td><td width="20%" class="sortable">'.Lang::get('Tags').'</td><td width="5%" class="sortable">'.Lang::get('Label', 's2_blog').'</td><td width="9%" class="sortable">'.Lang::get('Comments').'</td><td width="36">&nbsp;</td></tr></thead><tbody>'.$body.'</tbody></table>';
	}
	else
		echo '<div class="info-box"><p>'.Lang::get('No posts found', 's2_blog').'</p></div>';
}

function s2_blog_tag_list ()
{
	global $s2_db;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_post_tag AS pt',
		'WHERE'		=> 't.tag_id = pt.tag_id'
	);
	$raw_query = $s2_db->query_build($subquery, true);

	$query = array(
		'SELECT'	=> 'tag_id AS id, name, url, ('.$raw_query.') AS post_count',
		'FROM'		=> 'tags AS t',
		'ORDER BY'	=> 'post_count DESC'
	);
	($hook = s2_hook('fn_s2_blog_tag_list_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$names = $urls = $counts = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$names[$row['id']] = $row['name'];
		$counts[$row['id']] = $row['post_count'];
		$urls[$row['id']] = $row['url'];
	}
	return array($names, $urls, $counts);
}

function s2_blog_edit_post_form ($id)
{
	global $s2_db, $lang_admin, $s2_user;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'p.id = c.post_id'
	);
	$raw_query = $s2_db->query_build($subquery, true);

	$query = array(
		'SELECT'	=> 'title, text, create_time, modify_time, published, favorite, commented, url, label, ('.$raw_query.') AS comment_num, user_id, revision',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);
	$page = $s2_db->fetch_assoc($result);

	if (!$page['published'])
		s2_test_user_rights($s2_user['view_hidden'] || $s2_user['id'] == $page['user_id']);

	$page['path'] = S2_BLOG_PATH.date('Y/m/d/', $page['create_time']).urlencode($page['url']);

	$url_error = '';
	$url_status = s2_blog_check_url_status($page['create_time'], $page['url']);
	if ($url_status == 'empty')
		$url_error = $lang_admin['URL empty'];
	elseif ($url_status == 'not_unique')
		$url_error = $lang_admin['URL not unique'];

	$create_time = s2_array_from_time($page['create_time']);
	$modify_time = s2_array_from_time($page['modify_time']);

	// Fetching tags
	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_post_tag AS pt',
		'WHERE'		=> 't.tag_id = pt.tag_id'
	);
	$used_raw_query = $s2_db->query_build($subquery, true);

	$subquery = array(
		'SELECT'	=> 'id',
		'FROM'		=> 's2_blog_post_tag AS pt',
		'WHERE'		=> 't.tag_id = pt.tag_id AND pt.post_id = '.$id
	);
	$current_raw_query = $s2_db->query_build($subquery, true);

	$query = array(
		'SELECT'	=> 't.name, ('.$used_raw_query.') as used, ('.$current_raw_query.') as link_id',
		'FROM'		=> 'tags AS t',
		'ORDER BY'	=> 'used DESC'
	);
	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_chk_url_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$all_tags = $tags = array();
	while ($tag = $s2_db->fetch_assoc($result))
	{
		$all_tags[] = $tag['name'];
		if (!empty($tag['link_id']))
			$tags[$tag['link_id']] = $tag['name'];
	}
	ksort($tags);

	// Fetching labels
	$query = array(
		'SELECT'	=> 'label',
		'FROM'		=> 's2_blog_posts',
		'GROUP BY'	=> 'label',
		'ORDER BY'	=> 'count(label) DESC'
	);
	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_labels_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$labels = array();
	while ($row = $s2_db->fetch_row($result))
		$labels[] = $row[0];

	// Options for author select
	if ($s2_user['edit_site'])
	{
		$query = array( 
			'SELECT'	=> 'id, login',
			'FROM'		=> 'users',
			'WHERE'		=> 'create_articles = 1'
		);
		($hook = s2_hook('fn_s2_blog_edit_post_form_pre_users_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		$users = array(0 => '');
		while ($user = $s2_db->fetch_assoc($result))
			$users[$user['id']] = $user['login'];
	}

	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_output')) ? eval($hook) : null;

	ob_start();
?>
<form class="full_tab_form" name="artform" action="" onsubmit="SaveArticle('save_blog'); return false;">
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_pre_btn_col')) ? eval($hook) : null; ?>
	<div class="r-float">
<?php

	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_author')) ? eval($hook) : null;

	if ($s2_user['edit_site'])
	{

?>
		<label><?php echo $lang_admin['Author']; ?><br />
		<select name="page[user_id]">
<?php

		foreach ($users as $user_id => $login)
			echo "\t\t\t".'<option value="'.$user_id.'"'.($user_id == $page['user_id'] ? ' selected="selected"' : '').'>'.s2_htmlencode($login).'</option>'."\n";

?>
		</select></label>
<?php

	}

	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_labels')) ? eval($hook) : null;

?>
		<label title="<?php echo Lang::get('Label help', 's2_blog'); ?>"><?php echo Lang::get('Labels', 's2_blog'); ?><br />
		<select name="page[label]" data-prev-value="<?php echo s2_htmlencode($page['label']); ?>" onchange="ChangeSelect(this, '<?php echo Lang::get('Enter new label', 's2_blog'); ?>', '');">
<?php

	foreach ($labels as $label)
		echo "\t\t\t".'<option value="'.$label.'"'.($page['label'] == $label ? ' selected' : '').'>'.($label ? $label : Lang::get('No label', 's2_blog')).'</option>'."\n";

?>
			<option value="+"><?php echo Lang::get('New label', 's2_blog'); ?></option>
		</select></label>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_pre_checkboxes')) ? eval($hook) : null; ?>
		<input type="hidden" name="page[id]" value="<?php echo $id; ?>" />
		<input type="hidden" name="page[revision]" value="<?php echo $page['revision']; ?>" />
		<label for="favorite_checkbox"><input type="checkbox" id="favorite_checkbox" name="flags[favorite]" value="1"<?php if ($page['favorite']) echo ' checked="checked"'?> />
		<?php echo Lang::get('Favorite'); ?></label>
		<label for="com"><input type="checkbox" id="com" name="flags[commented]" value="1"<?php if ($page['commented']) echo ' checked="checked"'?> />
		<?php echo $lang_admin['Commented']; ?></label>
<?php

	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_links')) ? eval($hook) : null;

	if ($page['comment_num'])
	{
?>
		<a title="<?php echo $lang_admin['Go to comments']; ?>" href="#" onclick="return LoadBlogComments(<?php echo $id; ?>);"><?php echo Lang::get('Comments'); ?> &rarr;</a>
<?php
	}
	else
		echo "\t\t".$lang_admin['No comments']."\n";

	($hook = s2_hook('fn_s2_blog_edit_post_form_after_checkboxes')) ? eval($hook) : null;
?>
		<hr />
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_pre_url')) ? eval($hook) : null; ?>
		<label id="url_input_label"<?php if ($url_error) echo ' class="error" title="'.$url_error.'"'; ?> title_unique="<?php echo $lang_admin['URL not unique']; ?>" title_empty="<?php echo $lang_admin['URL empty']; ?>"><?php echo $lang_admin['URL part']; ?><br />
		<input type="text" name="page[url]" size="15" maxlength="255" value="<?php echo $page['url']; ?>" /></label>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_pre_published')) ? eval($hook) : null; ?>
		<label for="publiched_checkbox"<?php if ($page['published']) echo ' class="ok"'; ?>><input type="checkbox" id="publiched_checkbox" name="flags[published]" value="1"<?php if ($page['published']) echo ' checked="checked"'?> />
		<?php echo $lang_admin['Published']; ?></label>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_pre_save')) ? eval($hook) : null; ?>
		<input class="bitbtn save" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>"<?php if (!$s2_user['edit_site'] && $s2_user['id'] != $page['user_id']) echo ' disabled="disabled"'; ?> />
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_after_save')) ? eval($hook) : null; ?>
		<br />
		<br />
		<a title="<?php echo $lang_admin['Preview published']; ?>" id="preview_link" target="_blank" href="<?php echo $page['path']; ?>"<?php if (!$page['published']) echo ' style="display:none;"'; ?>><?php echo $lang_admin['Preview ready']; ?></a>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_after_prv')) ? eval($hook) : null; ?>
	</div>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_after_cols')) ? eval($hook) : null; ?>
	<div class="l-float">
		<table class="fields">
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_pre_title')) ? eval($hook) : null; ?>
			<tr>
				<td class="label"><?php echo $lang_admin['Title']; ?></td>
				<td><input type="text" name="page[title]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['title']); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_after_title')) ? eval($hook) : null; ?>
			<tr>
				<td class="label" title="<?php echo $lang_admin['Tags help']; ?>"><?php echo $lang_admin['Tags']; ?></td>
				<td><input type="text" name="page[tags]" size="100" value="<?php echo !empty($tags) ? s2_htmlencode(implode(', ', $tags).', ') : ''; ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_after_tags')) ? eval($hook) : null; ?>
		</table>
<?php ($hook = s2_hook('fn_s2_blog_edit_post_form_after_fields1')) ? eval($hook) : null; ?>
		<table class="fields">
			<tr>
				<td class="label"><?php echo $lang_admin['Create time']; ?></td>
				<td><nobr>
					<?php echo s2_get_time_input('page[create_time]', $create_time); ?>
					<a href="#" class="js" onclick="return SetTime(document.forms['artform'], 'page[create_time]');"><?php echo $lang_admin['Now']; ?></a>
				</nobr></td>
				<td class="label" title="<?php echo $lang_admin['Modify time help']; ?>"><?php echo $lang_admin['Modify time']; ?></td>
				<td><nobr>
					<?php echo s2_get_time_input('page[modify_time]', $modify_time); ?>
					<a href="#" class="js" onclick="return SetTime(document.forms['artform'], 'page[modify_time]');"><?php echo $lang_admin['Now']; ?></a>
				</nobr></td>
			</tr>
		</table>
<?php

	($hook = s2_hook('fn_s2_blog_edit_post_form_after_fields2')) ? eval($hook) : null;

	s2_toolbar();
	$padding = 9.583333;
	($hook = s2_hook('fn_s2_blog_edit_post_form_pre_text')) ? eval($hook) : null;

?>
		<div class="text_wrapper" style="top: <?php echo $padding; ?>em;">
			<textarea id="arttext" class="full_textarea" name="page[text]"><?php echo s2_htmlencode($page['text']); ?></textarea>
		</div>
	</div>
</form>
<?php
	return array('form' => ob_get_clean(), 'tags' => $all_tags);
}
