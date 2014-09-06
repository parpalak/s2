<?php
/**
 * A database layer class supporting transactions that relies on the MySQL PHP extension.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class DBLayer_MySQLInnodb extends DBLayer_MySQL
{
	const ENGINE = 'InnoDB';

	private $in_transaction = 0;


	public function start_transaction()
	{
		++$this->in_transaction;

		mysql_query('START TRANSACTION', $this->link_id);
		return;
	}


	public function end_transaction()
	{
		--$this->in_transaction;

		mysql_query('COMMIT', $this->link_id);
		return;
	}


	public function query($sql, $unbuffered = false)
	{
		if (defined('S2_SHOW_QUERIES'))
			$q_start = microtime(true);

		if ($unbuffered)
			$this->query_result = @mysql_unbuffered_query($sql, $this->link_id);
		else
			$this->query_result = @mysql_query($sql, $this->link_id);

		if ($this->query_result)
		{
			if (defined('S2_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, microtime(true) - $q_start);

			++$this->num_queries;

			return $this->query_result;
		}
		else
		{
			if (defined('S2_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, 0);

			$this->error_no = @mysql_errno($this->link_id);
			$this->error_msg = @mysql_error($this->link_id);

			// Rollback transaction
			if ($this->in_transaction)
				mysql_query('ROLLBACK', $this->link_id);

			--$this->in_transaction;

			return false;
		}
	}

	public function get_version()
	{
		$result = $this->query('SELECT VERSION()');

		return array(
			'name'		=> 'MySQL Standard (InnoDB)',
			'version'	=> preg_replace('/^([^-]+).*$/', '\\1', $this->result($result))
		);
	}
}
