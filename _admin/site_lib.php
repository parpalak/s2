<?php
/**
 * Common functions for the admin panel and pages generation
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

//
// JSON encoding
//

if (!function_exists('json_encode'))
{
	function s2_json_encode ($data)
	{
		switch ($type = gettype($data))
		{
			case 'NULL':
				return 'null';
			case 'boolean':
				return ($data ? 'true' : 'false');
			case 'integer':
			case 'double':
			case 'float':
				return $data;
			case 'string':
				return '"' . str_replace(array('\\', '"', "\n", "\r", "\t"), array('\\\\', '\\"', '\\n', '\\r', '\\t'), $data) . '"';
			case 'object':
				$data = get_object_vars($data);
			case 'array':
				$output_index_count = 0;
				$output_indexed = array();
				$output_associative = array();
				$size = count($data);
				if (array_keys($data) === range(0, $size - 1))
				{
					$output = '[';
					for ($i = 0; $i < $size; $i++)
					{
						$output .= s2_json_encode($data[$i]);
						if ($i < $size - 1)
							$output .= ',';
					}
					return $output . ']';
				}
				else
				{
					$output = '{';
					$i = 0;
					foreach ($data as $key => $value)
					{
						$output .= s2_json_encode($key) . ':' . s2_json_encode($value);
						if ($i < $size - 1)
							$output .= ',';
						$i++;
					}
					return $output . '}';
				}
			default:
				return '';
		}
	}
}
else
{
	function s2_json_encode ($data)
	{
		if (defined('JSON_UNESCAPED_UNICODE'))
			$result = json_encode($data, JSON_UNESCAPED_UNICODE);
		else
			$result = json_encode($data);
		return $result;
	}
}

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

	$format = $lang_admin['Input time format'];

	($hook = s2_hook('fn_get_time_input')) ? eval($hook) : null;
	return str_replace(array_keys($replace), array_values($replace), $format);
}

// A button that change a permission
function s2_grb ($b, $login, $permission, $allow_modify)
{
	global $lang_admin;

	$class = $b ? 'yes' : 'no';
	$alt = $b ? $lang_admin['Deny'] : $lang_admin['Allow'];
	return '<img class="buttons '.$class.($allow_modify ? '' : ' nohover').'" src="i/1.gif" alt="'.($allow_modify ? $alt : '').'" '.($allow_modify ? 'onclick="return SetPermission(\''.s2_htmlencode(addslashes($login)).'\', \''.$permission.'\');" ' : '').'/>';
}

function s2_get_user_list ()
{
	global $s2_db, $lang_user_permissions, $lang_user_permissions_help, $lang_admin, $s2_user;

	$s2_user_permissions = array(
		'view',
		'view_hidden',
		'hide_comments',
		'edit_comments',
		'create_articles',
		'edit_site',
		'edit_users'
	);
	($hook = s2_hook('fn_get_user_list_start')) ? eval($hook) : null;

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
		$name = $row['name'] ? s2_htmlencode($row['name']) : '&nbsp;—&nbsp;';
		$name_link = $s2_user['edit_users'] || $login == $s2_user['login'] ?
			'<a href="#" class="js" title="'.($row['name'] ? $lang_admin['Change name'] : $lang_admin['Set name']).'" onclick="return SetUserName(\''.s2_htmlencode(addslashes($login)).'\', \''.s2_htmlencode(addslashes($row['name'])).'\');">'.$name.'</a>' :
			$name;

		$email = $row['email'] ? s2_htmlencode($row['email']) : '&nbsp;—&nbsp;';
		$email_link = $s2_user['edit_users'] || $login == $s2_user['login'] ?
			'<a href="#" class="js" title="'.($row['email'] ? $lang_admin['Change email'] : $lang_admin['Set email']).'" onclick="return SetUserEmail(\''.s2_htmlencode(addslashes($login)).'\', \''.s2_htmlencode(addslashes($row['email'])).'\');">'.$email.'</a>' :
			$email;

		$permissions = array();
		foreach ($s2_user_permissions as $permission)
			$permissions[$permission] = '<td>'.s2_grb($row[$permission], $login, $permission, $s2_user['edit_users']).'</td>';

		($hook = s2_hook('fn_get_user_list_loop_pre_perm_merge')) ? eval($hook) : null;
		$permissions = implode('', $permissions);

		$buttons = array();
		if ($s2_user['edit_users'] || $login == $s2_user['login'])
			$buttons['change_pass'] = '<img class="rename" src="i/1.gif" alt="'.$lang_admin['Change password'].'" onclick="return SetUserPassword(\''.s2_htmlencode(addslashes($login)).'\');">';
		if ($s2_user['edit_users'])
			$buttons['delete'] = '<img class="delete" src="i/1.gif" alt="'.$lang_admin['Delete user'].'" onclick="return DeleteUser(\''.s2_htmlencode(addslashes($login)).'\');">';

		($hook = s2_hook('fn_get_user_list_loop_pre_buttons_merge')) ? eval($hook) : null;
		$buttons = '<td><span class="buttons">'.implode('', $buttons).'</span></td>';

		($hook = s2_hook('fn_get_user_list_loop_pre_row_merge')) ? eval($hook) : null;
		$body[] = '<td>'.s2_htmlencode($login).'</td><td><nobr>'.$name_link.'</nobr></td>'.'<td>'.$email_link.'</td>'.$permissions.$buttons;
	}

	$thead = array(
		'login'		=> '<td class="sortable">'.$lang_admin['Login'].'</td>',
		'name'		=> '<td class="sortable">'.$lang_admin['Name'].'</td>',
		'email'		=> '<td class="sortable">'.$lang_admin['Email'].'</td>',
	);

	($hook = s2_hook('fn_get_user_list_pre_loop_thead_perm')) ? eval($hook) : null;

	foreach ($s2_user_permissions as $permission)
		$thead[$permission] = '<td title="'.$lang_user_permissions_help[$permission].'">'.$lang_user_permissions[$permission].'</td>';

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
	global $s2_db, $lang_admin;

	$return = ($hook = s2_hook('fn_preload_editor_start')) ? eval($hook) : null;
	if ($return)
		return;

	if (empty($_GET['path']) || $_GET['path'] == '/')
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
			'SELECT'	=> 'a.id',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'url = \''.$s2_db->escape($request_array[$i]).'\' AND parent_id = '.$id
		);
		($hook = s2_hook('fn_preload_editor_loop_pre_get_parents_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$id = $s2_db->result($result);
		if (!$id)
			return;
	}

	($hook = s2_hook('fn_preload_editor_pre_output')) ? eval($hook) : null;

	echo 'setTimeout(function () { EditArticle('.$id.'); }, 0);';

	($hook = s2_hook('fn_preload_editor_end')) ? eval($hook) : null;
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

	ob_start();

?>
<hr />
<div class="toolbar">
	<img class="b" src="i/1.gif" alt="<?php echo $lang_admin['Bold']; ?>" onclick="return TagSelection('strong');" />
	<img class="i" src="i/1.gif" alt="<?php echo $lang_admin['Italic']; ?>" onclick="return TagSelection('em');" />
	<img class="strike" src="i/1.gif" alt="<?php echo $lang_admin['Strike']; ?>" onclick="return TagSelection('s');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="big" src="i/1.gif" alt="<?php echo $lang_admin['BIG']; ?>" onclick="return TagSelection('big');" />
	<img class="small" src="i/1.gif" alt="<?php echo $lang_admin['SMALL']; ?>" onclick="return TagSelection('small');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="sup" src="i/1.gif" alt="<?php echo $lang_admin['SUP']; ?>" onclick="return TagSelection('sup');" />
	<img class="sub" src="i/1.gif" alt="<?php echo $lang_admin['SUB']; ?>" onclick="return TagSelection('sub');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="nobr" src="i/1.gif" alt="<?php echo $lang_admin['NOBR']; ?>" onclick="return TagSelection('nobr');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="cut" src="i/1.gif" alt="<?php echo $lang_admin['Cut']; ?>" onclick="return InsertTag('<cut>', '');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="a" src="i/1.gif" alt="<?php echo $lang_admin['Link']; ?>" onclick="return InsertTag('<a href=&quot;&quot;>', '</a>');" />
	<img class="img" src="i/1.gif" alt="<?php echo $lang_admin['Image']; ?>" onclick="return GetImage();" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="h2" src="i/1.gif" alt="<?php echo $lang_admin['Header 2']; ?>" onclick="return InsertParagraph('h2');" />
	<img class="h3" src="i/1.gif" alt="<?php echo $lang_admin['Header 3']; ?>" onclick="return InsertParagraph('h3');" />
	<img class="h4" src="i/1.gif" alt="<?php echo $lang_admin['Header 4']; ?>" onclick="return InsertParagraph('h4');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="left" src="i/1.gif" alt="<?php echo $lang_admin['Left']; ?>" onclick="return InsertParagraph('');" />
	<img class="center" src="i/1.gif" alt="<?php echo $lang_admin['Center']; ?>" onclick="return InsertParagraph('center');" />
	<img class="right" src="i/1.gif" alt="<?php echo $lang_admin['Right']; ?>" onclick="return InsertParagraph('right');" />
	<img class="justify" src="i/1.gif" alt="<?php echo $lang_admin['Justify']; ?>" onclick="return InsertParagraph('justify');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="quote" src="i/1.gif" alt="<?php echo $lang_admin['Quote']; ?>" onclick="return InsertParagraph('blockquote');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="ul" src="i/1.gif" alt="<?php echo $lang_admin['UL']; ?>" onclick="return TagSelection('ul');" />
	<img class="ol" src="i/1.gif" alt="<?php echo $lang_admin['OL']; ?>" onclick="return TagSelection('ol');" />
	<img class="li" src="i/1.gif" alt="<?php echo $lang_admin['LI']; ?>" onclick="return TagSelection('li');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="pre" src="i/1.gif" alt="<?php echo $lang_admin['PRE']; ?>" onclick="return TagSelection('pre');" />
	<img class="code" src="i/1.gif" alt="<?php echo $lang_admin['CODE']; ?>" onclick="return TagSelection('code');" />
	<img class="separator" src="i/1.gif" alt="" />
	<img class="parag" src="i/1.gif" alt="<?php echo $lang_admin['Paragraphs info']; ?>" onclick="return Paragraph();" />
</div>
<?php

	$toolbar = ob_get_clean();

	($hook = s2_hook('fn_toolbar_end')) ? eval($hook) : null;

	echo $toolbar;
}

function s2_context_buttons ()
{
	global $lang_common, $lang_admin;

	$buttons = array(
		'Edit'			=> '<img id="context_edit" src="i/1.gif" alt="'.$lang_admin['Edit'].'" />',
		'Comments'		=> '<img id="context_comments" src="i/1.gif" alt="'.$lang_common['Comments'].'" />',
		'Subarticle'	=> '<img id="context_add" src="i/1.gif" alt="'.$lang_admin['Create subarticle'].'" />',
		'Delete'		=> '<img id="context_delete" src="i/1.gif" alt="'.$lang_admin['Delete'].'" />',
	);

	($hook = s2_hook('fn_context_buttons_start')) ? eval($hook) : null;

	echo '<span id="context_buttons">'.implode('', $buttons).'</span>';
}

function s2_get_tag_ids ($tag_str)
{
	global $s2_db;

	// String cleanup, lower-case copy
	$dirty_tags = explode(',', $tag_str);
	$fake_tags = array();
	foreach ($dirty_tags as $k => $tag)
	{
		$tag = trim($tag);
		$dirty_tags[$k] = $tag;
		$fake_tags[] = utf8_strtolower($tag);
	}

	// Case-independent
	array_unique($fake_tags);

	if (empty($fake_tags))
		return array();

	// New copies: normal, escaped for DB query and lowered
	$tags = $escaped_tags = $lowered_tags = array();
	foreach ($fake_tags as $k => $lowered_tag)
		if ($tag = $dirty_tags[$k])
		{
			$tags[] = $tag;
			$escaped_tags[] = $s2_db->escape($tag);
			$lowered_tags[] = $lowered_tag;
		}

	// Fetching real tags
	$query = array(
		'SELECT'	=> 't.tag_id, t.name',
		'FROM'		=> 'tags AS t',
		'WHERE'		=> 't.name in (\''.implode('\', \'', $escaped_tags).'\')'
	);
	($hook = s2_hook('fn_get_tag_ids_pre_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$ids = $real_tags = array();
	while ($real_tag = $s2_db->fetch_assoc($result))
	{
		$lowered_real_tag = utf8_strtolower($real_tag['name']);

		foreach ($lowered_tags as $k => $lowered_tag)
			if ($lowered_real_tag == $lowered_tag)
			{
				$ids[$k] = $real_tag['tag_id'];
				$real_tags[$k] = $real_tag['name'];
			}
	}

	// Inserting new tags
	foreach ($tags as $k => $new_tag)
		if (!isset($ids[$k]))
		{
			$query = array(
				'INSERT'	=> 'name, description, modify_time, url',
				'INTO'		=> 'tags',
				'VALUES'	=> '\''.$new_tag.'\', \'\', \'0\', \''.$new_tag.'\''
			);
			($hook = s2_hook('fn_get_tag_ids_pre_ins_tag_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);

			$ids[$k] = $s2_db->insert_id();
			$real_tags[$k] = $new_tag;
		}

	ksort($ids);

	return $ids;
}
