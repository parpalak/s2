<?php
/**
 * Loads common functions used throughout the site.
 *
 * @copyright (C) 2009-2010 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


//
// Dealing with date and time
//

// Puts the date into a string
function s2_date ($time)
{
	global $lang_month_small, $lang_common;

	if (!$time)
		return '';

	$date = date($lang_common['Date format'], $time);
	if (isset($lang_month_small))
		$date = str_replace(array_keys($lang_month_small), array_values($lang_month_small), $date);

	return $date;
}

// Puts the date and time into a string
function s2_date_time ($time)
{
	global $lang_month_small, $lang_common;

	if (!$time)
		return '';

	$date = date($lang_common['Time format'], $time);
	if (isset($lang_month_small))
		$date = str_replace(array_keys($lang_month_small), array_values($lang_month_small), $date);

	return $date;
}

// Returns a month defined in lang files
function s2_month ($month)
{
	global $lang_month_big;

	return $lang_month_big[$month - 1];
}

//
// Output integers using current language settings
//
function s2_number_format ($number, $trailing_zero = false, $decimal_count = false)
{
	global $lang_common;

	$result = number_format($number, $decimal_count === false ? $lang_common['Decimal count'] : $decimal_count, $lang_common['Decimal point'], $lang_common['Thousands separator']);
	if (!$trailing_zero)
		$result = preg_replace('#,?0*$#', '', $result);

	return $result;
}

function s2_frendly_filesize ($size)
{
	global $lang_common, $lang_filesize;

	$return = ($hook = s2_hook('fn_frendly_filesize_start')) ? eval($hook) : null;
	if ($return)
		return $return;

	$i = 0;
	while (($size/1024) > 1)
	{
		$size /= 1024;
		$i++;
	}

	return sprintf($lang_common['Filesize format'], s2_number_format($size), $lang_filesize[$i]);
}

//
// Searches for a template file (in the style or 'template' directory)
//
function s2_get_template ($template_id, $path = false)
{
	global $lang_common;

	if ($path === false)
		$path = S2_ROOT.'_include/templates/';

	$return = ($hook = s2_hook('fn_get_template_start')) ? eval($hook) : null;
	if ($return)
		return $return;

	if (file_exists(S2_ROOT.'_styles/'.S2_STYLE.'/templates/'.$template_id))
		$path = S2_ROOT.'_styles/'.S2_STYLE.'/templates/'.$template_id;
	elseif (file_exists($path.$template_id))
		$path = $path.$template_id;
	else 
		error(sprintf($lang_common['Template not found'], $path.$template_id));

	ob_start();
	include $path;
	$template = ob_get_clean();


	if (strpos($template, '</a>') !== false)
	{
		$request_uri = $_SERVER['REQUEST_URI'];
		if (strpos($request_uri, '?') !== false)
			$request_uri = substr($request_uri, 0, strpos($request_uri, '?'));

		function _s2_check_link($url, $request_uri, $text)
		{
			if ($url == $request_uri)
				return '<span>'.$text.'</span>';
			elseif (strpos($request_uri, $url) === 0)
				return '<a class="current" href="'.$url.'">'.$text.'</a>';
			else
				return '<a href="'.$url.'">'.$text.'</a>';
		}
		$template = preg_replace('#<a href="(.*?)">(.*?)</a>#e', '_s2_check_link(\'\\1\', \''.$request_uri.'\', \'\\2\')', $template);
	}

	($hook = s2_hook('fn_get_template_end')) ? eval($hook) : null;
	return $template;
}

function s2_get_service_template ($template_id = 'service.php', $path = false)
{
	global $lang_common;

	$template = s2_get_template($template_id, $path);

	$replace['<!-- meta -->'] = '<meta name="Generator" content="S2 '.S2_VERSION.'" />';
	$replace['<!-- site_title -->'] = S2_SITE_NAME;

	// Including the style
	ob_start();
	include S2_ROOT.'_styles/'.S2_STYLE.'/'.S2_STYLE.'.php';
	$replace['<!-- styles -->'] = ob_get_clean();

	// Footer
	$author = S2_WEBMASTER ? S2_WEBMASTER : S2_SITE_NAME;
	$link = S2_WEBMASTER_EMAIL ? s2_js_mailto($author, S2_WEBMASTER_EMAIL) : '<a href="'.S2_BASE_URL.'/">'.$author.'</a>';

	$replace['<!-- copyright -->'] = (S2_START_YEAR != date('Y') ?
		sprintf($lang_common['Copyright 2'], $link, S2_START_YEAR, date('Y')) :
		sprintf($lang_common['Copyright 1'], $link, date('Y'))).' '.
		sprintf($lang_common['Powered by'], '<a href="http://s2cms.ru/">S2</a>');

	foreach ($replace as $what => $to)
		$template = str_replace($what, $to, $template);

	return $template;
}


//
// Returns the full path for an article
//
function s2_path_from_id ($id, $visible_for_all = false)
{
	global $s2_db;

	if ($id <= 0)
		return false;

	$query = array(
		'SELECT'	=> 'url, parent_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id.($visible_for_all ? ' AND published = 1' : '')
	);
	($hook = s2_hook('fn_path_from_id_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	return ($row = $s2_db->fetch_row($result)) ? ($row[1] != S2_ROOT_ID ? s2_path_from_id($row[1], $visible_for_all).'/'.urlencode($row[0]) : '') : false;
}

//
// Encodes the contents of $str so that they are safe to output on an (X)HTML page
//
function s2_htmlencode($str)
{
	$return = ($hook = s2_hook('fn_s2_htmlencode_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

//
// JS-protected mailto: link
//
function s2_js_mailto($name, $email)
{
	$s = explode('@', $email);
	return '<script type="text/javascript"><!--'."\n".
		'mailto = "'.$s[0].'" + "%40" + "'.$s[1].'";'."\n".
		"document.write('<a href=\"mailto:' + mailto + '\">".str_replace('\'', '\\\'', $name)."</a>'); //-->\n".
		'</script>'.
		'<noscript>'.$name.', <small>['.$s[0].' at '.$s[1].']</small></noscript>';
}

// Unset any variables instantiated as a result of register_globals being enabled
function s2_unregister_globals()
{
	$register_globals = @ini_get('register_globals');
	if ($register_globals === "" || $register_globals === "0" || strtolower($register_globals) === "off")
		return;

	// Prevent script.php?GLOBALS[foo]=bar
	if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS']))
		exit('I\'ll have a steak sandwich and... a steak sandwich.');

	// Variables that shouldn't be unset
	$no_unset = array('GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');

	// Remove elements in $GLOBALS that are present in any of the superglobals
	$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());
	foreach ($input as $k => $v)
		if (!in_array($k, $no_unset) && isset($GLOBALS[$k]))
		{
			unset($GLOBALS[$k]);
			unset($GLOBALS[$k]);	// Double unset to circumvent the zend_hash_del_key_or_index hole in PHP <4.4.3 and <5.1.4
		}
}

// Removes any "bad" characters (characters which mess with the display of a page, are invisible, etc) from user input
function s2_remove_bad_characters()
{
	global $bad_utf8_chars;

	$bad_utf8_chars = array("\0", "\xc2\xad", "\xcc\xb7", "\xcc\xb8", "\xe1\x85\x9F", "\xe1\x85\xA0", "\xe2\x80\x80", "\xe2\x80\x81", "\xe2\x80\x82", "\xe2\x80\x83", "\xe2\x80\x84", "\xe2\x80\x85", "\xe2\x80\x86", "\xe2\x80\x87", "\xe2\x80\x88", "\xe2\x80\x89", "\xe2\x80\x8a", "\xe2\x80\x8b", "\xe2\x80\x8e", "\xe2\x80\x8f", "\xe2\x80\xaa", "\xe2\x80\xab", "\xe2\x80\xac", "\xe2\x80\xad", "\xe2\x80\xae", "\xe2\x80\xaf", "\xe2\x81\x9f", "\xe3\x80\x80", "\xe3\x85\xa4", "\xef\xbb\xbf", "\xef\xbe\xa0", "\xef\xbf\xb9", "\xef\xbf\xba", "\xef\xbf\xbb", "\xE2\x80\x8D");

	($hook = s2_hook('fn_remove_bad_characters_start')) ? eval($hook) : null;

	function _s2_remove_bad_characters($array)
	{
		global $bad_utf8_chars;
		return is_array($array) ? array_map('_s2_remove_bad_characters', $array) : str_replace($bad_utf8_chars, '', $array);
	}

	$_GET = _s2_remove_bad_characters($_GET);
	$_POST = _s2_remove_bad_characters($_POST);
	$_COOKIE = _s2_remove_bad_characters($_COOKIE);
	$_REQUEST = _s2_remove_bad_characters($_REQUEST);
}

// Clean version string from trailing '.0's
function clean_version($version)
{
	return preg_replace('/(\.0)+(?!\.)|(\.0+$)/', '$2', $version);
}

//
// Validate an e-mail address
//
function is_valid_email ($email)
{
	$return = ($hook = s2_hook('em_fn_is_valid_email_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	if (strlen($email) > 80)
		return false;

	return preg_match('/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\']+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/', $email);
}

//
// Return all code blocks that hook into $hook_id
//
function s2_hook($hook_id)
{
	global $s2_hooks;

	return !defined('S2_DISABLE_HOOKS') && isset($s2_hooks[$hook_id]) ? implode("\n", $s2_hooks[$hook_id]) : false;
}

//
// Custom headers X-S2-JS and X-S2-JS-delayed can contain a javascript code.
// Browser will execute this code on ajax queries.
//

function s2_add_js_header ($header)
{
	static $s2_js_header = '';
	$s2_js_header .= $header;
	header('X-S2-JS: '.$s2_js_header);
}

function s2_add_js_header_delayed ($header)
{
	static $s2_js_header = '';
	$s2_js_header .= $header;
	header('X-S2-JS-delayed: '.$s2_js_header);
}

//
// Turning off browser's cache
//
function s2_no_cache ($full = true)
{
	header('Expires: Wed, 07 Aug 1985 07:45:00 GMT');
	header('Pragma: no-cache');
	if ($full)
	{
		header('Cache-Control: no-cache, must-revalidate');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	}
}

// Display executed queries (if enabled) for debug
function s2_get_saved_queries()
{
	global $s2_db;

	// Get the queries so that we can print them out
	$saved_queries = $s2_db->get_saved_queries();

	ob_start();

?>
		<div id="debug">
			<table>
				<thead>
					<tr>
						<th class="tcl" scope="col">Time, ms</th>
						<th class="tcr" scope="col">Query</th>
					</tr>
				</thead>
				<tbody>
<?php

	$query_time_total = 0.0;
	foreach ($saved_queries as $cur_query)
	{
		$query_time_total += $cur_query[1];

?>
					<tr>
						<td class="tcl"><?php echo (($cur_query[1] != 0) ? s2_number_format($cur_query[1]*1000, false) : '&#160;') ?></td>
						<td class="tcr"><?php echo s2_htmlencode($cur_query[0]) ?></td>
					</tr>
<?php

	}

?>
					<tr class="totals">
						<td class="tcl"><em><?php echo s2_number_format($query_time_total*1000, false) ?></em></td>
						<td class="tcr"><em>Total query time</em></td>
					</tr>
				</tbody>
			</table>
		</div>
<?php

	return ob_get_clean();
}

function s2_404_header ()
{
	$return = ($hook = s2_hook('fn_404_header_start')) ? eval($hook) : null;
	if ($return != null)
		return;

//	global $count_this;

	header('HTTP/1.1 404 Not Found');
//	@log_it("\n404!".date('d.m H:i:s ').$_SERVER['REMOTE_ADDR'].' '.$_SERVER['HTTP_USER_AGENT']." ".getenv('REQUEST_URI').' '.$_SERVER['HTTP_REFERER'], '404');
//	$count_this = 0;
	s2_no_cache();
}

function error_404 ()
{
	global $lang_common;

//	@log_it("\n40x!".date('d.m H:i:s ').$_SERVER['REMOTE_ADDR'].' '.$_SERVER['HTTP_USER_AGENT']." ".getenv('REQUEST_URI').' '.$_SERVER['HTTP_REFERER'], '404');
	//include "404/error.php";
	//exit();
	header('HTTP/1.1 404 Not Found');
	header('Content-Type: text/html; charset=utf-8');

	$template = s2_get_service_template('error404.php');
	$replace = array(
		'<!-- head_title -->'	=> $lang_common['Error 404'],
		'<!-- title -->'		=> '<h1>'.$lang_common['Error 404'].'</h1>',
		'<!-- text -->'			=> sprintf($lang_common['Error 404 text'], S2_BASE_URL),
		'<!-- debug -->'		=> defined('S2_SHOW_QUERIES') ? s2_get_saved_queries() : '',
	);

	($hook = s2_hook('fn_error_404_pre_replace')) ? eval($hook) : null;

	foreach ($replace as $what => $to)
		$template = str_replace($what, $to, $template);

	die($template);
}

// Display a simple error message
function error()
{
	global $s2_config, $lang_common;

	if (!headers_sent())
	{
		// if no HTTP responce code is set we send 503
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Content-type: text/html; charset=utf-8');
	}

/*
	Parse input parameters. Possible function signatures:
	error('Error message.');
	error(__FILE__, __LINE__);
	error('Error message.', __FILE__, __LINE__);
*/
	$num_args = func_num_args();
	if ($num_args == 3)
	{
		$message = func_get_arg(0);
		$file = func_get_arg(1);
		$line = func_get_arg(2);
	}
	else if ($num_args == 2)
	{
		$file = func_get_arg(0);
		$line = func_get_arg(1);
	}
	else if ($num_args == 1)
		$message = func_get_arg(0);

	// Set a default title and gzip setting if the script failed before constants could be defined
	if (!defined('S2_SITE_NAME'))
	{
		define('S2_SITE_NAME', 'S2');
		define('S2_COMPRESS', 0);
	}

	// Empty all output buffers and stop buffering
	while (@ob_end_clean());

	// "Restart" output buffering if we are using ob_gzhandler (since the gzip header is already sent)
	if (S2_COMPRESS && extension_loaded('zlib') && !empty($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false))
		ob_start('ob_gzhandler');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<title>Error - <?php echo s2_htmlencode(S2_SITE_NAME) ?></title>
