<?php
/**
 * Functions for the comments management in the admin panel
 *
 * @copyright (C) 2007-2013 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

function s2_comment_menu_links ($mode = false)
{
	global $lang_admin;

	$output = array(
		'<a href="#" class="js'.(strpos($mode, 'new') !== false ? ' cur_link' : '').'" onclick="return LoadTable(\'load_new_comments\', \'comm_div\');">'.$lang_admin['Show new comments'].'</a>',
		'<a href="#" class="js'.(strpos($mode, 'hidden') !== false ? ' cur_link' : '').'" onclick="return LoadTable(\'load_hidden_comments\', \'comm_div\');">'.$lang_admin['Show hidden comments'].'</a>',
		'<a href="#" class="js'.(strpos($mode, 'last') !== false ? ' cur_link' : '').'" onclick="return LoadTable(\'load_last_comments\', \'comm_div\');">'.$lang_admin['Show last comments'].'</a>',
	);

	($hook = s2_hook('fn_comment_menu_links_end')) ? eval($hook) : null;
	return '<p class="p-js">'.implode('', $output).'</p>';
}

// Displays the comments tables
function s2_show_comments ($mode, $id = 0)
{
	global $s2_db, $session_id, $lang_admin, $s2_user;

	// Getting comments
	$query = array(
		'SELECT'	=> 'a.title, c.article_id, c.id, c.time, c.nick, c.email, c.show_email, c.subscribed, c.text, c.shown, c.sent, c.good, c.ip',
		'FROM'		=> 'art_comments AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'articles AS a',
				'ON'			=> 'a.id = c.article_id'
			)
		),
		'WHERE'		=> 'c.article_id = '.$id,
		'ORDER BY'	=> 'time'
	);

	$output = '';
	if ($mode == 'hidden')
	{
		// Show all hidden commetns
		$query['WHERE'] = 'shown = 0';
		$output = '<h2>'.$lang_admin['Hidden comments'].'</h2>';
	}
	elseif ($mode == 'new')
	{
		// Show unverified commetns
		$query['WHERE'] = 'shown = 0 AND sent = 0';
		$output = '<h2>'.$lang_admin['New comments'].'</h2>';
	}
	elseif ($mode == 'last')
	{
		// Show last 20 commetns
		unset($query['WHERE']);
		$query['ORDER BY'] = 'time DESC';
		$query['LIMIT'] = '20';
		$output = '<h2>'.$lang_admin['Last comments'].'</h2>';
	}

	($hook = s2_hook('fn_show_comments_pre_get_comm_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$article_titles = $comments_tables = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		// Do not show anyone hidden comments
		if (!$row['shown'] && !$s2_user['view_hidden'])
			continue;

		// Preparing row style
		$class = array();
		if (!$row['shown'])
			$class['hidden'] = 'hidden';
		if ($row['good'])
			$class['good'] = 'good';

		($hook = s2_hook('fn_show_comments_pre_class_merge')) ? eval($hook) : null;
		$class = !empty($class) ? ' class="'.implode(' ', $class).'"' : '';

		// Preparing row buttons
		$buttons = array();
		$buttons['edit'] = '<img class="edit" src="i/1.gif" alt="'.$lang_admin['Edit'].'" onclick="return LoadCommentsTable(\'edit_comment\', '.$row['id'].', \''.$mode.'\');" />';
		$buttons['hide'] =  $row['shown'] ?
			'<img class="hide" src="i/1.gif" alt="'.$lang_admin['Hide'].'" onclick="return LoadCommentsTable(\'hide_comment\', '.$row['id'].', \''.$mode.'\');" />' :
			'<img class="show" src="i/1.gif" alt="'.$lang_admin['Show'].'" onclick="return LoadCommentsTable(\'hide_comment\', '.$row['id'].', \''.$mode.'\')" />' . (
                !$row['sent'] ? '<img class="hide" src="i/1.gif" alt="'.$lang_admin['Leave hidden'].'" onclick="return LoadCommentsTable(\'hide_comment\', '.$row['id'].', \''.$mode.'\', \'1\');" />' : ''
            ) ;

		$buttons['mark'] = $row['good'] ?
			'<img class="unmark" src="i/1.gif" alt="'.$lang_admin['Unmark comment'].'" onclick="return LoadCommentsTable(\'mark_comment\', '.$row['id'].', \''.$mode.'\');" />' :
			'<img class="mark" src="i/1.gif" alt="'.$lang_admin['Mark comment'].'" onclick="return LoadCommentsTable(\'mark_comment\', '.$row['id'].', \''.$mode.'\');" />';

		$buttons['delete'] = '<img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete'].'" onclick="return DeleteComment('.$row['id'].', \''.$mode.'\');" />';

		($hook = s2_hook('fn_show_comments_pre_buttons_merge')) ? eval($hook) : null;
		$buttons = '<span class="buttons">'.implode('', $buttons).'</span>';

		if ($s2_user['view_hidden'])
		{
			$ip = $row['ip'];

			$email_status = array();
			if (!$row['show_email'])
				$email_status['hidden'] = $lang_admin['Hidden'];
			if ($row['subscribed'])
				$email_status['subscribed'] = $lang_admin['Subscribed'];

			($hook = s2_hook('fn_show_comments_pre_email_status_merge')) ? eval($hook) : null;
			$email_status_merged = !empty($email_status) ? ' ('.implode(', ', $email_status).')' : '';

			($hook = s2_hook('fn_show_comments_pre_email_merge')) ? eval($hook) : null;
			$email = '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a>'.$email_status_merged;
		}
		else
		{
			$email = '';
			$ip = '';
		}

		if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
			require S2_ROOT.'_include/comments.php';

		($hook = s2_hook('fn_show_comments_pre_table_row_merge')) ? eval($hook) : null;
		$comments_tables[$row['article_id']][] = '<tr'.$class.'><td>'.s2_htmlencode($row['nick']).'</td><td>'.s2_bbcode_to_html(s2_htmlencode($row['text'])).'</td><td>'.date("Y-m-d, H:i", $row['time']).'</td><td>'.$ip.'</td><td>'.$email.'</td><td>'.$buttons.'</td></tr>';
		$article_titles[$row['article_id']] = $row['title'];
	}

	if (empty($comments_tables))
	{
		$output .= '<div class="info-box"><p>'.$lang_admin['No comments'].'</p></div>';
	}
	else
	{
		if ($mode == 'new' && count($article_titles))
			$output .= '<div class="info-box"><p>'.$lang_admin['Premoderation info'].'</p></div>';

		($hook = s2_hook('fn_show_comments_after_table_merge')) ? eval($hook) : null;

		foreach ($article_titles as $article_id => $title)
		{
			$output_header = '<h3><a href="#" title="'.$lang_admin['Go to editor'].'" onclick="return EditArticle('.$article_id.');">&larr; '.s2_htmlencode($title).'</a></h3>';
			$output_subheader = $mode != 'all' ? '<a href="#" title="'.sprintf($lang_admin['All comments to'], s2_htmlencode($title)).'" onclick="return LoadComments('.$article_id.');">'.$lang_admin['All comments'].'</a>' : '';
			$output_body =
				'<table class="sort" width="100%">'.
					'<thead><tr><td width="8%" class="sortable">'.$lang_admin['Name'].'</td><td class="sortable">'.$lang_admin['Comment'].'</td><td width="8%" class="sortable curcol_down">'.$lang_admin['Date'].'</td><td width="8%" class="sortable">'.$lang_admin['IP'].'</td><td width="10%" class="sortable">'.$lang_admin['Email'].'</td><td width="64px">&nbsp;</td></tr></thead>'.
					'<tbody>'.implode('', strpos($mode, 'last') !== false ? array_reverse($comments_tables[$article_id]) : $comments_tables[$article_id]).'</tbody>'.
				'</table>';

			($hook = s2_hook('fn_show_comments_pre_output_merge')) ? eval($hook) : null;
			$output .= $output_header.$output_subheader.$output_body;
		}
	}

	($hook = s2_hook('fn_show_comments_end')) ? eval($hook) : null;
	return $output;
}

// Displays hidden comments and switches to the comments tab
// if premoderation is enabled.
function s2_for_premoderation ()
{
	global $s2_db, $s2_user, $lang_admin;

	if (!S2_PREMODERATION || !$s2_user['hide_comments'])
		return array('content' => s2_comment_menu_links());

	// Check if there are new comments
	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'shown = 0 AND sent = 0'
	);
	($hook = s2_hook('fn_for_premoderation_pre_comm_check_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);
	$new_comment_count = $s2_db->result($result);

	($hook = s2_hook('fn_for_premoderation_pre_comm_check')) ? eval($hook) : null;
	if (!$new_comment_count)
		return array('content' => s2_comment_menu_links());

	$output = array(
		'content'	=> s2_comment_menu_links('new').s2_show_comments('new'),
		'script'	=> 'PopupMessages.show(\''.$lang_admin['Unchecked comments'].'\', [{name: \''.$lang_admin['View comments'].'\', action: function () {selectTab(\'#comm_tab\'); LoadTable(\'load_new_comments\', \'comm_div\');}, once: true}]);'."\n",
	);

	($hook = s2_hook('fn_for_premoderation_end')) ? eval($hook) : null;
	return $output;
}

function s2_output_comment_form ($comment, $mode, $type)
{
	global $lang_admin;

	($hook = s2_hook('fn_output_comment_form_start')) ? eval($hook) : null;

?>
<div class="height_wrap" style="padding-bottom: 2.167em">
	<?php echo s2_comment_menu_links(); ?>
	<form class="full_tab_form" name="commform" action="" onsubmit="SaveComment('<?php echo $type; ?>'); return false;">
		<div class="main-column vert-flex">
			<table class="fields">
<?php ($hook = s2_hook('fn_output_comment_form_pre_name')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Name']; ?></td>
					<td><input type="text" name="comment[nick]" size="100" maxlength="255" value="<?php echo s2_htmlencode($comment['nick']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_comment_form_pre_email')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Email']; ?></td>
					<td><input type="text" name="comment[email]" size="100" maxlength="255" value="<?php echo s2_htmlencode($comment['email']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_comment_form_after_email')) ? eval($hook) : null; ?>
			</table>
			<input type="hidden" name="comment[id]" value="<?php echo $comment['id']; ?>" />
			<input type="hidden" name="mode" value="<?php echo s2_htmlencode($mode); ?>" />
<?php ($hook = s2_hook('fn_output_comment_form_pre_text')) ? eval($hook) : null; ?>
			<div class="text_wrapper">
				<textarea id="commtext" class="full_textarea" name="comment[text]"><?php echo s2_htmlencode($comment['text']); ?></textarea>
			</div>
		</div>
        <div class="aside-column">
            <label for="eml"><input type="checkbox" id="eml" name="comment[show_email]" value="1"<?php if ($comment['show_email']) echo ' checked="checked"'?> />
                <?php echo $lang_admin['Show email']; ?></label>
            <label for="sbs"><input type="checkbox" id="sbs" name="comment[subscribed]" value="1"<?php if ($comment['subscribed']) echo ' checked="checked"'?> />
                <?php echo $lang_admin['Subscribed']; ?></label>
            <?php ($hook = s2_hook('fn_output_comment_form_after_checkboxes')) ? eval($hook) : null; ?>
            <hr />
            <?php ($hook = s2_hook('fn_output_comment_form_pre_submit')) ? eval($hook) : null; ?>
            <input class="bitbtn savecomment" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>" />
        </div>
	</form>
</div>
<?php

}

// Actions

function s2_get_comment ($id)
{
	global $s2_db;

	// Get comment
	$query = array(
		'SELECT'	=> 'id, nick, email, text, show_email, subscribed',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_get_comment_pre_get_cmnt_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$comment = $s2_db->fetch_assoc($result);

	return $comment;
}

/**
 * @param int  $id
 * @param bool $leaveHidden Set true for spam
 * @return mixed
 */
