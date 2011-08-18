<?php
/**
 * Loading and saving site settings in the admin panel
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

if (!defined('S2_ROOT'))
	die;

$s2_const_types = array(
	'S2_SITE_NAME'			=> 'string',
	'S2_WEBMASTER'			=> 'string',
	'S2_WEBMASTER_EMAIL'	=> 'string',
	'S2_START_YEAR'			=> 'int',
	'S2_COMPRESS'			=> 'boolean',
	'S2_SHOW_COMMENTS'		=> 'boolean',
	'S2_ENABLED_COMMENTS'	=> 'boolean',
	'S2_PREMODERATION'		=> 'boolean',
	'S2_MAX_ITEMS'			=> 'int',
	'S2_FAVORITE_URL'		=> 'string',
	'S2_TAGS_URL'			=> 'string',
	'S2_ADMIN_COLOR'		=> 'string',
	'S2_LOGIN_TIMEOUT'		=> 'int',
);

($hook = s2_hook('opt_start')) ? eval($hook) : null;

//
// Gets options from the DB
//
function s2_read_options ()
{
	global $s2_db;

	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'config'
	);
	($hook = s2_hook('fn_read_options_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($row = $s2_db->fetch_assoc($result))
		$output[$row['name']] = $row['value'];

	($hook = s2_hook('fn_read_options_end')) ? eval($hook) : null;
	return $output;
}

//
// HTML code for the controls
//

function s2_get_styles ($current_style)
{
	$styles = array();
	$d = dir(S2_ROOT.'_styles');
	while (($entry = $d->read()) !== false)
		if ($entry != '.' && $entry != '..' && is_dir(S2_ROOT.'_styles/'.$entry) && file_exists(S2_ROOT.'_styles/'.$entry.'/'.$entry.'.php'))
			$styles[] = $entry;

	$d->close();

	($hook = s2_hook('fn_get_styles_pre_merge')) ? eval($hook) : null;

	$return = '';

	foreach ($styles as $style)
		$return .= '<option value="'.$style.'"'.($current_style == $style ? 'selected="selected"' : '').'>'.str_replace('_', ' ', $style).'</option>'."\n";

	return '<select id="style_select" name="opt[style]">'.$return.'</select>';
}

function s2_read_lang_dir ()
{
	$langs = array();
	$d = dir(S2_ROOT.'_lang');
	while (($entry = $d->read()) !== false)
		if ($entry != '.' && $entry != '..' && is_dir(S2_ROOT.'_lang/'.$entry) && file_exists(S2_ROOT.'_lang/'.$entry.'/common.php'))
			$langs[] = $entry;

	$d->close();

	return $langs;
}

function s2_get_lang ($current_lang)
{
	$langs = s2_read_lang_dir();

	($hook = s2_hook('fn_get_lang_pre_merge')) ? eval($hook) : null;

	$return = '';

	foreach ($langs as $lang)
		$return .= '<option value="'.$lang.'"'.($current_lang == $lang ? 'selected="selected"' : '').'>'.str_replace('_', ' ', $lang).'</option>'."\n";

	return '<select id="lang_select" name="opt[lang]">'.$return.'</select>';
}

function s2_get_checkbox ($name, $is_checked, $info, $label)
{
	return '<div class="input checkbox"><input type="checkbox" id="'.$name.'_input" name="opt['.$name.']" value="1"'.($is_checked ? ' checked="checked"' : '').' /><label for="'.$name.'_input"><span>'.$info.'</span>'.$label.'</label></div>';
}

function s2_get_input ($name, $value, $info, $label)
{
	return '<div class="input text"><label for="'.$name.'_input"><span>'.$info.'</span><small>'.$label.'</small></label><input type="text" id="'.$name.'_input" name="opt['.$name.']" size="60" maxlength="255" value="'.s2_htmlencode($value).'" /></div>';
}

function s2_get_color_input ($name, $value, $info, $label, $onchange = '')
{
	return '<div class="input color"><label for="'.$name.'_input"><span>'.$info.'</span><small>'.$label.'</small></label><input type="color" id="'.$name.'_input" name="opt['.$name.']" size="60" maxlength="20" value="'.s2_htmlencode($value).'" '.($onchange ? 'onchange="'.$onchange.'" ' : '').'/></div>';
}

//
// Returns the options form
//
function s2_get_options ($message = '')
{
	global $s2_db, $lang_common, $lang_admin, $lang_admin_opt, $lang_const_names, $lang_const_explain;

	$options = s2_read_options();

	$output = $message ? '<div class="info-box">'.$message.'</div>' : '';

	$style = '<div class="input select"><label for="style_select"><span>'.$lang_admin_opt['Styles'].'</span></label>'.s2_get_styles($options['S2_STYLE']).'</div>';
	$lang = '<div class="input select"><label for="lang_select"><span>'.$lang_admin_opt['Languages'].'</span></label>'.s2_get_lang($options['S2_LANGUAGE']).'</div>';

	($hook = s2_hook('fn_get_options_pre_site_fs')) ? eval($hook) : null;
	$fieldset = array(
		'S2_SITE_NAME' => s2_get_input('S2_SITE_NAME', $options['S2_SITE_NAME'], $lang_const_names['S2_SITE_NAME'], $lang_const_explain['S2_SITE_NAME']),
		'S2_WEBMASTER' => s2_get_input('S2_WEBMASTER', $options['S2_WEBMASTER'], $lang_const_names['S2_WEBMASTER'], $lang_const_explain['S2_WEBMASTER']),
		'S2_WEBMASTER_EMAIL' => s2_get_input('S2_WEBMASTER_EMAIL', $options['S2_WEBMASTER_EMAIL'], $lang_const_names['S2_WEBMASTER_EMAIL'], $lang_const_explain['S2_WEBMASTER_EMAIL']),
		'S2_START_YEAR' => s2_get_input('S2_START_YEAR', $options['S2_START_YEAR'], $lang_const_names['S2_START_YEAR'], $lang_const_explain['S2_START_YEAR']),
		'S2_COMPRESS' => s2_get_checkbox('S2_COMPRESS', $options['S2_COMPRESS'], $lang_const_names['S2_COMPRESS'], $lang_const_explain['S2_COMPRESS']),
		'S2_FAVORITE_URL' => s2_get_input('S2_FAVORITE_URL', $options['S2_FAVORITE_URL'], $lang_const_names['S2_FAVORITE_URL'], $lang_const_explain['S2_FAVORITE_URL']),
		'S2_TAGS_URL' => s2_get_input('S2_TAGS_URL', $options['S2_TAGS_URL'], $lang_const_names['S2_TAGS_URL'], $lang_const_explain['S2_TAGS_URL']),
		'S2_MAX_ITEMS' => s2_get_input('S2_MAX_ITEMS', $options['S2_MAX_ITEMS'], $lang_const_names['S2_MAX_ITEMS'], $lang_const_explain['S2_MAX_ITEMS']),
		'style' => $style,
		'lang' => $lang,
	);
	($hook = s2_hook('fn_get_options_pre_site_fs_merge')) ? eval($hook) : null;
	$output .= '<fieldset><legend>'.$lang_admin['Site'].'</legend>'.implode('', $fieldset).'</fieldset>';

	($hook = s2_hook('fn_get_options_pre_comment_fs')) ? eval($hook) : null;
	$fieldset = array(
		'S2_SHOW_COMMENTS' => s2_get_checkbox('S2_SHOW_COMMENTS', $options['S2_SHOW_COMMENTS'], $lang_const_names['S2_SHOW_COMMENTS'], $lang_const_explain['S2_SHOW_COMMENTS']),
		'S2_ENABLED_COMMENTS' => s2_get_checkbox('S2_ENABLED_COMMENTS', $options['S2_ENABLED_COMMENTS'], $lang_const_names['S2_ENABLED_COMMENTS'], $lang_const_explain['S2_ENABLED_COMMENTS']),
		'S2_PREMODERATION' => s2_get_checkbox('S2_PREMODERATION', $options['S2_PREMODERATION'], $lang_const_names['S2_PREMODERATION'], $lang_const_explain['S2_PREMODERATION']),
	);
	($hook = s2_hook('fn_get_options_pre_comment_fs_merge')) ? eval($hook) : null;
	$output .= '<fieldset><legend>'.$lang_common['Comments'].'</legend>'.implode('', $fieldset).'</fieldset>';

	$color_links = array();
	foreach (array ('#eeeeee', '#f4dbd5', '#f3e8d0', '#f2f2da', '#e0f3e0', '#d2f0f3', '#e7e4f4') as $color)
		$color_links[] = '<a class="js" href="#" style="background: '.$color.';" onclick="document.getElementById(\'S2_ADMIN_COLOR_input\').value = \''.$color.'\'; return SetBackground(\''.$color.'\');">'.$color.'</a>';

	($hook = s2_hook('fn_get_options_pre_admin_fs')) ? eval($hook) : null;
	$fieldset = array(
		'S2_ADMIN_COLOR' => s2_get_color_input('S2_ADMIN_COLOR', $options['S2_ADMIN_COLOR'], $lang_const_names['S2_ADMIN_COLOR'], sprintf($lang_const_explain['S2_ADMIN_COLOR'], implode(', ', $color_links)), 'SetBackground(this.value);'),
		'S2_LOGIN_TIMEOUT' => s2_get_input('S2_LOGIN_TIMEOUT', $options['S2_LOGIN_TIMEOUT'], $lang_const_names['S2_LOGIN_TIMEOUT'], $lang_const_explain['S2_LOGIN_TIMEOUT']),
	);
	($hook = s2_hook('fn_get_options_pre_admin_fs_merge')) ? eval($hook) : null;
	$output .= '<fieldset><legend>'.$lang_admin['Admin panel'].'</legend>'.implode('', $fieldset).'</fieldset>';

	($hook = s2_hook('fn_get_options_end')) ? eval($hook) : null;
	return '<form name="optform" method="post" action="">'.$output.'<center><input name="button" type="submit" value="'.$lang_admin_opt['Save options'].'" onclick="return SaveOptions();" /></center></form>';
}

//
// Writes options to the DB
//
function s2_save_options ($opt)
{
	global $s2_const_types, $s2_db, $lang_admin_opt;

	$return = '';
	($hook = s2_hook('fn_save_options_start')) ? eval($hook) : null;

	foreach ($s2_const_types as $name => $type)
	{
		switch ($type)
		{
			case 'boolean':
			$value = isset($opt[$name]) ? 1 : 0;
			break;
			case 'int':
			$value = isset($opt[$name]) ? (int) $opt[$name] : 0;
			break;
			case 'string':
			$value = $opt[$name];
		}

		if ($name == 'S2_WEBMASTER_EMAIL' && $value != '' && !is_valid_email($value))
		{
			$return .= '<p style="color: red;">'.$lang_admin_opt['Invalid webmaster email'].'</p>';
			continue;
		}

		($hook = s2_hook('fn_save_options_loop')) ? eval($hook) : null;

		if (constant($name) != $value)
		{
			$query = array(
				'UPDATE'	=> 'config',
				'SET'		=> 'value = \''.$s2_db->escape($value).'\'',
				'WHERE'		=> 'name = \''.$s2_db->escape($name).'\''
			);
			($hook = s2_hook('fn_save_options_loop_pre_update_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	$style = preg_replace('#[\.\\\/]#', '', $opt['style']);
	if (!file_exists(S2_ROOT.'_styles/'.$style.'/'.$style.'.php'))
		$return .= '<p style="color: red;">'.$lang_admin_opt['Invalid style'].'</p>';
	else if ($style != S2_STYLE)
	{
		$query = array(
			'UPDATE'	=> 'config',
			'SET'		=> 'value = \''.$s2_db->escape($style).'\'',
			'WHERE'		=> 'name = \'S2_STYLE\''
		);
		($hook = s2_hook('fn_save_options_pre_style_update_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}

	$lang = preg_replace('#[\.\\\/]#', '', $opt['lang']);
	if (!file_exists(S2_ROOT.'_lang/'.$lang.'/common.php'))
		$return .= '<p style="color: red;">'.sprintf($lang_admin_opt['Invalid lang pack'], s2_htmlencode($lang)).'</p>';
	else if ($lang != S2_LANGUAGE)
	{
		$query = array(
			'UPDATE'	=> 'config',
			'SET'		=> 'value = \''.$s2_db->escape($lang).'\'',
			'WHERE'		=> 'name = \'S2_LANGUAGE\''
		);
		($hook = s2_hook('fn_save_options_pre_lang_update_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}

	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_generate_config_cache();

	return $return;
}
