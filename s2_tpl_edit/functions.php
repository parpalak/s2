<?php
/**
 * Helper functions for template editor
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

function s2_tpl_edit_save ($template)
{
	$template_id = preg_replace('#[^0-9a-zA-Z\._\-]#', '', $template['filename']);

	($hook = s2_hook('fn_s2_tpl_edit_save_start')) ? eval($hook) : null;

	if (trim($template['text']))
		file_put_contents(S2_CACHE_DIR.'s2_tpl_edit_'.S2_STYLE.'_'.$template_id, $template['text']);
	else
	{
		@unlink(S2_CACHE_DIR.'s2_tpl_edit_'.S2_STYLE.'_'.$template_id);
		$template_id = '';
	}

	return $template_id;
}

function s2_tpl_edit_form ($template_filename = '')
{
	global $s2_db, $lang_admin, $lang_templates, $lang_s2_tpl_edit;

	($hook = s2_hook('fn_s2_tpl_edit_form_start')) ? eval($hook) : null;

?>
<form class="full_tab_form" name="s2_tpl_edit_form" action="">
	<div class="r-float" title="<?php echo $lang_s2_tpl_edit['Click template']; ?>">
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_submit')) ? eval($hook) : null; ?>
		<input class="bitbtn" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_s2_tpl_edit['Save']; ?>" onclick="return s2_tpl_edit.save('<?php echo $lang_s2_tpl_edit['Wrong filename']; ?>');" />
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_after_submit')) ? eval($hook) : null; ?>
		<hr />
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_tpl')) ? eval($hook) : null; ?>
		<div class="text_wrapper" style="padding-bottom: 3.2em;">
			<div class="tags_list">
<?php

	$templates = $lang_templates;
	unset($templates['+']);
	$template_text = '';

	($hook = s2_hook('fn_s2_tpl_edit_form_pre_get_tpl')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'DISTINCT template',
		'FROM'		=> 'articles'
	);
	($hook = s2_hook('fn_output_article_form_pre_get_tpl_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_row($result))
		if (!isset($templates[$row[0]]))
			$templates[$row[0]] = $row[0];

	unset($templates['']);

	if ($dir_handle = opendir(S2_CACHE_DIR))
	{
		$prefix = 's2_tpl_edit_'.S2_STYLE.'_';
		$prefix_len = strlen($prefix);

		while (($item = readdir($dir_handle)) !== false)
			if (substr($item, 0, $prefix_len) == $prefix)
			{
				$item = substr($item, $prefix_len);
				if (!isset($templates[$item]))
					$templates[$item] = $item;
			}

		closedir($dir_handle);
	}

	foreach ($templates as $filename => $name)
		echo '<a href="#" '.($filename == $template_filename ? 'class="cur_link" ' : '').'onclick="return s2_tpl_edit.load(\''.$filename.'\');">'.s2_htmlencode($name).'</a><br />'."\n";

	if (!$template_text && $template_filename)
	{
		// Ensure the template is cached
		clearstatcache();
		if (!file_exists(S2_CACHE_DIR.'s2_tpl_edit_'.S2_STYLE.'_'.$template_filename))
			s2_get_template($template_filename);
		$template_text = file_get_contents(S2_CACHE_DIR.'s2_tpl_edit_'.S2_STYLE.'_'.$template_filename);
	}
?>
			</div>
		</div>
	</div>
	<div class="l-float">
		<table class="fields">
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_fname')) ? eval($hook) : null; ?>
			<tr>
				<td class="label"><?php echo $lang_s2_tpl_edit['File name']; ?></td>
				<td><input type="text" name="template[filename]" size="50" maxlength="255" value="<?php echo s2_htmlencode($template_filename); ?>" /></td>
			</tr>
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_after_fname')) ? eval($hook) : null; ?>
		</table>
<?php

	$padding = 2.4;
	($hook = s2_hook('fn_s2_tpl_edit_form_pre_text')) ? eval($hook) : null;

?>
		<div class="text_wrapper" style="padding-bottom: <?php echo $padding; ?>em;">
			<textarea id="s2_tpl_edit_text" class="full_textarea" name="template[text]"><?php echo s2_htmlencode($template_text)?></textarea>
		</div>
	</div>
</form>
<?php

}
