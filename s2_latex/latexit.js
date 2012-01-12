/**
 * Replaces LaTeX formulae with pictures
 *
 * Inspired by http://www.codecogs.com/latex/htmlequations.php
 *
 * @copyright (C) 2011-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

var LatexIT = {scale : function(e,scale) {}};

(function ()
{
	var verOffset = navigator.userAgent.indexOf('Firefox'),
		ext = 'gif',
		oldFF = false;

	if (verOffset != -1)
	{
		var i, fullVersion = navigator.userAgent.substring(verOffset + 8);
		if ((i = fullVersion.indexOf(';')) != -1)
			fullVersion = fullVersion.substring(0, i);
		if ((i = fullVersion.indexOf(' ')) != -1)
			fullVersion = fullVersion.substring(0, i);
		if (parseInt('' + fullVersion) < 4)
			// FF 3 doesn't support svg in <img> and mistakes with <object> size
			oldFF = true;
	}

	if (!oldFF && document.implementation && document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#BasicStructure", "1.1"))
		ext = 'svg';

	// Add extension styles
	var head = document.getElementsByTagName('head')[0],
		style = document.createElement('style'),
		rules = '.s2_latex { display: block; } .s2_latex_span {vertical-align: middle; display: inline-block;}';

	style.type = 'text/css';
	if (style.styleSheet)
		style.styleSheet.cssText = rules;
	else
		style.appendChild(document.createTextNode(rules));
	head.appendChild(style);

	function bindReady (handler)
	{
		var called = false;

		function ready() 
		{
			if (called)
				return;
			called = true;
			handler();
		}

		if (document.addEventListener)
		{
			document.addEventListener("DOMContentLoaded", function()
			{
				document.removeEventListener("DOMContentLoaded", arguments.callee, false);
				ready();
			}, false );
		}
		else if (document.attachEvent)
		{
			if (document.documentElement.doScroll && window == window.top)
			{
				function tryScroll()
				{
					if (called)
						return;
					try 
					{
						document.documentElement.doScroll("left");
						ready();
					}
					catch(e)
					{
						setTimeout(tryScroll, 0);
					}
				}
				tryScroll();
			}

			document.attachEvent("onreadystatechange", function()
			{
				if (document.readyState === "complete" )
				{
					document.detachEvent("onreadystatechange", arguments.callee);
					ready();
				}
			});
		}

		if (window.addEventListener)
			window.addEventListener('load', ready, false)
		else if (window.attachEvent)
			window.attachEvent('onload', ready)
	}

	function create_image (sText)
	{
		sText = sText.replace(/\$\$(.*)\$\$/g, '$1');

		var eImg = document.createElement('IMG');
		eImg.setAttribute('src', s2_latex_url + '/latex.php?type=' + ext + '&latex=' + encodeURIComponent(sText));
		eImg.setAttribute('alt', sText);
		eImg.setAttribute('border', '0');
		eImg.className = 's2_latex';

		// This outer span fixes some bugs in Opera
		var eSpan = document.createElement('SPAN');
		eSpan.className = 's2_latex_span';
		eSpan.appendChild(eImg);

		return eSpan;
	}

	// Walks through the DOM tree
	function process_item (eItem)
	{
		var eNextChild = eItem.firstChild;

		while (eNextChild)
		{
			var eCurChild = eNextChild;
			eNextChild = eNextChild.nextSibling;

			if (eCurChild.nodeType == 1 && eCurChild.nodeName != 'SCRIPT' && eCurChild.nodeName != 'TEXTAREA' && eCurChild.nodeName != 'OBJECT')
				process_item(eCurChild);
			else if (eCurChild.nodeType == 3)
			{
				var as = (' ' + eCurChild.nodeValue + ' ').split(/\$\$/g);
				var item_num = as.length;
				if (item_num > 2)
				{
					as[0] = as[0].substring(1);
					as[item_num - 1] = as[item_num - 1].substring(0, as[item_num - 1].length - 1);

					for (var i = 0; i < item_num; i++)
					{
						if (i % 2)
						{
							if (i + 1 < item_num)
								eItem.insertBefore(create_image(as[i]), eCurChild);
							else
								eItem.insertBefore(document.createTextNode('$$' + as[i]), eCurChild);
						}
						else
							eItem.insertBefore(document.createTextNode(as[i]), eCurChild);
					}

					eItem.removeChild(eCurChild);
				}
			}
		}
	}

	bindReady(function()
	{
		process_item(document.getElementsByTagName('body')[0]);
	});
})();