function s2_toggle_hide_comment ($id, bool $leaveHidden = false)
{
	global $s2_db;

	// Does the comment exist?
	// We need article_id for displaying comments.
	// Also we need the comment if the premoderation is turned on.
	$query = array(
		'SELECT'	=> 'article_id, sent, shown, nick, email, text',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_toggle_hide_comment_pre_get_comment_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	$comment = $s2_db->fetch_assoc($result);
	if (!$comment)
		die('Comment not found!');

	$sent = 1;
	if (!$comment['shown'] && !$comment['sent'] && !$leaveHidden)
	{
		// Premoderation is enabled and we have to send the comment to be shown
		// to subscribed commentators
		if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
			require S2_ROOT.'_include/comments.php';

		// Getting some info about the article commented
		$query = array(
			'SELECT'	=> 'title, parent_id, url',
			'FROM'		=> 'articles',
			'WHERE'		=> 'id = '.$comment['article_id'].' AND published = 1 AND commented = 1'
		);
		($hook = s2_hook('fn_toggle_hide_comment_pre_get_page_info_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		if (($article = $s2_db->fetch_assoc($result)) && ($path = Model::path_from_id($article['parent_id'], true)) !== false)
		{
			$link = s2_abs_link($path.'/'.urlencode($article['url']));

			// Fetching receivers' names and adresses
			$query = array(
				'SELECT'	=> 'id, nick, email, ip, time',
				'FROM'		=> 'art_comments',
				'WHERE'		=> 'article_id = '.$comment['article_id'].' AND subscribed = 1 AND shown = 1 AND email <> \''.$s2_db->escape($comment['email']).'\''
			);
			($hook = s2_hook('fn_toggle_hide_comment_pre_get_receivers_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query);

			$receivers = array();
			while ($receiver = $s2_db->fetch_assoc($result))
				$receivers[$receiver['email']] = $receiver;

			foreach ($receivers as $receiver)
			{
				$unsubscribe_link = S2_BASE_URL.'/comment.php?mail='.urlencode($receiver['email']).'&id='.$comment['article_id'].'.&unsubscribe='.base_convert(substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16), 16, 36);
				s2_mail_comment($receiver['nick'], $receiver['email'], $comment['text'], $article['title'], $link, $comment['nick'], $unsubscribe_link);
			}
		}
		else
			$sent = 0;
	}

	// Toggle comment visibility
	$query = array(
		'UPDATE'	=> 'art_comments',
		'SET'		=> !$leaveHidden ? 'shown = 1 - shown, sent = '.$sent : 'shown = 0, sent = 1',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_toggle_hide_comment_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $comment['article_id'];
}

function s2_toggle_mark_comment ($id)
{
	global $s2_db;

	// Does the comment exist?
	// We need article_id for displaying comments
	$query = array(
		'SELECT'	=> 'article_id',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_toggle_mark_comment_pre_get_aid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_row($result))
		$article_id = $row[0];
	else
		die('Comment not found!');

	// Mark comment
	$query = array(
		'UPDATE'	=> 'art_comments',
		'SET'		=> 'good = 1 - good',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_toggle_mark_comment_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $article_id;
}

function s2_save_comment ($comment)
{
	global $s2_db;


	$nick = $s2_db->escape($comment['nick']);
	$email = $s2_db->escape($comment['email']);
	$text = $s2_db->escape($comment['text']);
	$id = (int) $comment['id'];

	$show_email = (int) isset($comment['show_email']);
	$subscribed = (int) isset($comment['subscribed']);

	$type = isset($_GET['type']) ? $_GET['type'] : '';

	if ($type == 'site')
	{
		// Does the comment exist?
		// We need article_id for displaying comments
		$query = array(
			'SELECT'	=> 'article_id',
			'FROM'		=> 'art_comments',
			'WHERE'		=> 'id = '.$id
		);
		($hook = s2_hook('fn_save_comment_pre_get_aid_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		if ($row = $s2_db->fetch_row($result))
			$article_id = $row[0];
		else
			die('Comment not found!');

		// Save comment
		$query = array(
			'UPDATE'	=> 'art_comments',
			'SET'		=> "nick = '$nick', email = '$email', text = '$text', show_email = '$show_email', subscribed = '$subscribed'",
			'WHERE'		=> 'id = '.$id
		);
		($hook = s2_hook('fn_save_comment_pre_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query);
	}

	($hook = s2_hook('fn_save_comment_end')) ? eval($hook) : null;

	return $article_id;
}

function s2_delete_comment ($id)
{
	global $s2_db;

	// Does the comment exist?
	// We need article_id for displaying the other comments
	$query = array(
		'SELECT'	=> 'article_id',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_comment_pre_get_aid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_row($result))
		$article_id = $row[0];
	else
		die('Comment not found!');

	$query = array(
		'DELETE'	=> 'art_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_comment_pre_del_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	return $article_id;
}
