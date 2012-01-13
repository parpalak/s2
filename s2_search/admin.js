/**
 * Adds functions to the admin panel
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

var s2_search = {

	reindex_query: function ()
	{
		GETAsyncRequest(sUrl + 'action=s2_search_makeindex', s2_search.reindex_callback);
	},

	reindex: function ()
	{
		s2_search.reindex_query();
		document.getElementById('s2_search_progress').innerHTML = ': 0%...';

		return false;
	},

	reindex_callback: function (xmlhttp)
	{
		var response = xmlhttp.responseText;
		if (response.indexOf('go_') == 0)
		{
			setTimeout(s2_search.reindex_query, 50);
			document.getElementById('s2_search_progress').innerHTML = ': ' + response.substring(3) + '%...';
		}
		else
		{
			if (response == 'stop')
				document.getElementById('s2_search_progress').innerHTML = ': 100%';
			else if (response)
				DisplayError(response);
		}
	},

	refresh_index: function (sChapter)
	{
		GETAsyncRequest(sUrl + 'action=s2_search_makeindex&chapter=' + encodeURIComponent(sChapter));
	}
}

Hooks.add('fn_save_article_end', 's2_search.refresh_index((sAction == "save_blog" ? "s2_blog_" : "") + document.forms["artform"].elements["page[id]"].value);');