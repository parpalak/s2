<?php
/**
 * Loads the proper database layer class.
 *
 * @copyright (C) 2009-2010 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


//
// Return current timestamp (with microseconds) as a float (used in dblayer)
//
if (defined('S2_SHOW_QUERIES'))
{
	function get_microtime()
	{
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}
}


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
		error('\''.$db_type.'\' is not a valid database type. Please check settings in config.php.', __FILE__, __LINE__);
		break;
}


// Create the database adapter object (and open/connect to/select db)
$s2_db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
