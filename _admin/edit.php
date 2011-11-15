<?php
/**
 * Displays content for the editor tab
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function s2_check_url_status ($parent_id, $url)
{
	global $s2_db;

	$url_status = 'ok';

	($hook = s2_hook('fn_check_url_status_start')) ? eval($hook) : null;

	if ($parent_id != S2_ROOT_ID)
	{
		if ($url == '')
			$url_status = 'empty';
		else
		{
			$query = array(
				'SELECT'	=> 'count(id)',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'a.url = \''.$url.'\' AND a.parent_id = '.$parent_id
			);
			($hook = s2_hook('fn_check_url_status_pre_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			if ($s2_db->result($result) != 1)
				$url_status = 'not_unique';
		}
	}

	return $url_status;
}

function s2_save_article ($page, $flags)
{
	global $s2_db, $lang_admin, $s2_user;

	$id = (int) $page['id'];
	$favorite = (int) isset($flags['favorite']);
	$published = (int) isset($flags['published']);
	$commented = (int) isset($flags['commented']);

	$create_time = !empty($page['create_time']) ? s2_time_from_array($page['create_time']) : time();
	$modify_time = !empty($page['modify_time']) ? s2_time_from_array($page['modify_time']) : time();

	$query = array(
		'SELECT'	=> 'user_id, parent_id, revision, pagetext',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_save_article_pre_get_art_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	
	if ($row = $s2_db->fetch_row($result))
		list($user_id, $parent_id, $revision, $pagetext) = $row;
	else
		die('Item not found!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($user_id == $s2_user['id']);

	if ($page['text'] != $pagetext)
	{
		// If the page text has been modified, we check if this modification is done by current user
		if ($revision != $page['revision'])
			return array($parent_id, $revision, 'conflict'); // No, it's somebody else

		$revision++;
	}

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> "title = '".$s2_db->escape($page['title'])."', meta_keys = '".$s2_db->escape($page['meta_keys'])."', meta_desc = '".$s2_db->escape($page['meta_desc'])."', excerpt = '".$s2_db->escape($page['excerpt'])."', pagetext = '".$s2_db->escape($page['text'])."', url = '".$s2_db->escape($page['url'])."', published = $published, favorite = $favorite, commented = $commented, create_time = $create_time, modify_time = $modify_time, template = '".$s2_db->escape($page['template'])."', revision = '".$s2_db->escape($revision)."'",
		'WHERE'		=> 'id = '.$id
	);

	if ($s2_user['edit_site'])
		$query['SET'] .= ', user_id = '.intval($page['user_id']);

	($hook = s2_hook('fn_save_article_pre_upd_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if ($s2_db->affected_rows() == -1)
		die($lang_admin['Not saved correct']);

	return array($parent_id, $revision, 'ok');
}

function s2_output_article_form ($id)
{
	global $s2_db, $lang_templates, $lang_common, $lang_admin, $s2_user;

	($hook = s2_hook('fn_output_article_form_start')) ? eval($hook) : null;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'a.id = c.article_id'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'title, meta_keys, meta_desc, excerpt, pagetext as text, create_time, modify_time, published, favorite, commented, url, template, parent_id, user_id, revision, ('.$raw_query.') as comment_count',
		'FROM'		=> 'articles AS a',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_output_article_form_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$page = $s2_db->fetch_assoc($result);

	$page['id'] = $id;

	if (!$page['published'])
		s2_test_user_rights($s2_user['view_hidden'] || $s2_user['id'] == $page['user_id']);

	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 'articles',
		'WHERE'		=> 'url = \''.$page['url'].'\' AND parent_id = '.$page['parent_id']
	);
	($hook = s2_hook('fn_output_article_form_pre_chk_url_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$url_error = '';
	if ($s2_db->result($result) != 1)
		$url_error = $lang_admin['URL not unique'];
	elseif ($page['url'] == '' && $page['parent_id'] != S2_ROOT_ID)
		$url_error = $lang_admin['URL empty'];

	$create_time = s2_array_from_time($page['create_time']);
	$modify_time = s2_array_from_time($page['modify_time']);

	$query = array(
		'SELECT'	=> 'DISTINCT template',
		'FROM'		=> 'articles'
	);
	($hook = s2_hook('fn_output_article_form_pre_get_tpl_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$templates = $lang_templates;
	$add_option = $templates['+'];
	unset($templates['+']);
	while ($row = $s2_db->fetch_row($result))
		if (!isset($templates[$row[0]]))
			$templates[$row[0]] = $row[0];
	$templates['+'] = $add_option;

	$query = array(
		'SELECT'	=> 'id, login',
		'FROM'		=> 'users',
		'WHERE'		=> 'create_articles = 1'
	);
	($hook = s2_hook('fn_output_article_form_pre_get_usr_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$users = array(0 => '');
	while ($user = $s2_db->fetch_assoc($result))
		 $users[$user['id']] = $user['login'];

	($hook = s2_hook('fn_output_article_form_pre_output')) ? eval($hook) : null;

?>
<form name="artform" action="" onsubmit="SaveArticle('save'); return false;">
<?php ($hook = s2_hook('fn_output_article_form_pre_btn_col')) ? eval($hook) : null; ?>
	<div class="r-float">
		<input type="hidden" name="page[id]" value="<?php echo $page['id']; ?>" />
		<input type="hidden" name="page[revision]" value="<?php echo $page['revision']; ?>" />
<?php

	($hook = s2_hook('fn_output_article_form_pre_author')) ? eval($hook) : null;

	if ($s2_user['edit_site'])
	{

?>
		<label><?php echo $lang_admin['Author']; ?><br />
		<select name="page[user_id]">
<?php

		foreach ($users as $id => $login)
			echo "\t\t\t".'<option value="'.$id.'"'.($id == $page['user_id'] ? ' selected="selected"' : '').'>'.s2_htmlencode($login).'</option>'."\n";

?>
		</select></label>
<?php

	}

	($hook = s2_hook('fn_output_article_form_pre_template')) ? eval($hook) : null;

?>
		<label><?php echo $lang_admin['Template']; ?><br />
		<select name="page[template]" data-prev-value="<?php echo s2_htmlencode($page['template']); ?>" onchange="ChangeSelect(this, '<?php echo $lang_admin['Add template info']; ?>', 'site.php');">
<?php

	foreach ($templates as $filename => $template)
		echo "\t\t\t".'<option value="'.s2_htmlencode($filename).'"'.($page['template'] == $filename ? ' selected="selected"' : '').'>'.s2_htmlencode($template).'</option>'."\n";

?>
		</select></label>
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
		<label id="url_input_label"<?php if ($url_error) echo ' class="error" title="'.$url_error.'"'; elseif ($page['parent_id'] == S2_ROOT_ID) echo ' title="'.$lang_admin['URL on mainpage'].'"'; ?> title_unique="<?php echo $lang_admin['URL not unique']; ?>" title_empty="<?php echo $lang_admin['URL empty']; ?>"><?php echo $lang_admin['URL part']; ?><br />
		<input type="text" name="page[url]" size="15" maxlength="255" value="<?php echo $page['url']; ?>" <?php $page['parent_id'] == S2_ROOT_ID ? print 'disabled="disabled" ' : null; ?>/></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_published')) ? eval($hook) : null; ?>
		<label for="pub"<?php if ($page['published']) echo ' class="ok"'; ?>><input type="checkbox" id="pub" name="flags[published]" value="1"<? if ($page['published']) echo ' checked="checked"' ?> />
		<?php echo $lang_admin['Published']; ?></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_save')) ? eval($hook) : null; ?>
		<input class="bitbtn save" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>"<?php if (!$s2_user['edit_site'] && $s2_user['id'] != $page['user_id']) echo ' disabled="disabled"'; ?> />
<?php ($hook = s2_hook('fn_output_article_form_after_save')) ? eval($hook) : null; ?>
		<br />
		<br />
		<a title="<?php echo $lang_admin['Preview published']; ?>" id="preview_link" target="_blank" href="<?php echo S2_PATH ?>/_admin/site_ajax.php?action=preview&id=<?php echo $page['id']; ?>"<?php if (!$page['published']) echo ' style="display:none;"'; ?>><?php echo $lang_admin['Preview ready']; ?></a>
<?php ($hook = s2_hook('fn_output_article_form_after_btn_col')) ? eval($hook) : null; ?>
	</div>
<?php ($hook = s2_hook('fn_output_article_form_after_cols')) ? eval($hook) : null; ?>
	<div class="l-float">
		<table class="fields">
<?php ($hook = s2_hook('fn_output_article_form_pre_title')) ? eval($hook) : null; ?>
			<tr>
				<td class="label"><?php echo $lang_admin['Title']; ?></td>
				<td><input type="text" name="page[title]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['title']); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_output_article_form_pre_mkeys')) ? eval($hook) : null; ?>
			<tr>
				<td class="label" title="<?php echo $lang_admin['Meta help']; ?>"><?php echo $lang_admin['Meta keywords']; ?></td>
				<td><input type="text" name="page[meta_keys]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['meta_keys']); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_output_article_form_pre_mdesc')) ? eval($hook) : null; ?>
			<tr>
				<td class="label" title="<?php echo $lang_admin['Meta help']; ?>"><?php echo $lang_admin['Meta description']; ?></td>
				<td><input type="text" name="page[meta_desc]" size="100" maxlength="255" value="<?php echo s2_htmlencode($page['meta_desc']); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_output_article_form_pre_cite')) ? eval($hook) : null; ?>
			<tr>
				<td class="label" title="<?php echo $lang_admin['Excerpt help']; ?>"><?php echo $lang_admin['Excerpt']; ?></td>
				<td><input type="text" name="page[excerpt]" size="100" value="<?php echo s2_htmlencode($page['excerpt']); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_output_article_form_after_cite')) ? eval($hook) : null; ?>
		</table>
<?php ($hook = s2_hook('fn_output_article_form_after_fields1')) ? eval($hook) : null; ?>
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

	($hook = s2_hook('fn_output_article_form_after_fields2')) ? eval($hook) : null;

	s2_toolbar();
	$padding = 14.1;
	($hook = s2_hook('fn_output_article_form_pre_text')) ? eval($hook) : null;

?>
		<div class="text_wrapper" style="padding-bottom: <?php echo $padding; ?>em;">
			<textarea id="arttext" name="page[text]"><?php echo s2_htmlencode($page['text']); ?></textarea>
		</div>
	</div>
</form>
<?

}