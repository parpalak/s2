<?php
/**
 * Hook idx_template_pre_replace
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($this instanceof \s2_extensions\s2_search\Page)
{
	$replace['<!-- s2_search_field -->'] = '';
}
else
{
	Lang::load('s2_search', function () use ($ext_info)
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_search'.'/lang/English.php';
	});
	$replace['<!-- s2_search_field -->'] = '<form class="s2_search_form" method="get" action="'.(S2_URL_PREFIX ? S2_PATH.S2_URL_PREFIX : S2_PATH.'/search').'">'.(S2_URL_PREFIX ? '<input type="hidden" name="search" value="1" />' : '').'<input type="text" name="q" id="s2_search_input" placeholder="'.Lang::get('Search', 's2_search').'"/></form>';
}
