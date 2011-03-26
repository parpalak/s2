/**
 * Autosearch library
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


(function ()
{
	// Ajax requester
	var http_request = false;

	if (window.XMLHttpRequest)
		http_request = new XMLHttpRequest();
	else if (window.ActiveXObject)
	{
		try
		{
			http_request = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch(e)
		{
			try
			{
				http_request = new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch(e){ }
		}
	}

	var last_search, eCurItem = null;

	function doSearch (str)
	{
		http_request.open('GET', s2_search_url + '/ajax.php?q=' + encodeURIComponent(str), true);
		http_request.onreadystatechange = function ()
		{
			if (http_request.readyState == 4 && http_request.status == 200)
				displayResults(http_request.responseText);
		};
		http_request.send(null);
		last_search = str;
	}

	function getBounds (eItem)
	{
		var rect = eItem.getBoundingClientRect();
		return {
			top: rect.top,
			right: rect.right,
			bottom: rect.bottom,
			left: rect.left,
			width: eItem.offsetWidth,
			height: eItem.offsetHeight
		};
	}

	function keyDown (e)
	{
		var key_code;
		if (window.event)
			key_code = window.event.keyCode;
		else if (e.keyCode)
			key_code = e.keyCode;
		else if (e.which)
			key_code = e.which;

		var stop_event = false;

		if (key_code == 13 && eCurItem)
		{
			var new_url = eCurItem.href;
			setTimeout(function ()
			{
				location.href = new_url;
			}, 0);
			search_input.form.action = '';
			hideResults();
			stop_event = true;
		}
		if (key_code == 27 && search_tips.style.display != 'none')
		{
			var old_value = search_input.value;
			hideResults();
			setTimeout(function ()
			{
				search_input.value = old_value;
				search_input.focus();
			}, 0);
			stop_event = true;
		}
		if (key_code == 38 || key_code == 40)
		{
			if (!eCurItem)
			{
				var aeItems = search_tips.getElementsByTagName('A');
				if (aeItems.length)
				{
					eCurItem = aeItems[key_code == 38 ? aeItems.length - 1 : 0];
					eCurItem.className = 'current';
					if (search_tips.scrollTop > -20 + eCurItem.offsetTop)
						search_tips.scrollTop = -20 + eCurItem.offsetTop;
					if (search_tips.scrollTop < 20 + eCurItem.offsetTop + eCurItem.offsetHeight - search_tips.offsetHeight)
						search_tips.scrollTop = 20 + eCurItem.offsetTop + eCurItem.offsetHeight - search_tips.offsetHeight;
				}
			}
			else
			{
				var eItem = eCurItem;
				eCurItem.className = '';
				steps:
				{
					while (key_code == 38 ? eItem.previousSibling : eItem.nextSibling)
					{
						eItem = key_code == 38 ? eItem.previousSibling : eItem.nextSibling;
						if (eItem.nodeName == 'A')
						{
							eCurItem = eItem;
							eCurItem.className = 'current';
							if (search_tips.scrollTop > -20 + eCurItem.offsetTop)
								search_tips.scrollTop = -20 + eCurItem.offsetTop;
							else if (search_tips.scrollTop < 20 + eCurItem.offsetTop + eCurItem.offsetHeight - search_tips.offsetHeight)
								search_tips.scrollTop = 20 + eCurItem.offsetTop + eCurItem.offsetHeight - search_tips.offsetHeight;
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

	var search_tips, key_code;

	function displayResults (sHTML)
	{
		if (!sHTML)
		{
			hideResults();
			return;
		}

		search_tips.innerHTML = sHTML;
		search_tips.style.display = 'block';

		var mc = getBounds(search_input);
		search_tips.style.top = document.documentElement.scrollTop + mc.bottom + shift_y + 'px';
		search_tips.style.left = mc.left + shift_x + 'px';
		search_tips.style.width = mc.width - 2 + delta_x + 'px';

		search_tips.scrollTop = 0;
	}

	function hideResults ()
	{
		if (search_tips)
			search_tips.style.display = 'none';
		eCurItem = null;
	}

	function hideHandler ()
	{
		blur_timer = setTimeout(function ()
		{
			last_search = '';
			hideResults();
		}, 20);
	}

	var search_input, search_timer, blur_timer, shift_x = 0, shift_y = 0, delta_x = 0;

	function init ()
	{
		// We have nothing to do without Ajas support
		if (!http_request)
			return;

		search_input = document.getElementById('s2_search_input');
		if (!search_input)
			search_input = document.getElementById('s2_search_input_ext');
		if (!search_input)
			return;

		var position_info = search_input.getAttribute('data-s2_search-pos');
		if (position_info)
		{
			position_info = position_info.split(/\s*,\s*/);
			shift_x = position_info[0] ? parseInt(position_info[0]) : shift_x;
			shift_y = position_info[1] ? parseInt(position_info[1]) : shift_y;
			delta_x = position_info[2] ? parseInt(position_info[2]) : delta_x;
		}

		// Search field events
		search_input.onkeydown = keyDown;
		search_input.onkeyup = function (e)
		{
			var new_search = search_input.value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
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
		search_input.onclick = function (e)
		{
			clearTimeout(blur_timer);
		};
		search_input.form.onsubmit = function (e)
		{
			if (eCurItem)
			{
				// IE <= 7 fixes
				var new_url = eCurItem.href;
				location.href = new_url;
				hideResults();
				return false;
			}
			if (!search_input.form.action)
				return false;
			return true;
		};
		search_input.setAttribute('autocomplete', 'off');

		// Autosearch results div
		search_tips = document.createElement('div');
		search_tips.style.display = 'none';
		search_tips.style.zIndex = '10';
		search_tips.id = 's2_search_tip';
		document.body.appendChild(search_tips);

		if (typeof(document.addEventListener) == 'undefined')
			document.attachEvent('onclick', hideHandler);
		else
			document.addEventListener('click', hideHandler, true);

		// Add extension styles
		var head = document.getElementsByTagName('head')[0],
			style = document.createElement('style'),
			rules = '#s2_search_tip { display: block; position: absolute; background: #fff; border: 1px solid #ccc; font-size: 0.85em; max-height: 25em; overflow: auto; overflow-x: hidden; -webkit-box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); -moz-box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); } #s2_search_tip a {display: block; padding: 2px; width: auto; outline: none;} #s2_search_tip a:hover {background: #ffd;} #s2_search_tip a.current {padding: 1px; border: 1px dotted #000;} #s2_search_tip em {background: #f2f2cc; text-decoration: inherit; font-style: normal; }';

		style.type = 'text/css';
		if (style.styleSheet)
			style.styleSheet.cssText = rules;
		else
			style.appendChild(document.createTextNode(rules));
		head.insertBefore(style, head.firstChild);
	}

	if (window.attachEvent)
		window.attachEvent('onload', init)
	else if (window.addEventListener)
		window.addEventListener('load', init, false)

})();