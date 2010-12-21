/**
 * Helper functions for file list in the editor tab
 *
 * @copyright (C) 2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_attachment
 */

var s2_attachment_was_upload = false;

function s2_attachment_upload_submit (eForm)
{
	s2_attachment_was_upload = true;
}

function s2_attachment_file_uploaded ()
{
	if (!s2_attachment_was_upload)
		return;

	var html = window.frames['s2_attachment_result'].document.getElementsByTagName('html')[0].innerHTML;
	if (html.indexOf('S2-State-Success') >= 0)
	{
		document.getElementById('s2_attachment_list').innerHTML = window.frames['s2_attachment_result'].document.getElementsByTagName('body')[0].innerHTML;
		document.getElementById('s2_attachment_file_upload_input').innerHTML = '<input name="pictures[]" multiple="true" min="1" max="999" size="9" type="file" />';
	}
	else
		window.open('javascript:document.write(\'' + html.replace(/\\/g, "\\\\").replace(/\'/g, '\\\'') + '\'); document.close();', 's2_error', 'width=500,height=300');
}

function s2_attachment_delete_file (iId, esWarning)
{
	if (!confirm(decodeURIComponent(esWarning)))
		return false;

	var Response = GETSyncRequest(sUrl + "action=s2_attachment_delete&id=" + iId);
	if (Response.status == '200')
		document.getElementById('s2_attachment_list').innerHTML = Response.text;

	return false;
}

function s2_attachment_rename_file (iId, sInfo, esName)
{
	var s = prompt(sInfo, decodeURIComponent(esName));
	if (typeof(s) != 'string')
		return false;

	var Response = GETSyncRequest(sUrl + "action=s2_attachment_rename&id=" + iId + '&name=' + encodeURIComponent(s));
	if (Response.status == '200')
		document.getElementById('s2_attachment_list').innerHTML = Response.text;

	return false;
}