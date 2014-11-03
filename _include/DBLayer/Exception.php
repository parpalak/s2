<?php
/**
 * Database layer exception
 *
 * @copyright (C) 2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class DBLayer_Exception extends Exception
{
	protected $error_query = '';

	public function __construct($message = '', $code = 0, $query = '', Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->error_query = $query;
	}

	public function getQuery()
	{
		return $this->error_query;
	}
}
