<?php
/**
 * Displays the list of pages and excerpts for a specified tag.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Service extends Page_HTML
{
	protected $template_id = 'service.php';

	public function __construct (array $params = array())
	{
		parent::__construct();
		$this->page = $params + $this->page;
	}
}
