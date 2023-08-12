<?php
/**
 * Displays content for the editor tab
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use S2\Cms\Pdo\DbLayer;

if (!defined('S2_ROOT'))
	die;

function s2_check_url_status ($parent_id, $url)
{
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$url_status = 'ok';

	($hook = s2_hook('fn_check_url_status_start')) ? eval($hook) : null;

	if ($parent_id != Model::ROOT_ID)
	{
		if ($url == '')
			$url_status = 'empty';
		else
		{
			$query = array(
				'SELECT'	=> 'count(id)',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'a.url = \''.$url.'\''.(S2_USE_HIERARCHY ? ' AND a.parent_id = '.$parent_id : '')
			);
			($hook = s2_hook('fn_check_url_status_pre_qr')) ? eval($hook) : null;
			$result = $s2_db->buildAndQuery($query);

			if ($s2_db->result($result) != 1)
				$url_status = 'not_unique';
		}
	}

	return $url_status;
}

function s2_save_article ($page, $flags)
{
	global $lang_admin, $s2_user;

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $id = (int) $page['id'];
	$favorite = (int) isset($flags['favorite']);
	$published = (int) isset($flags['published']);
	$commented = (int) isset($flags['commented']);

	$create_time = !empty($page['create_time']) ? s2_time_from_array($page['create_time']) : time();
	$modify_time = !empty($page['modify_time']) ? s2_time_from_array($page['modify_time']) : time();

	$query = array(
		'SELECT'	=> 'user_id, parent_id, revision, pagetext, title, url',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_save_article_pre_get_art_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);

	if ($row = $s2_db->fetchRow($result))
		list($user_id, $parent_id, $revision, $pagetext, $title, $url) = $row;
	else
		die('Item not found!');

	if (!$s2_user['edit_site'])
		s2_test_user_rights($user_id == $s2_user['id']);

	if ($page['text'] != $pagetext || $page['title'] != $title || $page['url'] != $url) {
		// If the page text has been modified, we check if this modification is done by current user
		if ($revision !== (int)$page['revision']) {
            return array($parent_id, $revision, 'conflict'); // No, it's somebody else
        }

		$revision++;
	}

	$excerpt = '';
	if (S2_ADMIN_CUT)
	{
		$text_parts = preg_split('#(<cut\\s*/?>|<p><cut /></p>)#s', $page['text'], 2);
		if (count($text_parts) > 1)
			$excerpt = $text_parts[0];
	}
	elseif (isset($page['excerpt']))
		$excerpt = $page['excerpt'];

	$error = false;

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> "title = '".$s2_db->escape($page['title'])."', meta_keys = '".$s2_db->escape($page['meta_keys'])."', meta_desc = '".$s2_db->escape($page['meta_desc'])."', excerpt = '".$s2_db->escape($excerpt)."', pagetext = '".$s2_db->escape($page['text'])."', url = '".$s2_db->escape($page['url'])."', published = $published, favorite = $favorite, commented = $commented, create_time = $create_time, modify_time = $modify_time, template = '".$s2_db->escape($page['template'])."', revision = " . $revision,
		'WHERE'		=> 'id = '.$id
	);

	if ($s2_user['edit_site'])
		$query['SET'] .= ', user_id = '.intval($page['user_id']);

	($hook = s2_hook('fn_save_article_pre_upd_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);
	if ($s2_db->affectedRows($result) === 0)
		$error = true;

	// Dealing with tags
	$new_tags_str = isset($page['tags']) ? $page['tags'] : '';
	$new_tags = s2_get_tag_ids($new_tags_str);

	$query = array(
		'SELECT'	=> 'tag_id',
		'FROM'		=> 'article_tag AS at',
		'WHERE'		=> 'article_id = '.$id,
		'ORDER BY'	=> 'id'
	);
	($hook = s2_hook('fn_save_article_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);

	$old_tags = array();
	while ($row = $s2_db->fetchRow($result))
		$old_tags[] = $row[0];

	// Compare old and new tags
	if (implode(',', $old_tags) != implode(',', $new_tags))
	{
		// Deleting old links
		$query = array(
			'DELETE'	=> 'article_tag',
			'WHERE'		=> 'article_id = '.$id
		);
		($hook = s2_hook('fn_save_article_pre_del_tags_qr')) ? eval($hook) : null;
        $s2_db->buildAndQuery($query);

		// Inserting new links
		foreach ($new_tags as $tag_id)
		{
			$query = array(
				'INSERT'	=> 'article_id, tag_id',
				'INTO'		=> 'article_tag',
				'VALUES'	=> $id.', '.$tag_id
			);
			($hook = s2_hook('fn_save_article_pre_ins_tags_qr')) ? eval($hook) : null;
            $result = $s2_db->buildAndQuery($query);
            if ($s2_db->affectedRows($result) === 0)
				$error = true;
		}
	}

	if ($error)
		die($lang_admin['Not saved correct']);

	return array($parent_id, $revision, 'ok');
}

function s2_output_article_form ($id)
{
	global $lang_templates, $lang_admin, $s2_user;

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    ($hook = s2_hook('fn_output_article_form_start')) ? eval($hook) : null;

	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'a.id = c.article_id'
	);
	$raw_query = $s2_db->build($subquery);

	$query = array(
		'SELECT'	=> 'title, meta_keys, meta_desc, excerpt, pagetext as text, create_time, modify_time, published, favorite, commented, url, template, parent_id, user_id, revision, ('.$raw_query.') as comment_count',
		'FROM'		=> 'articles AS a',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('fn_output_article_form_pre_page_get_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);
	$page = $s2_db->fetchAssoc($result);

	$page['id'] = $id;

	if (!$page['published'])
		s2_test_user_rights($s2_user['view_hidden'] || $s2_user['id'] == $page['user_id']);

	$create_time = s2_array_from_time($page['create_time']);
	$modify_time = s2_array_from_time($page['modify_time']);

	// Fetching tags
	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'article_tag AS at',
		'WHERE'		=> 't.tag_id = at.tag_id'
	);
	$used_raw_query = $s2_db->build($subquery);

	$subquery = array(
		'SELECT'	=> 'id',
		'FROM'		=> 'article_tag AS at',
		'WHERE'		=> 't.tag_id = at.tag_id AND at.article_id = '.$id
	);
	$current_raw_query = $s2_db->build($subquery);

	$query = array(
		'SELECT'	=> 't.name, ('.$used_raw_query.') as used, ('.$current_raw_query.') as link_id',
		'FROM'		=> 'tags AS t',
		'ORDER BY'	=> 'used DESC'
	);
	($hook = s2_hook('fn_output_article_form_pre_chk_url_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);

	$all_tags = $tags = array();
	while ($tag = $s2_db->fetchAssoc($result))
	{
		$all_tags[] = $tag['name'];
		if (!empty($tag['link_id']))
			$tags[$tag['link_id']] = $tag['name'];
	}
	ksort($tags);

	// Check the URL for errors
	$url_status = s2_check_url_status($page['parent_id'], $page['url']);
	$url_error = '';
	if ($url_status == 'not_unique')
		$url_error = $lang_admin['URL not unique'];
	elseif ($url_status == 'empty')
		$url_error = $lang_admin['URL empty'];

	// Options for template select
	$query = array(
		'SELECT'	=> 'DISTINCT a.template',
		'FROM'		=> 'articles AS a'
	);
	($hook = s2_hook('fn_output_article_form_pre_get_tpl_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);

	$templates = $lang_templates;
	$add_option = $templates['+'];
	unset($templates['+']);
	while ($row = $s2_db->fetchRow($result))
		if (!isset($templates[$row[0]]))
			$templates[$row[0]] = $row[0];

	if (!S2_USE_HIERARCHY)
		unset($templates['']);

	$templates['+'] = $add_option;

	// Options for author select
	if ($s2_user['edit_site'])
	{
		$query = array(
			'SELECT'	=> 'id, login',
			'FROM'		=> 'users',
			'WHERE'		=> 'create_articles = 1'
		);
		($hook = s2_hook('fn_output_article_form_pre_get_usr_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$users = array(0 => '');
		while ($user = $s2_db->fetchAssoc($result))
			 $users[$user['id']] = $user['login'];
	}

	($hook = s2_hook('fn_output_article_form_pre_output')) ? eval($hook) : null;

	ob_start();

	($hook = s2_hook('fn_output_article_form_output_start')) ? eval($hook) : null;
?>
<form class="full_tab_form" name="artform" action="" onsubmit="SaveArticle('save'); return false;">
    <?php ($hook = s2_hook('fn_output_article_form_pre_art_col')) ? eval($hook) : null; ?>
    <div class="main-column vert-flex">
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
            <?php

            ($hook = s2_hook('fn_output_article_form_pre_cite')) ? eval($hook) : null;

            if (!S2_ADMIN_CUT)
            {

                ?>
                <tr>
                    <td class="label" title="<?php echo $lang_admin['Excerpt help']; ?>"><?php echo $lang_admin['Excerpt']; ?></td>
                    <td><input type="text" name="page[excerpt]" size="100" value="<?php echo s2_htmlencode($page['excerpt']); ?>" /></td>
                </tr>
                <?php

            }

            ($hook = s2_hook('fn_output_article_form_after_cite')) ? eval($hook) : null;

            ?>
            <tr>
                <td class="label" title="<?php echo $lang_admin['Tags help']; ?>"><?php echo $lang_admin['Tags']; ?></td>
                <td><input type="text" name="page[tags]" size="100" value="<?php echo !empty($tags) ? s2_htmlencode(implode(', ', $tags).', ') : ''; ?>" /></td>
            </tr>
            <?php ($hook = s2_hook('fn_output_article_form_after_tags')) ? eval($hook) : null; ?>
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

        ($hook = s2_hook('fn_output_article_form_pre_text')) ? eval($hook) : null;

        ?>
        <div class="text_wrapper">
            <textarea tabindex="1" id="arttext" class="full_textarea" name="page[text]"><?php echo s2_htmlencode($page['text']); ?></textarea>
        </div>
    </div>
<?php ($hook = s2_hook('fn_output_article_form_pre_btn_col')) ? eval($hook) : null; ?>
	<div class="aside-column">
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

		foreach ($users as $userId => $login) {
            echo "\t\t\t" . '<option value="' . $userId . '"' . ($userId == $page['user_id'] ? ' selected="selected"' : '') . '>' . s2_htmlencode($login) . '</option>' . "\n";
        }

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
		<label for="favorite_checkbox"><input type="checkbox" id="favorite_checkbox" name="flags[favorite]" value="1"<?php if ($page['favorite']) echo ' checked="checked"' ?> />
		<?php echo Lang::get('Favorite'); ?></label>
		<label for="commented_checkbox" title="<?php echo $lang_admin['Commented info']; ?>"><input type="checkbox" id="commented_checkbox" name="flags[commented]" value="1"<?php if ($page['commented']) echo ' checked="checked"' ?> />
		<?php echo $lang_admin['Commented']; ?></label>
<?php

	if ($page['comment_count'])
	{

?>
		<a title="<?php echo $lang_admin['Go to comments']; ?>" href="#" onclick="return LoadComments(<?php echo $page['id']; ?>);"><?php echo Lang::get('Comments'); ?> &rarr;</a>
<?php

	}
	else
		echo "\t\t".$lang_admin['No comments']."\n";

?>
<?php ($hook = s2_hook('fn_output_article_form_after_checkboxes')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_output_article_form_pre_url')) ? eval($hook) : null; ?>
		<label id="url_input_label"<?php if ($url_error) echo ' class="error" title="'.$url_error.'"'; elseif ($page['parent_id'] == Model::ROOT_ID) echo ' title="'.$lang_admin['URL on mainpage'].'"'; ?> title_unique="<?php echo $lang_admin['URL not unique']; ?>" title_empty="<?php echo $lang_admin['URL empty']; ?>"><?php echo $lang_admin['URL part']; ?><br />
		<input type="text" name="page[url]" size="15" maxlength="255" value="<?php echo $page['url']; ?>" <?php echo $page['parent_id'] == Model::ROOT_ID ? 'disabled="disabled" ' : ''; ?>/></label>
<?php ($hook = s2_hook('fn_output_article_form_pre_published')) ? eval($hook) : null; ?>
		<label for="publiched_checkbox"<?php if ($page['published']) echo ' class="ok"'; ?>><input type="checkbox" id="publiched_checkbox" name="flags[published]" value="1"<?php if ($page['published']) echo ' checked="checked"' ?> />
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
</form>
<?php

	($hook = s2_hook('fn_output_article_form_end')) ? eval($hook) : null;

	return array('form' => ob_get_clean(), 'tags' => $all_tags);
}
