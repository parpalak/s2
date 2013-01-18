<?php
/**
 * Loads various functions used in parsing XML (mostly for extensions).
 *
 * @copyright (C) 2009-2013 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

//
// Parse XML data into an array
//
function s2_xml_to_array ($raw_xml)
{
	$xml_parser = xml_parser_create();
	xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 0);
	xml_parse_into_struct($xml_parser, $raw_xml, $vals);
	xml_parser_free($xml_parser);

	$_tmp = '';
	foreach ($vals as $xml_elem)
	{
		$x_tag = $xml_elem['tag'];
		$x_level = $xml_elem['level'];
		$x_type = $xml_elem['type'];

		if ($x_level != 1 && $x_type == 'close')
		{
			if (isset($multi_key[$x_tag][$x_level]))
				$multi_key[$x_tag][$x_level] = 1;
			else
				$multi_key[$x_tag][$x_level] = 0;
		}

		if ($x_level != 1 && $x_type == 'complete')
		{
			if ($_tmp == $x_tag)
				$multi_key[$x_tag][$x_level] = 1;

			$_tmp = $x_tag;
		}
	}

	foreach ($vals as $xml_elem)
	{
		$x_tag = $xml_elem['tag'];
		$x_level = $xml_elem['level'];
		$x_type = $xml_elem['type'];

		if ($x_type == 'open')
			$level[$x_level] = $x_tag;

		$start_level = 1;
		$php_stmt = '$xml_array';
		if ($x_type == 'close' && $x_level != 1)
			$multi_key[$x_tag][$x_level]++;

		while ($start_level < $x_level)
		{
			$php_stmt .= '[$level['.$start_level.']]';
			if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level])
				$php_stmt .= '['.($multi_key[$level[$start_level]][$start_level]-1).']';

			++$start_level;
		}

		$add = '';
		if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type == 'open' || $x_type == 'complete'))
		{
			if (!isset($multi_key2[$x_tag][$x_level]))
				$multi_key2[$x_tag][$x_level] = 0;
			else
				$multi_key2[$x_tag][$x_level]++;

			$add = '['.$multi_key2[$x_tag][$x_level].']';
		}

		if (isset($xml_elem['value']) && trim($xml_elem['value']) != '' && !array_key_exists('attributes', $xml_elem))
		{
			if ($x_type == 'open')
				$php_stmt_main = $php_stmt.'[$x_type]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
			else
				$php_stmt_main = $php_stmt.'[$x_tag]'.$add.' = $xml_elem[\'value\'];';

			eval($php_stmt_main);
		}

		if (array_key_exists('attributes', $xml_elem))
		{
			if (isset($xml_elem['value']))
			{
				$php_stmt_main = $php_stmt.'[$x_tag]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
				eval($php_stmt_main);
			}

			foreach ($xml_elem['attributes'] as $key=>$value)
			{
				$php_stmt_att=$php_stmt.'[$x_tag]'.$add.'[\'attributes\'][$key] = $value;';
				eval($php_stmt_att);
			}
		}
	}

	if (isset($xml_array))
	{
		// Make sure there's an array of notes (even if there is only one)
		if (isset($xml_array['extension']['note']))
		{
			if (!is_array(current($xml_array['extension']['note'])))
				$xml_array['extension']['note'] = array($xml_array['extension']['note']);
		}
		else
			$xml_array['extension']['note'] = array();

		// Make sure there's an array of hooks (even if there is only one)
		if (isset($xml_array['extension']['hooks']) && isset($xml_array['extension']['hooks']['hook']))
		{
			if (!is_array(current($xml_array['extension']['hooks']['hook'])))
				$xml_array['extension']['hooks']['hook'] = array($xml_array['extension']['hooks']['hook']);
		}
	}

	return isset($xml_array) ? $xml_array : array();
}


//
// Validate the syntax of an extension manifest file
//
function s2_validate_manifest ($xml_array, $folder_name)
{
	global $lang_admin_ext;

	$errors = array();

	$return = ($hook = s2_hook('xm_fn_validate_manifest_start')) ? eval($hook) : null;
	if ($return != null)
		return $errors;

	if (!isset($xml_array['extension']) || !is_array($xml_array['extension']))
		$errors[] = $lang_admin_ext['extension root error'];
	else
	{
		$ext = $xml_array['extension'];
		if (!isset($ext['attributes']['for']))
			$errors[] = $lang_admin_ext['extension/engine error'];
		else if ($ext['attributes']['for'] != 'S2')
			$errors[] = $lang_admin_ext['extension/engine error2'];
		else if (!isset($ext['attributes']['engine']))
			$errors[] = $lang_admin_ext['extension/engine error3'];
		else if ($ext['attributes']['engine'] != '1.0')
			$errors[] = $lang_admin_ext['extension/engine error4'];

		if (!isset($ext['id']) || $ext['id'] == '')
			$errors[] = $lang_admin_ext['extension/id error'];
		elseif ($ext['id'] != $folder_name)
			$errors[] = $lang_admin_ext['extension/id error2'];
		if (!isset($ext['title']) || $ext['title'] == '')
			$errors[] = $lang_admin_ext['extension/title error'];
		if (!isset($ext['version']) || $ext['version'] == '' || preg_match('/[^a-z0-9\- \.]+/i', $ext['version']))
			$errors[] = $lang_admin_ext['extension/version error'];
		if (!isset($ext['description']) || $ext['description'] == '')
			$errors[] = $lang_admin_ext['extension/description error'];
		if (!isset($ext['author']) || $ext['author'] == '')
			$errors[] = $lang_admin_ext['extension/author error'];
		if (!isset($ext['minversion']) || $ext['minversion'] == '')
			$errors[] = $lang_admin_ext['extension/minversion error'];
		if (isset($ext['minversion']) && version_compare(s2_clean_version(S2_VERSION), s2_clean_version($ext['minversion']), '<'))
			$errors[] = sprintf($lang_admin_ext['extension/minversion error2'], $ext['minversion']);
		if (!isset($ext['maxtestedon']) || $ext['maxtestedon'] == '')
			$errors[] = $lang_admin_ext['extension/maxtestedon error'];
		if (isset($ext['note']))
		{
			foreach ($ext['note'] as $note)
			{
				if (!isset($note['content']) || $note['content'] == '')
					$errors[] = $lang_admin_ext['extension/note error'];
				if (!isset($note['attributes']['type']) || $note['attributes']['type'] == '')
					$errors[] = $lang_admin_ext['extension/note error2'];
			}
		}
		if (isset($ext['hooks']) && is_array($ext['hooks']))
		{
			if (!isset($ext['hooks']['hook']) || !is_array($ext['hooks']['hook']))
				$errors[] = $lang_admin_ext['extension/hooks/hook error'];
			else
			{
				foreach ($ext['hooks']['hook'] as $hook)
				{
					if (!isset($hook['content']) || $hook['content'] == '')
						$errors[] = $lang_admin_ext['extension/hooks/hook error'];
					if (!isset($hook['attributes']['id']) || $hook['attributes']['id'] == '')
						$errors[] = $lang_admin_ext['extension/hooks/hook error2'];
					if (isset($hook['attributes']['priority']) && (!ctype_digit($hook['attributes']['priority']) || $hook['attributes']['priority'] < 0 || $hook['attributes']['priority'] > 10))
						$errors[] = $lang_admin_ext['extension/hooks/hook error3'];

					if (function_exists('token_get_all') && !empty($hook['content']))
					{
						$tokenized_hook = token_get_all('<?php '.$hook['content']);
						$last_element = array_pop($tokenized_hook);
						if (is_array($last_element) && $last_element[0] == T_INLINE_HTML)
							$errors[] = $lang_admin_ext['extension/hooks/hook error4'];
					}
				}
			}
		}
	}

	($hook = s2_hook('xm_fn_validate_manifest_end')) ? eval($hook) : null;

	return $errors;
}

define('S2_XML_FUNCTIONS_LOADED', 1);
