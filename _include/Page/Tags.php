<?php
/**
 * Displays tags list page.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Tags extends Page_Abstract
{
	public function __construct (array $params = array())
	{
		global $lang_common;

		$page = array(
			'path'			=> array(
				array(
					'title' => Model::main_page_title(),
					'link'  => s2_link('/'),
				),
				array(
					'title' => $lang_common['Tags'],
				),
			),
			'title'			=> $lang_common['Tags'],
			'date'			=> '',
			'text'			=> $this->renderPartial('tags_list', array('tags' => Placeholder::tags_list())),
		);

		($hook = s2_hook('pts_construct_end')) ? eval($hook) : null;

		$this->page = $page + $this->page;
	}
}
