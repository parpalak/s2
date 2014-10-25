<?php
/**
 * Displays tags list page.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Tags extends Page_HTML implements Page_Routable
{
	public function __construct (array $params = array())
	{
		parent::__construct();

		($hook = s2_hook('pts_construct_end')) ? eval($hook) : null;

		$this->page = array(
			'path'			=> array(
				array(
					'title' => Model::main_page_title(),
					'link'  => s2_link('/'),
				),
				array(
					'title' => Lang::get('Tags'),
				),
			),
			'title'			=> Lang::get('Tags'),
			'date'			=> '',
			'text'			=> $this->renderPartial('tags_list', array('tags' => Placeholder::tags_list())),
		);
	}
}
