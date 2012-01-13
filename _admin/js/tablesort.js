/*
originally written by paul sowden <paul@idontsmoke.co.uk> | http://idontsmoke.co.uk
modified and localized by alexander shurkayev <alshur@ya.ru> | http://htmlcoder.visions.ru
*/

var init_table = (function ()
{
	var sort_case_sensitive = false;

	function _sort(a, b)
	{
		var a = a[0];
		var b = b[0];
		var _a = (a + '').replace(/,/, '.');
		var _b = (b + '').replace(/,/, '.');
		if (Number(_a) && Number(_b)) return sort_numbers(_a, _b);
		else if (!sort_case_sensitive) return sort_insensitive(a, b);
		else return sort_sensitive(a, b);
	}

	function sort_numbers(a, b)
	{
		return a - b;
	}

	function sort_insensitive(a, b)
	{
		var anew = a.toLowerCase();
		var bnew = b.toLowerCase();
		if (anew < bnew) return -1;
		if (anew > bnew) return 1;
		return 0;
	}

	function sort_sensitive(a, b)
	{
		if (a < b) return -1;
		if (a > b) return 1;
		return 0;
	}

	function getConcatenedTextContent(node)
	{
		var _result = "";
		if (node == null)
			return _result;

		var childrens = node.childNodes,
			size = childrens.length;
		for (var i = 0; i < size; i++)
		{
			var child = childrens[i];
			switch (child.nodeType) 
			{
				case 1: // ELEMENT_NODE
				case 5: // ENTITY_REFERENCE_NODE
					_result += getConcatenedTextContent(child);
					break;
				case 3: // TEXT_NODE
				case 2: // ATTRIBUTE_NODE
				case 4: // CDATA_SECTION_NODE
					_result += child.nodeValue;
					break;
				case 6: // ENTITY_NODE
				case 7: // PROCESSING_INSTRUCTION_NODE
				case 8: // COMMENT_NODE
				case 9: // DOCUMENT_NODE
				case 10: // DOCUMENT_TYPE_NODE
				case 11: // DOCUMENT_FRAGMENT_NODE
				case 12: // NOTATION_NODE
				// skip
				break;
			}
			i++;
		}
		return _result;
	}

	function sort (e)
	{
		var el = window.event ? window.event.srcElement : e.currentTarget;
		while (el.tagName.toLowerCase() != "td")
			el = el.parentNode;

		var dad = el.parentNode,
			table = dad.parentNode.parentNode,
			up,
			aeTD = dad.getElementsByTagName("td");

		for (var i = aeTD.length; i-- ;)
		{
			var node = aeTD[i];
			if (node == el)
			{
				var curcol = i;
				if (node.className == "curcol_down")
				{
					up = 1;
					node.className = "curcol_up";
				}
				else
				{
					up = 0;
					node.className = "curcol_down";
				}
			}
			else if (node.className == "curcol_down" || node.className == "curcol_up")
				node.className = "";
		}

		var a = new Array(),
			tbody = table.getElementsByTagName("tbody")[0],
			aeTR = tbody.getElementsByTagName("tr"),
			size = aeTR.length;

		for (i = 0; i < size; i++)
		{
			node = aeTR[i];
			a[i] = new Array();
			a[i][0] = getConcatenedTextContent(node.getElementsByTagName("td")[curcol]);
			a[i][1] = node;
		}

		a.sort(_sort);
		if (up) a.reverse();
		for (i = 0; i < a.length; i++)
			tbody.appendChild(a[i][1]);
	}

	return function (e)
	{
		if (!document.getElementsByTagName)
			return;

		for (var j = 0; (thead = document.getElementsByTagName("thead").item(j)); j++)
		{
			var node;
			for (var i = 0; (node = thead.getElementsByTagName("td").item(i)); i++)
			{
				if (node.addEventListener)
					node.addEventListener("click", sort, false);
				else if (node.attachEvent)
					node.attachEvent("onclick", sort);
				node.title = node.title ? node.title + ' (' + s2_lang.click_to_sort + ')' : s2_lang.click_to_sort;
			}
		}
	}
}());
