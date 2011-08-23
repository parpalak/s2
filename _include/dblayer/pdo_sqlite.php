<?php
/**
 * A database layer class that relies on the SQLite PHP extension.
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


// Make sure we have support for PDO
if (!class_exists('PDO'))
	exit('This PHP environment does not have PDO support built in. PDO support is required if you want to use a PDO_SQLite database to run this site. Consult the PHP documentation for further assistance.');

// Make sure we have support for PDO_SQLite
if (!in_array("sqlite", PDO::getAvailableDrivers()))
	exit('This PHP environment does not have PDO_SQLite support built in. PDO_SQLite support is required if you want to use a PDO_SQLite database to run this site. Consult the PHP documentation for further assistance.');


class DBLayer
{
	var $prefix;
	var $link_id;
	var $query_result;
	var $row_count;
	var $in_transaction = 0;

	var $saved_queries = array();
	var $num_queries = 0;

	var $error_no = false;
	var $error_msg = 'Unknown';

	var $datatype_transformations = array(
		'/^SERIAL$/'															=>	'INTEGER',
		'/^(TINY|SMALL|MEDIUM|BIG)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i'	=>	'INTEGER',
		'/^(TINY|MEDIUM|LONG)?TEXT$/i'											=>	'TEXT'
	);


	function DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		// Prepend $db_name with the path to the site root directory
		$db_name = S2_ROOT.$db_name;

		$this->prefix = $db_prefix;

		if (!file_exists($db_name))
		{
			@touch($db_name);
			@chmod($db_name, 0666);
			if (!file_exists($db_name))
				error('Unable to create new database \''.$db_name.'\'. Permission denied', __FILE__, __LINE__);
		}

		if (!is_readable($db_name))
			error('Unable to open database \''.$db_name.'\' for reading. Permission denied', __FILE__, __LINE__);

		if (!is_writable($db_name))
			error('Unable to open database \''.$db_name.'\' for writing. Permission denied', __FILE__, __LINE__);

		try {
			if ($p_connect)
				$this->link_id = new PDO('sqlite:'.$db_name, "", "", array(PDO::ATTR_PERSISTENT => true));
			else
				$this->link_id = new PDO('sqlite:'.$db_name);

			$this->link_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		} catch (PDOException $e) {
			error('Unable to open database \''.$db_name.'\'. PDO_SQLite reported: '.$e->getMessage(), __FILE__, __LINE__);
		}
	}


	function start_transaction()
	{
		$retVal = false;

		++$this->in_transaction;

		try {
			$retVal = $this->link_id->beginTransaction();
		} catch (PDOException $e) {
			$this->error_msg = $e->getMessage();
		}

		return $retVal;
	}


	function end_transaction()
	{
		$retVal = false;

		if ($this->in_transaction)
			--$this->in_transaction;

		try {
			$retVal = $this->link_id->commit();
		} catch (PDOException $e) {
			$this->error_msg = $e->getMessage();
			$this->rollback_transaction();
		}

		return $retVal;
	}


	function query($sql, $unbuffered = false)
	{
		if (defined('S2_SHOW_QUERIES'))
			$q_start = $this->get_microtime();

/* 		if ($unbuffered)
			$this->query_result = @sqlite_unbuffered_query($this->link_id, $sql);
		else
 */
		try
		{
			$this->query_result = $this->link_id->query($sql);
		}
		catch (PDOException $e)
		{
			if (defined('S2_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, 0);

			$this->error_no = $this->link_id->errorCode();
			$this->error_msg = end($this->link_id->errorInfo());

			if ($this->in_transaction)
			{
				$this->rollback_transaction();
				--$this->in_transaction;
			}

			return false;
		}

		$this->row_count = $this->query_result ? $this->query_result->rowCount() : 0;

		if (defined('S2_SHOW_QUERIES'))
			$this->saved_queries[] = array($sql, $this->get_microtime() - $q_start);

		++$this->num_queries;

		return $this->query_result;
	}


	function query_build($query, $return_query_string = false, $unbuffered = false)
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
			{
				$new_query = $query;
				if ($return_query_string)
				{
					$query_set = array();
					foreach ($query['VALUES'] as $cur_values)
					{
						$new_query['VALUES'] = $cur_values;
						$query_set[] = $this->query_build($new_query, true, $unbuffered);
					}

					$sql = implode('; ', $query_set);
				}
				else
				{
					$result_set = null;
					foreach ($query['VALUES'] as $cur_values)
					{
						$new_query['VALUES'] = $cur_values;
						$result_set = $this->query_build($new_query, false, $unbuffered);
					}

					return $result_set;
				}
			}
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

		return ($return_query_string) ? $sql : $this->query($sql, $unbuffered);
	}


	function result($query_id = 0, $row = 0, $col = 0)
	{
		if ($query_id)
		{
			for ($i = $row + 1; $i--;)
				if (!($db_row = $query_id->fetch(PDO::FETCH_NUM)))
					return false;

			return isset($db_row[$col]) ? $db_row[$col] : false;
		}
		else
			return false;
	}


	function fetch_assoc($query_id = 0)
	{
		return ($query_id) ? $query_id->fetch(PDO::FETCH_ASSOC) : false;
	}


	function fetch_row($query_id = 0)
	{
		return ($query_id) ? $query_id->fetch(PDO::FETCH_NUM) : false;
	}


	function num_rows($query_id = 0)
	{
		return ($this->link_id) ? $this->row_count : false;
	}


	function affected_rows()
	{
		return ($this->link_id) ? $this->row_count : false;
	}


	function insert_id()
	{
		return ($this->link_id) ? $this->link_id->lastInsertId() : false;
	}


	function get_num_queries()
	{
		return $this->num_queries;
	}


	function get_saved_queries()
	{
		return $this->saved_queries;
	}


	function free_result(&$query_id)
	{
		if (!$query_id)
			return false;

		$query_id->closeCursor();
		$query_id = null;
		return true;
	}


	function escape($str)
	{
		return is_array($str) ? '' : substr($this->link_id->quote($str), 1, -1);
	}


	function error()
	{
		$result['error_sql'] = @current(@end($this->saved_queries));
		$result['error_no'] = $this->error_no;
		$result['error_msg'] = $this->error_msg;

		return $result;
	}


	function close()
	{
		if ($this->link_id)
		{
			if ($this->in_transaction)
			{
				$this->link_id->commit();
			}

			$this->link_id = null;

			return true;
		}
		else
			return false;
	}


	function set_names($names)
	{
		return;
	}


	function get_version()
	{
		$sql = 'SELECT sqlite_version()';
		$result = $this->query($sql) or error(__FILE__, __LINE__);
		list($ver) = $this->fetch_row($result);
		$this->free_result($result);

		return array(
			'name'		=> 'SQLite',
			'version'	=> $ver
		);
	}


	function get_microtime()
	{
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}


	function array_insert(&$input, $offset, $element, $key = null)
	{
		if ($key == null)
			$key = $offset;

		// Determine the proper offset if we're using a string
		if (!is_int($offset))
			$offset = array_search($offset, array_keys($input), true);

		// Out of bounds checks
		if ($offset > count($input))
			$offset = count($input);
		else if ($offset < 0)
			$offset = 0;

		$input = array_merge(array_slice($input, 0, $offset), array($key => $element), array_slice($input, $offset));
	}


	function table_exists($table_name, $no_prefix = false)
	{
		$result = $this->query('SELECT 1 FROM sqlite_master WHERE name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND type=\'table\'');
		$return = $this->fetch_row($result) ? true : false;
		$this->free_result($result);
		return $return;
	}


	function field_exists($table_name, $field_name, $no_prefix = false)
	{
		$result = $this->query('SELECT sql FROM sqlite_master WHERE name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND type=\'table\'') or error(__FILE__, __LINE__);
		$return = $this->result($result);
		$this->free_result($result);
		if (!$return)
			return false;

		return preg_match('#[\r\n]'.preg_quote($field_name).' #', $return);
	}


	function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$result = $this->query('SELECT 1 FROM sqlite_master WHERE tbl_name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' AND name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_'.$this->escape($index_name).'\' AND type=\'index\'') or error(__FILE__, __LINE__);
		$return = $this->fetch_row($result) ? true : false;
		$this->free_result($result);
		return $return;
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
				$query .= 'UNIQUE ('.implode(',', $key_fields).'),'."\n";
		}

		// We remove the last two characters (a newline and a comma) and add on the ending
		$query = substr($query, 0, strlen($query) - 2)."\n".')';

		$result = $this->query($query) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$this->add_index($table_name, $index_name, $index_fields, false, $no_prefix);
		}
	}


	function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return;

		$sql = 'DROP TABLE '.($no_prefix ? '' : $this->prefix).$table_name;
		$result = $this->query($sql) or error(__FILE__, __LINE__);
		$this->free_result($result);

		return true;
	}


	function get_table_info($table_name, $no_prefix = false)
	{
		// Grab table info
		$result = $this->query('SELECT sql FROM sqlite_master WHERE tbl_name = \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\' ORDER BY type DESC') or error('Unable to fetch table information', __FILE__, __LINE__, $this->error());

		$table = array();
		$table['indices'] = array();
		$i = 0;
		while ($cur_index = $this->fetch_assoc($result))
		{
			if (empty($cur_index['sql']))
				continue;

			if (!isset($table['sql']))
				$table['sql'] = $cur_index['sql'];
			else
				$table['indices'][] = $cur_index['sql'];

			$i++;
		}
		$this->free_result($result);

		if (!$i)
			return;

		// Work out the columns in the table currently
		$table_lines = explode("\n", $table['sql']);
		$table['columns'] = array();
		foreach ($table_lines as $table_line)
		{
			$table_line = trim($table_line);
			if (substr($table_line, 0, 12) == 'CREATE TABLE')
				continue;
			else if (substr($table_line, 0, 11) == 'PRIMARY KEY')
				$table['primary_key'] = $table_line;
			else if (substr($table_line, 0, 6) == 'UNIQUE')
				$table['unique'] = $table_line;
			else if (substr($table_line, 0, strpos($table_line, ' ')) != '')
				$table['columns'][substr($table_line, 0, strpos($table_line, ' '))] = trim(substr($table_line, strpos($table_line, ' ')));
		}

		return $table;
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = 0, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$table = $this->get_table_info($table_name, $no_prefix);

		// Create temp table
		$now = time();
		$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' (', $table['sql']);
		$result = $this->query($tmptable) or error(__FILE__, __LINE__);
		$this->free_result($result);

		$result = $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Create new table sql
		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);
		$query = $field_type;
		if (!$allow_null)
			$query .= ' NOT NULL';
		if ($default_value === null || $default_value === '')
			$default_value = '\'\'';

		$query .= ' DEFAULT '.$default_value;

		$old_columns = array_keys($table['columns']);
		$this->array_insert($table['columns'], $after_field, $query.',', $field_name);

		$new_table = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details;

		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';

		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'];

		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$this->drop_table($table_name, $no_prefix) or error(__FILE__, __LINE__);

		// Create new table
		$result = $this->query($new_table) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
			{
				$result = $this->query($cur_index) or error(__FILE__, __LINE__);
				$this->free_result($result);
			}
		}

		//Copy content back
		$result = $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' ('.implode(', ', $old_columns).') SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Drop temp table
		$this->drop_table($table_name.'_t'.$now, $no_prefix) or error(__FILE__, __LINE__);

		return true;
	}


	function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = 0, $no_prefix = false)
	{
		return false;
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$table = $this->get_table_info($table_name, $no_prefix);

		// Create temp table
		$now = time();
		$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' (', $table['sql']);
		$result = $this->query($tmptable) or error(__FILE__, __LINE__);
		$this->free_result($result);

		$result = $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name)) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Work out the columns we need to keep and the sql for the new table
		unset($table['columns'][$field_name]);
		$new_columns = array_keys($table['columns']);

		$new_table = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' (';

		foreach ($table['columns'] as $cur_column => $column_details)
			$new_table .= "\n".$cur_column.' '.$column_details;

		if (isset($table['unique']))
			$new_table .= "\n".$table['unique'].',';

		if (isset($table['primary_key']))
			$new_table .= "\n".$table['primary_key'];

		$new_table = trim($new_table, ',')."\n".');';

		// Drop old table
		$this->drop_table($table_name, $no_prefix) or error(__FILE__, __LINE__);

		// Create new table
		$result = $this->query($new_table) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table['indices'] as $cur_index)
			{
				$result = $this->query($cur_index) or error(__FILE__, __LINE__);
				$this->free_result($result);
			}
		}

		//Copy content back
		$result = $this->query('INSERT INTO '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).' SELECT '.implode(', ', $new_columns).' FROM '.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'_t'.$now) or error(__FILE__, __LINE__);
		$this->free_result($result);

		// Drop temp table
		$this->drop_table($table_name.'_t'.$now, $no_prefix) or error(__FILE__, __LINE__);

		return true;
	}


	function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		$sql = 'CREATE '.($unique ? 'UNIQUE ' : '').'INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.' ON '.($no_prefix ? '' : $this->prefix).$table_name.'('.implode(',', $index_fields).')';
		$result = $this->query($sql) or error(__FILE__, __LINE__);
		$this->free_result($result);

		return true;
	}


	function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		$sql = 'DROP INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name;
		$result = $this->query($sql) or error(__FILE__, __LINE__);
		$this->free_result($result);

		return true;
	}
}