</head>
<body style="margin: 40px; font: 85%/130% verdana, arial, sans-serif; color: #333;">
<h1><?php echo (isset($lang_common['Error encountered']) ? $lang_common['Error encountered'] : 'An error was encountered'); ?></h1>
<hr />
<?php

	if (isset($message))
		echo '<p>'.$message.'</p>'."\n";

	if ($num_args > 1)
	{
		if (defined('S2_DEBUG'))
		{
			if (isset($file) && isset($line))
				echo '<p><em>The error occurred on line '.$line.' in '.$file.'</em></p>'."\n";

			$db_error = isset($GLOBALS['s2_db']) ? $GLOBALS['s2_db']->error() : array();
			if (!empty($db_error['error_msg']))
			{
				echo '<p><strong>Database reported:</strong> '.s2_htmlencode($db_error['error_msg']).(($db_error['error_no']) ? ' (Errno: '.$db_error['error_no'].')' : '').'.</p>'."\n";

				if ($db_error['error_sql'] != '')
					echo '<p><strong>Failed query:</strong> <code>'.s2_htmlencode($db_error['error_sql']).'</code></p>'."\n";
			}
		}
		else
			echo '<p><strong>Note:</strong> For detailed error information (necessary for troubleshooting), enable "DEBUG mode". To enable "DEBUG mode", open up the file config.php in a text editor, add a line that looks like "define(\'S2_DEBUG\', 1);" (without the quotation marks), and re-upload the file. Once you\'ve solved the problem, it is recommended that "DEBUG mode" be turned off again (just remove the line from the file and re-upload it).</p>'."\n";
	}

?>
</body>
</html>
<?php

	// If a database connection was established (before this error) we close it
	if (isset($GLOBALS['s2_db']))
		$GLOBALS['s2_db']->close();

	exit;
}