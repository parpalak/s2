<?php
/**
 * Installation script.
 *
 * Used to actually install S2.
 *
 * @copyright (C) 2009-2011 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


define('S2_VERSION', '1.0a3');
define('S2_DB_REVISION', 1);
define('MIN_PHP_VERSION', '4.3.0');
define('MIN_MYSQL_VERSION', '4.1.2');

define('S2_ROOT', '../');
define('S2_DEBUG', 1);

if (file_exists(S2_ROOT.'config.php'))
	exit('The file \'config.php\' already exists which would mean that S2 is already installed. You should go <a href="'.S2_ROOT.'">here</a> instead.');


// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
	exit('You are running PHP version '.PHP_VERSION.'. S2 requires at least PHP '.MIN_PHP_VERSION.' to run properly. You must upgrade your PHP installation before you can continue.');

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Turn off PHP time limit
@set_time_limit(0);

// We need some stuff from functions.php
require S2_ROOT.'_include/functions.php';
require 'options.php';

// Load UTF-8 functions
require S2_ROOT.'_include/utf8/utf8.php';
require S2_ROOT.'_include/utf8/ucwords.php';
require S2_ROOT.'_include/utf8/trim.php';

// Strip out "bad" UTF-8 characters
s2_remove_bad_characters();

//
// Generate output to be used for config.php
//
function generate_config_file ()
{
	global $db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix, $base_url, $s2_cookie_name;

	foreach (array('', '/?', '/index.php', '/index.php?') as $prefix)
	{
		$url_prefix = $prefix;
		$content = s2_get_remote_file($base_url.$url_prefix.'/this/URL/_DoEs_/_NoT_/_eXiSt', 4);
		var_dump($content);
		if (false !== strpos($content['content'], '<meta name="Generator" content="S2 '.S2_VERSION.'" />'))
			break;
	}

	return '<?php'."\n\n".'$db_type = \''.$db_type."';\n".
		'$db_host = \''.$db_host."';\n".
		'$db_name = \''.addslashes($db_name)."';\n".
		'$db_username = \''.addslashes($db_username)."';\n".
		'$db_password = \''.addslashes($db_password)."';\n".
		'$db_prefix = \''.addslashes($db_prefix)."';\n".
		'$p_connect = false;'."\n\n".
		'define(\'S2_BASE_URL\', \''.$base_url.'\');'."\n".
		'define(\'S2_PATH\', \''.preg_replace('#^[^:/]+://[^/]*#', '', $base_url).'\');'."\n".
		'define(\'S2_URL_PREFIX\', \''.$url_prefix.'\');'."\n\n".
		'$s2_cookie_name = '."'".$s2_cookie_name."';\n";
}

$language = isset($_GET['lang']) ? $_GET['lang'] : (isset($_POST['req_language']) ? trim($_POST['req_language']) : 'Russian');
$language = preg_replace('#[\.\\\/]#', '', $language);
if (!file_exists(S2_ROOT.'_lang/'.$language.'/install.php'))
	exit('The language pack you have chosen doesn\'t seem to exist or is corrupt. Please recheck and try again.');

// Load the language files
require S2_ROOT.'_lang/'.$language.'/install.php';
include S2_ROOT.'_lang/'.$language.'/common.php';

if (isset($_POST['generate_config']))
{
	header('Content-Type: text/x-delimtext; name="config.php"');
	header('Content-disposition: attachment; filename=config.php');

	$db_type = $_POST['db_type'];
	$db_host = $_POST['db_host'];
	$db_name = $_POST['db_name'];
	$db_username = $_POST['db_username'];
	$db_password = $_POST['db_password'];
	$db_prefix = $_POST['db_prefix'];
	$base_url = $_POST['base_url'];
	$s2_cookie_name = $_POST['cookie_name'];

	echo generate_config_file();
	exit;
}

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

if (!isset($_POST['form_sent']))
{
	// Determine available database extensions
	$dual_mysql = $mysql_innodb = false;
	$db_extensions = array();
	if (function_exists('mysqli_connect'))
	{
		$db_extensions[] = array('mysqli', 'MySQL Improved');
		$db_extensions[] = array('mysqli_innodb', 'MySQL Improved (InnoDB)');
		$mysql_innodb = true;
	}
	if (function_exists('mysql_connect'))
	{
		$db_extensions[] = array('mysql', 'MySQL Standard');
		$db_extensions[] = array('mysql_innodb', 'MySQL Standard (InnoDB)');
		$mysql_innodb = true;

		if (count($db_extensions) > 2)
			$dual_mysql = true;
	}
//	if (function_exists('sqlite_open'))
//		$db_extensions[] = array('sqlite', 'SQLite');
	if (function_exists('pg_connect'))
		$db_extensions[] = array('pgsql', 'PostgreSQL');

	if (empty($db_extensions))
		error($lang_install['No database support']);

	// Make an educated guess regarding base_url
	$base_url_guess = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://').preg_replace('/:80$/', '', $_SERVER['HTTP_HOST']).substr(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), 0, -6);
	if (substr($base_url_guess, -1) == '/')
		$base_url_guess = substr($base_url_guess, 0, -1);

	// Check for available language packs
	$languages = s2_read_lang_dir();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="Generator" content="S2 <?php echo S2_VERSION; ?>" />
<title><?php printf($lang_install['Install S2'], S2_VERSION) ?></title>
<link rel="stylesheet" type="text/css" href="<?php echo S2_ROOT ?>_admin/css/style.css" />
<style type="text/css">
html {
	overflow: auto;
}
body {
	margin: 40px;
	height: auto;
	min-height: 0;
}
</style>
</head>
<body>

<div id="brd-main">

	<h1><?php printf($lang_install['Install S2'], S2_VERSION) ?></h1>

<?php

	if (count($languages) > 1)
	{

?>	<form method="get" accept-charset="utf-8" action="install.php">
		<h2><?php echo $lang_install['Part 0'] ?></h2>
		<fieldset>
			<legend><?php echo $lang_install['Choose language legend'] ?></legend>
			<div class="input select">
				<label for="fld0"><span><?php echo $lang_install['Installer language'] ?></span> <small><?php echo $lang_install['Choose language help'] ?></small></label>
				<select id="fld0" name="lang">
<?php

		foreach ($languages as $lang)
			echo "\t\t\t\t".'<option value="'.$lang.'"'.($language == $lang ? ' selected="selected"' : '').'>'.$lang.'</option>'."\n";

?>
				</select>
			</div>
		</fieldset>
		<center><input type="submit" name="changelang" value="<?php echo $lang_install['Choose language'] ?>" /></center>
		<input type="hidden" name="form_sent" value="1" />
	</form>
<?php

	}

?>
	<form method="post" accept-charset="utf-8" action="install.php">
		<input type="hidden" name="form_sent" value="1" />
		<h2><?php echo $lang_install['Part1'] ?></h2>
		<div class="info-box">
			<p><?php echo $lang_install['Part1 intro'] ?></p>
			<ul class="spaced">
				<li><span><strong><?php echo $lang_install['Database type'] ?></strong> <?php echo $lang_install['Database type info']; if ($dual_mysql) echo '<br />'.$lang_install['Mysql type info']; if ($mysql_innodb) echo '<br />'.$lang_install['MySQL InnoDB info'] ?></span></li>
				<li><span><strong><?php echo $lang_install['Database server'] ?></strong> <?php echo $lang_install['Database server info'] ?></span></li>
				<li><span><strong><?php echo $lang_install['Database name'] ?></strong> <?php echo $lang_install['Database name info'] ?></span></li>
				<li><span><strong><?php echo $lang_install['Database user pass'] ?></strong> <?php echo $lang_install['Database username info'] ?></span></li>
				<li><span><strong><?php echo $lang_install['Table prefix'] ?></strong> <?php echo $lang_install['Table prefix info'] ?></span></li>
			</ul>
		</div>
		<fieldset>
			<legend><?php echo $lang_install['Part1 legend'] ?></legend>
				<div class="input select required">
					<label for="fld1"><span><?php echo $lang_install['Database type'] ?> <em><?php echo $lang_install['Required'] ?></em></span> <small><?php echo $lang_install['Database type help'] ?></small></label>
					<select id="fld1" name="req_db_type">
<?php

	foreach ($db_extensions as $db_type)
		echo "\t\t\t\t\t".'<option value="'.$db_type[0].'">'.$db_type[1].'</option>'."\n";

?>					</select>
				</div>
				<div class="input text required">
					<label for="fld2"><span><?php echo $lang_install['Database server'] ?> <em><?php echo $lang_install['Required'] ?></em></span> <small><?php echo $lang_install['Database server help'] ?></small></label>
					<input id="fld2" type="text" name="req_db_host" value="localhost" size="50" maxlength="100" />
				</div>
				<div class="input text required">
					<label for="fld3"><span><?php echo $lang_install['Database name'] ?> <em><?php echo $lang_install['Required'] ?></em></span> <small><?php echo $lang_install['Database name help'] ?></small></label>
					<input id="fld3" type="text" name="req_db_name" size="35" maxlength="50" />
				</div>
				<div class="input text">
					<label for="fld4"><span><?php echo $lang_install['Database username'] ?></span> <small><?php echo $lang_install['Database username help'] ?></small></label>
					<input id="fld4" type="text" name="db_username" size="35" maxlength="50" />
				</div>
				<div class="input text">
					<label for="fld5"><span><?php echo $lang_install['Database password'] ?></span> <small><?php echo $lang_install['Database password help'] ?></small></label>
					<input id="fld5" type="password" name="db_password" size="35" />
				</div>
				<div class="input text">
					<label for="fld6"><span><?php echo $lang_install['Table prefix'] ?></span> <small><?php echo $lang_install['Table prefix help'] ?></small></label>
					<input id="fld6" type="text" name="db_prefix" size="20" maxlength="30" />
				</div>
		</fieldset>

		<h2><?php echo $lang_install['Part2'] ?></h2>
		<div class="info-box">
			<p><?php echo $lang_install['Part2 intro'] ?></p>
			<ul class="spaced">
				<li><span><strong><?php echo $lang_install['Admin username'] ?></strong> <?php echo $lang_install['Admin username info'] ?></span></li>
				<li><span><strong><?php echo $lang_install['Admin e-mail'] ?></strong> <?php echo $lang_install['Admin e-mail info'] ?></span></li>
			</ul>
		</div>
		<fieldset>
			<legend><?php echo $lang_install['Part2 legend'] ?></legend>
				<div class="input text required">
					<label for="fld7"><span><?php echo $lang_install['Admin username'] ?> <em><?php echo $lang_install['Required'] ?></em></span> <small><?php echo $lang_install['Username help'] ?></small></label>
					<input id="fld7" type="text" name="req_username" size="35" maxlength="25" />
				</div>
				<div class="input text required">
					<label for="fld10"><span><?php echo $lang_install['Admin e-mail'] ?></span> <small><?php echo $lang_install['E-mail address help'] ?></small></label>
					<span class="fld-input"><input id="fld10" type="text" name="adm_email" size="50" maxlength="80" />
				</div>
		</fieldset>
		<h2><?php echo $lang_install['Part3'] ?></h2>
		<div class="info-box">
			<p><?php echo $lang_install['Part3 intro'] ?></p>
			<ul class="spaced">
				<li><span><strong><?php echo $lang_install['Base URL'] ?></strong> <?php echo $lang_install['Base URL info'] ?></span></li>
			</ul>
		</div>
		<fieldset>
			<legend><?php echo $lang_install['Part3 legend'] ?></legend>
				<div class="input text required">
					<label for="fld13"><span><?php echo $lang_install['Base URL'] ?> <em><?php echo $lang_install['Required'] ?></em></span> <small><?php echo $lang_install['Base URL help'] ?></small></label>
					<span class="fld-input"><input id="fld13" type="text" name="req_base_url" value="<?php echo $base_url_guess ?>" size="60" maxlength="100" /></span>
				</div>
<?php

	if (count($languages) > 1)
	{

?>
				<div class="input select">
					<label for="fld14"><span><?php echo $lang_install['Default language'] ?></span> <small><?php echo $lang_install['Default language help'] ?></small></label>
					<select id="fld14" name="req_language">
<?php

		foreach ($languages as $lang)
			echo "\t\t\t\t\t".'<option value="'.$lang.'"'.($language == $lang ? ' selected="selected"' : '').'>'.$lang.'</option>'."\n";

?>					</select>
				</div>
<?php

	}
	else
	{

?>
				<input type="hidden" name="req_language" value="<?php echo $languages[0]; ?>" />
<?php
	}

?>
		</fieldset>
		<center><input type="submit" name="start" value="<?php echo $lang_install['Start install'] ?>" /></center>
	</form>
</div>

</body>
</html>
<?php

}
else
{
	//
	// Strip slashes only if magic_quotes_gpc is on.
	//
	function unescape($str)
	{
		return (get_magic_quotes_gpc() == 1) ? stripslashes($str) : $str;
	}


	$db_type = $_POST['req_db_type'];
	$db_host = trim($_POST['req_db_host']);
	$db_name = trim($_POST['req_db_name']);
	$db_username = unescape(trim($_POST['db_username']));
	$db_password = unescape(trim($_POST['db_password']));
	$db_prefix = trim($_POST['db_prefix']);
	$username = unescape(trim($_POST['req_username']));
	$email = unescape(strtolower(trim($_POST['adm_email'])));
	$default_lang = preg_replace('#[\.\\\/]#', '', unescape(trim($_POST['req_language'])));

	// Make sure base_url doesn't end with a slash
	if (substr($_POST['req_base_url'], -1) == '/')
		$base_url = substr($_POST['req_base_url'], 0, -1);
	else
		$base_url = $_POST['req_base_url'];

	// Validate form
	if (utf8_strlen($db_name) == 0)
		error($lang_install['Missing database name']);

	// Validate prefix
	if (strlen($db_prefix) > 40)
		error(sprintf($lang_install['Too long table prefix'], $db_prefix));

	if (strlen($db_prefix) > 0 && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db_prefix))
		error(sprintf($lang_install['Invalid table prefix'], $db_prefix));

	if (utf8_strlen($username) < 2)
		error($lang_install['Username too short']);
	if (utf8_strlen($username) > 25)
		error($lang_install['Username too long']);

	// Validate email
	if ($email && !is_valid_email($email))
		error($lang_install['Invalid email']);

	if (utf8_strlen($base_url) == 0)
		error($lang_install['Missing base url']);

	if (!file_exists(S2_ROOT.'_lang/'.$default_lang.'/common.php'))
		error($lang_install['Invalid language']);

	// Load the appropriate DB layer class
	switch ($db_type)
	{
		case 'mysql':
			require S2_ROOT.'_include/dblayer/mysql.php';
			break;
			
		case 'mysql_innodb':
			require S2_ROOT.'_include/dblayer/mysql_innodb.php';
			break;

		case 'mysqli':
			require S2_ROOT.'_include/dblayer/mysqli.php';
			break;

		case 'mysqli_innodb':
			require S2_ROOT.'_include/dblayer/mysqli_innodb.php';
			break;
			
		case 'pgsql':
			require S2_ROOT.'_include/dblayer/pgsql.php';
			break;

		case 'sqlite':
			require S2_ROOT.'_include/dblayer/sqlite.php';
			break;

		default:
			error(sprintf($lang_install['No such database type'], s2_htmlencode($db_type)));
	}

	// Create the database object (and connect/select db)
	$s2_db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, false);


	// If MySQL, make sure it's at least 4.1.2
	if ($db_type == 'mysql' || $db_type == 'mysqli')
	{
		$mysql_info = $s2_db->get_version();
		if (version_compare($mysql_info['version'], MIN_MYSQL_VERSION, '<'))
			error(sprintf($lang_install['Invalid MySQL version'], $mysql_version, MIN_MYSQL_VERSION));
	}

	// Check SQLite prefix collision
	if ($db_type == 'sqlite' && strtolower($db_prefix) == 'sqlite_')
		error($lang_install['SQLite prefix collision']);


	// Make sure S2 isn't already installed
	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 'users'
	);

	$result = $s2_db->query_build($query);
	if ($s2_db->num_rows($result))
		error(sprintf($lang_install['S2 already installed'], $db_prefix, $db_name));


	// Check if InnoDB is available
 	if ($db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
 	{
		$result = $s2_db->query('SHOW VARIABLES LIKE \'have_innodb\'');
		list (, $result) = $s2_db->fetch_row($result);
		if ((strtoupper($result) != 'YES'))
			error('InnoDB does not seem to be enabled. Please choose a database layer that does not have InnoDB support, or enable InnoDB on your MySQL server.');
	}

	// Start a transaction
	$s2_db->start_transaction();


	// Create all tables
	$schema = array(
		'FIELDS'		=> array(
			'name'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'value'	=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			)
		),
		'PRIMARY KEY'	=> array('name')
	);

	$s2_db->create_table('config', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'id'				=> array(
				'datatype'		=> 'VARCHAR(150)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'title'				=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'version'			=> array(
				'datatype'		=> 'VARCHAR(25)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'description'		=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'author'			=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'uninstall'			=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'uninstall_note'	=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'disabled'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'dependencies'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			)
		),
		'PRIMARY KEY'	=> array('id')
	);

	$s2_db->create_table('extensions', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'VARCHAR(150)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'extension_id'	=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'code'			=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'installed'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'priority'		=> array(
				'datatype'		=> 'TINYINT(1) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '5'
			)
		),
		'PRIMARY KEY'	=> array('id', 'extension_id')
	);

	$s2_db->create_table('extension_hooks', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'parent_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'meta_keys'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'meta_desc'		=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'title'			=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'excerpt'		=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'pagetext'		=> array(
				'datatype'		=> 'LONGTEXT',
				'allow_null'	=> true
			),
			'create_time'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'modify_time'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'priority'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'published'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'favorite'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'commented'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'children_preview'=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'url'			=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'template'		=> array(
				'datatype'		=> 'VARCHAR(30)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			)
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'url_idx'			=> array('url'),
			'create_time_idx'	=> array('create_time'),
			'parent_id_idx'		=> array('parent_id'),
			'template_idx'		=> array('template')
		)
	);

	$s2_db->create_table('articles', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'article_id'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'time'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'ip'			=> array(
				'datatype'		=> 'VARCHAR(39)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'nick'			=> array(
				'datatype'		=> 'VARCHAR(50)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'email'			=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'show_email'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'subscribed'	=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'shown'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'sent'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '1'
			),
			'good'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'text'			=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'article_id_idx'	=> array('article_id'),
			'time_idx'			=> array('time')
		)
	);

	$s2_db->create_table('art_comments', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'tag_id'		=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'name'			=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'description'	=> array(
				'datatype'		=> 'TEXT',
				'allow_null'	=> true
			),
			'modify_time'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'url'			=> array(
				'datatype'		=> 'VARCHAR(255)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
		),
		'PRIMARY KEY'	=> array('tag_id'),
		'UNIQUE KEYS'	=> array(
			'name_idx'	=> array('name')
		)
	);

	$s2_db->create_table('tags', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'id'			=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'article_id'	=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'tag_id'		=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
		),
		'PRIMARY KEY'	=> array('id'),
		'INDEXES'		=> array(
			'article_id_idx'		=> array('article_id'),
			'tag_id_idx'			=> array('tag_id'),
		),
	);

	$s2_db->create_table('article_tag', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'challenge'		=> array(
				'datatype'		=> 'VARCHAR(32)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'salt'			=> array(
				'datatype'		=> 'VARCHAR(32)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'time'			=> array(
				'datatype'		=> 'INT(10) UNSIGNED',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'login'			=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> true
			),
		),
		'INDEXES'		=> array(
			'challenge_idx'		=> array('challenge'),
		),
	);

	$s2_db->create_table('users_online', $schema);


	$schema = array(
		'FIELDS'		=> array(
			'id'				=> array(
				'datatype'		=> 'SERIAL',
				'allow_null'	=> false
			),
			'login'				=> array(
				'datatype'		=> 'VARCHAR(200)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'password'			=> array(
				'datatype'		=> 'VARCHAR(40)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'email'				=> array(
				'datatype'		=> 'VARCHAR(80)',
				'allow_null'	=> false,
				'default'		=> '\'\''
			),
			'view'				=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'view_hidden'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'hide_comments'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'edit_comments'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'edit_site'			=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
			'edit_users'		=> array(
				'datatype'		=> 'TINYINT(1)',
				'allow_null'	=> false,
				'default'		=> '0'
			),
		),
		'PRIMARY KEY'	=> array('id'),
		'UNIQUE KEYS'	=> array(
			'login_idx'	=> array('login'),
		)
	);

	$s2_db->create_table('users', $schema);


	$now = time();

	// Admin user
	$query = array(
		'INSERT'	=> 'login, password, email, view, view_hidden, hide_comments, edit_comments, edit_site, edit_users',
		'INTO'		=> 'users',
		'VALUES'	=> '\''.$s2_db->escape($username).'\', \''.md5('Life is not so easy :-)').'\', \''.$s2_db->escape($email).'\', 1, 1, 1, 1, 1, 1'
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);
	//$new_uid = $s2_db->insert_id();

	// Enable/disable automatic check for updates depending on PHP environment (require cURL, fsockopen or allow_url_fopen)
	//$check_for_updates = (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? 1 : 0;

	// Insert config data
	$config = array(
		'S2_SITE_NAME'				=> "'".$lang_install['Site name']."'",
		'S2_WEBMASTER'				=> "''",
		'S2_WEBMASTER_EMAIL'		=> "'".$email."'",
		'S2_START_YEAR'				=> "'".date('Y')."'",
		'S2_FAVORITE_URL'			=> "'favorite'",
		'S2_TAGS_URL'				=> "'tags'",
		'S2_COMPRESS'				=> "'1'",
		'S2_STYLE'					=> "'zeta'",
		'S2_LANGUAGE'				=> "'".$s2_db->escape($default_lang)."'",
		'S2_SHOW_COMMENTS'			=> "'1'",
		'S2_ENABLED_COMMENTS'		=> "'1'",
		'S2_PREMODERATION'			=> "'0'",
		'S2_LOGIN_TIMEOUT'			=> "'60'",
		'S2_DB_REVISION'			=> "'".S2_DB_REVISION."'",
	);

	foreach ($config as $conf_name => $conf_value)
	{
		$query = array(
			'INSERT'	=> 'name, value',
			'INTO'		=> 'config',
			'VALUES'	=> '\''.$conf_name.'\', '.$conf_value.''
		);

		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Insert some other default data
	$query = array(
		'INSERT'	=> 'parent_id, title, create_time, modify_time, published, template, children_preview',
		'INTO'		=> 'articles',
		'VALUES'	=> '0, \''.$lang_install['Main Page'].'\', 0, '.$now.', 1, \'mainpage.php\', 0'
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'INSERT'	=> 'parent_id, title, create_time, modify_time, published, template, url',
		'INTO'		=> 'articles',
		'VALUES'	=> '1, \''.$lang_install['Section example'].'\', '.$now.', '.$now.', 1, \'site.php\', \'section1\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'INSERT'	=> 'parent_id, title, create_time, modify_time, published, template, url, pagetext, excerpt',
		'INTO'		=> 'articles',
		'VALUES'	=> '2, \''.$lang_install['Page example'].'\', '.$now.', '.$now.', 1, \'\', \'page1\', \''.$lang_install['Page text'].'\', \''.$lang_install['Page text'].'\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$s2_db->end_transaction();


	$alerts = array();
	// Check if the cache directory is writable
	if (!is_writable(S2_ROOT.'_cache/'))
		$alerts[] = '<li><span>'.$lang_install['No cache write'].'</span></li>';

	// Check if default pictures directory is writable
	if (!is_writable(S2_ROOT.'_pictures/'))
		$alerts[] = '<li><span>'.$lang_install['No pictures write'].'</span></li>';

	// Check if we disabled uploading pictures because file_uploads was disabled
	$uploads = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;
	if (!$uploads)
		$alerts[] = '<li><span>'.$lang_install['File upload alert'].'</span></li>';

	// Add some random bytes at the end of the cookie name to prevent collisions
	$s2_cookie_name = 's2_cookie_'.mt_rand();

	/// Generate the config.php file data
	$config = generate_config_file();

	// Attempt to write config.php and serve it up for download if writing fails
	$written = false;
	if (is_writable(S2_ROOT))
	{
		$fh = @fopen(S2_ROOT.'config.php', 'wb');
		if ($fh)
		{
			fwrite($fh, $config);
			fclose($fh);

			$written = true;
		}
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="Generator" content="S2 <?php echo S2_VERSION; ?>" />
<title><?php printf($lang_install['Install S2'], S2_VERSION) ?></title>
<link rel="stylesheet" type="text/css" href="<?php echo S2_ROOT ?>_admin/css/style.css" />
<style type="text/css">
html {
	overflow: auto;
}
body {
	margin: 40px;
	height: auto;
	min-height: 0;
}
</style>
</head>
<body>

	<h1><?php printf($lang_install['Install S2'], S2_VERSION) ?></h1>
	<p><?php printf($lang_install['Success description'], S2_VERSION) ?></p>
	<p><?php echo $lang_install['Success welcome'] ?></p>
	<h2><?php echo $lang_install['Final instructions'] ?></h2>
<?php

	if (!empty($alerts))
	{

?>
	<div class="info-box">
		<p class="warn"><strong><?php echo $lang_install['Warning'] ?></strong></p>
		<ul>
			<?php echo implode("\n\t\t\t\t", $alerts)."\n" ?>
		</ul>
	</div>
<?php 

	}

	if (!$written)
	{

?>
		<div class="info-box">
			<p class="warn"><?php echo $lang_install['No write info 1'] ?></p>
			<p class="warn"><?php printf($lang_install['No write info 2'], '<a href="'.S2_ROOT.'">'.$lang_install['Go to index'].'</a>') ?></p>
		</div>
		<form method="post" accept-charset="utf-8" action="install.php">
			<input type="hidden" name="generate_config" value="1" />
			<input type="hidden" name="db_type" value="<?php echo $db_type; ?>" />
			<input type="hidden" name="db_host" value="<?php echo $db_host; ?>" />
			<input type="hidden" name="db_name" value="<?php echo s2_htmlencode($db_name); ?>" />
			<input type="hidden" name="db_username" value="<?php echo s2_htmlencode($db_username); ?>" />
			<input type="hidden" name="db_password" value="<?php echo s2_htmlencode($db_password); ?>" />
			<input type="hidden" name="db_prefix" value="<?php echo s2_htmlencode($db_prefix); ?>" />
			<input type="hidden" name="base_url" value="<?php echo s2_htmlencode($base_url); ?>" />
			<input type="hidden" name="cookie_name" value="<?php echo s2_htmlencode($s2_cookie_name); ?>" />
			<center><input type="submit" value="<?php echo $lang_install['Download config'] ?>" /></center>
		</form>
<?php

	}
	else
	{

?>
		<div class="info-box">
			<p class="warn"><?php printf($lang_install['Write info'], '<a href="'.S2_ROOT.'">'.$lang_install['Go to index'].'</a>') ?></p>
		</div>
<?php

	}

?>

</body>
</html>

<?php

}
