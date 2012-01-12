/*
* LaTeX IT - JavaScript to Convert Latex within an HTML page into Equations
* Copyright (C) 2009 William Bateman, 2008 Waipot Ngamsaad
* Modified by Roman Parpalak

* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.

* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.

* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

var LatexIT = (function ()
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
			oldFF = true;
	}

	// FF 3 doesn't support svg in <img> and mistakes with <object> size
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

	return ({
		mode : ext,

		pre : function(txt)
		{
			if (!txt.match(/<img.*?>/i))
			{
				//Clean code
				txt = txt.replace(/<br>/gi,"");
				txt = txt.replace(/<br \/>/gi,"");

				//Create img tag
				txt = "<img src=\"http://latex.codecogs.com/" + this.mode + ".latex?" + txt + "\" alt=\"" + txt + "\" title=\"" + txt + "\" border=\"0\" class=\"latex\" style=\"margin:0; padding:0; border:0\" />";
			}
			return txt;
		},

		create_image : function (sText)
		{
			sText = sText.replace(/\$\$(.*)\$\$/g, '$1');

			var eImg = document.createElement('IMG');
			eImg.setAttribute('src', s2_latex_url + '/latex.php?type=' + this.mode + '&latex=' + encodeURIComponent(sText));
			eImg.setAttribute('alt', sText);
			eImg.setAttribute('border', '0');
			eImg.className = 's2_latex';

			// This outer span fixes some bugs in Opera
			var eSpan = document.createElement('SPAN');
			eSpan.className = 's2_latex_span';
			eSpan.appendChild(eImg);

			return eSpan;
		},

		process_item : function (eItem)
		{
			var eNextChild = eItem.firstChild;

			while (eNextChild)
			{
				var eCurChild = eNextChild;
				eNextChild = eNextChild.nextSibling;

				if (eCurChild.nodeType == 1 && eCurChild.nodeName != 'SCRIPT' && eCurChild.nodeName != 'TEXTAREA' && eCurChild.nodeName != 'OBJECT')
					LatexIT.process_item(eCurChild);
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
									eItem.insertBefore(LatexIT.create_image(as[i]), eCurChild);
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
		},

		render : function(tag, latexmode)
		{
			var aeItem = document.getElementsByTagName(tag);
			for (var i = aeItem.length; i-- ;)
			{
				var eItem = aeItem[i];
				if (latexmode)
					LatexIT.process_item(eItem);
				else
				{
					try
					{
						if (eItem.getAttribute("lang") == "latex" || eItem.getAttribute("xml:lang") == "latex")
							eItem.innerHTML = LatexIT.pre(eItem.innerHTML);
					}
					catch (e) {}
				}
			} 
		},

		add : function(tag, latexmode)
		{
			if (typeof(latexmode) == 'undefined')
				latexmode = false;
			LatexIT.bindReady(function()
			{
				LatexIT.render(tag, latexmode);
			});
		},

		bindReady : function (handler)
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
		},

		scale : function(e,scale)
		{
		}
	});
})();

LatexIT.add('*');
LatexIT.add('body', true);
