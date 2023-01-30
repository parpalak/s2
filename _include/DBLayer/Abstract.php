<?php
/**
 * A database abstract layer class.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

abstract class DBLayer_Abstract
{
	protected $saved_queries = array();
	protected $num_queries = 0;

	protected $prefix = '';

	private static function getClassName ($db_type)
	{
		$classes = array(
			'mysqli' => 'MySQLi',
			'mysqli_innodb' => 'MySQLiInnoDB',
			'pgsql' => 'PgSQL',
			'pdo_sqlite' => 'PDOSQLite',
		);

		if (!isset($classes[$db_type]))
			throw new Exception('\''.$db_type.'\' is not a valid database type. Please check settings in config.php.');

		return 'DBLayer_' . $classes[$db_type];
	}

	/**
	 * @param $db_type
	 * @param $db_host
	 * @param $db_username
	 * @param $db_password
	 * @param $db_name
	 * @param $db_prefix
	 * @param $p_connect
	 * @return DBLayer_Abstract
	 */
	public static function getInstance ($db_type, $db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		$class = self::getClassName($db_type);
		return new $class($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
	}

	public function __construct ($db_prefix)
	{
		$this->prefix = $db_prefix;
	}

	public function start_transaction ()
	{
		return;
	}

	public function end_transaction()
	{
		return;
	}

	public function get_num_queries()
	{
		return $this->num_queries;
	}

    public function get_saved_queries()
	{
		return $this->saved_queries;
	}

	/**
	 * @param string $sql
	 * @param bool $unbuffered
	 * @return resource
	 */
	abstract function query($sql, $unbuffered = false);

	protected function set_names($names)
	{
		return $this->query('SET NAMES \''.$this->escape($names).'\'');
	}

	/**
	 * @param array $query
	 * @param bool $return_query_string
	 * @param bool $unbuffered
	 * @return resource|string
	 */
	public function query_build(array $query, $return_query_string = false, $unbuffered = false)
	{
		$sql = '';

		if (isset($query['SELECT']))
		{
			$sql = 'SELECT '.$query['SELECT'].' FROM '.(isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['FROM'];

			if (isset($query['JOINS']))
			{
				foreach ($query['JOINS'] as $cur_join)
					$sql .= ' '.key($cur_join).' '.(isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).current($cur_join).' ON '.$cur_join['ON'];
			}

			if (!empty($query['WHERE']))
				$sql .= ' WHERE '.$query['WHERE'];
			if (!empty($query['GROUP BY']))
				$sql .= ' GROUP BY '.$query['GROUP BY'];
			if (!empty($query['HAVING']))
				$sql .= ' HAVING '.$query['HAVING'];
			if (!empty($query['ORDER BY']))
				$sql .= ' ORDER BY '.$query['ORDER BY'];
			if (!empty($query['LIMIT']))
				$sql .= ' LIMIT '.$query['LIMIT'];
		}
		else if (isset($query['INSERT']))
		{
			$sql = 'INSERT INTO '.(isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['INTO'];

			if (!empty($query['INSERT']))
				$sql .= ' ('.$query['INSERT'].')';

			if (is_array($query['VALUES']))
				$sql .= ' VALUES('.implode('),(', $query['VALUES']).')';
			else
				$sql .= ' VALUES('.$query['VALUES'].')';
		}
		else if (isset($query['UPDATE']))
		{
			$query['UPDATE'] = (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['UPDATE'];

			$sql = 'UPDATE '.$query['UPDATE'].' SET '.$query['SET'];

			if (!empty($query['WHERE']))
				$sql .= ' WHERE '.$query['WHERE'];
		}
		else if (isset($query['DELETE']))
		{
			$sql = 'DELETE FROM '.(isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['DELETE'];

			if (!empty($query['WHERE']))
				$sql .= ' WHERE '.$query['WHERE'];
		}
		else if (isset($query['REPLACE']))
		{
			$sql = 'REPLACE INTO '.(isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['INTO'];

			if (!empty($query['REPLACE']))
				$sql .= ' ('.$query['REPLACE'].')';

			$sql .= ' VALUES('.$query['VALUES'].')';
		}

		return $return_query_string ? $sql : $this->query($sql, $unbuffered);
	}

	/**
	 * @param string $str
	 * @return string
	 */
	abstract function escape($str): string;
	abstract function result($query_id = 0, $row = 0, $col = 0);
	abstract function fetch_row($query_id);
	abstract function fetch_assoc($query_id);
	abstract function insert_id();
	abstract function free_result($query_id);
	abstract function get_version();
	abstract function create_table($table_name, $schema, $no_prefix = false);
	abstract function close();

	public function fetch_assoc_all ($query_id = 0)
	{
		$return = array();
		while ($row = $this->fetch_assoc($query_id))
			$return[] = $row;

		return $return;
	}
}
