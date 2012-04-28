/**
 * Adds functions to the admin panel
 *
 * @copyright (C) 2011-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

var s2_search = {

	reindex_query: function ()
	{
		GETAsyncRequest(sUrl + 'action=s2_search_makeindex', function (http, data)
		{
			if (data.indexOf('go_') == 0)
			{
				setTimeout(s2_search.reindex_query, 50);
				$('#s2_search_progress').html(': <b>' + data.substring(3) + '%</b>...');
			}
			else
			{
				if (data == 'stop')
					$('#s2_search_progress').html(': 100%');
				else if (data)
					DisplayError(data);
			}
		});
	},

	reindex: function ()
	{
		s2_search.reindex_query();
		$('#s2_search_progress').html(': <b>0%</b>...');

		return false;
	},

	build_index: function ()
	{
		selectTab('#admin_tab');
		selectTab('#admin-stat_tab');
		setTimeout(s2_search.reindex, 50);
	},

	refresh_index: function (sAction, sId)
	{
		GETAsyncRequest(sUrl + 'action=s2_search_makeindex&save_action=' + encodeURIComponent(sAction) + '&id=' + encodeURIComponent(sId));
	}
}

Hooks.add('fn_save_article_end', function (sAction) { s2_search.refresh_index(sAction, document.forms['artform'].elements['page[id]'].value); });