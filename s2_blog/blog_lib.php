<?php
/**
 * Helper functions for blog administrating
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */


// Converts a date like 2010/05/09 into the timestamp
function s2_blog_parse_date ($time, $day_shift = 0)
{
	global $lang_s2_blog;

	$regex = $lang_s2_blog['Date pattern']; // 'Y/m/d'
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

function s2_blog_output_post_list ($criteria)
{
	global $s2_db, $lang_common, $lang_admin, $lang_s2_blog, $session_id;

	$conditions = array();
	$messages = array();

	if (!empty($criteria['start_time']))
	{
		$time = s2_blog_parse_date($criteria['start_time']);
		if ($time === false)
			$messages[] = sprintf($lang_s2_blog['Invalid start date'], date($lang_s2_blog['Date pattern'], time() - 86400), date($lang_s2_blog['Date pattern']));
		elseif ((int) $time != 0)
			$conditions[] = 'create_time > ' . ((int) $time);
		else
			$messages[] = $time;
	}

	if (!empty($criteria['end_time']))
	{
		$time = s2_blog_parse_date($criteria['end_time'], 1);
		if ($time === false)
			$messages[] = sprintf($lang_s2_blog['Invalid end date'], date($lang_s2_blog['Date pattern'], time() - 86400), date($lang_s2_blog['Date pattern']));
		elseif ((int) $time != 0)
			$conditions[] = 'create_time < ' . ((int) $time);
		else
			$messages[] = $time;
	}

	if (isset($criteria['text']) &&  $criteria['text'] != '')
	{
		$condition = array();
		foreach (explode(' ', $criteria['text']) as $word)
			if ($word != '')
				$condition[] = 'title LIKE \'%'.$s2_db->escape($word).'%\' OR p.text LIKE \'%'.$s2_db->escape($word).'%\'';
		if (count($condition))
			$conditions[] = '('.implode(' OR ', $condition).')';
	}

	if (isset($criteria['hidden']) &&  $criteria['hidden'] == '1')
		$conditions[] = 'published = 0';

	// Determine if we can show hidden info like e-mails and IP addresses
	$query = array(
		'SELECT'	=> 'view_hidden',
		'FROM'		=> 'users_online AS l',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'users AS u',
				'ON'			=> 'u.login = l.login'
			)
		),
		'WHERE'		=> 'challenge = \''.$s2_db->escape($session_id).'\''
	);
	($hook = s2_hook('blrq_action_load_blog_posts_pre_get_perm_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$show_hidden = $s2_db->num_rows($result) == 1 && $s2_db->result($result);

	if (!$show_hidden)
		$conditions[] = 'published = 1';

	$key_search = isset($criteria['key']) ? trim($criteria['key']) : '';

	list($tag_names, $tag_urls, $tag_count) = s2_blog_tag_list();

	if ($key_search != '')
	{
		$tag_ids = array();
		foreach ($tag_names as $tag_id => $tag)
			if (stristr($tag, $key_search) !== false && $tag_count[$tag_id])
				$tag_ids[] = $tag_id;

		if (!empty($tag_ids))
			$conditions[] = 'p.id in (SELECT pt.post_id FROM '.$s2_db->prefix.'s2_blog_post_tag AS pt WHERE pt.tag_id IN ('.implode(', ', $tag_ids).'))';
		else
			$conditions = array('NULL');
	}

	($hook = s2_hook('blrq_action_load_blog_posts_pre_crit_merge')) ? eval($hook) : null;

	$condition = count($conditions) ? implode(' AND ', $conditions) : '1';
	$message = empty($messages) ? '' : '<div class="info-box"><p>'.implode('</p><p>', $messages).'</p></div>';

	$query = array(
		'SELECT'	=> 'id, title, published, commented, (SELECT count(c.post_id) FROM '.$s2_db->prefix.'s2_blog_comments AS c WHERE c.post_id = p.id) as comment_count, create_time, label, favorite',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> $condition,
		'ORDER BY'	=> 'create_time DESC'
	);
	($hook = s2_hook('blrq_action_load_blog_posts_pre_fetch_posts_query')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$rows = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$row['tags'] = array();
		($hook = s2_hook('blrq_action_load_blog_posts_pre_format_row_array')) ? eval($hook) : null;
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
		($hook = s2_hook('blrq_action_load_blog_posts_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		while ($row = $s2_db->fetch_assoc($result))
			if (isset($rows[$row['post_id']]))
				$rows[$row['post_id']]['tags'][] = $tag_names[$row['tag_id']];

		$body = '';

		foreach ($rows as $row)
		{
			$class = $row['published'] ? '' : ' class="hidden"';
			$comment = $row['comment_count'] ? '<a href="#" onclick="return LoadBlogComments('.$row['id'].');" ondblclick="return true;">'.$row['comment_count'].'</a>' : ($row['commented'] ? '' : '×'); // commented
			$buttons = array(
				'favorite' => $row['favorite'] ?
					'<img class="favorite" src="i/1.gif" alt="'.$lang_s2_blog['Undo favorite'].'" onclick="return ToggleFavBlog(this, '.$row['id'].');">' :
					'<img class="notfavorite" src="i/1.gif" alt="'.$lang_s2_blog['Do favorite'].'" onclick="return ToggleFavBlog(this, '.$row['id'].');">',
				'delete' => '<img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete'].'" onclick="return DeleteRecord(this, '.$row['id'].', \''.s2_htmlencode(addslashes(sprintf($lang_s2_blog['Delete warning'], $row['title']))).'\');">',
			);

			($hook = s2_hook('blrq_action_load_blog_posts_pre_item_merge')) ? eval($hook) : null;

			$buttons = '<span class="buttons">'.implode('', $buttons).'</span>';
			$tags = implode(', ', $row['tags']);
			$date = date('Y/m/d', $row['create_time']);

			($hook = s2_hook('blrq_action_load_blog_posts_pre_row_merge')) ? eval($hook) : null;

			$body .= '<tr'.$class.'><td class="content"><a href="#" onclick="return EditRecord('.$row['id'].'); ">'.$row['title'].'</a></td><td>'.$date.'</td><td>'.$tags.'</td><td>'.$row['label'].'</td><td>'.$comment.'</td><td>'.$buttons.'</td></tr>';
		}

		echo '<table width="100%" class="sort"><thead><tr><td>'.$lang_s2_blog['Post'].'</td><td width="10%">'.$lang_admin['Date'].'</td><td width="20%">'.$lang_admin['Tags'].'</td><td width="5%">'.$lang_s2_blog['Label'].'</td><td width="9%">'.$lang_common['Comments'].'</td><td width="36">&nbsp;</td></tr></thead><tbody>'.$body.'</tbody></table>';
	}
	else
		echo '<div class="info-box"><p>'.$lang_s2_blog['No posts found'].'</p></div>';
}

function s2_blog_tag_list ()
{
	global $s2_db;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_post_tag AS pt',
		'WHERE'		=> 't.tag_id = pt.tag_id'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'tag_id AS id, name, url, ('.$raw_query.') AS post_count',
		'FROM'		=> 'tags AS t',
		'ORDER BY'	=> 'post_count DESC'
	);
	($hook = s2_hook('s2_blog_fn_post_form_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

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
	global $s2_db, $lang_s2_blog, $lang_common, $lang_admin;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'p.id = c.post_id'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'title, text, create_time, modify_time, published, favorite, commented, url, label, ('.$raw_query.') AS comment_num',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('s2_blog_fn_post_form_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$page = $s2_db->fetch_assoc($result);

	if (!$page['published'])
	{
		$required_rights = array('view_hidden');
		($hook = s2_hook('s2_blog_fn_post_form_pre_perm_check')) ? eval($hook) : null;
		s2_test_user_rights($GLOBALS['session_id'], $required_rights);
	}

	$page['path'] = BLOG_BASE.date('Y/m/d/', $page['create_time']).urlencode($page['url']);

	$url_error = '';
	if ($page['url'] == '')
		$url_error = $lang_admin['URL empty'];
	else
	{
		$start_time = intval($page['create_time'] / 86400) * 86400;
		$end_time = $start_time + 86400;

		$query = array(
			'SELECT'	=> 'count(id)',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'url = \''.$page['url'].'\' AND create_time < '.$end_time.' AND create_time >= '.$start_time
		);
		($hook = s2_hook('s2_blog_fn_post_form_pre_check_url_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($s2_db->result($result) != 1)
			$url_error = $lang_admin['URL not unique'];
	}

	$cr_time = s2_array_from_time($page['create_time']);
	$m_time = s2_array_from_time($page['modify_time']);

	$query = array(
		'SELECT'	=> 'tag_id',
		'FROM'		=> 's2_blog_post_tag',
		'WHERE'		=> 'post_id = '.$id,
		'ORDER BY'	=> 'id'
	);
	($hook = s2_hook('s2_blog_fn_post_form_pre_tags_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$i = 0;
	$tag_order = array();
	$tag_string = '|';
	while ($row = $s2_db->fetch_row($result))
	{
		$tag_order[$row[0]] = ++$i;
		$tag_string .= $row[0].'|';
	}

	list($tag_names, $tag_urls, $tag_count) = s2_blog_tag_list();

	$query = array(
		'SELECT'	=> 'label',
		'FROM'		=> 's2_blog_posts',
		'GROUP BY'	=> 'label',
		'ORDER BY'	=> 'count(label) DESC'
	);
	($hook = s2_hook('fn_blog_get_post_pre_labels_fetch_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$labels = array();
	while ($row = $s2_db->fetch_row($result))
		$labels[] = $row[0];

	($hook = s2_hook('s2_blog_fn_post_form_pre_output')) ? eval($hook) : null;

?>
<form name="artform" action="" onsubmit="setTimeout('SaveArticle(\'save_blog\');', 0); return false;">
	<div class="l-float">
		<div class="border">
			<table class="fields">
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_title')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Title']; ?></td>
					<td><input type="text" name="page[title]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['title']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('s2_blog_fn_post_form_after_title')) ? eval($hook) : null; ?>
			</table>
<?php ($hook = s2_hook('s2_blog_fn_post_form_after_fields1')) ? eval($hook) : null; ?>
			<table class="fields">
				<tr>
					<td class="label"><?php echo $lang_admin['Create time']; ?></td>
					<td><nobr>
						<?php echo s2_get_time_input('cr_time', $cr_time); ?>
						<a href="#" class="js" onclick="return SetTime(document.artform, 'cr_time');"><?php echo $lang_admin['Now']; ?></a>
					</nobr></td>
					<td class="label"><?php echo $lang_admin['Modify time']; ?></td>
					<td><nobr>
						<?php echo s2_get_time_input('m_time', $m_time); ?>
						<a href="#" class="js" onclick="return SetTime(document.artform, 'm_time');"><?php echo $lang_admin['Now']; ?></a>
					</nobr></td>
				</tr>
			</table>
<?php

	($hook = s2_hook('s2_blog_fn_post_form_after_fields2')) ? eval($hook) : null;

	s2_toolbar();
	$padding = 8;
	($hook = s2_hook('s2_blog_fn_post_form_pre_text')) ? eval($hook) : null;

?>
			<div class="text_wrapper" style="padding-bottom: <?php echo $padding; ?>em;">
				<textarea id="wText" name="page[text]"><?php echo s2_htmlencode($page['text']); ?></textarea>
			</div>
		</div>
	</div>
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_tag_col')) ? eval($hook) : null; ?>
	<div class="r-float" title="<?php echo $lang_admin['Click tag']; ?>">
		<?php echo $lang_admin['Tags:']; ?>
		<hr />
		<div class="text_wrapper" style="padding-bottom: 2.5em;">
			<div class="tags_list">
<?php

	foreach ($tag_names as $tag_id => $tag)
		echo "\t\t\t\t".'<span id="tag_'.$tag_id.'">'.(isset($tag_order[$tag_id]) ? $tag_order[$tag_id] : '').'</span> <a href="#" onclick="return BlogAddTag(\''.$tag_id.'\');">'.$tag.' ('.$tag_count[$tag_id].')</a><br />'."\n";

?>
				<input type="hidden" name="keywords" value="<?php echo $tag_string; ?>" />
			</div>
		</div>
	</div>
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_btn_col')) ? eval($hook) : null; ?>
	<div class="r-float">
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_parag_btn')) ? eval($hook) : null; ?>
		<input class="bitbtn parag" type="button" value="<?php echo $lang_admin['Paragraphs']; ?>" onclick="return Paragraph();" />
<?php ($hook = s2_hook('s2_blog_fn_post_form_after_parag_btn')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_reset')) ? eval($hook) : null; ?>
		<input class="bitbtn reset" type="reset" value="<?php echo $lang_admin['Reset']; ?>" onclick="return confirm(S2_LANG_RESET_PROMPT);" />
		<br />
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_clear')) ? eval($hook) : null; ?>
		<input class="bitbtn new" type="button" value="<?php echo $lang_admin['Clear']; ?>" onclick="ClearForm(); return false;" />
<?php ($hook = s2_hook('s2_blog_fn_post_form_after_clear')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_labels')) ? eval($hook) : null; ?>
		<label><?php echo $lang_s2_blog['Labels']; ?><br />
		<select size="1" name="page[label]">
<?php

	foreach ($labels as $label)
		echo '<option value="'.$label.'"'.($page['label'] == $label ? ' selected' : '').'>'.($label ? $label : $lang_s2_blog['No label']).'</option>';

?>
		</select></label>
		<label><?php echo $lang_s2_blog['New label']; ?><br />
		<input type="text" name="page[new_label]" size="15" maxlength="30" value="" /></label>
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_checkboxes')) ? eval($hook) : null; ?>
		<input type="hidden" name="page[id]" value="<?php echo $id; ?>" />
		<label for="fav"><input type="checkbox" id="fav" name="flags[favorite]" value="1"<? if ($page['favorite']) echo ' checked="checked"'?> />
		<?php echo $lang_common['Favorite']; ?></label>
		<label for="com"><input type="checkbox" id="com" name="flags[commented]" value="1"<? if ($page['commented']) echo ' checked="checked"'?> />
		<?php echo $lang_admin['Commented']; ?></label>
<?php

	($hook = s2_hook('s2_blog_fn_post_form_pre_links')) ? eval($hook) : null;

	if ($page['comment_num'])
	{
?>
		<a title="<?php echo $lang_admin['Go to comments']; ?>" href="#" onclick="return LoadBlogComments(<?php echo $id; ?>);"><?php echo $lang_common['Comments']; ?> &rarr;</a>
<?php
	}
	else
		echo "\t\t".$lang_admin['No comments']."\n";

	($hook = s2_hook('s2_blog_fn_post_form_after_checkboxes')) ? eval($hook) : null;
?>
		<hr />
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_url')) ? eval($hook) : null; ?>
		<label id="url_input_label"<?php if ($url_error) echo ' class="error" title="'.$url_error.'"'; ?> title_unique="<?php echo $lang_admin['URL not unique']; ?>" title_empty="<?php echo $lang_admin['URL empty']; ?>"><?php echo $lang_admin['URL part']; ?><br />
		<input type="text" name="page[url]" size="15" maxlength="255" value="<?php echo $page['url']; ?>" /></label>
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_published')) ? eval($hook) : null; ?>
		<label for="pub"<?php if ($page['published']) echo ' class="ok"'; ?>><input type="checkbox" id="pub" name="flags[published]" value="1"<? if ($page['published']) echo ' checked="checked"'?> />
		<?php echo $lang_admin['Published']; ?></label>
<?php ($hook = s2_hook('s2_blog_fn_post_form_pre_save')) ? eval($hook) : null; ?>
		<input class="bitbtn save" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>" />
<?php ($hook = s2_hook('s2_blog_fn_post_form_after_save')) ? eval($hook) : null; ?>
		<br />
		<br />
		<a title="<?php echo $lang_admin['Preview published']; ?>" target="_blank" href="<?php echo $page['path']; ?>"><?php echo $lang_admin['Preview ready']; ?></a>
<?php ($hook = s2_hook('s2_blog_fn_post_form_after_prv')) ? eval($hook) : null; ?>
	</div>
</form>
<?

}

function s2_blog_toggle_hide_comment ($id)
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
	($hook = s2_hook('blrq_action_hide_blog_comment_pre_get_comment_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if ($s2_db->num_rows($result) != 1)
		die('Comment not found!');

	$comment = $s2_db->fetch_assoc($result);

	$sent = 1;
	if (!$comment['shown'] && !$comment['sent'])
	{
		// Premoderation is enabled and we have to send the comment to be shown
		// to the subscribed commentators
		if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
			require S2_ROOT.'_include/comments.php';

		global $lang_comments;
		require S2_ROOT.'_lang/'.S2_LANGUAGE.'/comments.php';

		// Getting some info about the post commented
		$query = array(
			'SELECT'	=> 'title, create_time, url',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'id = '.$comment['post_id'].' AND published = 1 AND commented = 1'
		);
		($hook = s2_hook('blrq_action_hide_blog_comment_pre_get_post_info_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($post = $s2_db->fetch_assoc($result))
		{
			$link = S2_BASE_URL.'/'.urlencode(S2_BLOG_URL).date('/Y/m/d/', $post['create_time']).urlencode($post['url']);

			// Fetching receivers' names and addresses
			$query = array(
				'SELECT'	=> 'id, nick, email, ip, time',
				'FROM'		=> 's2_blog_comments',
				'WHERE'		=> 'post_id = '.$comment['post_id'].' and subscribed = 1 and email <> \''.$s2_db->escape($comment['email']).'\''
			);
			($hook = s2_hook('s2_blog_fn_toggle_hide_comment_pre_get_receivers_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			$receivers = array();
			while ($receiver = $s2_db->fetch_assoc($result))
				$receivers[$receiver['email']] = $receiver;

			foreach ($receivers as $receiver)
			{
				$unsubscribe_link = S2_BASE_URL.'/comment.php?mail='.urlencode($receiver['email']).'&id='.$comment['post_id'].'&blog_unsubscribe='.substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16);
				s2_mail_comment($receiver['nick'], $receiver['email'], $comment['text'], $post['title'], $link, $comment['nick'], $unsubscribe_link);
			}
		}
		else
			$sent = 0;
	}

	// Toggle comment visibility
	$query = array(
		'UPDATE'	=> 's2_blog_comments',
		'SET'		=> 'shown = 1 - shown, sent = '.$sent,
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('blrq_action_hide_blog_comment_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	echo s2_comment_menu_links();
	echo s2_show_comments('s2_blog', $comment['post_id']);
}
