<?php
/**
 * Interface for a page controller used for routing.
 *
 * @copyright 2014-2024 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

use Symfony\Component\HttpFoundation\Request;

interface Page_Routable
{
	public function __construct (array $params = array());
	public function handle(Request $request);
}
