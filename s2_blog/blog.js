/**
 * Helper functions for blog administrating
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

function BlogAddTag (iId)
{
	var sPostTags = document.artform.keywords.value;

	if (sPostTags.indexOf('|' + iId + '|') != -1)
	{
		sPostTags = sPostTags.replace('|' + iId + '|', '|');
		document.getElementById('tag_' + iId).innerHTML = '';

		if (sPostTags != '|')
		{
			var aTags = sPostTags.slice(1, -1).split('|');
			for (var i = aTags.length; i-- ;)
				document.getElementById('tag_' + aTags[i]).innerHTML = i + 1;
		}
	}
	else
	{
		sPostTags = sPostTags + iId + '|';
		document.getElementById('tag_' + iId).innerHTML = sPostTags.slice(1, -1).split('|').length;
	}

	document.artform.keywords.value = sPostTags;
	return false;
}

function LoadPosts ()
{
	var sRequest = StringFromForm(document.blogform);
	POSTAsyncRequest(sUrl + "action=load_blog_posts", sRequest, function(http)
	{
		document.getElementById('blog_div').innerHTML = http.responseText;
		init_table(null);
	});
	return false;
}

function EditRecord (iId)
{
	LoadArticle(sURI = sUrl + 'action=edit_blog_post&id=' + iId);

	return false;
}

function DeleteRecord (eItem, iId, sWarning)
{
	if (!confirm(sWarning))
		return false;

	GETAsyncRequest(sUrl + "action=delete_blog_post&id=" + iId, function (http)
	{
		if (http.responseText)
			alert(http.responseText)
		else
			eItem.parentNode.parentNode.parentNode.parentNode.removeChild(eItem.parentNode.parentNode.parentNode);
	});
	return false;
}

function CreateBlankRecord ()
{
	GETAsyncRequest(sUrl + 'action=create_blog_post', function (http)
	{
		setTimeout(LoadPosts, 10);
		EditRecord(http.responseText);
	});
	return false;
}

// Blog comments

function LoadBlogComments (iId)
{
	GETAsyncRequest(sUrl + "action=load_blog_comments&id=" + iId, function (http)
	{
		document.getElementById('comm_div').innerHTML = http.responseText;
		init_table(null);
		SelectTab(document.getElementById('comm_tab'), true);
	});
	return false;
}

function DeleteBlogComment (iId, sMode)
{
	if (!confirm(s2_lang.delete_comment))
		return false;

	GETAsyncRequest(sUrl + "action=delete_blog_comment&id=" + iId + '&mode=' + sMode, function (http)
	{
		document.getElementById('comm_div').innerHTML = http.responseText;
		init_table(null);
	});
	return false 
}

function ToggleFavBlog (eItem, iId)
{
	GETAsyncRequest(sUrl + "action=flip_favorite_post&id=" + iId, function (http)
	{
		var temp = eItem.getAttribute('data-class');
		eItem.setAttribute('data-class', eItem.getAttribute('class'));
		eItem.setAttribute('class', temp);

		temp = eItem.getAttribute('data-alt');
		eItem.setAttribute('data-alt', eItem.getAttribute('alt'));
		eItem.setAttribute('alt', temp);
		eItem.setAttribute('title', temp);
	});
	return false;
}

function AdjustPreviewLink ()
{
	var eA = document.getElementById('preview_link'),
		sLink = eA.getAttribute('href'),
		eForm = document.forms['artform'],
		sYear = eForm.elements['page[create_time][year]'].value,
		sMonth = eForm.elements['page[create_time][mon]'].value,
		sDay = eForm.elements['page[create_time][day]'].value;

	eA.setAttribute('href', sLink.replace(/\/\d*\/\d*\/\d*(\/[^\/]*)$/, '/' + sYear + '/' + sMonth + '/' + sDay + '$1'))
}

Hooks.add('fn_s2_counter_draw_chart_pre_rss', 'settings_file += ",../_extensions/s2_blog/rss.xml?" + Math.random();');
Hooks.add('fn_save_article_end', 'AdjustPreviewLink();');