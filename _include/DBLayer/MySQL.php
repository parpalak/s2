<?php
/**
 * A database layer class that relies on the MySQL PHP extension.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class DBLayer_MySQL extends DBLayer_Abstract
{
	const ENGINE = 'MyISAM';

	var $link_id;
	var $query_result;

	var $datatype_transformations = array(
		'/^SERIAL$/'	=>	'INT(10) UNSIGNED AUTO_INCREMENT'
	);


	public function __construct($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		// Make sure we have built in support for MySQL
		if (!function_exists('mysql_connect'))
			throw new Exception('This PHP environment doesn\'t have MySQL support built in. MySQL support is required if you want to use a MySQL database to run this site. Consult the PHP documentation for further assistance.');

		parent::__construct($db_prefix);

		if ($p_connect)
			$this->link_id = @mysql_pconnect($db_host, $db_username, $db_password);
		else
			$this->link_id = @mysql_connect($db_host, $db_username, $db_password);

		if ($this->link_id)
		{
			if (!@mysql_select_db($db_name, $this->link_id))
				throw new DBLayer_Exception('Unable to select database. MySQL reported: '.mysql_error());
		}
		else
			throw new DBLayer_Exception('Unable to connect to MySQL. MySQL reported: '.mysql_error());

		// Setup the client-server character set (UTF-8)
		if (!defined('S2_NO_SET_NAMES'))
			$this->set_names('utf8');

		return $this->link_id;
	}

	function query($sql, $unbuffered = false)
	{
		if (defined('S2_SHOW_QUERIES'))
			$q_start = microtime(true);

		if ($unbuffered)
			$this->query_result = @mysql_unbuffered_query($sql, $this->link_id);
		else
			$this->query_result = @mysql_query($sql, $this->link_id);

		if (isset($q_start))
			$this->saved_queries[] = array($sql, microtime(true) - $q_start);

		if (!$this->query_result)
			throw new DBLayer_Exception(@mysql_error($this->link_id), @mysql_errno($this->link_id), $sql);

		++$this->num_queries;

		return $this->query_result;
	}

	function result($query_id = 0, $row = 0, $col = 0)
	{
		return ($query_id) ? @mysql_result($query_id, $row, $col) : false;
	}


	function fetch_assoc($query_id = 0)
	{
		return ($query_id) ? @mysql_fetch_assoc($query_id) : false;
	}


	function fetch_row($query_id = 0)
	{
		return ($query_id) ? @mysql_fetch_row($query_id) : false;
	}


	function num_rows($query_id = 0)
	{
		return ($query_id) ? @mysql_num_rows($query_id) : false;
	}


	function affected_rows()
	{
		return ($this->link_id) ? @mysql_affected_rows($this->link_id) : false;
	}


	function insert_id()
	{
		return ($this->link_id) ? @mysql_insert_id($this->link_id) : false;
	}


	function free_result($query_id = false)
	{
		return ($query_id) ? @mysql_free_result($query_id) : false;
	}


	function escape($str)
	{
		if (is_array($str))
			return '';
		else if (function_exists('mysql_real_escape_string'))
			return mysql_real_escape_string($str, $this->link_id);
		else
			return mysql_escape_string($str);
	}


	function close()
	{
		if ($this->link_id)
		{
			if ($this->query_result)
				@mysql_free_result($this->query_result);

			return @mysql_close($this->link_id);
		}
		else
			return false;
	}


	function get_version()
	{
		$result = $this->query('SELECT VERSION()');

		return array(
			'name'		=> 'MySQL Standard',
            'version'	=> $this->result($result)
		);
	}


	function table_exists($table_name, $no_prefix = false)
	{
		$result = $this->query('SHOW TABLES LIKE \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\'');
		return $this->num_rows($result) > 0;
	}


	function field_exists($table_name, $field_name, $no_prefix = false)
	{
		$result = $this->query('SHOW COLUMNS FROM '.($no_prefix ? '' : $this->prefix).$table_name.' LIKE \''.$this->escape($field_name).'\'');
		return $this->num_rows($result) > 0;
	}


	function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$exists = false;

		$result = $this->query('SHOW INDEX FROM '.($no_prefix ? '' : $this->prefix).$table_name);
		while ($cur_index = $this->fetch_assoc($result))
		{
			if (strtolower($cur_index['Key_name']) == strtolower(($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name))
			{
				$exists = true;
				break;
			}
		}

		return $exists;
	}


	function create_table($table_name, $schema, $no_prefix = false)
	{
		if ($this->table_exists($table_name, $no_prefix))
			return;

		$query = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$table_name." (\n";

		// Go through every schema element and add it to the query
		foreach ($schema['FIELDS'] as $field_name => $field_data)
		{
			$field_data['datatype'] = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_data['datatype']);

			$query .= $field_name.' '.$field_data['datatype'];

			if (isset($field_data['collation']))
				$query .= 'CHARACTER SET utf8 COLLATE utf8_'.$field_data['collation'];

			if (!$field_data['allow_null'])
				$query .= ' NOT NULL';

			if (isset($field_data['default']))
				$query .= ' DEFAULT '.$field_data['default'];

			$query .= ",\n";
		}

		// If we have a primary key, add it
		if (isset($schema['PRIMARY KEY']))
			$query .= 'PRIMARY KEY ('.implode(',', $schema['PRIMARY KEY']).'),'."\n";

		// Add unique keys
		if (isset($schema['UNIQUE KEYS']))
		{
			foreach ($schema['UNIQUE KEYS'] as $key_name => $key_fields)
				$query .= 'UNIQUE KEY '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$key_name.'('.implode(',', $key_fields).'),'."\n";
		}

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$query .= 'KEY '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.'('.implode(',', $index_fields).'),'."\n";
		}

		// We remove the last two characters (a newline and a comma) and add on the ending
		$query = substr($query, 0, strlen($query) - 2)."\n".') ENGINE = '.(isset($schema['ENGINE']) ? $schema['ENGINE'] : self::ENGINE).' CHARACTER SET utf8';

		$this->query($query);
	}


	function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return;

		$this->query('DROP TABLE '.($no_prefix ? '' : $this->prefix).$table_name);
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if ($default_value !== null && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		$this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' ADD '.$field_name.' '.$field_type.($allow_null ? ' ' : ' NOT NULL').($default_value !== null ? ' DEFAULT '.$default_value : ' ').($after_field != null ? ' AFTER '.$after_field : ''));
	}


	function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if ($default_value !== null && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		$this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' MODIFY '.$field_name.' '.$field_type.($allow_null ? ' ' : ' NOT NULL').($default_value !== null ? ' DEFAULT '.$default_value : ' ').($after_field != null ? ' AFTER '.$after_field : ''));
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' DROP '.$field_name);
	}


	function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return;

		$this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' ADD '.($unique ? 'UNIQUE ' : '').'INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.' ('.implode(',', $index_fields).')');
	}


	function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return;

		$this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' DROP INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name);
	}
}
