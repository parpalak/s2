<?php
/**
 * Common functions for the admin panel and pages generation
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

//
// Time conversion (for forms)
//

function s2_array_from_time ($time)
{
	$return = ($hook = s2_hook('fn_array_from_time')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	$array['hour'] = $time ? date('H', $time) : '';
	$array['min'] = $time ? date('i', $time) : '';
	$array['day'] = $time ? date('d', $time) : '';
	$array['mon'] = $time ? date('m', $time) : '';
	$array['year'] = $time ? date('Y', $time) : '';

	return $array;
}

function s2_time_from_array ($array)
{

	$return = ($hook = s2_hook('fn_time_from_array')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	$hour = isset($array['hour']) ? (int) $array['hour'] : 0;
	$min = isset($array['min']) ? (int) $array['min'] : 0;
	$day = isset($array['day']) ? (int) $array['day'] : 0;
	$mon = isset($array['mon']) ? (int) $array['mon'] : 0;
	$year = isset($array['year']) ? (int) $array['year'] : 0;

	return checkdate($mon, $day, $year) ? mktime($hour, $min, 0, $mon, $day, $year) : 0;
}

//
// "Smart" replacing "\n\n" to '</p><p>' and "\n" to '<br />'
//

function s2_cut_br ($s)
{
	return substr($s, -6) == '<br />' ? substr($s, 0, -6) : $s;
}

function s2_nl2p ($text)
{
	$text = str_replace ("\r", '', $text);
	$paragraphs = explode("\n\n", $text); // split on empty lines
	for ($i = count($paragraphs); $i-- ;)
	{
		// We are working with non-empty contents
		if (!trim($paragraphs[$i]))
			continue;

		$lines = explode("\n", $paragraphs[$i]); // split on new lines
		// Removing spaces
		for ($j = count($lines); $j-- ;)
		{
			$lines[$j] = rtrim($lines[$j]);
			if ($lines[$j] == '<br />')
				$lines[$j] = '';
		}

		// There is no \r symbol, we've removed them all. We introduce it now
		// to replace a bit later
		$lines = implode("\r", $lines);

		// "Smart" replacing \n -> <br />
		if (strpos($lines, 'pre>') === false &&
			strpos($lines, 'script>') === false &&
			strpos($lines, 'style>') === false &&
			strpos($lines, 'ol>') === false &&
			strpos($lines, 'ul>') === false &&
			strpos($lines, 'li>') === false)
		{
			$lines = str_replace ("p>\r", "p>\n", $lines);
			$lines = str_replace ("<br />\r", "<br />\n", $lines);
			$lines = str_replace ("\r<p", "\n<p", $lines);
			$lines = str_replace ("\r", "<br />\n", $lines);
		}
		else
			$lines = str_replace ("\r", "\n", $lines);

		// Fixing <p> tag
		if (strpos($lines, '<p') === false &&
			strpos($lines, 'blockquote') === false &&
			strpos($lines, 'pre>') === false &&
			strpos($lines, 'script>') === false &&
			strpos($lines, 'style>') === false &&
			strpos($lines, 'h2>') === false &&
			strpos($lines, 'h3>') === false &&
			strpos($lines, 'h4>') === false &&
			strpos($lines, 'ol>') === false &&
			strpos($lines, 'ul>') === false &&
			strpos($lines, 'li>') === false)
		{
			if ((strpos($lines, '</p>') === false))
				$lines = '<p>'.s2_cut_br($lines).'</p>'; // No paragraph tag
			else //there is </p> but <p>
				$lines = '<p>'.$lines; // Paragraph tag isn't opened
		}
		if (strpos($lines, '<p') !== false && strpos($lines, '</p>') === false) //there is <p> but </p>
			$lines = s2_cut_br($lines).'</p>'; // Paragraph tag isn't closed

		$paragraphs[$i] = $lines;
	}

	return implode("\n\n", $paragraphs);
}

//
// Articles tree handling
//

function s2_delete_branch ($id)
{
	global $s2_db;

	$return = ($hook = s2_hook('fn_delete_branch_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	$query = array(
		'SELECT'	=> 'id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'parent_id = '.$id
	);
	($hook = s2_hook('fn_delete_branch_pre_get_ids_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_row($result))
		s2_delete_branch($row[0]);

	$query = array(
		'DELETE'	=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_delete_branch_pre_del_art_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'DELETE'	=> 'article_tag',
		'WHERE'		=> 'article_id = '.$id
	);
	($hook = s2_hook('fn_delete_branch_pre_del_tags_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'DELETE'	=> 'art_comments',
		'WHERE'		=> 'article_id = '.$id
	);
	($hook = s2_hook('fn_delete_branch_pre_del_comments_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

//
// Builds HTML tree for the admin panel
//

function s2_get_child_branches ($id, $root = true)
{
	global $s2_db;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'a.id = c.article_id'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'title, id, priority, published, ('.$raw_query.') as comments_count, parent_id',
		'FROM'		=> 'articles AS a',
		'WHERE'		=> 'parent_id = '.$id,
		'ORDER BY'	=> 'priority'
	);
	($hook = s2_hook('fn_get_child_branches_pre_get_art_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = '';
	for ($i = $s2_db->num_rows($result); $i-- ;)
	{
		$article = $s2_db->fetch_assoc($result);

		$expand = '<div></div>';
		$strike = $article['published'] ? '' : ' style="text-decoration: line-through;"';
		$comments = $article['comments_count'] ? ' comm="'.$article['comments_count'].'"' : '';
		$span = '<div><span id="'.$article['id'].'"'.$strike.$comments.'>'.$article['title'].'</span></div>';
		$children = s2_get_child_branches($article['id'], false);

		($hook = s2_hook('fn_get_child_branches_after_get_branch')) ? eval($hook) : null;
		$output .= '<li class="'.($children ? 'ExpandClosed' : 'ExpandLeaf').(!$i ? ' IsLast' : '' ).'">'.$expand.$span.$children.'</li>';
	}

	($hook = s2_hook('fn_get_child_branches_end')) ? eval($hook) : null;
	return $output && !$root ? '<ul>'.$output.'</ul>' : $output;
}

//
// Functions below generate HTML-code for some pages in the admin panel
//

function s2_get_time_input ($name, $values)
{
	global $lang_admin;

	$replace = array(
		'[hour]'	=> '<input type="text" class="char-2" name="'.$name.'[hour]" size="2" value="'.$values['hour'].'" />',
		'[minute]'	=> '<input type="text" class="char-2" name="'.$name.'[min]" size="2" value="'.$values['min'].'" />',
		'[day]'		=> '<input type="text" class="char-2" name="'.$name.'[day]" size="2" value="'.$values['day'].'" />',
		'[month]'	=> '<input type="text" class="char-2" name="'.$name.'[mon]" size="2" value="'.$values['mon'].'" />',
		'[year]'	=> '<input type="text" class="char-4" name="'.$name.'[year]" size="4" value="'.$values['year'].'" />'
	);

	$format = $lang_admin['Imput time format'];

	($hook = s2_hook('fn_get_time_input')) ? eval($hook) : null;
	return str_replace(array_keys($replace), array_values($replace), $format);
}

function s2_output_article_form ($id)
{
	global $s2_db, $lang_templates, $lang_common, $lang_admin;

	($hook = s2_hook('fn_output_article_form_start')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'title, meta_keys, meta_desc, citation, pagetext as text, create_time, modify_time, published, favorite, commented, url, children_preview, template, parent_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_output_article_form_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$page = $s2_db->fetch_assoc($result);

	$page['id'] = $id;

	if (!$page['published'])
	{
		$required_rights = array('view_hidden');
		($hook = s2_hook('fn_output_article_form_pre_perm_check')) ? eval($hook) : null;
		s2_test_user_rights($GLOBALS['session_id'], $required_rights);
	}

	$cr_time = s2_array_from_time($page['create_time']);
	$m_time = s2_array_from_time($page['modify_time']);

	($hook = s2_hook('fn_output_article_form_pre_output')) ? eval($hook) : null;

?>
<form name="artform" action="" onsubmit="setTimeout('SaveArticle(\'save\');', 0); return false;">
	<div class="l-float pad-1">
		<div class="border">
			<table class="fields">
<?php ($hook = s2_hook('fn_output_article_form_pre_title')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Title']; ?></td>
					<td><input type="text" name="page[title]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['title']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_article_form_pre_mkeys')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Meta keywords']; ?></td>
					<td><input type="text" name="page[meta_keys]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['meta_keys']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_article_form_pre_mdesc')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Meta descr']; ?></td>
					<td><input type="text" name="page[meta_desc]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['meta_desc']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_article_form_pre_cite')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Cite']; ?></td>
					<td><input type="text" name="page[citation]" size="100" value="<?php echo s2_htmlencode($page['citation']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_article_form_after_cite')) ? eval($hook) : null; ?>
			</table>
<?php ($hook = s2_hook('fn_output_article_form_after_fields1')) ? eval($hook) : null; ?>
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

	($hook = s2_hook('fn_output_article_form_after_fields2')) ? eval($hook) : null;

	s2_toolbar();
	$padding = 14.1;
	($hook = s2_hook('fn_output_article_form_pre_text')) ? eval($hook) : null;

?>
			<div class="text_wrapper" style="padding-bottom: <?php echo $padding; ?>em;">
				<textarea id="wText" name="page[text]"><?php echo s2_htmlencode($page['text']); ?></textarea>
			</div>
		</div>
	</div>
<?php ($hook = s2_hook('fn_output_article_form_pre_btn_col')) ? eval($hook) : null; ?>
	<div class="r-float">
<?php ($hook = s2_hook('fn_output_article_form_pre_checkboxes')) ? eval($hook) : null; ?>
		<input type="checkbox" id="pub" name="flags[published]" value="1"<? if ($page['published']) echo ' checked="checked"' ?> />
		<label for="pub"><?php echo $lang_admin['Published']; ?></label>
		<br />
		<input type="checkbox" id="fav" name="flags[favorite]" value="1"<? if ($page['favorite']) echo ' checked="checked"' ?> />
		<label for="fav"><?php echo $lang_common['Favorite']; ?></label>
		<br />
		<input type="checkbox" id="com" name="flags[commented]" value="1"<? if ($page['commented']) echo ' checked="checked"' ?> />
		<label for="com"><?php echo $lang_admin['Commented']; ?></label>
<?php ($hook = s2_hook('fn_output_article_form_after_checkboxes')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_url')) ? eval($hook) : null; ?>
		<?php echo $lang_admin['URL part']; ?>
		<br />
		<input type="text" name="page[url]" size="15" maxlength="255" value="<?php echo $page['url']; ?>" />
		<br />
<?php ($hook = s2_hook('fn_output_article_form_pre_template')) ? eval($hook) : null; ?>
		<?php echo $lang_admin['Template']; ?><br />
		<select name="page[template]">
<?php

	foreach ($lang_templates as $n => $v)
		echo "\t\t\t".'<option value="'.$n.'"'.($page['template'] == $n ? ' selected' : '').'>'.$v.'</option>'."\n";

?>
		</select>
		<br />
<?php ($hook = s2_hook('fn_output_article_form_pre_subcontent')) ? eval($hook) : null; ?>
		<input type="checkbox" id="subarticles" name="flags[children_preview]" value="1"<? if ($page['children_preview']) echo ' checked="checked"' ?> />
		<label for="subarticles"><?php echo $lang_admin['Subcontent']; ?></label>
<?php ($hook = s2_hook('fn_output_article_form_after_subcontent')) ? eval($hook) : null; ?>
		<hr />
		<input type="hidden" name="page[id]" value="<?php echo $page['id']; ?>" />
<?php ($hook = s2_hook('fn_output_article_form_pre_parag_btn')) ? eval($hook) : null; ?>
		<input class="bitbtn parag" type="button" value="<?php echo $lang_admin['Paragraphs']; ?>" onclick="return Paragraph();" />
<?php ($hook = s2_hook('fn_output_article_form_after_parag_btn')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_reset')) ? eval($hook) : null; ?>
		<input class="bitbtn reset" type="reset" value="<?php echo $lang_admin['Reset']; ?>" onclick="return confirm(S2_LANG_RESET_PROMPT);" />
		<br />
<?php ($hook = s2_hook('fn_output_article_form_pre_clear')) ? eval($hook) : null; ?>
		<input class="bitbtn new" type="button" value="<?php echo $lang_admin['Clear']; ?>" onclick="ClearForm(); return false;" />
<?php ($hook = s2_hook('fn_output_article_form_after_clear')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_save')) ? eval($hook) : null; ?>
		<input class="bitbtn save" name="button" type="submit" value="<?php echo $lang_admin['Save']; ?>" />
<?php ($hook = s2_hook('fn_output_article_form_after_save')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_prv')) ? eval($hook) : null; ?>
		<img src="i/monitor.png" alt="<?php echo $lang_admin['Preview ready']; ?>" width="16" height="16" onclick="window.open(sUrl + 'action=preview&id=<?php echo $page['id']; ?>', 'previewwindow', 'scrollbars=yes,toolbar=yes', 'True'); return false;" />
<?php ($hook = s2_hook('fn_output_article_form_after_prv')) ? eval($hook) : null; ?>
	</div>
<?php ($hook = s2_hook('fn_output_article_form_after_cols')) ? eval($hook) : null; ?>
</form>
<?

}

function s2_comment_menu_links ($mode = false)
{
	global $lang_admin;

	$output = array(
		'<a href="#" class="js'.($mode == 'new' ? ' curr' : '').'" onclick="return LoadTable(\'load_new_comments\', \'comm_div\');">'.$lang_admin['Show new comments'].'</a>',
		'<a href="#" class="js'.($mode == 'hidden' ? ' curr' : '').'" onclick="return LoadTable(\'load_hidden_comments\', \'comm_div\');">'.$lang_admin['Show hidden comments'].'</a>',
		'<a href="#" class="js'.($mode == 'last' ? ' curr' : '').'" onclick="return LoadTable(\'load_last_comments\', \'comm_div\');">'.$lang_admin['Show last comments'].'</a>',
	);

	($hook = s2_hook('fn_comment_menu_links_end')) ? eval($hook) : null;
	return '<p class="js">'.implode('', $output).'</p>';
}

// Displays the comments tables
function s2_show_comments ($mode, $id = 0)
{
	global $s2_db, $session_id, $lang_admin;

	// Getting comments
	$query = array(
		'SELECT'	=> 'a.title, c.article_id, c.id, c.time, c.nick, c.email, c.show_email, c.subscribed, c.text, c.shown, c.good, c.ip',
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
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$s2_db->num_rows($result))
		return $output.'<div class="info-box"><p>'.$lang_admin['No comments'].'</p></div>';

	// Determine if we can show hidden info like e-mails and IP
	$query = array(
		'SELECT'	=> 'view_hidden',
		'FROM'		=> 'users_online AS o',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'users AS u',
				'ON'			=> 'u.login = o.login'
			)
		),
		'WHERE'		=> 'challenge = \''.$s2_db->escape($session_id).'\''
	);
	($hook = s2_hook('fn_show_comments_pre_get_perm_qr')) ? eval($hook) : null;
	$result2 = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$show_hidden = $s2_db->num_rows($result2) == 1 && $s2_db->result($result2);

	$article_titles = $comments_tables = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
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
		$buttons['edit'] = '<img src="i/ce.png" alt="'.$lang_admin['Edit'].'" width="16" height="16" onclick="return DoAction(\'edit_comment\', '.$row['id'].', \'comm_div\');" />';
		$buttons['hide'] =  $row['shown'] ?
			'<img src="i/cd.png" alt="'.$lang_admin['Hide'].'" width="16" height="16" onclick="return DoAction(\'hide_comment\', '.$row['id'].', \'comm_div\');" />' :
			'<img src="i/ca.png" alt="'.$lang_admin['Show'].'" width="16" height="16" onclick="return DoAction(\'hide_comment\', '.$row['id'].', \'comm_div\')" />' ;

		$buttons['mark'] = $row['good'] ?
			'<img src="i/thumb_down.png" alt="'.$lang_admin['Unmark comment'].'" width="16" height="16" onclick="return DoAction(\'mark_comment\', '.$row['id'].', \'comm_div\');" />' :
			'<img src="i/thumb_up.png" alt="'.$lang_admin['Mark comment'].'" width="16" height="16" onclick="return DoAction(\'mark_comment\', '.$row['id'].', \'comm_div\');" />';

		$buttons['delete'] = '<img src="i/cross.png" alt="'.$lang_admin['Delete'].'" width="16" height="16" onclick="return DeleteComment('.$row['id'].');" />';

		($hook = s2_hook('fn_show_comments_pre_buttons_merge')) ? eval($hook) : null;
		$buttons = '<nobr>'.implode('', $buttons).'</nobr>';

		if ($show_hidden)
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

		($hook = s2_hook('fn_show_comments_pre_table_row_merge')) ? eval($hook) : null;
		$comments_tables[$row['article_id']][] = '<tr'.$class.'><td>'.s2_htmlencode($row['nick']).'</td><td class="content">'.s2_htmlencode($row['text']).'</td><td>'.date("Y/m/d, H:i", $row['time']).'</td><td>'.$ip.'</td><td>'.$email.'</td><td>'.$buttons.'</td></tr>';
		$article_titles[$row['article_id']] = $row['title'];
	}

	foreach ($article_titles as $article_id => $title)
		$output .= '<h3>'.sprintf($lang_admin['Comments to'], $title).'</h3>'.
			'<table class="sort" width="100%">'.
				'<thead><tr><td width="8%">'.$lang_admin['Name'].'</td><td>'.$lang_admin['Comment'].'</td><td width="8%">'.$lang_admin['Date'].'</td><td width="8%">'.$lang_admin['IP'].'</td><td width="10%">'.$lang_admin['Email'].'</td><td width="64px">&nbsp;</td></tr></thead>'.
				'<tbody>'.implode('', $comments_tables[$article_id]).'</tbody>'.
			'</table>';

	($hook = s2_hook('fn_show_comments_end')) ? eval($hook) : null;
	return $output;
}

// Column with articles that have the tag specified
function s2_get_tag_articles ($tag_id)
{
	global $s2_db, $lang_admin;

	$query = array(
		'SELECT'	=> 'art.title, atg.id as link_id',
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
		$list .= '<li><img src="i/delete.png" alt="'.$lang_admin['Delete from list'].'" onclick="DeleteKey('.$row['link_id'].');" />'.$row['title'].'</li>';

	return $list ? '<ul>' . $list . '</ul>' : '';
}

// A button that change a permission
function s2_grb ($b, $login, $permission, $allow_modify)
{
	global $lang_admin;

	$src = $b ? 'tick' : 'cross';
	$alt = $b ? $lang_admin['Deny'] : $lang_admin['Allow'];
	return '<img src="i/'.$src.'.png" height="16" width="16" alt="'.($allow_modify ? $alt : '').'" '.($allow_modify ? 'onclick="return SetPermission(\''.s2_htmlencode(addslashes($login)).'\', \''.$permission.'\');" ' : '').'/>';
}

function s2_get_user_list ($cur_login, $is_admin = false)
{
	global $s2_db, $lang_user_permissions, $lang_admin;

	$s2_user_permissions = array(
		'view',
		'view_hidden',
		'hide_comments',
		'edit_comments',
		'edit_site',
		'edit_users'
	);
	($hook = s2_hook('fn_get_user_list_start')) ? eval($hook) : null;

	if (!$is_admin)
	{
		// Extra permissions check
		$query = array(
			'SELECT'	=> 'edit_users',
			'FROM'		=> 'users',
			'WHERE'		=> 'login = \''.$s2_db->escape($cur_login).'\''
		);
		($hook = s2_hook('fn_get_user_list_pre_get_perm_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		$is_admin = $s2_db->num_rows($result) == 1 && $s2_db->result($result) == 1;
	}

	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'users'
	);

	($hook = s2_hook('fn_get_user_list_pre_select_query')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$body = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$login = $row['login'];
		$email = $row['email'] ? s2_htmlencode($row['email']) : '———';
		$email_link = $is_admin || $login == $cur_login ?
			'<a href="#" class="js" title="'.($row['email'] ? $lang_admin['Change email'] : $lang_admin['Set email']).'" onclick="return SetUserEmail(\''.s2_htmlencode(addslashes($login)).'\', \''.s2_htmlencode(addslashes($row['email'])).'\');">'.$email.'</a>' :
			$email;

		$permissions = array();
		foreach ($s2_user_permissions as $permission)
			$permissions[$permission] = '<td>'.s2_grb($row[$permission], $login, $permission, $is_admin).'</td>';

		($hook = s2_hook('fn_get_user_list_loop_pre_perm_merge')) ? eval($hook) : null;
		$permissions = implode('', $permissions);

		$buttons = array();
		if ($is_admin || $login == $cur_login)
			$buttons['change_pass'] = '<img src="i/textfield.png" width="16" height="16" alt="'.$lang_admin['Change password'].'" onclick="return SetUserPassword(\''.s2_htmlencode(addslashes($login)).'\');">';
		if ($is_admin)
			$buttons['delete'] = '<img src="i/delete.png" width="16" height="16" alt="'.$lang_admin['Delete user'].'" onclick="return DeleteUser(\''.s2_htmlencode(addslashes($login)).'\');">';

		($hook = s2_hook('fn_get_user_list_loop_pre_buttons_merge')) ? eval($hook) : null;
		$buttons = '<td><nobr>'.implode('', $buttons).'</nobr></td>';

		($hook = s2_hook('fn_get_user_list_loop_pre_row_merge')) ? eval($hook) : null;
		$body[] = '<td>'.s2_htmlencode($login).'</td>'.$permissions.'<td>'.$email_link.'</td>'.$buttons;
	}

	$thead = array();
	$thead['login'] = '<td>'.$lang_admin['Login'].'</td>';

	($hook = s2_hook('fn_get_user_list_pre_loop_thead_perm')) ? eval($hook) : null;

	foreach ($s2_user_permissions as $permission)
		$thead[$permission] = '<td>'.$lang_user_permissions[$permission].'</td>';

	$thead['email'] = '<td>'.$lang_admin['Email'].'</td>';
	$thead['buttons'] = '<td>&nbsp;</td>';

	($hook = s2_hook('fn_get_user_list_pre_thead_merge')) ? eval($hook) : null;
	$thead = implode('', $thead);

	($hook = s2_hook('fn_get_user_list_pre_table_merge')) ? eval($hook) : null;
	$table = '<table width="100%" class="sort"><thead><tr>'.$thead.'</tr></thead><tbody><tr>'.implode('</tr><tr>', $body).'</tr></tbody></table>';

	($hook = s2_hook('fn_get_user_list_end')) ? eval($hook) : null;
	return $table;
}

// Displays hidden comments and switches to the comments tab
// if premoderation is enabled.
function s2_for_premoderation ()
{
	if (!S2_PREMODERATION)
		return s2_comment_menu_links();

	global $s2_db, $login, $lang_admin;

	// Check for permissions
	$query = array(
		'SELECT'	=> 'login',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$s2_db->escape($login).'\' AND hide_comments = 1'
	);
	($hook = s2_hook('fn_for_premoderation_pre_perm_check_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$s2_db->num_rows($result))
		return s2_comment_menu_links();

	// Check if there are new comments
	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'shown = 0 AND sent = 0'
	);
	($hook = s2_hook('fn_for_premoderation_pre_comm_check_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$new_comment_count = $s2_db->result($result);

	($hook = s2_hook('fn_for_premoderation_pre_comm_check')) ? eval($hook) : null;
	if (!$new_comment_count)
		return s2_comment_menu_links();

	$output = s2_comment_menu_links('new');
	$output .= '<div class="info-box"><p>'.$lang_admin['Premoderation info'].'</p></div>';
	$output .= '<script type="text/javascript">document.location.hash = "#comm";</script>';
	$output .= s2_show_comments('new');

	($hook = s2_hook('fn_for_premoderation_end')) ? eval($hook) : null;
	return $output;
}

// Loads an article and switches to the editor tab
// if the article is specified in GET parameters
function s2_preload_editor ()
{
	global $s2_db;

	if (!isset($_GET['path']))
		return;

	$return = ($hook = s2_hook('fn_preload_editor_start')) ? eval($hook) : null;
	if ($return)
		return;

	$request_array = explode('/', $_GET['path']);   //   []/[dir1]/[dir2]/[dir3]/[file1]

	// Remove last empty element
	if ($request_array[count($request_array) - 1] == '')
		unset($request_array[count($request_array) - 1]);

	$id = S2_ROOT_ID;
	$max = count($request_array);

	// Walking through page parents
	for ($i = 0; $i < $max; $i++)
	{
		$query = array (
			'SELECT'	=> 'id',
			'FROM'		=> 'articles',
			'WHERE'		=> 'url=\''.$s2_db->escape($request_array[$i]).'\' AND parent_id='.$id
		);
		($hook = s2_hook('fn_preload_editor_loop_pre_get_parents_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($s2_db->num_rows($result) != 1)
			return;

		$id = $s2_db->result($result);
	}

	s2_output_article_form($id);
	echo '<script type="text/javascript">document.location.hash = "#edit"; eTextarea = document.getElementById("wText");</script>';
}

function s2_toolbar ()
{
	global $lang_admin;

	$return = ($hook = s2_hook('fn_toolbar_start')) ? eval($hook) : null;
	if ($return !== null)
	{
		echo $return;
		return;
	}

?>
<hr />
<div class="pan">
	<img src="i/page_white.png" alt="<?php echo $lang_admin['Clear']; ?>" onclick="return ClearForm();" />
	&nbsp;
	<img src="i/b.png" alt="<?php echo $lang_admin['Bold']; ?>" onclick="return TagSelection('strong');" />
	<img src="i/i.png" alt="<?php echo $lang_admin['Italic']; ?>" onclick="return TagSelection('em');" />
	<img src="i/s.png" alt="<?php echo $lang_admin['Strike']; ?>" onclick="return TagSelection('s');" />
	&nbsp;
	<img src="i/link.png" alt="<?php echo $lang_admin['Link']; ?>" onclick="return InsertTag('<a href=&quot;&quot;>', '</a>');" />
	<img src="i/quote.png" alt="<?php echo $lang_admin['Quote']; ?>" onclick="return TagSelection('blockquote');" />
	<img src="i/picture.png" alt="<?php echo $lang_admin['Image']; ?>" onclick="return GetImage();" />
	&nbsp;
	<img src="i/big.png" alt="<?php echo $lang_admin['BIG']; ?>" onclick="return TagSelection('big');" />
	<img src="i/small.png" alt="<?php echo $lang_admin['SMALL']; ?>" onclick="return TagSelection('small');" />
	&nbsp;
	<img src="i/sup.png" alt="<?php echo $lang_admin['SUP']; ?>" onclick="return TagSelection('sup');" />
	<img src="i/sub.png" alt="<?php echo $lang_admin['SUB']; ?>" onclick="return TagSelection('sub');" />
	&nbsp;
	<img src="i/h2.png" alt="<?php echo $lang_admin['Header 2']; ?>" onclick="return TagSelection('h2');" />
	<img src="i/h3.png" alt="<?php echo $lang_admin['Header 3']; ?>" onclick="return TagSelection('h3');" />
	<img src="i/h4.png" alt="<?php echo $lang_admin['Header 4']; ?>" onclick="return TagSelection('h4');" />
	&nbsp;
	<img src="i/left.png" alt="<?php echo $lang_admin['Left']; ?>" onclick="return TagSelection('p');" />
	<img src="i/center.png" alt="<?php echo $lang_admin['Center']; ?>" onclick="return InsertTag('<p align=&quot;center&quot;>', '</p>');" />
	<img src="i/right.png" alt="<?php echo $lang_admin['Right']; ?>" onclick="return InsertTag('<p align=&quot;right&quot;>', '</p>');" />
	<img src="i/justify.png" alt="<?php echo $lang_admin['Justify']; ?>" onclick="return InsertTag('<p align=&quot;justify&quot;>', '</p>');" />
	&nbsp;
	<img src="i/ul.png" alt="<?php echo $lang_admin['UL']; ?>" onclick="return TagSelection('ul');" />
	<img src="i/ol.png" alt="<?php echo $lang_admin['OL']; ?>" onclick="return TagSelection('ol');" />
	<img src="i/li.png" alt="<?php echo $lang_admin['LI']; ?>" onclick="return TagSelection('li');" />
	&nbsp;
	<img src="i/pre.png" alt="<?php echo $lang_admin['PRE']; ?>" onclick="return TagSelection('pre');" />
	<img src="i/code.png" alt="<?php echo $lang_admin['CODE']; ?>" onclick="return TagSelection('code');" />
	&nbsp;
	<img src="i/nobr.png" alt="<?php echo $lang_admin['NOBR']; ?>" onclick="return TagSelection('nobr');" />
</div>
<?php

}

function s2_context_buttons ()
{
	global $lang_common, $lang_admin;

	$buttons = array(
		'Edit'			=> '<img src="i/pencil.png" onclick="EditArticle();" alt="'.$lang_admin['Edit'].'" />',
		'Comments'		=> '<img src="i/comments.png" onclick="LoadComments();" alt="'.$lang_common['Comments'].'" />',
		'Subarticle'	=> '<img src="i/page_white_add.png" onclick="CreateChildArticle();" alt="'.$lang_admin['Create subarticle'].'" />',
		'Delete'		=> '<img src="i/delete.png" class="delete" onclick="DeleteArticle();" alt="'.$lang_admin['Delete'].'" />',
	);

	($hook = s2_hook('fn_context_buttons_start')) ? eval($hook) : null;

	echo '<span id="context_buttons">'.implode('', $buttons).'</span>';
}

function s2_output_comment_form ($comment, $type)
{
	global $lang_admin;

	($hook = s2_hook('fn_output_comment_form_start')) ? eval($hook) : null;

?>
<div class="text_wrapper" style="padding-bottom: 2.167em">
	<?php echo s2_comment_menu_links(); ?>
	<form name="commform" action="" onsubmit="setTimeout('SaveComment(\'<?php echo $type; ?>\');', 0); return false;">
		<div class="l-float pad-1">
			<div class="border">
				<table class="fields">
<?php ($hook = s2_hook('fn_output_comment_form_pre_name')) ? eval($hook) : null; ?>
					<tr>
						<td class="label"><?php echo $lang_admin['Name:']; ?></td>
						<td><input type="text" name="comment[nick]" size="100" maxlength="255" value="<?php echo s2_htmlencode($comment['nick']); ?>" /></td>
					</tr>
<?php ($hook = s2_hook('fn_output_comment_form_pre_email')) ? eval($hook) : null; ?>
					<tr>
						<td class="label"><?php echo $lang_admin['Email:']; ?></td>
						<td><input type="text" name="comment[email]" size="100" maxlength="255" value="<?php echo $comment['email']; ?>" /></td>
					</tr>
<?php ($hook = s2_hook('fn_output_comment_form_after_email')) ? eval($hook) : null; ?>
				</table>
				<input type="hidden" name="comment[id]" value="<?php echo $comment['id']; ?>" />
<?php

	$padding = 4.3;
	($hook = s2_hook('fn_output_comment_form_pre_text')) ? eval($hook) : null;

?>
				<div class="text_wrapper" style="padding-bottom: <?php echo $padding; ?>em;">
					<textarea id="commtext" name="comment[text]"><?php echo s2_htmlencode($comment['text']); ?></textarea>
				</div>
			</div>
		</div>
		<div class="r-float">
			<input type="checkbox" id="eml" name="comment[show_email]" value="1"<?php if ($comment['show_email']) echo ' checked="checked"'?> />
			<label for="eml"><?php echo $lang_admin['Show email']; ?></label>
			<br />
			<input type="checkbox" id="sbs" name="comment[subscribed]" value="1"<?php if ($comment['subscribed']) echo ' checked="checked"'?> />
			<label for="sbs"><?php echo $lang_admin['Subscribed']; ?></label>
			<br />
<?php ($hook = s2_hook('fn_output_comment_form_after_checkboxes')) ? eval($hook) : null; ?>
			<hr />
<?php ($hook = s2_hook('fn_output_comment_form_pre_submit')) ? eval($hook) : null; ?>
			<input class="bitbtn save" name="button" type="submit" value="<?php echo $lang_admin['Save']; ?>" />
		</div>
	</form>
</div>
<?php

}

function s2_output_tag_form ($tag, $m_time)
{
	global $s2_db, $lang_admin;

	($hook = s2_hook('fn_output_tag_form_start')) ? eval($hook) : null;

?>
<form name="tagform" action="">
	<div class="l-float">
		<div class="border">
			<table class="fields">
<?php ($hook = s2_hook('fn_output_tag_form_pre_name')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Tag:']; ?></td>
					<td><input type="text" name="tag[name]" size="50" maxlength="255" value="<?php echo s2_htmlencode($tag['name']); ?>" /></td>
				</tr>
<?php ($hook = s2_hook('fn_output_tag_form_pre_time')) ? eval($hook) : null; ?>
				<tr>
					<td class="label"><?php echo $lang_admin['Modify time']; ?></td>
					<td>
						<?php echo s2_get_time_input('m_time', $m_time); ?>
						<a href="#" class="js" onclick="return SetTime(document.tagform, 'm_time');"><?php echo $lang_admin['Now']; ?></a>
					</td>
				</tr>
<?php ($hook = s2_hook('fn_output_tag_form_after_time')) ? eval($hook) : null; ?>
			</table>
<?php

	$padding = 4.3;
	($hook = s2_hook('fn_output_tag_form_pre_text')) ? eval($hook) : null;

?>
			<div class="text_wrapper" style="padding-bottom: <?php echo $padding; ?>em;">
				<textarea id="kText" name="tag[description]"><?php echo s2_htmlencode($tag['description'])?></textarea>
			</div>
		</div>
	</div>
	<div class="r-float" title="<?php echo $lang_admin['Click tag']; ?>">
		<?php echo $lang_admin['Tags:']; ?>
		<hr />
		<div class="text_wrapper" style="padding-bottom: 2.5em;">
			<div class="tags_list">
<?php

	($hook = s2_hook('fn_output_tag_form_pre_get_tags')) ? eval($hook) : null;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'article_tag AS at',
		'WHERE'		=> 't.tag_id = at.tag_id'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'tag_id AS id, name, ('.$raw_query.') AS art_count',
		'FROM'		=> 'tags AS t',
		'ORDER BY'	=> 'name'
	);
	($hook = s2_hook('fn_output_tag_form_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$tag_names = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$tag_names[$row['id']] = $row['name'];
		$info = $row['art_count'];
		($hook = s2_hook('fn_output_tag_form_loop_get_tags_qr')) ? eval($hook) : null;
		echo '<a href="#" '.($row['id'] == $tag['id'] ? 'style="font-weight: bold;" ' : '').'onclick="return LoadTag(\''.$row['id'].'\');">'.s2_htmlencode($row['name']).' ('.$info.')</a><br />'."\n";
	}

?>
			</div>
		</div>
	</div>
	<div class="r-float">
<?php ($hook = s2_hook('fn_output_tag_form_pre_url')) ? eval($hook) : null; ?>
		<?php echo $lang_admin['URL part']; ?>
		<br />
		<input type="text" name="tag[url]" size="18" maxlength="255" value="<?php echo $tag['url']; ?>" />
		<br />
<?php ($hook = s2_hook('fn_output_tag_form_pre_replace_tag')) ? eval($hook) : null; ?>
		<?php echo $lang_admin['Replace tag']; ?>
		<br />
		<select name="tag[id]">
			<option value="0"><?php echo $lang_admin['New']; ?></option>
<?php

	($hook = s2_hook('fn_output_tag_form_pre_loop_replace_tag')) ? eval($hook) : null;

	foreach ($tag_names as $k => $v)
		echo "\t\t\t".'<option value="'.$k.'"'.($k == $tag['id'] ? 'selected="selected"' : '').'>'.s2_htmlencode($v).'</option>'."\n";

?>
		</select>
<?php ($hook = s2_hook('fn_output_tag_form_after_replace_tag')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_tag_form_pre_submit')) ? eval($hook) : null; ?>
		<input class="bitbtn savetag" name="button" type="submit" value="<?php echo $lang_admin['Save']; ?>" onclick="return SaveTag();" />
<?php ($hook = s2_hook('fn_output_tag_form_after_submit')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_tag_form_pre_reset')) ? eval($hook) : null; ?>
		<input class="bitbtn resettag" type="reset" value="<?php echo $lang_admin['Reset']; ?>" onclick="return confirm(S2_LANG_RESET_PROMPT);" />
<?php

	($hook = s2_hook('fn_output_tag_form_pre_delete')) ? eval($hook) : null;

	if ($tag['id'])
	{

?>
		<br />
		<br />
		<a href="#" onclick="return DeleteTag(<?php echo $tag['id'], ', \'', s2_htmlencode(addslashes($tag['name'])), '\''; ?>);" style="color: #f00;"><?php printf($lang_admin['Delete tag'], s2_htmlencode($tag['name'])); ?></a>
<?php

	}
	($hook = s2_hook('fn_output_tag_form_after_delete')) ? eval($hook) : null; ?>
	</div>
</form>
<?php

}
