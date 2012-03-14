/**
 * Basic functions: ajax, md5, popup messages.
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function hex_md5 (string)
{
	// Based on http://www.webtoolkit.info/javascript-md5.html

	function RotateLeft(lValue, iShiftBits)
	{
		return (lValue<<iShiftBits) | (lValue>>>(32-iShiftBits));
	}
 
	function AddUnsigned(lX, lY)
	{
		var lX8 = (lX & 0x80000000),
		lY8 = (lY & 0x80000000),
		lX4 = (lX & 0x40000000),
		lY4 = (lY & 0x40000000),
		lResult = (lX & 0x3FFFFFFF)+(lY & 0x3FFFFFFF);

		if (lX4 & lY4)
			return (lResult ^ 0x80000000 ^ lX8 ^ lY8);

		if (lX4 | lY4)
			return (lResult & 0x40000000) ? (lResult ^ 0xC0000000 ^ lX8 ^ lY8) : (lResult ^ 0x40000000 ^ lX8 ^ lY8);

		return (lResult ^ lX8 ^ lY8);
	}
 
	function F(x, y, z) { return (x & y) | ((~x) & z); }
	function G(x, y, z) { return (x & z) | (y & (~z)); }
	function H(x, y, z) { return (x ^ y ^ z); }
	function I(x, y, z) { return (y ^ (x | (~z))); }
 
	function FF(a, b, c, d, x, s, ac)
	{
		a = AddUnsigned(a, AddUnsigned(AddUnsigned(F(b, c, d), x), ac));
		return AddUnsigned(RotateLeft(a, s), b);
	}
 
	function GG(a,b,c,d,x,s,ac)
	{
		a = AddUnsigned(a, AddUnsigned(AddUnsigned(G(b, c, d), x), ac));
		return AddUnsigned(RotateLeft(a, s), b);
	}
 
	function HH(a,b,c,d,x,s,ac)
	{
		a = AddUnsigned(a, AddUnsigned(AddUnsigned(H(b, c, d), x), ac));
		return AddUnsigned(RotateLeft(a, s), b);
	}
 
	function II(a,b,c,d,x,s,ac)
	{
		a = AddUnsigned(a, AddUnsigned(AddUnsigned(I(b, c, d), x), ac));
		return AddUnsigned(RotateLeft(a, s), b);
	}
 
	function ConvertToWordArray(string)
	{
		var lWordCount;
		var lMessageLength = string.length;
		var lNumberOfWords_temp1 = lMessageLength + 8;
		var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64))/64;
		var lNumberOfWords = (lNumberOfWords_temp2 + 1)*16;
		var lWordArray = Array(lNumberOfWords - 1);
		var lBytePosition = 0;
		var lByteCount = 0;
		while (lByteCount < lMessageLength)
		{
			lWordCount = (lByteCount-(lByteCount % 4))/4;
			lBytePosition = (lByteCount % 4)*8;
			lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount)<<lBytePosition));
			lByteCount++;
		}
		lWordCount = (lByteCount-(lByteCount % 4))/4;
		lBytePosition = (lByteCount % 4)*8;
		lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80<<lBytePosition);
		lWordArray[lNumberOfWords-2] = lMessageLength<<3;
		lWordArray[lNumberOfWords-1] = lMessageLength>>>29;
		return lWordArray;
	};
 
	function WordToHex(lValue)
	{
		var WordToHexValue = "", WordToHexValue_temp = "", lByte, lCount;
		for (lCount = 0; lCount <= 3; lCount++)
		{
			lByte = (lValue>>>(lCount*8)) & 255;
			WordToHexValue_temp = "0" + lByte.toString(16);
			WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length - 2,2);
		}
		return WordToHexValue;
	};
 
	var x = Array();
	var k, AA, BB, CC, DD, a, b, c, d;
	var S11=7, S12=12, S13=17, S14=22;
	var S21=5, S22=9 , S23=14, S24=20;
	var S31=4, S32=11, S33=16, S34=23;
	var S41=6, S42=10, S43=15, S44=21;
 
	string = unescape(encodeURIComponent(string));
 
	x = ConvertToWordArray(string);
 
	a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
 
	for (k=0; k < x.length; k += 16)
	{
		AA=a; BB=b; CC=c; DD=d;
		a=FF(a,b,c,d,x[k+0], S11,0xD76AA478);
		d=FF(d,a,b,c,x[k+1], S12,0xE8C7B756);
		c=FF(c,d,a,b,x[k+2], S13,0x242070DB);
		b=FF(b,c,d,a,x[k+3], S14,0xC1BDCEEE);
		a=FF(a,b,c,d,x[k+4], S11,0xF57C0FAF);
		d=FF(d,a,b,c,x[k+5], S12,0x4787C62A);
		c=FF(c,d,a,b,x[k+6], S13,0xA8304613);
		b=FF(b,c,d,a,x[k+7], S14,0xFD469501);
		a=FF(a,b,c,d,x[k+8], S11,0x698098D8);
		d=FF(d,a,b,c,x[k+9], S12,0x8B44F7AF);
		c=FF(c,d,a,b,x[k+10],S13,0xFFFF5BB1);
		b=FF(b,c,d,a,x[k+11],S14,0x895CD7BE);
		a=FF(a,b,c,d,x[k+12],S11,0x6B901122);
		d=FF(d,a,b,c,x[k+13],S12,0xFD987193);
		c=FF(c,d,a,b,x[k+14],S13,0xA679438E);
		b=FF(b,c,d,a,x[k+15],S14,0x49B40821);
		a=GG(a,b,c,d,x[k+1], S21,0xF61E2562);
		d=GG(d,a,b,c,x[k+6], S22,0xC040B340);
		c=GG(c,d,a,b,x[k+11],S23,0x265E5A51);
		b=GG(b,c,d,a,x[k+0], S24,0xE9B6C7AA);
		a=GG(a,b,c,d,x[k+5], S21,0xD62F105D);
		d=GG(d,a,b,c,x[k+10],S22,0x2441453);
		c=GG(c,d,a,b,x[k+15],S23,0xD8A1E681);
		b=GG(b,c,d,a,x[k+4], S24,0xE7D3FBC8);
		a=GG(a,b,c,d,x[k+9], S21,0x21E1CDE6);
		d=GG(d,a,b,c,x[k+14],S22,0xC33707D6);
		c=GG(c,d,a,b,x[k+3], S23,0xF4D50D87);
		b=GG(b,c,d,a,x[k+8], S24,0x455A14ED);
		a=GG(a,b,c,d,x[k+13],S21,0xA9E3E905);
		d=GG(d,a,b,c,x[k+2], S22,0xFCEFA3F8);
		c=GG(c,d,a,b,x[k+7], S23,0x676F02D9);
		b=GG(b,c,d,a,x[k+12],S24,0x8D2A4C8A);
		a=HH(a,b,c,d,x[k+5], S31,0xFFFA3942);
		d=HH(d,a,b,c,x[k+8], S32,0x8771F681);
		c=HH(c,d,a,b,x[k+11],S33,0x6D9D6122);
		b=HH(b,c,d,a,x[k+14],S34,0xFDE5380C);
		a=HH(a,b,c,d,x[k+1], S31,0xA4BEEA44);
		d=HH(d,a,b,c,x[k+4], S32,0x4BDECFA9);
		c=HH(c,d,a,b,x[k+7], S33,0xF6BB4B60);
		b=HH(b,c,d,a,x[k+10],S34,0xBEBFBC70);
		a=HH(a,b,c,d,x[k+13],S31,0x289B7EC6);
		d=HH(d,a,b,c,x[k+0], S32,0xEAA127FA);
		c=HH(c,d,a,b,x[k+3], S33,0xD4EF3085);
		b=HH(b,c,d,a,x[k+6], S34,0x4881D05);
		a=HH(a,b,c,d,x[k+9], S31,0xD9D4D039);
		d=HH(d,a,b,c,x[k+12],S32,0xE6DB99E5);
		c=HH(c,d,a,b,x[k+15],S33,0x1FA27CF8);
		b=HH(b,c,d,a,x[k+2], S34,0xC4AC5665);
		a=II(a,b,c,d,x[k+0], S41,0xF4292244);
		d=II(d,a,b,c,x[k+7], S42,0x432AFF97);
		c=II(c,d,a,b,x[k+14],S43,0xAB9423A7);
		b=II(b,c,d,a,x[k+5], S44,0xFC93A039);
		a=II(a,b,c,d,x[k+12],S41,0x655B59C3);
		d=II(d,a,b,c,x[k+3], S42,0x8F0CCC92);
		c=II(c,d,a,b,x[k+10],S43,0xFFEFF47D);
		b=II(b,c,d,a,x[k+1], S44,0x85845DD1);
		a=II(a,b,c,d,x[k+8], S41,0x6FA87E4F);
		d=II(d,a,b,c,x[k+15],S42,0xFE2CE6E0);
		c=II(c,d,a,b,x[k+6], S43,0xA3014314);
		b=II(b,c,d,a,x[k+13],S44,0x4E0811A1);
		a=II(a,b,c,d,x[k+4], S41,0xF7537E82);
		d=II(d,a,b,c,x[k+11],S42,0xBD3AF235);
		c=II(c,d,a,b,x[k+2], S43,0x2AD7D2BB);
		b=II(b,c,d,a,x[k+9], S44,0xEB86D391);
		a=AddUnsigned(a,AA);
		b=AddUnsigned(b,BB);
		c=AddUnsigned(c,CC);
		d=AddUnsigned(d,DD);
	}
 
	var temp = WordToHex(a) + WordToHex(b) + WordToHex(c) + WordToHex(d);
 
	return temp.toLowerCase();
}

function StringFromForm (aeItem)
{
	var sRequest = 'ajax=1', i, eItem;

	for (i = aeItem.length; i-- ;)
	{
		eItem = aeItem[i];
		if (!eItem.name)
			continue;
		if (eItem.nodeName == 'INPUT' && (eItem.type != 'checkbox' || eItem.checked))
			sRequest += '&' + eItem.name + '=' + encodeURIComponent(eItem.value);
		if (eItem.nodeName == 'TEXTAREA' || eItem.nodeName == 'SELECT')
			sRequest += '&' + eItem.name + '=' + encodeURIComponent(eItem.value);
	}

	return sRequest;
}

//
// Ajax wrappers
//

function getHTTPRequestObject() 
{
	var xmlHttpRequest = false;

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

var xmlhttp_sync = getHTTPRequestObject();

function CheckStatus (xmlhttp)
{
	if (xmlhttp.status != 200)
	{
		UnknownError(xmlhttp.responseText, xmlhttp.status);
		return false;
	}

	var s2_status = xmlhttp.getResponseHeader('X-S2-Status');

	if (s2_status && s2_status != 'Success')
	{
		if (s2_status == 'Lost' || s2_status == 'Expired' || s2_status == 'Wrong_IP')
			PopupMessages.showUnique(xmlhttp.responseText, 'expired_session');
		else if (s2_status == 'Forbidden')
			PopupMessages.showUnique(xmlhttp.responseText, 'forbidden_action');
		else
			PopupMessages.show(xmlhttp.responseText);

		return false;
	}

	var exec_code = xmlhttp.getResponseHeader('X-S2-JS');
	if (exec_code)
		eval(exec_code);
	var after_code = xmlhttp.getResponseHeader('X-S2-JS-delayed');
	if (after_code)
		setTimeout(function () {eval(after_code);}, 0);

	return true;
}

function AsyncCallback ()
{
	if (this.readyState != 4)
		return;

	if (typeof SetWait == 'function')
		SetWait(false);

	if (CheckStatus(this) && this.S2CustomCallback)
		this.S2CustomCallback(this);
};

function AjaxRequest (sRequestUrl, sParam, fCallback)
{
	var xmlhttp;

	if (typeof SetWait == 'function')
		SetWait(true);

	if (fCallback == null)
	{
		xmlhttp = xmlhttp_sync;
	}
	else
	{
		xmlhttp = getHTTPRequestObject();
		if (typeof fCallback == 'function')
			xmlhttp.S2CustomCallback = fCallback;
	}

	xmlhttp.open(sParam == '' ? 'GET' : 'POST', sRequestUrl, fCallback != null);

	if (sParam != '')
		xmlhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

	if (fCallback != null)
		xmlhttp.onreadystatechange = AsyncCallback;

	xmlhttp.send(sParam == '' ? null : sParam);

	if (fCallback != null)
		return;

	var no_error = CheckStatus(xmlhttp);

	if (typeof SetWait == 'function')
		SetWait(false);

	return {'text': xmlhttp.responseText, 'status': (no_error ? '200' : '-1')};
}

function GETSyncRequest (sRequestUrl)
{
	return AjaxRequest(sRequestUrl, '');
}

function GETAsyncRequest (sRequestUrl, fCallback)
{
	if (fCallback == null)
		fCallback = true;
	AjaxRequest(sRequestUrl, '', fCallback);
}

function POSTSyncRequest (sRequestUrl, sParam)
{
	return AjaxRequest(sRequestUrl, sParam);
}

function POSTAsyncRequest (sRequestUrl, sParam, fCallback)
{
	if (fCallback == null)
		fCallback = true;
	AjaxRequest(sRequestUrl, sParam, fCallback);
}

//
// Displaying messages
//

function DisplayError (sError)
{
	var eDiv = document.createElement('DIV');
	document.body.appendChild(eDiv);
	eDiv.setAttribute('id', 'error_dialog');
	eDiv.innerHTML = '<div class="error_back"></div><div class="error_window"><div class="error_text">' + sError + '</div></div><input type="button" id="close_error_button" value="Ok"></div>';

	var eButton = document.getElementById('close_error_button');
	eButton.onclick = function () { eDiv.parentNode.removeChild(eDiv); };
	setTimeout(function () { eButton.focus() }, 40);
}

function UnknownError (sError, iStatus)
{
	if (sError.indexOf('</body>') >= 0 && sError.indexOf('</html>') >= 0)
		DisplayError(sError);
	else
		DisplayError(s2_lang.unknown_error + ' ' + iStatus + '<br />' +
				s2_lang.server_response + '<br />' + sError);
}

function PopupWindow (sTitle, sHeader, sInfo, sText)
{
	// HTML encode for textarea
	var div = document.createElement('div');
	div.appendChild(document.createTextNode(sText));
	sText = div.innerHTML;

	var wnd = window.open('about:blank', '', '', 'True');
	wnd.document.open();

	var color = '#eee';
	try
	{
		if (window.getComputedStyle) // All the cool bro
			color = window.getComputedStyle(document.body, null).backgroundColor;
		else if (document.body.currentStyle) // Heh, IE8
			color = document.body.currentStyle.backgroundColor;
	}
	catch (e)
	{
		color = '#eee';
	}

	var head = '<title>' + sTitle + '</title>' +
		'<style>html {height: 100%; margin: 0;} body {margin: 0 auto; padding: 9em 10% 1em; height: 100%; background: ' + color + '; font: 75% Verdana, sans-serif;} body, textarea {-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box;} h1 {margin: 0; padding: 0.5em 0 0;} textarea {width: 100%; height: 100%;} .text {position: absolute; top: 0; width: 80%;}</style>';
	var body = '<div class="text"><h1>' + sHeader + '</h1>' + 
		'<p>' + sInfo + '</p></div><textarea readonly="readonly">' + sText + '</textarea>';

	eval(Hooks.get('fn_popup_window_pre_mgr'));

	var text = '<!DOCTYPE html><html><head>' + head + '</head><body>' + body + '</body></html>';

	wnd.document.write(text);
	wnd.document.close();
}

var PopupMessages = {

	show : function (sMessage, aActions, iTime, sId)
	{
		var eDiv = document.getElementById('popup_message');
		if (!eDiv)
		{
			eDiv = document.createElement('DIV');
			document.body.appendChild(eDiv);
			eDiv.setAttribute('id', 'popup_message');

			var eInnerDiv = document.createElement('DIV');
			eDiv.appendChild(eInnerDiv);

			var eImg = document.createElement('IMG');
			eImg.setAttribute('src', 'i/1.gif');
			eImg.setAttribute('alt', '');
			eImg.onclick = function () { eDiv.parentNode.removeChild(eDiv); };
			eInnerDiv.appendChild(eImg);
		}
		else
			var eInnerDiv = eDiv.firstChild;

		if (sId)
		{
			var aeMessages = eInnerDiv.childNodes;
			for (var i = aeMessages.length; i-- ;)
				if (aeMessages[i].nodeName == 'DIV' && aeMessages[i].getAttribute('data-id') == sId)
				{
					aeMessages[i].style.color = 'transparent';
					setTimeout(function () { aeMessages[i].style.color = ''; }, 200);
					setTimeout(function () { aeMessages[i].style.color = 'transparent'; }, 350);
					setTimeout(function () { aeMessages[i].style.color = ''; }, 500);
					return;
				}
		}

		var eMessage = document.createElement('DIV');
		eMessage.setAttribute('data-id', sId || '');
		eInnerDiv.appendChild(eMessage);

		if (iTime)
		{
			setTimeout(function ()
			{
				eMessage.parentNode.removeChild(eMessage);
				if (eInnerDiv.childNodes.length == 1 && eDiv.parentNode)
					eDiv.parentNode.removeChild(eDiv);
			}, iTime * 1000);
		}

		eMessage.innerHTML = sMessage;

		var max = aActions ? aActions.length : 0;
		for (var i = 0; i < max; i++)
		{
			var eA = document.createElement('A');
			eA.setAttribute('class', 'action');
			eA.setAttribute('href', '#');
			eA.appendChild(document.createTextNode(aActions[i].name));
			(function (action, once)
			{
				eA.onclick = function ()
				{
					action();
					if (once)
					{
						eMessage.parentNode.removeChild(eMessage);
						if (eInnerDiv.childNodes.length == 1)
							eDiv.parentNode.removeChild(eDiv);
					}
					return false;
				}
			}(aActions[i].action, aActions[i].once));
			eMessage.appendChild(document.createTextNode('\u00a0 '));
			eMessage.appendChild(eA);
		}
	},

	showUnique: function (sMessage, sId)
	{
		this.show(sMessage, null, null, sId);
	},

	hide: function (sId)
	{
		var eDiv = document.getElementById('popup_message');
		if (!eDiv || !sId)
			return;

		var aeMessages = eDiv.firstChild.childNodes;
		for (var i = aeMessages.length; i-- ;)
			if (aeMessages[i].nodeName == 'DIV' && aeMessages[i].getAttribute('data-id') == sId)
				aeMessages[i].parentNode.removeChild(aeMessages[i]);

		if (eDiv.firstChild.childNodes.length == 1 && eDiv.parentNode)
			eDiv.parentNode.removeChild(eDiv);
	}
};

//
// Login form processing
//

function SendLoginData (eForm, fSuccess, fFailed)
{
	eForm.key.value = hex_md5(hex_md5(eForm.pass.value + 'Life is not so easy :-)') + ';-)' + eForm.getAttribute('data-salt'));
	var Response = POSTSyncRequest(sUrl + 'action=login', StringFromForm(eForm));

	if (Response.status == '200' && Response.text == 'OK')
		fSuccess();
	else if (Response.text.substr(0, 9) == 'OLD_SALT_')
	{
		var params = Response.text.split('_');
		eForm.setAttribute('data-salt', params[2]);
		eForm.challenge.value = params[3];
		setTimeout(function () { SendLoginData (eForm, fSuccess, fFailed); }, 0);
	}
	else
		fFailed(Response.text);
}

function SendAjaxLoginForm ()
{
	document.forms['loginform'].login.value = username;

	SendLoginData(document.forms['loginform'], function ()
	{
		PopupMessages.hide('expired_session')
	}, function (sText)
	{
		document.getElementById('ajax_login_message').innerHTML = sText;
		setTimeout(function ()
		{
			eDiv = document.getElementById('ajax_login_message');
			if (eDiv)
				eDiv.innerHTML = '';
		}, 5000)
	});
}
