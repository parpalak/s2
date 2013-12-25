<?php
/**
 * Database update script.
 *
 * @copyright (C) 2011-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_DB_LAST_REVISION'))
	die;

if (S2_DB_REVISION < 2)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_MAX_ITEMS\', \'0\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	define('S2_MAX_ITEMS', 0);
}

if (S2_DB_REVISION < 3)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_COLOR\', \'#eeeeee\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	define('S2_ADMIN_COLOR', '#eeeeee');
}

if (S2_DB_REVISION < 4)
{
	$s2_db->add_index('articles', 'children_idx', array('parent_id', 'published'));
	$s2_db->add_index('art_comments', 'sort_idx', array('article_id', 'time', 'shown'));
	$s2_db->add_index('tags', 'url_idx', array('url'));
}

if (S2_DB_REVISION < 5)
{
	$s2_db->add_field('articles', 'user_id', 'INT(10) UNSIGNED', false, '0', 'template');
	$s2_db->add_field('articles', 'revision', 'INT(10) UNSIGNED', false, '1', 'modify_time');
	$s2_db->add_field('users', 'create_articles', 'INT(10) UNSIGNED', false, '0', 'edit_comments');

	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'create_articles = edit_site'
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

if (S2_DB_REVISION < 6)
{
	$s2_db->add_field('users', 'name', 'VARCHAR(80)', false, '', 'password');
}

if (S2_DB_REVISION < 7)
{
	$s2_db->drop_field('articles', 'children_preview');
}

if (S2_DB_REVISION < 8)
{
	$s2_db->add_field('users_online', 'ua', 'VARCHAR(200)', false, '', 'login');
	$s2_db->add_field('users_online', 'ip', 'VARCHAR(39)', false, '', 'login');
	$s2_db->add_index('users_online', 'login_idx', array('login'));
}

if (S2_DB_REVISION < 9)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_NEW_POS\', \'0\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	define('S2_ADMIN_NEW_POS', '0');
}

if (S2_DB_REVISION < 10)
{
	$check_for_updates = (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? '1' : '0';

	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_UPDATES\', \''.$check_for_updates.'\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	define('S2_ADMIN_UPDATES', $check_for_updates);
}

if (S2_DB_REVISION < 11)
{
	$s2_db->add_field('extensions', 'admin_affected', 'TINYINT(1) UNSIGNED', false, '0', 'author');
}

if (S2_DB_REVISION < 12)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_ADMIN_CUT\', \'0\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	define('S2_ADMIN_CUT', '0');
}

if (S2_DB_REVISION < 13)
{
	$s2_db->add_field('users_online', 'comment_cookie', 'VARCHAR(32)', false, '', 'ua');
}

if (S2_DB_REVISION < 14)
{
	$query = array(
		'INSERT'	=> 'name, value',
		'INTO'		=> 'config',
		'VALUES'	=> '\'S2_USE_HIERARCHY\', \'1\''
	);

	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	define('S2_USE_HIERARCHY', '1');
}

$query = array(
	'UPDATE'	=> 'config',
	'SET'		=> 'value = \''.S2_DB_LAST_REVISION.'\'',
	'WHERE'		=> 'name = \'S2_DB_REVISION\''
);

$s2_db->query_build($query) or error(__FILE__, __LINE__);

if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
	require S2_ROOT.'_include/cache.php';

s2_generate_config_cache();
