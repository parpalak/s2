<?php
/**
 * A database layer class supporting transactions that relies on the MySQLi PHP extension.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class DBLayer_MySQLiInnodb extends DBLayer_MySQli
{
	const ENGINE = 'InnoDB';

	private $in_transaction = 0;


	public function start_transaction()
	{
		++$this->in_transaction;

		mysqli_query($this->link_id, 'START TRANSACTION');
		return;
	}


	public function end_transaction()
	{
		--$this->in_transaction;

		mysqli_query($this->link_id, 'COMMIT');
		return;
	}


	public function query($sql, $unbuffered = false)
	{
		if (defined('S2_SHOW_QUERIES'))
			$q_start = microtime(true);

		$this->query_result = @mysqli_query($this->link_id, $sql);

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

			$this->error_no = @mysqli_errno($this->link_id);
			$this->error_msg = @mysqli_error($this->link_id);

			// Rollback transaction
			if ($this->in_transaction)
				mysqli_query($this->link_id, 'ROLLBACK');

			--$this->in_transaction;

			return false;
		}
	}

	public function get_version()
	{
		$result = $this->query('SELECT VERSION()');

		return array(
			'name'		=> 'MySQL Improved (InnoDB)',
			'version'	=> preg_replace('/^([^-]+).*$/', '\\1', $this->result($result))
		);
	}
}
