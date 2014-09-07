<?php
/**
 * Displays tags list page.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Tags implements Page_Abstract
{
	public function render ($params)
	{
		global $template, $page;

		// We process tags pages in a different way
		$template_id = 'site.php';
		$page = self::s2_make_tags_pages();
		$template = s2_get_template($template_id);
	}

	//
	// Builds tags pages
	//
	private static function s2_make_tags_pages ()
	{
		global $s2_db, $lang_common;

		$page = array(
			'text'			=> '<div class="tags_list">'.Placeholder::tags_list().'</div>',
			'date'			=> '',
			'title'			=> $lang_common['Tags'],
			'path'			=> '<a href="'.s2_link('/').'">'.s2_htmlencode(Model::main_page_title()).'</a>'.$lang_common['Crumbs separator'].$lang_common['Tags'],
		);

		($hook = s2_hook('fn_s2_make_tags_pages_tags_end')) ? eval($hook) : null;

		return $page;
	}
}
