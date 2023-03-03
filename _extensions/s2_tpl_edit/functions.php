<?php
/**
 * Helper functions for template editor
 *
 * @copyright (C) 2012-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */


if (!defined('S2_ROOT'))
	die;

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

function s2_tpl_edit_file_list ($template_filename)
{
	global $lang_templates;
    /** @var \DBLayer_Abstract $s2_db */
    $s2_db = \Container::get('db');

	$templates = $lang_templates;
	unset($templates['+']);

	($hook = s2_hook('fn_s2_tpl_edit_file_list_pre_get_tpl')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'DISTINCT template',
		'FROM'		=> 'articles'
	);
	($hook = s2_hook('fn_s2_tpl_edit_file_list_pre_get_tpl_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

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

	$return = '';
	foreach ($templates as $filename => $name)
	{
		if (substr($filename, -3) == '.js')
			$link = '<script type="text/javascript" src="<?php echo S2_PATH; ?>/_cache/s2_tpl_edit_'.S2_STYLE.'_'.$filename.'"></script>';
		elseif (substr($filename, -4) == '.css')
			$link = '<link rel="stylesheet" type="text/css" href="<?php echo S2_PATH; ?>/_cache/s2_tpl_edit_'.S2_STYLE.'_'.$filename.'" />';
		else
			$link = '<?php include S2_ROOT.\'/_cache/s2_tpl_edit_'.S2_STYLE.'_'.$filename.'\'; ?>'; // TODO move _cache to a constant

		$return .= '<a href="#" class="js'.($filename == $template_filename ? ' cur_link' : '').'" draggable="true" data-copy="'.s2_htmlencode($link).'" onclick="return s2_tpl_edit.load(\''.$filename.'\');">'.s2_htmlencode($name).'</a><br />'."\n";
	}

	return $return;
}

function s2_tpl_edit_form ()
{
	global $lang_admin;

	($hook = s2_hook('fn_s2_tpl_edit_form_start')) ? eval($hook) : null;

?>
<form class="full_tab_form" name="s2_tpl_edit_form" action="" onsubmit="return s2_tpl_edit.save('<?php echo Lang::get('Wrong filename', 's2_tpl_edit'); ?>', this);">
	<div class="main-column vert-flex">
		<table class="fields">
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_fname')) ? eval($hook) : null; ?>
			<tr>
				<td class="label"><?php echo Lang::get('File name', 's2_tpl_edit'); ?></td>
				<td><input type="text" name="template[filename]" size="50" maxlength="255" value="" /></td>
			</tr>
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_after_fname')) ? eval($hook) : null; ?>
		</table>
<?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_text')) ? eval($hook) : null; ?>
		<div class="text_wrapper">
			<textarea id="s2_tpl_edit_text" class="full_textarea" name="template[text]"></textarea>
		</div>
	</div>
    <div class="aside-column vert-flex" title="<?php echo Lang::get('Help', 's2_tpl_edit'); ?>">
        <?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_submit')) ? eval($hook) : null; ?>
        <input class="bitbtn" name="button" type="submit" title="<?php echo $lang_admin['Save info']; ?>" value="<?php echo $lang_admin['Save']; ?>" />
        <?php ($hook = s2_hook('fn_s2_tpl_edit_form_after_submit')) ? eval($hook) : null; ?>
        <hr />
        <?php ($hook = s2_hook('fn_s2_tpl_edit_form_pre_tpl')) ? eval($hook) : null; ?>
        <div class="tags_list" id="s2_tpl_edit_file_list">
            <?php

            echo s2_tpl_edit_file_list('');

            ?>
        </div>
    </div>
</form>
<?php

}

function s2_tpl_edit_content($templateFilename = ''): array
{
    $templateText = '';
    $return       = array(
        'filename' => $templateFilename,
        'menu'     => s2_tpl_edit_file_list($templateFilename),
    );

    ($hook = s2_hook('fn_s2_tpl_edit_content_start')) ? eval($hook) : null;

    if ($templateText === '' && $templateFilename !== '') {
        // Ensure the template is cached
        clearstatcache();
        $cachedTemplateFilename = S2_CACHE_DIR . 's2_tpl_edit_' . S2_STYLE . '_' . $templateFilename;
        if (file_exists($cachedTemplateFilename)) {
            $templateExists = true;
        } else {
            // Get template to trigger its caching
            try {
                $templateExists = (s2_get_template($templateFilename) !== '');
            } catch (Exception $e) {
                $templateExists = false;
            }
        }
        if ($templateExists) {
            $templateText = file_get_contents($cachedTemplateFilename);
        }
    }
    $return['text'] = $templateText;

    return $return;
}
