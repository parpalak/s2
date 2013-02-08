/**
 * Basic functions: ajax, md5, popup messages.
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function str_replace (from, to, str)
{
	to = to.replace(/\$/g, '$$$$');
	while (str.indexOf(from) >= 0)
		str = str.replace(from, to);
	return str;
}

function hex_md5 (string)
{
	// Based on http://www.webtoolkit.info/javascript-md5.html

	function rot_l(lValue, iShiftBits)
	{
		return (lValue<<iShiftBits) | (lValue>>>(32-iShiftBits));
	}

	function add_usgn(lX, lY)
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
		a = add_usgn(a, add_usgn(add_usgn(F(b, c, d), x), ac));
		return add_usgn(rot_l(a, s), b);
	}

	function GG(a,b,c,d,x,s,ac)
	{
		a = add_usgn(a, add_usgn(add_usgn(G(b, c, d), x), ac));
		return add_usgn(rot_l(a, s), b);
	}

	function HH(a,b,c,d,x,s,ac)
	{
		a = add_usgn(a, add_usgn(add_usgn(H(b, c, d), x), ac));
		return add_usgn(rot_l(a, s), b);
	}

	function II(a,b,c,d,x,s,ac)
	{
		a = add_usgn(a, add_usgn(add_usgn(I(b, c, d), x), ac));
		return add_usgn(rot_l(a, s), b);
	}

	function ConvertToWordArray (s)
	{
		var lMsgLen = s.length,
			lNumWords_tmp1 = lMsgLen + 8,
			lNumWords_tmp2 = (lNumWords_tmp1 - (lNumWords_tmp1 % 64))/64,
			lNumWords = (lNumWords_tmp2 + 1)*16,
			lWrdArr = new Array(lNumWords - 1),
			lBytePos = 0,
			lByteCnt = 0;
		while (lByteCnt < lMsgLen)
		{
			var lWordCount = (lByteCnt-(lByteCnt % 4))/4;
			lBytePos = (lByteCnt % 4)*8;
			lWrdArr[lWordCount] = (lWrdArr[lWordCount] | (s.charCodeAt(lByteCnt)<<lBytePos));
			lByteCnt++;
		}
		lWordCount = (lByteCnt-(lByteCnt % 4))/4;
		lBytePos = (lByteCnt % 4)*8;
		lWrdArr[lWordCount] = lWrdArr[lWordCount] | (0x80<<lBytePos);
		lWrdArr[lNumWords-2] = lMsgLen<<3;
		lWrdArr[lNumWords-1] = lMsgLen>>>29;
		return lWrdArr;
	}

	function word2hex(lValue)
	{
		var val = "", tmp = "", lByte, lCount;
		for (lCount = 0; lCount <= 3; lCount++)
		{
			lByte = (lValue>>>(lCount*8)) & 255;
			tmp = "0" + lByte.toString(16);
			val = val + tmp.substr(tmp.length - 2,2);
		}
		return val;
	}

	var k, AA, BB, CC, DD, a, b, c, d,
		S11=7, S12=12, S13=17, S14=22,
		S21=5, S22=9 , S23=14, S24=20,
		S31=4, S32=11, S33=16, S34=23,
		S41=6, S42=10, S43=15, S44=21;

	string = unescape(encodeURIComponent(string));

	var x = ConvertToWordArray(string);

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
		a=add_usgn(a,AA);
		b=add_usgn(b,BB);
		c=add_usgn(c,CC);
		d=add_usgn(d,DD);
	}

	return (word2hex(a) + word2hex(b) + word2hex(c) + word2hex(d)).toLowerCase();
}

var SetBackground = (function ()
{
	var _size = 150, maxAlpha = 5.5, maxLine = 4;

	function noise ()
	{
		if (!document.createElement('canvas').getContext)
			return '';

		var canvas = document.createElement('canvas');
		canvas.width = canvas.height = _size;

		var ctx = canvas.getContext('2d'),
			img = ctx.createImageData(_size, _size),
			repeat_num = 0, alpha = 1, x, y, idx;

		for (y = _size; y--; )
		for (x = _size, idx = (x + y * _size) * 4; x--; )
		{
			if (Math.random() * maxLine < repeat_num++)
				alpha =  (repeat_num = 0) + ~~(Math.random() * maxAlpha);
			idx -= 4;
			img.data[idx] = img.data[idx + 1] = img.data[idx + 2] = 0;
			img.data[idx + 3] = alpha;
		}

		ctx.putImageData(img, 0, 0);

		return 'url(' + canvas.toDataURL('image/png') + ')';
	}

	var back_img = noise(),
		head = document.getElementsByTagName('head')[0],
		style = document.createElement('style');

	style.type = 'text/css';
	head.appendChild(style);

	return function (c)
	{
		var css_rule = 'body {background: ' + back_img + ' ' + c + '; background-attachment: local; background-size: ' + _size*8 + 'px ' + _size + 'px;} #tag_names li.cur_tag, .tabsheets > dt.active {background-color: ' + c + ';}';

		if (style.styleSheet)
			style.styleSheet.cssText = css_rule;
		else
		{
			if (style.firstChild)
				style.removeChild(style.firstChild);
			style.appendChild(document.createTextNode(css_rule));
		}
	};
}());

//
// Ajax wrappers
//

function checkAjaxStatus (XHR)
{
	XHR.s2ErrorFlag = true;

	if (XHR.status != 200)
	{
		UnknownError(XHR.responseText, XHR.status);
		return false;
	}

	var status = XHR.getResponseHeader('X-S2-Status');

	if (status && status != 'Success')
	{
		if (status == 'Lost' || status == 'Expired' || status == 'Wrong_IP')
			PopupMessages.showUnique(XHR.responseText, 'expired_session');
		else if (status == 'Forbidden')
			PopupMessages.showUnique(XHR.responseText, 'forbidden_action');
		else
			PopupMessages.show(XHR.responseText);

		return false;
	}

	XHR.s2ErrorFlag = false;
	return true;
}

function GETAsyncRequest (sRequestUrl, fCallback)
{
	$.get(sRequestUrl, function (data, textStatus, jqXHR)
	{
		if (!jqXHR.s2ErrorFlag && typeof fCallback == 'function')
			fCallback(jqXHR, data);
	});
}

function POSTAsyncRequest (sRequestUrl, sParam, fCallback)
{
	$.post(sRequestUrl, sParam, function (data, textStatus, jqXHR)
	{
		if (!jqXHR.s2ErrorFlag && typeof fCallback == 'function')
			fCallback(jqXHR, data);
	});
}

//
// Displaying messages
//

function DisplayError (sError)
{
	var $button = $('<input>').attr({'type': 'button', 'tabindex': "0", 'class': 'close_error_button'}).val('Ok'),
		$div = $('<div>').addClass('error_dialog')
		.append('<div class="error_back"></div><div class="error_window"><div class="error_text">' + sError + '</div></div>')
		.append($button).appendTo($('body'));

	$button.click(function ()
	{
		$div.remove();
		$('.close_error_button').last().focus();
	});

	setTimeout(function () { $button.focus() }, 100);
}

function UnknownError (sError, iStatus)
{
	if (sError.indexOf('</body>') == -1 || sError.indexOf('</html>') == -1)
		sError = s2_lang.unknown_error + ' ' + iStatus + '<br />' +
				s2_lang.server_response + '<br />' + sError;

	DisplayError(sError);
}

var PopupMessages = {

	show : function (sMessage, aActions, iTime, sId)
	{
		var $popup = $('#popup_message'), $list, $cross;

		if (!$popup.length)
		{
			$cross = $('<a>').attr({'class': 'cross', 'href': '#', 'tabindex': '0'})
				.click(function () { $popup.remove(); return false; });
			$list = $('<div>').addClass('message-list').append($cross);
			$popup = $('<div>').attr('id', 'popup_message').append($list);
			$('body').append($popup);
		}
		else
		{
			$list = $popup.children();
			$cross = $list.children('.cross');
		}
		$cross[0].focus();

		if (sId)
		{
			var $message = $list.children('div[data-id="' + sId + '"]');
			if ($message.length)
			{
				$message.fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100);
				return;
			}
		}

		$message = $('<div>').attr({'class': 'message', 'data-id': sId || ''}).appendTo($list);

		if (iTime)
		{
			setTimeout(function ()
			{
				$message.remove();
				if (!$list.children('.message').length)
					$popup.remove();
			}, iTime * 1000);
		}

		$message.html(sMessage);

		var max = aActions ? aActions.length : 0, i = 0;
		for (; i < max; i++)
		{
			var eA = $('<a>').attr({'class': 'action', 'href': '#', 'tabindex': '0'}).text(aActions[i].name);
			(function (action, once)
			{
				eA.click(function ()
				{
					action();
					if (once)
					{
						$message.remove();
						if (!$list.children('.message').length)
							$popup.remove();
					}
					return false;
				});
			}(aActions[i].action, aActions[i].once));
			$message.append('\u00a0 ').append(eA);
		}
	},

	showUnique: function (sMessage, sId)
	{
		this.show(sMessage, null, null, sId);
	},


	hide: function (sId)
	{
		if (!sId)
			return;

		var $popup = $('#popup_message'),
			$list = $popup.children();

		if (!$popup.length)
			return;

		$list.children('div[data-id="' + sId + '"]').remove();
		if (!$list.children('.message').length)
			$($popup).remove();
	}
};

//
// Login form processing
//

function SendLoginData (eForm, fOk, fFail)
{
	eForm.key.value = hex_md5(hex_md5(eForm.pass.value + 'Life is not so easy :-)') + ';-)' + eForm.getAttribute('data-salt'));
	POSTAsyncRequest(sUrl + 'action=login', $(eForm).serialize(), function (http)
	{
		var txt = http.responseText;
		if (txt == 'OK')
			fOk();
		else if (txt.substr(0, 9) == 'OLD_SALT_')
		{
			var params = txt.split('_');
			eForm.setAttribute('data-salt', params[2]);
			eForm.challenge.value = params[3];
			setTimeout(function () { SendLoginData (eForm, fOk, fFail); }, 0);
		}
		else
			fFail(txt);
	});
}

function SendAjaxLoginForm ()
{
	var frm = document.forms['loginform'];
	frm.elements['login'].value = username;
	$('#ajax_login_message').clearQueue().hide();

	SendLoginData(frm, function ()
	{
		PopupMessages.hide('expired_session');
	}, function (sText)
	{
		$('#ajax_login_message').hide().html(sText).fadeIn(100).delay(5000).fadeOut(100);
	});
}
