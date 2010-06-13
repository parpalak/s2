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
				$condition[] = 'title LIKE \'%'.$s2_db->escape($word).'%\' OR pagetext LIKE \'%'.$s2_db->escape($word).'%\'';
		if (count($condition))
		{
			$query['WHERE'] = '('.implode(' OR ', $condition).')';

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

	$output = '';
	for ($i = $s2_db->num_rows($result); $i-- ;)
	{
		$article = $s2_db->fetch_assoc($result);

		// This element will have onclick event handler and proper styles
		$expand = '<div></div>';
		$strike = $article['published'] ? '' : ' style="text-decoration: line-through;"';
		// Custom attribute
		$comments = $article['comment_num'] ? ' comments="'.$article['comment_num'].'"' : '';
		$span = '<div><span class="additional">'.s2_date($article['create_time']).'</span><span id="'.$article['id'].'"'.$strike.$comments.'>'.$article['title'].'</span></div>';

		// We do not display childrens in search results
		$children = !$search ? s2_get_child_branches($article['id'], false) : '';

		($hook = s2_hook('fn_get_child_branches_after_get_branch')) ? eval($hook) : null;
		$output .= '<li class="'.($children || $search && $article['child_num'] ? 'ExpandClosed' : 'ExpandLeaf').(!$i ? ' IsLast' : '' ).($search ? ' Search' : '').'">'.$expand.$span.$children.'</li>';
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

function s2_check_form_controls ($id)
{
	global $s2_db;

	s2_add_js_header_delayed('var e = document.getElementById("pub"); e.parentNode.className = e.checked ? "ok" : "";');

	$query = array(
		'SELECT'	=> 'parent_id, url',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_check_form_controls_pre_get_pid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	list($parent_id, $url) = $s2_db->fetch_row($result);

	if ($parent_id != S2_ROOT_ID)
	{
		$query = array(
			'SELECT'	=> 'count(id)',
			'FROM'		=> 'articles',
			'WHERE'		=> 'url = \''.$url.'\' AND parent_id = '.$parent_id
		);
		($hook = s2_hook('fn_check_form_controls_pre_check_url_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($s2_db->result($result) != 1)
		{
			s2_add_js_header_delayed('var e = document.getElementById("url_input_label"); e.className="error"; e.title=e.getAttribute("title_unique");');
			return;
		}
		elseif ($url == '')
		{
			s2_add_js_header_delayed('var e = document.getElementById("url_input_label"); e.className="error"; e.title=e.getAttribute("title_empty");');
			return;
		}
	}
	s2_add_js_header_delayed('var e = document.getElementById("url_input_label"); e.className=""; e.title="";');
}

function s2_output_article_form ($id)
{
	global $s2_db, $lang_templates, $lang_common, $lang_admin;

	($hook = s2_hook('fn_output_article_form_start')) ? eval($hook) : null;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'a.id = c.article_id'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'title, meta_keys, meta_desc, citation, pagetext as text, create_time, modify_time, published, favorite, commented, url, children_preview, template, parent_id, ('.$raw_query.') as comment_count',
		'FROM'		=> 'articles AS a',
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

	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 'articles',
		'WHERE'		=> 'url = \''.$page['url'].'\' AND parent_id = '.$page['parent_id']
	);
	($hook = s2_hook('fn_output_article_form_pre_get_pid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$url_error = '';
	if ($s2_db->result($result) != 1)
		$url_error = $lang_admin['URL not unique'];
	elseif ($page['url'] == '' && $page['parent_id'] != S2_ROOT_ID)
		$url_error = $lang_admin['URL empty'];

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
		<input type="hidden" name="page[id]" value="<?php echo $page['id']; ?>" />
<?php ($hook = s2_hook('fn_output_article_form_pre_parag_btn')) ? eval($hook) : null; ?>
		<input class="bitbtn parag" type="button" title="<?php echo $lang_admin['Paragraphs info']; ?>" value="<?php echo $lang_admin['Paragraphs']; ?>" onclick="return Paragraph();" />
<?php ($hook = s2_hook('fn_output_article_form_after_parag_btn')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_reset')) ? eval($hook) : null; ?>
		<input class="bitbtn reset" type="reset" value="<?php echo $lang_admin['Reset']; ?>" onclick="return confirm(S2_LANG_RESET_PROMPT);" />
		<br />
<?php ($hook = s2_hook('fn_output_article_form_pre_clear')) ? eval($hook) : null; ?>
		<input class="bitbtn new" type="button" value="<?php echo $lang_admin['Clear']; ?>" onclick="ClearForm(); return false;" />
<?php ($hook = s2_hook('fn_output_article_form_after_clear')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_template')) ? eval($hook) : null; ?>
		<label><?php echo $lang_admin['Template']; ?><br />
		<select name="page[template]">
<?php

	foreach ($lang_templates as $n => $v)
		echo "\t\t\t".'<option value="'.$n.'"'.($page['template'] == $n ? ' selected' : '').'>'.$v.'</option>'."\n";

?>
		</select></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_subcontent')) ? eval($hook) : null; ?>
		<label for="subarticles" title="<?php echo $lang_admin['Children preview']; ?>"><input type="checkbox" id="subarticles" name="flags[children_preview]" value="1"<? if ($page['children_preview']) echo ' checked="checked"' ?> />
		<?php echo $lang_admin['Subcontent']; ?></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_checkboxes')) ? eval($hook) : null; ?>
		<label for="fav"><input type="checkbox" id="fav" name="flags[favorite]" value="1"<? if ($page['favorite']) echo ' checked="checked"' ?> />
		<?php echo $lang_common['Favorite']; ?></label>
		<label for="com" title="<?php echo $lang_admin['Commented info']; ?>"><input type="checkbox" id="com" name="flags[commented]" value="1"<? if ($page['commented']) echo ' checked="checked"' ?> />
		<?php echo $lang_admin['Commented']; ?></label>	
<?php

	if ($page['comment_count'])
	{
?>
		<a title="<?php echo $lang_admin['Go to comments']; ?>" href="#" onclick="return LoadComments(<?php echo $page['id']; ?>);"><?php echo $lang_common['Comments']; ?> &rarr;</a>
<?php
	}
	else
		echo "\t\t".$lang_admin['No comments']."\n";

?>
<?php ($hook = s2_hook('fn_output_article_form_after_checkboxes')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_url')) ? eval($hook) : null; ?>
		<label id="url_input_label"<?php if ($url_error) echo ' class="error" title="'.$url_error.'"'; ?> title_unique="<?php echo $lang_admin['URL not unique']; ?>" title_empty="<?php echo $lang_admin['URL empty']; ?>"><?php echo $lang_admin['URL part']; ?><br />
		<input type="text" name="page[url]" size="15" maxlength="255" value="<?php echo $page['url']; ?>" /></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_published')) ? eval($hook) : null; ?>
		<label for="pub"<?php if ($page['published']) echo ' class="ok"'; ?>><input type="checkbox" id="pub" name="flags[published]" value="1"<? if ($page['published']) echo ' checked="checked"' ?> />
		<?php echo $lang_admin['Published']; ?></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_save')) ? eval($hook) : null; ?>
		<input class="bitbtn save" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>" />
<?php ($hook = s2_hook('fn_output_article_form_after_save')) ? eval($hook) : null; ?>
		<br />
		<br />
		<a title="<?php echo $lang_admin['Preview published']; ?>" target="_blank" href="<?php echo S2_PATH ?>/admin/site_ajax.php?action=preview&id=<?php echo $page['id']; ?>"><?php echo $lang_admin['Preview ready']; ?></a>
<?php ($hook = s2_hook('fn_output_article_form_after_btn_col')) ? eval($hook) : null; ?>
	</div>
<?php ($hook = s2_hook('fn_output_article_form_after_cols')) ? eval($hook) : null; ?>
</form>
<?

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
		$list .= '<li><img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete from list'].'" onclick="DeleteArticleFromTag('.$row['link_id'].');" />'.$row['title'].'</li>';

	return $list ? '<ul>' . $list . '</ul>' : '';
}

// A button that change a permission
function s2_grb ($b, $login, $permission, $allow_modify)
{
	global $lang_admin;

	$class = $b ? 'yes' : 'no';
	$alt = $b ? $lang_admin['Deny'] : $lang_admin['Allow'];
	return '<img class="buttons '.$class.'" src="i/1.gif" alt="'.($allow_modify ? $alt : '').'" '.($allow_modify ? 'onclick="return SetPermission(\''.s2_htmlencode(addslashes($login)).'\', \''.$permission.'\');" ' : '').'/>';
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
			$buttons['change_pass'] = '<img class="rename" src="i/1.gif" alt="'.$lang_admin['Change password'].'" onclick="return SetUserPassword(\''.s2_htmlencode(addslashes($login)).'\');">';
		if ($is_admin)
			$buttons['delete'] = '<img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete user'].'" onclick="return DeleteUser(\''.s2_htmlencode(addslashes($login)).'\');">';

		($hook = s2_hook('fn_get_user_list_loop_pre_buttons_merge')) ? eval($hook) : null;
		$buttons = '<td><span class="buttons">'.implode('', $buttons).'</span></td>';

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
	echo '<script type="text/javascript">document.location.hash = "#edit"; eTextarea = document.getElementById("wText"); curr_md5 = hex_md5(StringFromForm(document.artform)); sCurrTextId = sUrl + "action=load&id='.$id.'";</script>';
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
<div class="toolbar">
	<img class="new" src="i/1.gif" alt="<?php echo $lang_admin['Clear']; ?>" onclick="return ClearForm();" />
	&nbsp;
	<img class="b" src="i/1.gif" alt="<?php echo $lang_admin['Bold']; ?>" onclick="return TagSelection('strong');" />
	<img class="i" src="i/1.gif" alt="<?php echo $lang_admin['Italic']; ?>" onclick="return TagSelection('em');" />
	<img class="strike" src="i/1.gif" alt="<?php echo $lang_admin['Strike']; ?>" onclick="return TagSelection('s');" />
	&nbsp;
	<img class="a" src="i/1.gif" alt="<?php echo $lang_admin['Link']; ?>" onclick="return InsertTag('<a href=&quot;&quot;>', '</a>');" />
	<img class="quote" src="i/1.gif" alt="<?php echo $lang_admin['Quote']; ?>" onclick="return TagSelection('blockquote');" />
	<img class="img" src="i/1.gif" alt="<?php echo $lang_admin['Image']; ?>" onclick="return GetImage();" />
	&nbsp;
	<img class="big" src="i/1.gif" alt="<?php echo $lang_admin['BIG']; ?>" onclick="return TagSelection('big');" />
	<img class="small" src="i/1.gif" alt="<?php echo $lang_admin['SMALL']; ?>" onclick="return TagSelection('small');" />
	&nbsp;
	<img class="sup" src="i/1.gif" alt="<?php echo $lang_admin['SUP']; ?>" onclick="return TagSelection('sup');" />
	<img class="sub" src="i/1.gif" alt="<?php echo $lang_admin['SUB']; ?>" onclick="return TagSelection('sub');" />
	&nbsp;
	<img class="h2" src="i/1.gif" alt="<?php echo $lang_admin['Header 2']; ?>" onclick="return TagSelection('h2');" />
	<img class="h3" src="i/1.gif" alt="<?php echo $lang_admin['Header 3']; ?>" onclick="return TagSelection('h3');" />
	<img class="h4" src="i/1.gif" alt="<?php echo $lang_admin['Header 4']; ?>" onclick="return TagSelection('h4');" />
	&nbsp;
	<img class="left" src="i/1.gif" alt="<?php echo $lang_admin['Left']; ?>" onclick="return TagSelection('p');" />
	<img class="center" src="i/1.gif" alt="<?php echo $lang_admin['Center']; ?>" onclick="return InsertTag('<p align=&quot;center&quot;>', '</p>');" />
	<img class="right" src="i/1.gif" alt="<?php echo $lang_admin['Right']; ?>" onclick="return InsertTag('<p align=&quot;right&quot;>', '</p>');" />
	<img class="justify" src="i/1.gif" alt="<?php echo $lang_admin['Justify']; ?>" onclick="return InsertTag('<p align=&quot;justify&quot;>', '</p>');" />
	&nbsp;
	<img class="ul" src="i/1.gif" alt="<?php echo $lang_admin['UL']; ?>" onclick="return TagSelection('ul');" />
	<img class="ol" src="i/1.gif" alt="<?php echo $lang_admin['OL']; ?>" onclick="return TagSelection('ol');" />
	<img class="li" src="i/1.gif" alt="<?php echo $lang_admin['LI']; ?>" onclick="return TagSelection('li');" />
	&nbsp;
	<img class="pre" src="i/1.gif" alt="<?php echo $lang_admin['PRE']; ?>" onclick="return TagSelection('pre');" />
	<img class="code" src="i/1.gif" alt="<?php echo $lang_admin['CODE']; ?>" onclick="return TagSelection('code');" />
	&nbsp;
	<img class="nobr" src="i/1.gif" alt="<?php echo $lang_admin['NOBR']; ?>" onclick="return TagSelection('nobr');" />
</div>
<?php

}

function s2_context_buttons ()
{
	global $lang_common, $lang_admin;

	$buttons = array(
		'Edit'			=> '<img class="edit" src="i/1.gif" onclick="EditArticle();" alt="'.$lang_admin['Edit'].'" />',
		'Comments'		=> '<img class="comments" src="i/1.gif" onclick="LoadComments();" alt="'.$lang_common['Comments'].'" />',
		'Subarticle'	=> '<img class="add" src="i/1.gif" onclick="CreateChildArticle();" alt="'.$lang_admin['Create subarticle'].'" />',
		'Delete'		=> '<img class="delete" src="i/1.gif" onclick="DeleteArticle();" alt="'.$lang_admin['Delete'].'" />',
	);

	($hook = s2_hook('fn_context_buttons_start')) ? eval($hook) : null;

	echo '<span id="context_buttons">'.implode('', $buttons).'</span>';
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
