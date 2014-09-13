<?php
/**
 * Abstract page render class.
 *
 * @copyright (C) 2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

abstract class Page_Abstract
{
	protected $template_id = 'site.php';
	protected $template = null;

	abstract public function __construct (array $params = array());


	public function obtainTemplate ($path = false)
	{
		try {
			$this->template = s2_get_template($this->template_id, $path);
		}
		catch (Exception $e) {
			error($e->getMessage());
		}
	}

	public function getTemplate ()
	{
		if ($this->template === null)
			$this->obtainTemplate();

		return $this->template;
	}
}
