/**
 * Ajax requests.
 *
 * GET and POST requests via XMLHttpRequest.
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function getHTTPRequestObject() 
{
	var xmlHttpRequest;
	/*@cc_on
	@if (@_jscript_version >= 5)
	try
	{
		xmlHttpRequest = new ActiveXObject("Msxml2.XMLHTTP");
	}
	catch (exception1)
	{
		try
		{
			xmlHttpRequest = new ActiveXObject("Microsoft.XMLHTTP");
		}
		catch (exception2)
		{
			xmlHttpRequest = false;
		}
	}
	@else
		xmlhttpRequest = false;
	@end @*/

	if (!xmlHttpRequest && typeof XMLHttpRequest != 'undefined')
	{
		try
		{
			xmlHttpRequest = new XMLHttpRequest();
		}
		catch (exception)
		{
			xmlHttpRequest = false;
		}
	}
	return xmlHttpRequest;
}

xmlhttp = getHTTPRequestObject();

function unknown_error (sError, iStatus)
{
	if (sError.indexOf('</body>') >= 0 && sError.indexOf('</html>') >= 0)
		window.open('javascript:document.write(\'' + sError.replace(/\\/g, "\\\\") + '\'); document.close();', 's2_error', 'width=500,height=300');
	else
		alert(S2_LANG_UNKNOWN_ERROR + ' ' + iStatus + '\n' +
			S2_LANG_SERVER_RESPONSE + '\n' + sError);
}

var after_code = '';

function GETSyncRequest (sUrl)
{
	SetWait(true);

	xmlhttp.open("GET", sUrl, false);
	xmlhttp.send(null);

	if (xmlhttp.status == '408')
	{
		if (confirm(S2_LANG_EXPIRED_SESSION))
			document.location.reload();
	}
	else if (xmlhttp.status == '404')
	{
		alert(S2_LANG_404);
	}
	else if (xmlhttp.status == '403')
	{
		alert(S2_LANG_403);
	}
	else if (xmlhttp.status == '200')
	{
		var exec_code = xmlhttp.getResponseHeader('X-S2-JS');
		if (exec_code)
			eval(exec_code);
		after_code = xmlhttp.getResponseHeader('X-S2-JS-delayed');
		if (after_code)
			setTimeout('eval(after_code);', 0);
	}
	else
		unknown_error(xmlhttp.responseText, xmlhttp.status)

	SetWait(false);

	return {'text': xmlhttp.responseText, 'status': xmlhttp.status};
}

function POSTSyncRequest (sUrl, sParam)
{
	SetWait(true);

	xmlhttp.open('POST', sUrl, false);
	xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlhttp.setRequestHeader("Content-length", sParam.length);
	xmlhttp.setRequestHeader("Connection", "close");
	xmlhttp.send(sParam);

	if (xmlhttp.status == '408')
	{
		if (confirm(S2_LANG_EXPIRED_SESSION))
			document.location.reload();
	}
	else if (xmlhttp.status == '404')
	{
		alert(S2_LANG_404);
	}
	else if (xmlhttp.status == '403')
	{
		alert(S2_LANG_403);
	}
	else if (xmlhttp.status == '200')
	{
		var exec_code = xmlhttp.getResponseHeader('X-S2-JS');
		if (exec_code)
			eval(exec_code);
		after_code = xmlhttp.getResponseHeader('X-S2-JS-delayed');
		if (after_code)
			setTimeout('eval(after_code);', 0);
	}
	else
		unknown_error(xmlhttp.responseText, xmlhttp.status)

	SetWait(false);

	return {'text': xmlhttp.responseText, 'status': xmlhttp.status};
}