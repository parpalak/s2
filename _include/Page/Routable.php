<?php
/**
 * Interface for a page controller used for routing.
 *
 * @copyright (C) 2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

interface Page_Routable
{
	public function __construct (array $params = array());
	public function render();
}
