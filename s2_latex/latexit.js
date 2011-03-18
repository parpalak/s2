/* Cross-Browser Split 1.0.1
(c) Steven Levithan <stevenlevithan.com>; MIT License
An ECMA-compliant, uniform cross-browser split method */

var cbSplit;

// avoid running twice, which would break `cbSplit._nativeSplit`'s reference to the native `split`
if (!cbSplit)
{
	cbSplit = function (str, separator, limit)
	{
		// if `separator` is not a regex, use the native `split`
		if (Object.prototype.toString.call(separator) !== "[object RegExp]")
		{
			return cbSplit._nativeSplit.call(str, separator, limit);
		}

		var output = [],
			lastLastIndex = 0,
			flags = (separator.ignoreCase ? "i" : "") +
					(separator.multiline  ? "m" : "") +
					(separator.sticky ? "y" : ""),
			separator = RegExp(separator.source, flags + "g"), // make `global` and avoid `lastIndex` issues by working with a copy
			separator2, match, lastIndex, lastLength;

		str = str + ""; // type conversion
		if (!cbSplit._compliantExecNpcg)
		{
			separator2 = RegExp("^" + separator.source + "$(?!\\s)", flags); // doesn't need /g or /y, but they don't hurt
		}

		/* behavior for `limit`: if it's...
		- `undefined`: no limit.
		- `NaN` or zero: return an empty array.
		- a positive number: use `Math.floor(limit)`.
		- a negative number: no limit.
		- other: type-convert, then use the above rules. */
		if (limit === undefined || +limit < 0)
		{
			limit = Infinity;
		}
		else
		{
			limit = Math.floor(+limit);
			if (!limit)
			{
				return [];
			}
		}

		while (match = separator.exec(str))
		{
			lastIndex = match.index + match[0].length; // `separator.lastIndex` is not reliable cross-browser

			if (lastIndex > lastLastIndex)
			{
				output.push(str.slice(lastLastIndex, match.index));

				// fix browsers whose `exec` methods don't consistently return `undefined` for nonparticipating capturing groups
				if (!cbSplit._compliantExecNpcg && match.length > 1)
				{
					match[0].replace(separator2, function ()
					{
						for (var i = 1; i < arguments.length - 2; i++)
						{
							if (arguments[i] === undefined)
							{
								match[i] = undefined;
							}
						}
					});
				}

				if (match.length > 1 && match.index < str.length)
				{
					Array.prototype.push.apply(output, match.slice(1));
				}

				lastLength = match[0].length;
				lastLastIndex = lastIndex;

				if (output.length >= limit)
				{
					break;
				}
			}

			if (separator.lastIndex === match.index)
			{
				separator.lastIndex++; // avoid an infinite loop
			}
		}

		if (lastLastIndex === str.length)
		{
			if (lastLength || !separator.test(""))
			{
				output.push("");
			}
		}
		else
		{
			output.push(str.slice(lastLastIndex));
		}

		return output.length > limit ? output.slice(0, limit) : output;
	};

	cbSplit._compliantExecNpcg = /()??/.exec("")[1] === undefined; // NPCG: nonparticipating capturing group
	cbSplit._nativeSplit = String.prototype.split;

} // end `if (!cbSplit)`

// for convenience...
String.prototype.split = function (separator, limit)
{
	return cbSplit(this, separator, limit);
};


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
	if (!oldFF && document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#BasicStructure", "1.1"))
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
			while (true)
			{
				var bProcessed = false;
				for (var j = 0, max = eItem.childNodes.length; j < max; j++)
				{
					if (eItem.childNodes[j].nodeType == 1 && eItem.childNodes[j].nodeName != 'SCRIPT' && eItem.childNodes[j].nodeName != 'TEXTAREA' && eItem.childNodes[j].nodeName != 'OBJECT')
						LatexIT.process_item(eItem.childNodes[j]);
					else if (eItem.childNodes[j].nodeType == 3)
					{
						var eCurItem = eItem.childNodes[j];
						var as = eCurItem.nodeValue.split(/(\$\$.*?[^\\]\$\$)/g);
						if (as.length > 1)
						{
							bProcessed = true;
							for (var i = 0; i < as.length; i++)
							{
								if (as[i].substr(0, 2) == '$$' && as[i].substr(as[i].length - 2, 2) == '$$')
									eItem.insertBefore(LatexIT.create_image(as[i]), eCurItem);
								else
									eItem.insertBefore(document.createTextNode(as[i]), eCurItem);
							}
							eItem.removeChild(eCurItem);
							break;
						}
					}
				}
				if (!bProcessed)
					break;
			}
		},

		render : function(tag, latexmode)
		{
			var aeItem = document.getElementsByTagName(tag);
			for (var i = aeItem.length; i-- ;)
			{
				if (latexmode)
					LatexIT.process_item(aeItem[i]);
				else
				{
					try
					{
						if (aeItem[i].getAttribute("lang") == "latex" || aeItem[i].getAttribute("xml:lang") == "latex")
							aeItem[i].innerHTML = LatexIT.pre(aeItem[i].innerHTML);
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
