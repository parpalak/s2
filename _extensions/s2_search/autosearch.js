/**
 * Autosearch library
 *
 * @copyright 2011-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */


(function ()
{
	var xhr = false;

	if (window.XMLHttpRequest)
		xhr = new XMLHttpRequest();
	else if (window.ActiveXObject)
	{
		try
		{
			xhr = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch(e)
		{
			try
			{
				xhr = new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch(e){ }
		}
	}

	var last_search, eCurItem = null;

	function doSearch (str)
	{
		xhr.open('GET', s2_search_url + '/ajax.php?q=' + encodeURIComponent(str), true);
		xhr.onreadystatechange = function ()
		{
			if (xhr.readyState == 4 && xhr.status == 200)
				displayResults(xhr.responseText);
		};
		xhr.send(null);
		last_search = str;
	}

	function getOffsetRect (eItem) {
		var
			box = eItem.getBoundingClientRect(),
			body_box = document.body.getBoundingClientRect(),

			top  = box.top - body_box.top,
			left = box.left - body_box.left;

		return {top: Math.round(top), left: Math.round(left), width: eItem.offsetWidth, height: eItem.offsetHeight};
	}

	function keyDown (e)
	{
		var iKey;
		if (window.event)
			iKey = window.event.keyCode;
		else if (e.keyCode)
			iKey = e.keyCode;
		else if (e.which)
			iKey = e.which;

		var stop_event = false;

		if (iKey == 13 && eCurItem)
		{
			var new_url = eCurItem.href;
			setTimeout(function ()
			{
				location.href = new_url;
			}, 0);
			SInp.form.action = '';
			hideResults();
			stop_event = true;
		}
		if (iKey == 27 && STips.style.display != 'none')
		{
			var old_value = SInp.value;
			hideResults();
			setTimeout(function ()
			{
				SInp.value = old_value;
				SInp.focus();
			}, 0);
			stop_event = true;
		}
		if (iKey == 38 || iKey == 40)
		{
			if (!eCurItem)
			{
				var aeItems = STips.getElementsByTagName('A');
				if (aeItems.length)
				{
					eCurItem = aeItems[iKey == 38 ? aeItems.length - 1 : 0];
					eCurItem.className = 'current';
					if (STips.scrollTop > -20 + eCurItem.offsetTop)
						STips.scrollTop = -20 + eCurItem.offsetTop;
					if (STips.scrollTop < 20 + eCurItem.offsetTop + eCurItem.offsetHeight - STips.offsetHeight)
						STips.scrollTop = 20 + eCurItem.offsetTop + eCurItem.offsetHeight - STips.offsetHeight;
				}
			}
			else
			{
				var eItem = eCurItem;
				eCurItem.className = '';
				steps:
				{
					while (iKey == 38 ? eItem.previousSibling : eItem.nextSibling)
					{
						eItem = iKey == 38 ? eItem.previousSibling : eItem.nextSibling;
						if (eItem.nodeName == 'A')
						{
							eCurItem = eItem;
							eCurItem.className = 'current';
							if (STips.scrollTop > -20 + eCurItem.offsetTop)
								STips.scrollTop = -20 + eCurItem.offsetTop;
							else if (STips.scrollTop < 20 + eCurItem.offsetTop + eCurItem.offsetHeight - STips.offsetHeight)
								STips.scrollTop = 20 + eCurItem.offsetTop + eCurItem.offsetHeight - STips.offsetHeight;
							break steps;
						}
					}
					eCurItem =  null;
				}
			}
			stop_event = true;
		}

 		if (stop_event)
		{
 			if (window.event)
			{
				window.event.cancelBubble = true;
				window.event.returnValue = false;
			}
			try
			{
				e.stopPropagation();
				e.preventDefault();
			}
			catch (error) {}
 			return false;
		}
	}

	var STips;

	function displayResults (sHTML)
	{
		if (!sHTML)
		{
			hideResults();
			return;
		}

		STips.innerHTML = sHTML;
		STips.style.display = 'block';

		var mc = getOffsetRect(SInp);
		STips.style.top = mc.top + mc.height + shift_y + 'px';
		STips.style.left = mc.left + shift_x + 'px';
		STips.style.width = mc.width - 2 + delta_x + 'px';

		STips.scrollTop = 0;
	}

	function hideResults ()
	{
		if (STips)
			STips.style.display = 'none';
		eCurItem = null;
	}

	function hide ()
	{
		blur_timer = setTimeout(function ()
		{
			last_search = '';
			hideResults();
		}, 20);
	}

	var SInp, search_timer, blur_timer, shift_x = 0, shift_y = 0, delta_x = 0;

	function init ()
	{
		// We have nothing to do without Ajax support
		if (!xhr)
			return;

		SInp = document.getElementById('s2_search_input');
		if (!SInp)
			SInp = document.getElementById('s2_search_input_ext');
		if (!SInp)
			return;

		var pos_info = SInp.getAttribute('data-s2_search-pos');
		if (pos_info)
		{
			pos_info = pos_info.split(/\s*,\s*/);
			shift_x = pos_info[0] ? parseInt(pos_info[0]) : shift_x;
			shift_y = pos_info[1] ? parseInt(pos_info[1]) : shift_y;
			delta_x = pos_info[2] ? parseInt(pos_info[2]) : delta_x;
		}

		// Search field events
		SInp.onkeydown = keyDown;
		SInp.onkeyup = function (e)
		{
			var new_search = SInp.value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
			if (last_search != new_search)
			{
				clearTimeout(search_timer);
				if (new_search.length >= 1)
					search_timer = setTimeout(function () { doSearch(new_search); }, 250);
				else
				{
					last_search = '';
					hideResults();
				}
			}
		};
		SInp.onclick = function (e)
		{
			clearTimeout(blur_timer);
		};
		SInp.form.onsubmit = function (e)
		{
			if (eCurItem)
			{
				// IE <= 7 fixes
				var new_url = eCurItem.href;
				location.href = new_url;
				hideResults();
				return false;
			}
			return !!SInp.form.action;
		};
		SInp.setAttribute('autocomplete', 'off');

		// Autosearch results div
		STips = document.createElement('div');
		STips.style.display = 'none';
		STips.style.zIndex = '10';
		STips.id = 's2_search_tip';
		document.body.appendChild(STips);

		if (typeof(document.addEventListener) == 'undefined')
			document.attachEvent('onclick', hide);
		else
			document.addEventListener('click', hide, true);

		// Add extension styles
		var head = document.getElementsByTagName('head')[0],
			style = document.createElement('style'),
			rules = '#s2_search_tip { display: block; position: absolute; background: #fff; border: 1px solid #ccc; font-size: 0.85em; max-height: 25em; overflow: auto; overflow-x: hidden; -webkit-box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); -moz-box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); } #s2_search_tip a {display: block; padding: 2px; width: auto; outline: none;} #s2_search_tip a:hover {background: #ffd;} #s2_search_tip a.current {padding: 1px; border: 1px dotted #000;} #s2_search_tip em {background: #fff8d3; text-decoration: inherit; font-style: normal; }';

		style.type = 'text/css';
		if (style.styleSheet)
			style.styleSheet.cssText = rules;
		else
			style.appendChild(document.createTextNode(rules));
		head.insertBefore(style, head.firstChild);
	}

	if (window.attachEvent)
		window.attachEvent('onload', init);
	else if (window.addEventListener)
		window.addEventListener('load', init, false);

})();