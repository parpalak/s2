/**
 * Helper functions for blog administrating
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

function BlogAddTag(iId)
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
	var Response = POSTSyncRequest(sUrl + "action=load_blog_posts", sRequest);
	if (Response.status == '200')
	{
		document.getElementById('blog_div').innerHTML = Response.text;
		init_table(null);
	}
	return false;
}

function EditRecord (iId)
{
	var sURI = sUrl + "action=edit_blog_post&id=" + iId;

	if (sCurrTextId != sURI)
	{
		// We are going to reload the editor content
		// only if the article to be loaded differs from the current one.

		if (document.artform && IsChanged(document.artform) && !confirm(S2_LANG_UNSAVED))
			return false;

		var Response = GETSyncRequest(sURI);
		if (Response.status != '200')
			return false;

		document.getElementById('form_div').innerHTML = Response.text;
		CommitChanges(document.artform);

		sCurrTextId = sURI;
	}
	SelectTab(document.getElementById('edit_tab'), true);
	return false;
}

function DeleteRecord (eItem, iId, sWarning)
{
	if (!confirm(sWarning))
		return false;

	var Response = GETSyncRequest(sUrl + "action=delete_blog_post&id=" + iId);
	if (Response.status == '200')
	{
		if (Response.text)
			alert(Response.text)
		else
			eItem.parentNode.parentNode.parentNode.parentNode.removeChild(eItem.parentNode.parentNode.parentNode);
	}

	return false;
}

function CreateBlankRecord ()
{
	if (document.artform && IsChanged(document.artform) && !confirm(S2_LANG_UNSAVED))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=create_blog_post');
	if (Response.status != '200')
		return false;

	// We've just asked about saving changes. User doesn't want to.
	CommitChanges(document.artform);

	EditRecord(Response.text);

	return false;
}

// Blog comments

function LoadBlogComments (iId)
{
	var Response = GETSyncRequest(sUrl + "action=load_blog_comments&id=" + iId);
	if (Response.status != '200')
		return false;

	document.getElementById('comm_div').innerHTML = Response.text;
	init_table(null);
	SelectTab(document.getElementById('comm_tab'), true);

	return false;
}

function DeleteBlogComment (iId)
{
	if (!confirm(S2_LANG_DELETE_COMMENT))
		return false;

	var Response = GETSyncRequest(sUrl + "action=delete_blog_comment&id=" + iId);
	if (Response.status == '200')
	{
		document.getElementById('comm_div').innerHTML = Response.text;
		init_table(null);
	}
	return false 
}

function ToggleFavBlog (eItem, iId)
{
	var Response = GETSyncRequest(sUrl + "action=flip_favorite_post&id=" + iId);

	if (Response.status != '200')
		return false;

	if (eItem.outerHTML)
		eItem.outerHTML = Response.text;
	else
	{
		eItem.setAttribute('class', get_attr(Response.text, 'class'));
		eItem.setAttribute('alt', get_attr(Response.text, 'alt'));
	}

	return false;
}

add_hook('fn_s2_counter_draw_chart_pre_rss', 'settings_file += ",../_extensions/s2_blog/rss.xml?" + Math.random();');
