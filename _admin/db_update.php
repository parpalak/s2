<?php
/**
 * Database update script.
 *
 * @copyright (C) 2011 Roman Parpalak
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

$query = array(
	'UPDATE'	=> 'config',
	'SET'		=> 'value = \''.S2_DB_LAST_REVISION.'\'',
	'WHERE'		=> 'name = \'S2_DB_REVISION\''
);

$s2_db->query_build($query) or error(__FILE__, __LINE__);

if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
	require S2_ROOT.'_include/cache.php';

s2_generate_config_cache();
