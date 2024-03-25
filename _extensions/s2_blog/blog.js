/**
 * Helper functions for blog administrating
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

function LoadPosts ()
{
	POSTAsyncRequest(sUrl + "action=load_blog_posts", $(document.forms['blogform']).serialize(), function(http, data)
	{
		$('#blog_div').html(data);
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
			$(eItem).parent().parent().parent().remove();
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

function LoadBlogComments (iId)
{
	GETAsyncRequest(sUrl + "action=load_blog_comments&id=" + iId, function (http, data)
	{
		$('#comm_div').html(data);
		selectTab('#comm_tab');
	});
	return false;
}

function DeleteBlogComment (iId, sMode)
{
	if (!confirm(s2_lang.delete_comment))
		return false;

	GETAsyncRequest(sUrl + "action=delete_blog_comment&id=" + iId + '&mode=' + sMode, function (http, data)
	{
		$('#comm_div').html(data);
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

$(document).on('save_article_end.s2', function (e, sAction) {
	if (sAction != 'save_blog')
		return;

	var $a = $('#preview_link'),
		sLink = $a.attr('href'),
		frm = document.forms['artform'].elements,
		sDate = frm['page[create_time]'].value,
		dateObj = new Date(sDate),
		sYear = dateObj.getFullYear(),
		sMonth = String(dateObj.getMonth() + 1).padStart(2, '0'),
		sDay = String(dateObj.getDate()).padStart(2, '0'),
		sURL = frm['page[url]'].value;

	$a.attr('href', sLink.replace(/\/\d*\/\d*\/\d*\/[^\/]*$/, '/' + sYear + '/' + sMonth + '/' + sDay + '/' + sURL))
});
