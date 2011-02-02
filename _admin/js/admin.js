/**
 * Main JS functions
 *
 * Drag & drop, event handlers for the admin panel
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

// Simple hooks managing

var hooks = [];

function add_hook (hook, code)
{
	if (typeof(hooks[hook]) != 'string')
		hooks[hook] = code;
	else
		hooks[hook] += code;
}

// Helper functions

function str_replace (substr, newsubstr, str)
{
	while (str.indexOf(substr) >= 0)
		str = str.replace(substr, newsubstr);
	return str;
}

function get_attr (sStr, sAttr)
{
	var iBeg = sStr.indexOf(sAttr);
	if (iBeg != -1)
	{
		var iStart = sStr.indexOf('"', iBeg) + 1;
		var iEnd = sStr.indexOf('"', iStart);
		return sStr.substring(iStart, iEnd);
	}
	return '';
}

function SetTime (eForm, sName)
{
	var d = new Date();

	d.setTime(time_shift + d.getTime());

	eForm[sName + '[hour]'].value = d.getHours();
	eForm[sName + '[min]'].value = d.getMinutes();
	eForm[sName + '[day]'].value = d.getDate();
	eForm[sName + '[mon]'].value = d.getMonth() + 1;
	eForm[sName + '[year]'].value = d.getFullYear();

	return false;
}

function StringFromForm (aeItem)
{
	var sRequest = 'ajax=1', i, eItem;

	for (i = aeItem.length; i-- ;)
	{
		eItem = aeItem[i];
		if (eItem.nodeName == 'INPUT')
		{
			if (eItem.type == 'text' || eItem.type == 'hidden')
				sRequest += '&' + eItem.name + '=' + encodeURIComponent(eItem.value);
			if (eItem.type == 'checkbox' && eItem.checked)
				sRequest += '&' + eItem.name + '=' + encodeURIComponent(eItem.value);
		}
		if (eItem.nodeName == 'TEXTAREA' || eItem.nodeName == 'SELECT')
			sRequest += '&' + eItem.name + '=' + encodeURIComponent(eItem.value);
	}

	return sRequest;
}

// Initialization

var Event = (function ()
{
	var bIE = document.attachEvent != null;
	var bFF = !document.attachEvent && document.addEventListener;

	bIE && attachEvent('onload', Init);
	bFF && addEventListener('load', Init, true);

	return (
	{
		add: function (eItem, type, handler, use_capture)
		{
			bFF && eItem.addEventListener(type, handler, use_capture ? true : false);
			bIE && eItem.attachEvent('on' + type, handler);
		},

		remove: function (eItem, type, handler, use_capture)
		{
			bFF && eItem.removeEventListener(type, handler, use_capture ? true : false);
			bIE && eItem.detachEvent('on' + type, handler);
		}
	});
}())

var sCurrTextId = ''; // A unique string for the document currently loaded to the editor

var ua = navigator.userAgent.toLowerCase();
var isIE = (ua.indexOf('msie') != -1 && ua.indexOf('opera') == -1);
var isSafari = ua.indexOf('safari') != -1;
var isGecko = (ua.indexOf('gecko') != -1 && !isSafari);

function Init ()
{
	InitMovableDivs();
	Drag.init();
	Search.init();

	var keyboard_event = isIE || isSafari ? 'keydown' : 'keypress';

	// Ctrl + S
	Event.add(document, keyboard_event, SaveHandler, true);

	// Mouse events in the tree
	Event.add(document.getElementById('tree'), 'mousedown', MouseDown);

	// Tooltips
	Event.add(document, 'mouseover', function (e)
	{
		var eItem = window.event ? window.event.srcElement : e.target;
		var title = eItem.title;

		if (!title && eItem.nodeName == 'IMG')
			title = eItem.title = eItem.alt;

		if (title)
			window.status = title;
	});
	Event.add(document, 'mouseout', function (e)
	{
		window.status = window.defaultStatus;
	});

	var eTagValues = document.getElementById('tag_values');
	eTagValues.onmouseover = TagvaluesMouseIn;
	eTagValues.onmouseout = TagvaluesMouseOut;

	window.onbeforeunload = function ()
	{
		if (document.artform && IsChanged(document.artform))
			return S2_LANG_UNSAVED_EXIT;
	}

	cur_page = document.location.hash;
	setInterval(CheckPage, 400);
	SetWait(false);
}

function SaveHandler (e)
{
	e = e || window.event;
	var key = e.keyCode || e.which;
	key = !isGecko ? (key == 83 ? 1 : 0) : (key == 115 ? 1 : 0);
	if (e.ctrlKey && key)
	{
		(hook = hooks['fn_save_handler_start']) ? eval(hook) : null;

		if (e.preventDefault)
			e.preventDefault();
		e.returnValue = false;

		if (document.artform && '#edit' == cur_page)
			document.artform.onsubmit();

		if (document.commform && '#comm' == cur_page)
			document.commform.onsubmit();

		if (document.tagform && '#tag' == cur_page)
			SaveTag();

		if (document.optform && '#admin-opt' == cur_page)
			SaveOptions();

		return false;
	}
}

function Logout ()
{
	GETSyncRequest(sUrl + 'action=logout');
	document.location.reload();
}

// Search field events handler

var Search = (function ()
{
	var search_string = '';
	var eInput;

	return (
	{
		init: function ()
		{
			eInput = document.getElementById('search_field');

			// Search field help message.
			// It appears when the field is empty.
			eInput.onblur = function ()
			{
				if (eInput.value == '')
				{
					eInput.className = 'inactive';
					eInput.value = eInput.defaultValue;
				}
			}
			eInput.onfocus = function ()
			{
				if (eInput.className == 'inactive')
				{
					eInput.className = '';
					eInput.value = '';
				}
			}

			var DoSearch = function ()
			{
				GETAsyncRequest(sUrl + 'action=load_tree&id=0&search=' + encodeURIComponent(search_string), function (xmlhttp) {
					document.getElementById('tree').innerHTML = '<ul>' + xmlhttp.responseText + '</ul>';
					SetWait(false);
				});
			}

			var search_timer;

			var KeyDown = function (e)
			{
				e = e || window.event;
				var key = e.keyCode || e.which;

				if (key == 13)
				{
					// Immediate search on enter press
					clearTimeout(search_timer);
					search_string = eInput.value;
					DoSearch();
				}
				else 
					// We have to wait a little for eInput.value to change
					setTimeout(function ()
					{
						if (search_string == eInput.value || eInput.className == 'inactive')
							return;

						search_string = eInput.value;
						SetWait(true);
						clearTimeout(search_timer);
						search_timer = setTimeout(DoSearch, 250);
					}, 0);
			}
			if (isIE || isSafari)
				eInput.onkeydown = KeyDown;
			else
				eInput.onkeypress = KeyDown;
		},

		// Get search string
		string: function ()
		{
			return search_string;
		},

		// Cancel search mode
		reset: function ()
		{
			if (!eInput)
				return;
			eInput.value = eInput.defaultValue;
			eInput.className = 'inactive';
			search_string = '';
		}
	})
}())

// Turning animated icon on or off
function SetWait (bWait)
{
	document.getElementById('loading').style.display = bWait ? 'block' : 'none';
	document.getElementsByTagName('body')[0].style.cursor = bWait ? 'progress' : 'default';
}

// Handling "back" and "forward" browser buttons

var cur_page = '';

function SetPage (sId)
{
	cur_page = '#' + str_replace('_tab', '', sId);
	if (document.location.hash != cur_page)
		document.location.hash = cur_page;
	//window.open('#' + pagehash[1], '_self');
}

function CheckPage ()
{
	if (document.location.hash != cur_page)
	{
		var new_page = document.location.hash.substr(1)
		if (new_page.indexOf('-') != -1)
			SelectTab(document.getElementById(new_page.split('-')[0] + '_tab'), false);
		SelectTab(document.getElementById(new_page + '_tab'), true);
	}
}

// Tracking editor content changes

var curr_md5 = '';

function CommitChanges (arg)
{
	if (typeof(arg) == 'string')
		curr_md5 = hex_md5(arg);
	else
		curr_md5 = hex_md5(StringFromForm(arg));
}

function IsChanged (eForm)
{
	(hook = hooks['fn_is_changed']) ? eval(hook) : null;

	return curr_md5 != hex_md5(StringFromForm(eForm));
}

// Creating the button panel and div for drag

var buttonPanel = null;

var Drag = (function ()
{
	var draggableDiv = null;
	var drag_html = '';

	return (
	{
		init: function ()
		{
			if (draggableDiv == null)
			{
				draggableDiv = document.createElement('DIV');
				document.body.appendChild(draggableDiv);
				draggableDiv.id = 'dragged';
				Drag.move(-99, -99);
			}
		},

		move: function (x, y)
		{
			draggableDiv.style.left = x + 10 + 'px';
			draggableDiv.style.top = y + 0 + 'px';
		},

		hide: function ()
		{
			draggableDiv.style.visibility = 'hidden';
			drag_html = '';
			Drag.move(-99, -99);
		},

		show: function (s)
		{
			draggableDiv.style.visibility = 'visible';
			drag_html = s;
			draggableDiv.innerHTML = s;
		},

		set_hint: function (s)
		{
			draggableDiv.innerHTML = drag_html + (s ? '<br />' + s : '');
		}
	});
}())

function InitMovableDivs ()
{
	if (buttonPanel == null)
	{
		buttonPanel = document.getElementById('context_buttons');
		buttonPanel.parentNode.removeChild(buttonPanel);
	}
}


//=======================[Expanding the tree]===================================

var asExpanded = [];

function ExpandSavedItem (sId)
{
	var iId = parseInt(sId);

	if (!isNaN(iId))
		asExpanded[iId] = '';
}

function SaveExpand ()
{
	var aSpan = document.getElementById('tree_div').getElementsByTagName('SPAN');
	var i, iId;

	asExpanded = [];

	for (i = aSpan.length; i-- ;)
	{
		iId = parseInt(aSpan[i].id);
		if (!isNaN(iId) && aSpan[i].parentNode.parentNode.className.indexOf('ExpandOpen') != -1)
			asExpanded[iId] = 1;
	}
}

function LoadExpand ()
{
	var i, eLi;

	for (i in asExpanded)
	{
		eLi = document.getElementById(i).parentNode.parentNode;
		eLi.className = str_replace('ExpandClosed', 'ExpandOpen', eLi.className);
	}
}

function CloseAll ()
{
	var i, aLi = document.getElementById('tree_div').getElementsByTagName('LI');

	for (i = aLi.length; i-- ;)
		if (aLi[i].className.indexOf('ExpandOpen') != -1)
			aLi[i].className = str_replace('ExpandOpen', 'ExpandClosed', aLi[i].className);

	var eLi = document.getElementById('1').parentNode.parentNode;
	eLi.className = str_replace('ExpandClosed', 'ExpandOpen', eLi.className);
}

function OpenAll ()
{
	var i, aLi = document.getElementById('tree_div').getElementsByTagName('LI');

	for (i = aLi.length; i-- ;)
		if (aLi[i].className.indexOf('ExpandClosed') != -1)
			aLi[i].className = str_replace('ExpandClosed', 'ExpandOpen', aLi[i].className);
}

function OpenById (sId)
{
	CloseAll();
	ReleaseItem();

	var e = document.getElementById(sId);
	if (!e)
		return;

	HighlightItem(e);

	while (e.parentNode)
	{
		e = e.parentNode;
		if (e.nodeName == 'LI' && e.className.indexOf('ExpandClosed') != -1)
			e.className = str_replace('ExpandClosed', 'ExpandOpen', e.className);
	}
}

function RefreshTree ()
{
	Search.reset();

	var Response = GETSyncRequest(sUrl + 'action=load_tree&id=0&search=');
	if (Response.status != '200')
		return;

	SaveExpand()
	document.getElementById('tree').innerHTML = '<ul>' + Response.text + '</ul>';
	LoadExpand();
}

//=======================[Highlight and renaming]===============================

function HighlightItem (item)
{
	item.className = 'but_panel';
	item.appendChild(buttonPanel);
}

function ReleaseItem ()
{
	if (buttonPanel.parentNode)
	{
		buttonPanel.parentNode.className = '';
		buttonPanel.parentNode.removeChild(buttonPanel);
	}
}

var RejectName = function () {};

function EditItemName (eSpan)
{
	var sSavedName = eSpan.firstChild.nodeValue;

	RejectName = function ()
	{
		eSpan.firstChild.nodeValue = sSavedName;
		eInput.onblur = null;
		RejectName = function () {};
		eSpan.removeChild(eInput);
		HighlightItem(eSpan);
		eSpan = null;
	}

	var KeyDown = function (e)
	{
		if (!eSpan)
			return;

		e = e || window.event;
		var iCode = e.keyCode || e.which;

		if (iCode == 13)
		{
			// Enter
			var sTitle = eInput.value;

			SaveExpand();
			var Response = POSTSyncRequest(sUrl + 'action=rename&id=' + eSpan.id, 'title=' + encodeURIComponent(sTitle));
			if (Response.status == '200')
			{
				if (Response.text != '')
					alert(Response.text);
				else
				{
					eSpan.firstChild.nodeValue = sTitle;
					eInput.onblur = null;
					RejectName = function () {};
					eSpan.removeChild(eInput);
					HighlightItem(eSpan);
					eSpan = null;
				}
			}
		}
		else if (iCode == 27)
			// Escape
			RejectName();
		else
			// It's a hack that allows to make the input as wide as the text contained
			// '___' makes it a bit larger.
			// str_replace changes usual spaces to non-breaking ones.
			setTimeout(function ()
			{
				if (eSpan)
					eSpan.firstChild.nodeValue = '___' + str_replace(' ', ' ', eInput.value);
			}, 0);
	}

	var eInput = document.createElement('INPUT');
	eInput.setAttribute('type', 'text');
	eInput.onblur = RejectName;
	if (isIE || isSafari)
		eInput.onkeydown = KeyDown;
	else
		eInput.onkeypress = KeyDown;
	eInput.value = sSavedName;

	eSpan.insertBefore(eInput, eSpan.childNodes[1]);
	eInput.focus();
	eInput.select();
	eSpan.firstChild.nodeValue += '___';
	ReleaseItem();
}

//=======================[Drag & drop]==========================================

var sourceElement, acceptorElement, sourceParent;
var far;

var dragging;

// We have to create a "UL" child node if there is no one
function SetItemChildren (eSpan, sInnerHTML)
{
	var eLi = eSpan.parentNode.parentNode;

	if (eLi.lastChild.nodeName == 'UL')
		eLi.lastChild.innerHTML = sInnerHTML;
	else
	{
		var eUl = document.createElement('UL');
		eLi.className = str_replace('ExpandLeaf', 'ExpandOpen', eLi.className);
		eLi.appendChild(eUl);
		eUl.innerHTML = sInnerHTML;
	}
}

// We have to remove the "UL" node if the list is empty
function SetParentChildren (eParentUl, str)
{
	if (str != '')
		eParentUl.innerHTML = str;
	else
	{
		var eLi = eParentUl.parentNode;
		eLi.removeChild(eLi.lastChild);
		eLi.className = str_replace('ExpandOpen', 'ExpandLeaf', eLi.className);
	}
}

function StopDrag()
{
	dragging = false;
	sourceElement.className = '';
	Drag.hide();

	if (acceptorElement)
	{
		SaveExpand();

		acceptorElement.className = '';

		if (far)
		{
			var eItem = acceptorElement;
			var eSourceLi = sourceElement.parentNode.parentNode;

			while (eItem)
			{
				if (eItem == eSourceLi)
				{
					alert(S2_LANG_NO_LOOPS);
					acceptorElement = null;
					return;
				}
				eItem = eItem.parentNode;
			}

			var Response = GETSyncRequest(sUrl + 'action=drag&sid=' + sourceElement.id + '&did=' + acceptorElement.id + '&far=' + far);
			if (Response.status != '200')
			{
				acceptorElement = null;
				return;
			}

			var a = Response.text.split('|', 2);
			SetParentChildren(sourceParent, a[1]); //source
			SetItemChildren(acceptorElement, a[0]); //destination
		}
		else
		{
			var Response = GETSyncRequest(sUrl + 'action=drag&sid=' + sourceElement.id + '&did=' + acceptorElement.id + '&far=' + far);
			if (Response.status != '200')
			{
				acceptorElement = null;
				return;
			}
			sourceParent.innerHTML = Response.text;
		}
		acceptorElement = null;
		LoadExpand();
	}
}

//=======================[Mouse events]=========================================

var mouseX, mouseY, mouseStartX, mouseStartY;

function MouseDown (e)
{
	var t = window.event ? window.event.srcElement : e.target;

	if (t.nodeName == 'DIV' && t.innerHTML == '')
	{
		// Click on the expand image
		var node = t.parentNode;

		if (node.className.indexOf('ExpandOpen') != -1)
			node.className = str_replace('ExpandOpen', 'ExpandClosed', node.className);
		else if (node.className.indexOf('ExpandClosed') != -1)
			node.className = str_replace('ExpandClosed', 'ExpandOpen', node.className);

		return;
	}
	else if (t.nodeName == 'SPAN' && !isNaN(parseInt(t.id)))
		sourceElement = t;
	else
		// Do not handle span child eventss
		return;

	var oCanvas = document.getElementsByTagName((document.compatMode && document.compatMode == 'CSS1Compat') ? 'HTML' : 'BODY')[0];
	mouseStartX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseStartY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	Event.add(document, 'mouseover', MouseIn);
	Event.add(document, 'mouseout', MouseOut);
	Event.add(document, 'mousemove', MouseMove);
	Event.add(document, 'mouseup', MouseUp);

	e.preventDefault && e.preventDefault();
	window.event && (window.event.returnValue = false);
	t.unselectable = true;
}

function MouseMove (e)
{
	var oCanvas = document.getElementsByTagName((document.compatMode && document.compatMode == 'CSS1Compat') ? 'HTML' : 'BODY')[0];
	mouseX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	if (!dragging && (Math.abs(mouseStartY - mouseY) > 5 || Math.abs(mouseStartX - mouseX) > 5))
	{
		dragging = true;

		clearTimeout(idTimer);
		bIntervalPassed = true;

		RejectName();
		ReleaseItem();

		sourceElement.className = 'source';

		Drag.show('<strong>' + sourceElement.innerHTML + '</strong>');

		sourceParent = sourceElement.parentNode.parentNode.parentNode;
		far = 0;
	}

	Drag.move(mouseX, mouseY);
}

var idTimer, bIntervalPassed = true;

function MouseUp (e)
{
	var is_drop = dragging;
	if (dragging)
		StopDrag();

	RejectName();

	if (bMouseInTagvalues)
	{
		AddArticleToTag(sourceElement.id);
		TagvaluesMouseOut();
	}
	else if (!bIntervalPassed)
	{
		// Double click
		clearTimeout(idTimer);
		bIntervalPassed = true;

		if (!is_drop)
		{
			var sJob = sUrl + 'action=preview&id=' + sourceElement.id;
			setTimeout(function()
			{
				var wnd = window.open(sJob, 's2_preview_window', 'scrollbars=yes,toolbar=yes', 'True');
			}, 0);
		}
	}
	else
	{
		// Single click
		var sJob = '';
		if (sourceElement == buttonPanel.parentNode)
			// Highlighted item
			sJob = !is_drop ? sourceElement.id : '';
		else
		{
			ReleaseItem();
			HighlightItem(sourceElement);
		}

		bIntervalPassed = false;
		idTimer = setTimeout(function ()
		{
			bIntervalPassed = true;
			if (sJob)
				EditItemName(document.getElementById(sJob));
		}, 400);
	}
	sourceElement = null;

	Event.remove(document, 'mouseover', MouseIn);
	Event.remove(document, 'mouseout', MouseOut);
	Event.remove(document, 'mousemove', MouseMove);
	Event.remove(document, 'mouseup', MouseUp);
}

// Rollovers

function MouseIn (e)
{
	if (sourceElement == null ||
		sourceElement.id == '1' ||
		Search.string())
		return;

	var t = window.event ? window.event.srcElement : e.target;

	if (t.nodeName != 'SPAN' ||
		isNaN(parseInt(t.id)) ||
		t == acceptorElement ||
		t == sourceElement)
		return;

	acceptorElement = t;
	if (far)
	{
		t.className = 'over_far';
		Drag.set_hint(str_replace('%s', acceptorElement.innerHTML, S2_LANG_MOVE));
	}
	else
	{
		if (t.parentNode.parentNode.parentNode != sourceParent)
		{
			far = 1;
			t.className = 'over_far';
			Drag.set_hint(str_replace('%s', acceptorElement.innerHTML, S2_LANG_MOVE));
		}
		else
		{
			if (mouseStartY > mouseY)
			{
				Drag.set_hint(S2_LANG_MOVE_UP);
				t.className = 'over_top';
				t.parentNode.parentNode.firstChild.className = 'over_top';
			}
			else
			{
				Drag.set_hint(S2_LANG_MOVE_DOWN);
				t.className = 'over_bottom';
				t.parentNode.parentNode.firstChild.className = 'over_bottom';
			}
		}
	}
}

function MouseOut(e)
{
	var t = window.event ? window.event.srcElement : e.target;

	if (sourceElement != null && t == acceptorElement && t != sourceElement)
	{
		t.className = '';
		t.parentNode.parentNode.firstChild.className = '';
		acceptorElement = null;
		Drag.set_hint('');
	}
}

//=======================[Tree button handlers]=================================

function DeleteArticle ()
{
	var eSpan = buttonPanel.parentNode;

	if (!confirm(str_replace('%s', eSpan.innerText ? eSpan.innerText : eSpan.textContent, S2_LANG_DELETE_ITEM)))
		return;

	var Response = GETSyncRequest(sUrl + 'action=delete&id=' + eSpan.id);
	if (Response.status != '200')
		return;

	SaveExpand();
	ReleaseItem();
	SetParentChildren(eSpan.parentNode.parentNode.parentNode, Response.text);
	LoadExpand();
}

function CreateChildArticle ()
{
	var eSpan = buttonPanel.parentNode;
	var eLi = eSpan.parentNode.parentNode;

	var Response = GETSyncRequest(sUrl + 'action=create&id=' + eSpan.id);
	if (Response.status != '200')
		return;

	ReleaseItem();
	SetItemChildren(eSpan, Response.text);

	eSpan = eLi.lastChild.lastChild.lastChild.lastChild;

	HighlightItem(eSpan);
	EditItemName(eSpan);
}

function EditArticle (iId)
{
	if (typeof(iId) == 'undefined')
		iId = buttonPanel.parentNode.id;

	var sURI = sUrl + 'action=load&id=' + iId;

	if (sCurrTextId != sURI)
	{
		// We are going to reload the editor content
		// only if the article to be loaded differs from the current one.

		if (document.artform && IsChanged(document.artform) && !confirm(S2_LANG_UNSAVED))
			return false;

		var Response = GETSyncRequest(sURI);
		if (Response.status != '200')
			return false;

		document.getElementById('form_div').innerHTML = Response.text;
		CommitChanges(document.artform);

		sCurrTextId = sURI;
	}
	SelectTab(document.getElementById('edit_tab'), true);
	return false;
}

function LoadComments (iId)
{
	if (typeof(iId) == 'undefined')
		iId = buttonPanel.parentNode.id;

	var Response = GETSyncRequest(sUrl + 'action=load_comments&id=' + iId);
	if (Response.status != '200')
		return false;

	document.getElementById('comm_div').innerHTML = Response.text;
	init_table(null);
	SelectTab(document.getElementById('comm_tab'), true);
	return false;
}

//=======================[Editor button handlers]===============================

function ClearForm()
{
	if (!confirm(S2_LANG_CLEAR_PROMPT))
		return false;

	var aeInput = document.artform.getElementsByTagName('INPUT'), i;
	for (i = aeInput.length; i-- ;)
		if (aeInput[i].type == 'text')
			aeInput[i].value = '';

	aeInput = document.artform.getElementsByTagName('TEXTAREA');
	for (i = aeInput.length; i-- ;)
		aeInput[i].value = '';

	return false;
}

function SaveArticle (sAction)
{
	var sRequest = StringFromForm(document.artform);

	var Response = POSTSyncRequest(sUrl + 'action=' + sAction, sRequest);
	if (Response.status == '200')
	{
		if (Response.text != '')
			alert(Response.text);
		else
			CommitChanges(sRequest);
	}

	return false;
}

function ChangeTemplate (eSelect, sHelp)
{
	if (eSelect[eSelect.selectedIndex].value == '+')
	{
		// Adding new template
		// Ask for the filename
		var filename = prompt(sHelp, 'site.php');
		if (typeof(filename) != 'string')
		{
			// Cancel button

			eSelect.value = eSelect.getAttribute('data-prev-value');
			return;
		}
		else
		{
			// Ok button

			var isItem = false;
			for (var i = eSelect.length; i-- ;)
				if (eSelect[i].value == filename)
				{
					isItem = true;
					break;
				}

			if (!isItem)
			{
				// Add new item to the dropdown list
				var eOption = document.createElement('OPTION');
				eOption.setAttribute('value', filename);
				eOption.appendChild(document.createTextNode(filename));

				var eLastOption = eSelect.lastChild;
				while (eLastOption.nodeName != 'OPTION')
					eLastOption = eLastOption.previousSibling;

				eSelect.insertBefore(eOption, eLastOption);
			}

			eSelect.value = filename;
		}
	}

	// Remember the current item
	eSelect.setAttribute('data-prev-value', eSelect[eSelect.selectedIndex].value);
}

function TagSelection (sTag)
{
	return InsertTag('<' + sTag + '>', '</' + sTag + '>');
}

function get_selection (e)
{
	// Mozilla and DOM 3.0
	if ('selectionStart' in e)
	{
		var l = e.selectionEnd - e.selectionStart;
		return { start: e.selectionStart, end: e.selectionEnd, length: l, text: e.value.substr(e.selectionStart, l) };
	}
	// IE
	else if (document.selection)
	{
		e.focus();
		var r = document.selection.createRange();
		var tr = e.createTextRange();
		var tr2 = tr.duplicate();
		tr2.moveToBookmark(r.getBookmark());
		tr.setEndPoint('EndToStart', tr2);
		if (r == null || tr == null)
			return { start: e.value.length, end: e.value.length, length: 0, text: '' };

		//for some reason IE doesn't always count the \n and \r in the length
		var text_part = r.text.replace(/[\r\n]/g, '.'); 
		var text_whole = e.value.replace(/[\r\n]/g, '.');
		var the_start = text_whole.indexOf(text_part, tr.text.length);

		return { start: the_start, end: the_start + text_part.length, length: text_part.length, text: r.text };
	}
	//Browser not supported
	else
		return { start: e.value.length, end: e.value.length, length: 0, text: '' };
}

function set_selection (e, start_pos, end_pos)
{
	// Mozilla and DOM 3.0
	if ('selectionStart' in e)
	{
		e.focus();
		e.selectionStart = start_pos;
		e.selectionEnd = end_pos;
	}
	// IE
	else if (document.selection)
	{
		e.focus();
		var tr = e.createTextRange();

		//Fix IE from counting the newline characters as two seperate characters
		var stop_it = start_pos;
		for (i = 0; i < stop_it; i++)
			if (e.value[i].search(/[\r\n]/) != -1)
				start_pos = start_pos - .5;
		stop_it = end_pos;
		for (i = 0; i < stop_it; i++)
			if (e.value[i].search(/[\r\n]/) != -1)
				end_pos = end_pos - .5;

		tr.moveEnd('textedit', -1);
		tr.moveStart('character', start_pos);
		tr.moveEnd('character', end_pos - start_pos);
		tr.select();
	}
}

function InsertTag (sOpenTag, sCloseTag, selection)
{
	eTextarea = document.artform['page[text]'];
	if (selection == null)
		selection = get_selection(eTextarea);

	var replace_str = sOpenTag + selection.text + sCloseTag;
	var start_pos = selection.start;
	var end_pos = start_pos + replace_str.length;
	eTextarea.value = eTextarea.value.substr(0, start_pos) + replace_str + eTextarea.value.substr(selection.end, eTextarea.value.length);
	set_selection(eTextarea, start_pos, end_pos);

	return false;
}

var slEditorSelection = null;

function GetImage ()
{
	slEditorSelection = get_selection(document.artform['page[text]']);
	LoadPictureManager();
	SelectTab(document.getElementById('pict_tab'), true);
	return false;
}

function Paragraph ()
{
	var Response = POSTSyncRequest(sUrl + 'action=smart_paragraph', 'data=' + encodeURIComponent(document.artform['page[text]'].value));

	if (Response.status == '200')
		document.artform['page[text]'].value = Response.text;
}

//=======================[Comment management]===================================

function DeleteComment (iId)
{
	if (!confirm(S2_LANG_DELETE_COMMENT))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=delete_comment&id=' + iId);

	if (Response.status == '200')
	{
		document.getElementById('comm_div').innerHTML = Response.text;
		init_table(null);
	}

	return false;
}

function EditComment (iId)
{
	var Response = GETSyncRequest(sUrl + 'action=edit_comment&id=' + iId);
	if (Response.status != '200')
		return false;

	document.getElementById('comm_div').innerHTML = Response.text;
	return false;
}

function SaveComment (sType)
{
	var sRequest = StringFromForm(document.commform);
	var Response = POSTSyncRequest(sUrl + 'action=save_comment&type=' + sType, sRequest);
	if (Response.status == '200')
	{
		document.getElementById('comm_div').innerHTML = Response.text;
		init_table(null);
	}
	return false;
}

function LoadTable (sAction, sID)
{
	var Response = GETSyncRequest(sUrl + 'action=' + sAction);
	if (Response.status == '200')
	{
		document.getElementById(sID).innerHTML = Response.text;
		init_table(null);
	}
	return false;
}

function LoadTableExt (sAction, iId, sID)
{
	var Response = GETSyncRequest(sUrl + 'action=' + sAction + '&id=' + iId);
	if (Response.status == '200')
	{
		document.getElementById(sID).innerHTML = Response.text;
		init_table(null);
	}
	return false;
}


//=======================[Tags for articles]====================================

function LoadTagNames ()
{
	var Response = GETSyncRequest(sUrl + 'action=load_tagnames');
	if (Response.status == '200')
		document.getElementById('tag_list').innerHTML = Response.text;
	return false;
}

var bTagsOpen = false;

function SwitchTags (eItem)
{
	if (bTagsOpen)
	{
		document.getElementById('keytable').style.width = '16px';
		document.getElementById('tag_values').style.display = 'none';
		document.getElementById('tag_names').style.display = 'none';
		eItem.className = 'closed';
		eItem.alt = S2_LANG_SHOW_TAGS;
	}
	else
	{
		LoadTagNames();
		document.getElementById('keytable').style.width = '416px';
		document.getElementById('tag_values').style.display = 'block';
		document.getElementById('tag_names').style.display = 'block';
		eItem.className = 'opened';
		eItem.alt = S2_LANG_HIDE_TAGS;
	}
	bTagsOpen = !bTagsOpen;
	return false;
}

var iCurrentTagId, eCurrentTag = null;

function ChooseTag (eItem)
{
	if (eCurrentTag)
	{
		eCurrentTag.className = '';
		eCurrentTag.onmouseover = null;
		eCurrentTag.onmouseout = null;
	}
	eCurrentTag = eItem;
	eCurrentTag.onmouseover = TagvaluesMouseIn;
	eCurrentTag.onmouseout = TagvaluesMouseOut;
	eCurrentTag.className = 'cur_tag';
	iCurrentTagId = eCurrentTag.getAttribute('tagid');

	var Response = GETSyncRequest(sUrl + 'action=load_tagvalues&id=' + iCurrentTagId);
	if (Response.status == '200')
		document.getElementById('tag_values').innerHTML = Response.text;

	return false;
}

var bMouseInTagvalues = false;

function TagvaluesMouseIn ()
{
	if (!sourceElement || !eCurrentTag)
		return;

	bMouseInTagvalues = true;
	var aName = eCurrentTag.innerHTML.split(' (');
	Drag.set_hint(str_replace('%s', aName[aName.length - 2], S2_LANG_ADD_TO_TAG));

	document.getElementById('tag_values').style.backgroundColor = '#d2e5fc';
	eCurrentTag.style.backgroundColor = '#d2e5fc';
}

function TagvaluesMouseOut ()
{
	bMouseInTagvalues = false;
	if (sourceElement)
		Drag.set_hint('');

	document.getElementById('tag_values').style.backgroundColor = '';

	if (eCurrentTag)
		eCurrentTag.style.backgroundColor = '';
}

function AddArticleToTag (iId)
{
	var Response = GETSyncRequest(sUrl + 'action=add_to_tag&tag_id=' + iCurrentTagId + '&article_id=' + iId);
	if (Response.status == '200')
	{
		document.getElementById('tag_values').innerHTML = Response.text;
		eCurrentTag.childNodes[1].innerHTML = parseInt(eCurrentTag.childNodes[1].innerHTML) + 1;
	}
	return false;
}

function DeleteArticleFromTag (iId)
{
	if (!confirm(S2_LANG_DELETE_TAG_LINK))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=delete_from_tag&id=' + iId);
	if (Response.status == '200')
	{
		document.getElementById('tag_values').innerHTML = Response.text;
		eCurrentTag.childNodes[1].innerHTML = parseInt(eCurrentTag.childNodes[1].innerHTML) - 1;
	}
	return false;
}

//=======================[Inserting pictures]===================================

var bPictureManagerLoaded = false;

function LoadPictureManager ()
{
	if (bPictureManagerLoaded)
		return;

	var wnd = window.open('pictman.php', 'pict_frame', '', 'True');
	bPictureManagerLoaded = true;
}

function ReturnImage(s, w, h)
{
	if (!document.artform || !document.artform['page[text]'])
		return;

	SelectTab(document.getElementById('edit_tab'), true);
	var sOpenTag = '<img src="' + s + '" width="' + w + '" height="' + h +'" ' + 'alt="', sCloseTag = '" />';
	InsertTag(sOpenTag, sCloseTag, slEditorSelection);
}

//=======================[Preview]==============================================

function Preview ()
{
	if (!document.artform || !document.artform['page[text]'])
		return;

	var s = str_replace('<!-- s2_text -->', document.artform['page[text]'].value, template);
	s = str_replace('<!-- s2_title -->', '<h1>' + document.artform['page[title]'].value + '</h1>', s);
	window.frames['preview_frame'].document.open();
	window.frames['preview_frame'].document.write(s);
	window.frames['preview_frame'].document.close();
}

//=======================[Users tab]============================================

function LoadUserList ()
{
	var eDiv = document.getElementById('user_div');

	var Response = GETSyncRequest(sUrl + 'action=load_userlist');
	if (Response.status == '200')
	{
		eDiv.innerHTML = Response.text;
		init_table(null);
	}
}

function AddUser (sUser)
{
	if (sUser == '')
	{
		alert(S2_LANG_EMPTY_LOGIN);
		return false;
	}

	var Response = GETSyncRequest(sUrl + 'action=add_user&name=' + sUser);
	if (Response.status == '200')
	{
		document.getElementById('user_div').innerHTML = Response.text;
		init_table(null);
	}
	return false;
}

function SetPermission (sUser, sPermission)
{
	var Response = GETSyncRequest(sUrl + 'action=user_set_permission&name=' + sUser + '&permission=' + sPermission);
	if (Response.status == '200')
	{
		document.getElementById('user_div').innerHTML = Response.text;
		init_table(null);
	}
	return false;
}

function SetUserPassword (sUser)
{
	var s = prompt(str_replace('%s', sUser, S2_LANG_NEW_PASSWORD));
	if (typeof(s) != 'string')
		return false;

	var Response = POSTSyncRequest(sUrl + 'action=user_set_password&name=' + sUser, 'pass=' + encodeURIComponent(hex_md5(s + 'Life is not so easy :-)')));
	if (Response.status == '200')
		alert(Response.text);

	return false;
}

function SetUserEmail (sUser, sEmail)
{
	var s = prompt(str_replace('%s', sUser, S2_LANG_NEW_EMAIL));
	if (typeof(s) == 'string')
	{
		var Response = GETSyncRequest(sUrl + 'action=user_set_email&name=' + sUser + '&email=' + s);
		if (Response.status == '200')
		{
			document.getElementById('user_div').innerHTML = Response.text;
			init_table(null);
		}
	}
	return false;
}

function DeleteUser (sUser)
{
	if (!confirm(str_replace('%s', sUser, S2_LANG_DELETE_USER)))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=delete_user&name=' + sUser);
	if (Response.status == '200')
	{
		document.getElementById('user_div').innerHTML = Response.text;
		init_table(null);
	}
	return false;
}

//=======================[Tags tab]=============================================

function LoadTags ()
{
 	var eDiv = document.getElementById('tag_div');

	if (eDiv.innerHTML != '')
		return false;

	var Response = GETSyncRequest(sUrl + 'action=load_tags');
	if (Response.status == '200')
		eDiv.innerHTML = Response.text;

	return false;
}

function LoadTag (iId)
{
	var Response = GETSyncRequest(sUrl + 'action=load_tag&id=' + iId);

	if (Response.status == '200')
		document.getElementById('tag_div').innerHTML = Response.text;

	return false;
}

function SaveTag ()
{
	if (document.tagform['tag[name]'].value == '')
	{
		alert(S2_LANG_EMPTY_TAG);
		return false;
	}

	var sRequest = StringFromForm(document.tagform);
	var Response = POSTSyncRequest(sUrl + 'action=save_tag', sRequest);
	if (Response.status == '200')
		document.getElementById('tag_div').innerHTML = Response.text;

	return false;
}

function DeleteTag (iId, sName)
{
	if (!confirm(str_replace('%s', sName, S2_LANG_DELETE_TAG)))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=delete_tag&id=' + iId);
	if (Response.status == '200')
		document.getElementById('tag_div').innerHTML = Response.text;

	return false;
}

//=======================[Options tab]==========================================

function LoadOptions ()
{
	var eDiv = document.getElementById('opt_div');

	var Response = GETSyncRequest(sUrl + 'action=load_options');
	if (Response.status == '200')
		eDiv.innerHTML = Response.text;

	return false;
}

function SaveOptions ()
{
	var sRequest = StringFromForm(document.optform);
	var Response = POSTSyncRequest(sUrl + 'action=save_options', sRequest);
	if (Response.status == '200')
		document.getElementById('opt_div').innerHTML = Response.text;

	return false;
}

//=======================[Extensions tab]=======================================

function LoadExtensions ()
{
	var eDiv = document.getElementById('ext_div');

	var Response = GETSyncRequest(sUrl + 'action=load_extensions');
	if (Response.status == '200')
		eDiv.innerHTML = Response.text;

	return false;
}

function FlipExtension (sId)
{
	var eDiv = document.getElementById('ext_div');

	var Response = GETSyncRequest(sUrl + 'action=flip_extension&id=' + sId);
	if (Response.status == '200')
		eDiv.innerHTML = Response.text;

	return false;
}

function UninstallExtension (sId, sMessage)
{
	if (!confirm(str_replace('%s', sId, S2_LANG_DELETE_EXTENSION)))
		return false;

	if (sMessage != '' && !confirm(str_replace('%s', sMessage, S2_LANG_UNINSTALL_MESSAGE)))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=uninstall_extension&id=' + sId);
	if (Response.status == '200')
		document.getElementById('ext_div').innerHTML = Response.text;

	return false;
}

function InstallExtension (sId, sMessage)
{
	if (!confirm((sMessage != '' ? str_replace('%s', sMessage, S2_LANG_INSTALL_MESSAGE) : '') + str_replace('%s', sId, S2_LANG_INSTALL_EXTENSION)))
		return false;

	var Response = GETSyncRequest(sUrl + 'action=install_extension&id=' + sId);
	if (Response.status == '200')
		document.getElementById('ext_div').innerHTML = Response.text;

	return false;
}

//=======================[Stat tab]=============================================

function LoadStatInfo ()
{
	var eDiv = document.getElementById('stat_div');

	var Response = GETSyncRequest(sUrl + 'action=load_stat_info');
	if (Response.status == '200')
		eDiv.innerHTML = Response.text;

	return false;
}