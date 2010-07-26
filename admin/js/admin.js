/**
 * Main JS functions
 *
 * Drag & drop, event handlers for the admin panel
 *
 * @copyright (C) 2007-2010 Roman Parpalak
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

var bIE = document.attachEvent != null;
var bFF = !document.attachEvent && document.addEventListener;

if (bIE)
	attachEvent('onload', Init);
if (bFF)
	addEventListener('load', Init, true);

var eTextarea;
var sCurrTextId = ''; // A unique string for the document currently loaded to the editor

var ua = navigator.userAgent.toLowerCase();
var isIE = (ua.indexOf('msie') != -1 && ua.indexOf('opera') == -1);
var isSafari = ua.indexOf('safari') != -1;
var isGecko = (ua.indexOf('gecko') != -1 && !isSafari);

function Init ()
{
	InitMovableDivs();

	var keyboard_event = isIE || isSafari ? 'keydown' : 'keypress';
	if (bIE)
	{
		// Ctrl + S
		document.attachEvent('on' + keyboard_event, SaveHandler);

		// Mouse events in the tree
		document.getElementById('tree').attachEvent('onmousedown', MouseDown);

		// Tooltips
		document.attachEvent('onmouseover', ShowTip);
		document.attachEvent('onmouseout', HideTip);
	}
	if (bFF)
	{
		// Ctrl + S
		document.addEventListener(keyboard_event, SaveHandler, true);

		// Mouse events in the tree
		document.getElementById('tree').addEventListener('mousedown', MouseDown, false);

		// Tooltips
		document.addEventListener('mouseover', ShowTip, false);
		document.addEventListener('mouseout', HideTip, false);
	}

	// Search field help message.
	// It appears when the field is empty.
	var search_field = document.getElementById('search_field');
	search_field.onkeypress = SearchKeyPress;
	ResetSearchField();
	search_field.onblur = function ()
	{
		var search_field = document.getElementById('search_field');
		if (search_field.value == '')
		{
			search_field.className = 'inactive';
			search_field.value = S2_LANG_SEARCH;
		}
	}
	search_field.onfocus = function ()
	{
		var search_field = document.getElementById('search_field');
		if (search_field.className == 'inactive')
		{
			search_field.className = '';
			search_field.value = '';
		}
	}

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

// Tooltips

function ShowTip (e)
{
	var eItem = window.event ? window.event.srcElement : e.target;

	if (eItem.nodeName == "IMG")
	{
		var alt = eItem.getAttribute('alt');
		window.status = alt;
		if (!eItem.getAttribute('title'))
			eItem.setAttribute('title', alt);
	}
}

function HideTip (e)
{
	var eItem = window.event ? window.event.srcElement : e.target;

	if (eItem.nodeName == "IMG")
		window.status = window.defaultStatus;
}

// Search field events handler

var search_timer, search_string;

function ResetSearchField ()
{
	var search_field = document.getElementById('search_field');
	search_field.value = S2_LANG_SEARCH;
	search_field.className = 'inactive';
}

function SearchKeyPress (e)
{
	e = e || window.event;
	var key = e.keyCode || e.which;

	clearTimeout(search_timer);
	if (key == 13)
	{
		search_string = document.getElementById('search_field').value;
		DoSearch();
	}
	else
	{
		setTimeout("search_string = document.getElementById('search_field').value", 0);
		search_timer = setTimeout(DoSearch, 1000);
	}
}

function DoSearch ()
{
	var Response = GETSyncRequest(sUrl + 'action=search&s=' + encodeURIComponent(search_string));
	if (Response.status != '200')
		return;

	document.getElementById('tree').innerHTML = '<ul>' + Response.text + '</ul>';
}

// Turning animated icon on or off

function SetWait (bWait)
{
	var eAni = document.getElementById('loading');
	var eTree = document.getElementById('tree_div');

	if (bWait)
	{
		eAni.style.display = 'block';
		eTree.style.cursor = 'wait';
	}
	else
	{
		eAni.style.display = 'none';
		eTree.style.cursor = 'default';
	}
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

var draggableDiv = null, buttonPanel = null;

function InitMovableDivs ()
{
	if (draggableDiv == null)
	{
		draggableDiv = document.createElement('DIV');
		document.body.appendChild(draggableDiv);
		draggableDiv.setAttribute('id', 'dragged');
		MoveDraggableDiv(-99, -99);
	}
	if (buttonPanel == null)
	{
		buttonPanel = document.getElementById('context_buttons');
		buttonPanel.parentNode.removeChild(buttonPanel);
	}
}

function MoveDraggableDiv(x, y)
{
	draggableDiv.style.width = 'auto';

	draggableDiv.style.left = x + 10 + 'px';
	draggableDiv.style.top = y + 0 + 'px';
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

	var e = document.getElementById(sId);
	ReleaseItem();
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
	var Response = GETSyncRequest(sUrl + 'action=load_tree&id=0');
	if (Response.status != '200')
		return;

	ResetSearchField();
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

var sSavedName = '', eInput;

function RejectName ()
{
	if (sSavedName == '')
		return;

	var eSpan = eInput.parentNode;
	eSpan.firstChild.nodeValue = sSavedName;
	sSavedName = '';
	eSpan.removeChild(eInput);
}

function RenameKeyPress (e)
{
	var iCode = (e ? e : window.event).keyCode;

	// Enter
	if (iCode == 13)
	{
		var eSpan = eInput.parentNode;
		var sTitle = eInput.value;

		SaveExpand();
		var Response = POSTSyncRequest(sUrl + 'action=rename&id=' + buttonPanel.parentNode.id, 'title=' + encodeURIComponent(sTitle));
		ReleaseItem();
		if (Response.status == '200')
		{
			if (Response.text != '')
				alert(Response.text);
			else
			{
				eSpan.firstChild.nodeValue = sTitle;
				sSavedName = '';
				eSpan.removeChild(eInput);
			}
		}
	}
	// Escape
	if (iCode == 27)
		RejectName();
}

function EditItemName (eSpan)
{
	sSavedName = eSpan.firstChild.nodeValue;
	var iWidth = eSpan.offsetWidth - eSpan.lastChild.offsetWidth;

	eInput = document.createElement('INPUT');
	eInput.setAttribute('type', 'text');
	eInput.onblur = RejectName;
	eInput.onkeypress = RenameKeyPress;
	eInput.setAttribute('value', sSavedName);
	eInput.style.width = iWidth + 'px';

	eSpan.insertBefore(eInput, eSpan.childNodes[1]);
	eInput.focus();
	eInput.select();
	eSpan.firstChild.nodeValue = '';
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

var drag_html = '';

function StartDrag ()
{
	ReleaseItem();

	sourceElement.className = 'source';
	dragging = true;

	drag_html = '<strong>' + sourceElement.innerHTML + '</strong>';
	draggableDiv.innerHTML = drag_html;
	draggableDiv.style.visibility = 'visible';

	sourceParent = sourceElement.parentNode.parentNode.parentNode;
	far = 0;
}

function StopDrag()
{
	dragging = false;
	sourceElement.className = '';
	MoveDraggableDiv(-99, -99);
	draggableDiv.style.visibility = 'hidden';
	drag_html = '';

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

	if (bIE)
	{
		if (t.parentNode.parentNode.parentNode.parentNode.id != 'tree')
		{
			document.attachEvent('onmouseover', MouseIn);
			document.attachEvent('onmouseout', MouseOut);
			document.attachEvent('onmousemove', MouseMove);
		}
		document.attachEvent('onmouseup', MouseUp);
		window.event.returnValue = false;
		t.unselectable = true;
	}
	if (bFF)
	{
		if (t.parentNode.parentNode.parentNode.parentNode.id != 'tree')
		{
			document.addEventListener('mouseover', MouseIn, false);
			document.addEventListener('mouseout', MouseOut, false);
			document.addEventListener('mousemove', MouseMove, false);
		}
		document.addEventListener('mouseup', MouseUp, false);
		e.preventDefault();
	}
}

function MouseMove (e)
{
	var oCanvas = document.getElementsByTagName((document.compatMode && document.compatMode == 'CSS1Compat') ? 'HTML' : 'BODY')[0];
	mouseX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	if (!dragging && (Math.abs(mouseStartY - mouseY) > 5 || Math.abs(mouseStartX - mouseX) > 5))
		StartDrag();

	MoveDraggableDiv(mouseX, mouseY);
}

var idTimer, bIntervalPassed = true;

function MouseUp (e)
{
	var is_drop = dragging;
	if (dragging)
		StopDrag();

	if (sSavedName)
		RejectName();

	if (bMouseInTagvalues)
	{
		AddArticleToTag(sourceElement.id);
		TagvaluesMouseOut();
	}
	else if (!bIntervalPassed)
	{
		// Double click
		if (!is_drop)
			var wnd = window.open(sUrl + 'action=preview&id=' + sourceElement.id, 'previewwindow1', 'scrollbars=yes,toolbar=yes', 'True');

		clearTimeout(idTimer);
		bIntervalPassed = true;
	}
	else
	{
		// Single click
		var sJob = '';
		if (sourceElement == buttonPanel.parentNode)
			// Highlighted item
			sJob = !is_drop ? ' EditItemName(document.getElementById("' + sourceElement.id + '"));' : '';
		else
		{
			ReleaseItem();
			HighlightItem(sourceElement);
		}
		bIntervalPassed = false;
		idTimer = setTimeout('bIntervalPassed = true;' + sJob, 400);
	}
	sourceElement = null;

	if (bIE)
	{
		document.detachEvent('onmouseover', MouseIn);
		document.detachEvent('onmouseout', MouseOut);
		document.detachEvent('onmousemove', MouseMove);
		document.detachEvent('onmouseup', MouseUp);
	}
	if (bFF)
	{
		document.removeEventListener('mouseover', MouseIn, false);
		document.removeEventListener('mouseout', MouseOut, false);
		document.removeEventListener('mousemove', MouseMove, false);
		document.removeEventListener('mouseup', MouseUp, false);
	}
}

// Rollovers

function MouseIn (e)
{
	var t = window.event ? window.event.srcElement : e.target;

	if (t.nodeName == 'SPAN' && sourceElement != null && !isNaN(parseInt(t.id)) && t != acceptorElement && t != sourceElement)
	{
		acceptorElement = t;
		if (far)
		{
			t.className = 'over_far';
			draggableDiv.innerHTML = drag_html + '<br />' +
				str_replace('%s', acceptorElement.innerHTML, S2_LANG_MOVE);
		}
		else
		{
			if (t.parentNode.parentNode.parentNode != sourceParent)
			{
				far = 1;
				t.className = 'over_far';
				draggableDiv.innerHTML = drag_html + '<br />' +
					str_replace('%s', acceptorElement.innerHTML, S2_LANG_MOVE);
			}
			else
			{
				if (mouseStartY > mouseY)
				{
					draggableDiv.innerHTML = drag_html + '<br />' + S2_LANG_MOVE_UP;
					t.className = 'over_top';
				}
				else
				{
					draggableDiv.innerHTML = drag_html + '<br />' + S2_LANG_MOVE_DOWN;
					t.className = 'over_bottom';
				}
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
		acceptorElement = null;
		draggableDiv.innerHTML = drag_html;
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
		CommitChanges(document.artform)
		eTextarea = document.getElementById('wText');

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

function Logout ()
{
	GETSyncRequest(sUrl + 'action=logout');
	document.location.reload();
}

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

function TagSelection (sTag)
{
	return InsertTag('<' + sTag + '>', '</' + sTag + '>');
}

function InsertTag(sOpenTag, sCloseTag)
{
	if (typeof(eTextarea.selectionStart) != 'undefined')
	{
		var iStart = eTextarea.selectionStart, iEnd = eTextarea.selectionEnd;
		var s = new String(eTextarea.value);

		var s1 = s.substring(0, iStart);
		var s2 = s.substring(iStart, iEnd);
		var s3 = s.substring(iEnd);
		var old_top = eTextarea.scrollTop;
		eTextarea.value = s1 + sOpenTag + s2 + sCloseTag + s3;
		eTextarea.setSelectionRange(iStart, iEnd + sOpenTag.length + sCloseTag.length);
		eTextarea.scrollTop = old_top; 
		eTextarea.focus();
	}
	else if (document.selection && document.selection.type == 'Text')
	{
		var old_top = eTextarea.scrollTop;
		var eSelect = document.selection.createRange();
		eSelect.text = sOpenTag + eSelect.text + sCloseTag;
		eSelect.select();
		eTextarea.scrollTop = old_top; 
	}
	else
		eTextarea.value = eTextarea.value + sOpenTag + sCloseTag;

	return false;
}

var iSelStart = iSelEnd = -10;

function GetImage ()
{
	if (typeof(eTextarea.selectionStart) != 'undefined')
	{
		iSelStart = eTextarea.selectionStart;
		iSelEnd = eTextarea.selectionEnd;
	}
	ShowPictMan('edit_tab');
	return false;
}

function Paragraph ()
{
	var Response = POSTSyncRequest(sUrl + 'action=smart_paragraph', 'data=' + encodeURIComponent(eTextarea.value));

	if (Response.status == '200')
		eTextarea.value = Response.text;
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
		document.getElementById('tree_panel').style.marginRight = '-30px';
		document.getElementById('tree_panel').style.paddingRight = '30px';
		eItem.className = 'closed';
		eItem.alt = S2_LANG_SHOW_TAGS;
	}
	else
	{
		LoadTagNames();
		document.getElementById('keytable').style.width = '416px';
		document.getElementById('tag_values').style.display = 'block';
		document.getElementById('tag_names').style.display = 'block';
		document.getElementById('tree_panel').style.marginRight = '-430px';
		document.getElementById('tree_panel').style.paddingRight = '430px';
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
	draggableDiv.innerHTML = drag_html + '<br />' +
		str_replace('%s', aName[aName.length - 2], S2_LANG_ADD_TO_TAG);

	document.getElementById('tag_values').style.backgroundColor = '#ddf';
	eCurrentTag.style.backgroundColor = '#ddf';
}

function TagvaluesMouseOut ()
{
	bMouseInTagvalues = false;
	if (sourceElement)
		draggableDiv.innerHTML = drag_html;

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

var eSrcInput = eWidthInput = eHeightInput = null, bPictManLoaded = false, sReturnTab = '';

function ShowPictMan (sTab)
{
	if (!bPictManLoaded)
	{
		var wnd = window.open('pictman.php', 'pict_frame', '', 'True');
		bPictManLoaded = true;
	}
	sReturnTab = sTab;
	SelectTab(document.getElementById('pict_tab'), true);
}

function ReturnImage(s, w, h)
{
	SelectTab(document.getElementById(sReturnTab), true);

	if (sReturnTab == 'edit_tab')
	{
		var sOpenTag ='<img src="' + s + '" width="' + w + '" height="' + h +'" ' + 'alt="', sCloseTag = '" />';

		if (iSelStart >= 0)
		{
			var s = new String(eTextarea.value);
			var s1 = s.substring(0, iSelStart);
			var s2 = s.substring(iSelStart, iSelEnd);
			var s3 = s.substring(iSelEnd);
			var old_top = eTextarea.scrollTop;
			eTextarea.value = s1 + sOpenTag + s2 + sCloseTag + s3;
			eTextarea.setSelectionRange(iSelStart, iSelEnd + sOpenTag.length + sCloseTag.length);
			eTextarea.scrollTop = old_top; 
			eTextarea.focus();
		}
		else if (document.selection)
		{
			var eSelect = document.selection.createRange();
			eSelect.text = sOpenTag + eSelect.text + sCloseTag;
			eSelect.select();
		}
		else
			eTextarea.value = eTextarea.value + sOpenTag + sCloseTag;
	}
}

//=======================[Preview]==============================================

function Preview ()
{
	if (!eTextarea)
		return;

	var s = str_replace('<!-- text -->', eTextarea.value, template);
	s = str_replace('<!-- title -->', '<h1>' + document.artform['page[title]'].value + '</h1>', s);
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