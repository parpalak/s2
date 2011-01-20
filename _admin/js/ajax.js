/**
 * Ajax requests.
 *
 * GET and POST requests via XMLHttpRequest.
 *
 * @copyright (C) 2007-2011 Roman Parpalak
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

xmlhttp_sync = getHTTPRequestObject();
xmlhttp_async = getHTTPRequestObject();

function CheckStatus (xmlhttp)
{
	if (xmlhttp.status != 200)
	{
		UnknownError(xmlhttp.responseText, xmlhttp.status);
		return false;
	}

	var s2_status = xmlhttp.getResponseHeader('X-S2-Status');

	if (s2_status == 'Expired' || s2_status == 'Lost' || s2_status == 'Forbidden')
	{
		alert(xmlhttp.responseText);
		return false
	}

	var exec_code = xmlhttp.getResponseHeader('X-S2-JS');
	if (exec_code)
		eval(exec_code);
	var after_code = xmlhttp.getResponseHeader('X-S2-JS-delayed');
	if (after_code)
		setTimeout(function () {eval(after_code);}, 0);

	return true;
}

function AjaxRequest (sRequestUrl, sParam, fCallback)
{
	var xmlhttp;

	if (fCallback == null)
	{
		SetWait(true);
		xmlhttp = xmlhttp_sync;
	}
	else
		xmlhttp = xmlhttp_async;

	if (sParam == '')
	{
		xmlhttp.open('GET', sRequestUrl, fCallback != null);
		if (fCallback != null)
			xmlhttp.onreadystatechange = function() {
				CheckStatus(xmlhttp);
				fCallback();
			};
		xmlhttp.send(null);
	}
	else
	{
		xmlhttp.open('POST', sRequestUrl, fCallback != null);
		xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xmlhttp.setRequestHeader("Content-length", sParam.length);
		xmlhttp.setRequestHeader("Connection", "close");
		if (fCallback != null)
			xmlhttp.onreadystatechange = function() {
				CheckStatus(xmlhttp);
				fCallback();
			};
		xmlhttp.send(sParam);
	}

	if (fCallback != null)
		return;

	var no_error = CheckStatus(xmlhttp);

	SetWait(false);

	return {'text': xmlhttp.responseText, 'status': (no_error ? '200' : '-1')};
}

function GETSyncRequest (sRequestUrl)
{
	return AjaxRequest(sRequestUrl, '');
}

function GETAsyncRequest (sRequestUrl, fCallback)
{
	AjaxRequest(sRequestUrl, '', fCallback);
}

function POSTSyncRequest (sRequestUrl, sParam)
{
	return AjaxRequest(sRequestUrl, sParam);
}

function DisplayError (sError)
{
	var eDiv = document.createElement('DIV');
	document.body.appendChild(eDiv);
	eDiv.setAttribute('id', 'error_dialog');
	eDiv.innerHTML = '<div class="error_back"></div><div class="error_window"><div class="error_text">' + sError + '</div></div><input type="button" onclick="CloseError();" value="Ok"></div>';
}

function CloseError ()
{
	var eDiv = document.getElementById('error_dialog');
	eDiv.parentNode.removeChild(eDiv);
}

function UnknownError (sError, iStatus)
{
	if (sError.indexOf('</body>') >= 0 && sError.indexOf('</html>') >= 0)
		DisplayError(sError);
	else
		DisplayError(S2_LANG_UNKNOWN_ERROR + ' ' + iStatus + '<br />' +
				S2_LANG_SERVER_RESPONSE + '<br />' + sError);
}
