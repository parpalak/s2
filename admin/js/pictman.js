/**
 * Picture manager JS functions
 *
 * Drag & drop, event handlers for the picture manager
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function str_replace(substr, newsubstr, str)
{
	while (str.indexOf(substr) >= 0)
		str = str.replace(substr, newsubstr);
	return str;
}

var bIE = document.attachEvent != null;
var bFF = !document.attachEvent && document.addEventListener;

//=======================[Expanding tree]=======================================

var asExpanded = [];

function ExpandSavedItem (sId)
{
	asExpanded[sId] = "";
}

function SaveExpand ()
{
	var aSpan = document.getElementById("tree_div").getElementsByTagName("SPAN");
	var sPath;

	for (var i = aSpan.length; i-- ;)
		if (sPath = aSpan[i].getAttribute("path"))
			asExpanded[sPath] = aSpan[i].parentNode.parentNode.parentNode.className;
}

function LoadExpand ()
{
	var aSpan = document.getElementById("tree_div").getElementsByTagName("SPAN");
	var sPath;

	for (var i = aSpan.length; i-- ;)
		if ((sPath = aSpan[i].getAttribute("path")) && asExpanded[sPath] == "" && aSpan[i].parentNode.firstChild.nodeName == "A")
		{
			aSpan[i].parentNode.firstChild.innerHTML = '<img src="i/m.gif" alt="" />';
			aSpan[i].parentNode.parentNode.parentNode.className = '';
			aSpan[i].parentNode.childNodes[1].setAttribute("src", "i/fo.png");
		}
}

function UnHide (eThis)
{
	if (eThis.parentNode.parentNode.parentNode.className == 'cl')
	{
		eThis.innerHTML = '<img src="i/m.gif" alt="" />';
		eThis.parentNode.parentNode.parentNode.className = '';
		eThis.nextSibling.setAttribute("src", "i/fo.png");
	}
	else
	{
		eThis.innerHTML = '<img src="i/p.gif" alt="" />';
		eThis.parentNode.parentNode.parentNode.className = 'cl';
		eThis.nextSibling.setAttribute("src", "i/fc.png");
	}
	return false;
}

//=======================[Moving divs]==========================================

var draggableDiv = null, buttonPanel = null;

function InitMovableDivs ()
{
	if (draggableDiv == null) 
	{
		draggableDiv = document.createElement("DIV");
		document.body.appendChild(draggableDiv);
		draggableDiv.setAttribute("id", "dragged");
		MoveDraggableDiv(-99, -99);
	}
	if (buttonPanel == null)
	{
		buttonPanel = document.createElement("SPAN");
		buttonPanel.setAttribute("id", "buttons");
		buttonPanel.innerHTML = '<img src="i/page_white_add.png" onclick="CreateSubFolder(); return false;" alt="' + S2_LANG_CREATE_SUBFOLDER + '" /><img src="i/delete.png" class="delete" onclick="DeleteFolder(); return false;" alt="' + S2_LANG_DELETE + '" />';
	}
}

//=======================[Event handlers]=======================================

function SetCallbacks ()
{
	var aSpan = document.getElementById("tree_div").getElementsByTagName("SPAN");

	for (var i = aSpan.length; i-- ;)
	{
		if (typeof(aSpan[i].getAttribute("path")) != 'string')
			continue;

		aSpan[i].onmousedown = MouseDown;
		aSpan[i].onmouseover = MouseIn;
		aSpan[i].onmouseout= MouseOut;
		aSpan[i].unselectable = true;
		if (aSpan[i].parentNode.firstChild.nodeName == "A")
		{
			if (aSpan[i].parentNode.childNodes[1].nodeName != "IMG") 
			{
				var eImg = document.createElement("IMG");

				eImg.setAttribute("alt", "");
				eImg.setAttribute("class", "i");
				eImg.setAttribute("src", "i/fc.png");
				eImg.onmousedown = MouseDown;

				aSpan[i].parentNode.insertBefore(eImg, aSpan[i].parentNode.childNodes[1]);
			}
		}
		else if (aSpan[i].parentNode.firstChild.nodeName != "IMG")
		{
			var eImg = document.createElement("IMG");

			eImg.setAttribute("alt", "");
			eImg.setAttribute("class", "i");
			eImg.setAttribute("src", "i/fc.png");
			eImg.onmousedown = MouseDown;

			aSpan[i].parentNode.insertBefore(eImg, aSpan[i].parentNode.firstChild);
		}
	}
	InitMovableDivs();
}

function MoveDraggableDiv (x, y)
{
	draggableDiv.style.width = "auto";

	draggableDiv.style.left = x + 10 + "px";
	draggableDiv.style.top = y + 0 + "px";
}

//=======================[Highlight & renaming]=================================

var eHigh = null;
var eFileInfo, eFilePanel;
var sCurDir = '';
var sExecDouble = '';

function HighlightItem (item)
{
	if (typeof(item.getAttribute("path")) == 'string')
	{
		item.className = "but_panel";
		item.appendChild(buttonPanel);
		var Response = GETSyncRequest(sUrl + "action=load_items&path=" + item.getAttribute("path"));
		if (Response.status != "200")
			return;

		eFilePanel.innerHTML = Response.text;
		sCurDir = item.getAttribute("path");
		document.getElementById('fold_name').innerHTML = "<b>" + (item.innerText ? item.innerText : item.textContent) + "</b>";
	}
	if (item.getAttribute("fname"))
	{
		eHigh = item;
		item.className = "but_panel";
		var str = S2_LANG_FILE + sPicturePrefix + item.getAttribute("fname");
		if (item.getAttribute("fval"))
			str += "<br />" + S2_LANG_VALUE + item.getAttribute("fval");
		if (item.getAttribute("fsize"))
		{
			var a = item.getAttribute("fsize").split('*');
			str += "<br />" + S2_LANG_SIZE + a[0] + "&times;" + a[1];
			sExecDouble = '(window.top.ReturnImage ? window.top : opener).ReturnImage(\'' + sPicturePrefix + item.getAttribute('fname') + '\', \'' + a[0] + '\', \'' + a[1] + '\');'
			str += '<br /><input type="button" onclick="' + sExecDouble + ' return false;" value="' + S2_LANG_INSERT + '">';
		}
		else
			sExecDouble = '';
		eFileInfo.innerHTML = str;
	}
}

function ReleaseItem ()
{
	if (buttonPanel.parentNode)
	{
		buttonPanel.parentNode.className = "";
		buttonPanel.parentNode.removeChild(buttonPanel);
	}
	if (eHigh)
	{
		eHigh.className = "";
		eHigh = null;
	}
}

var sSavedName = "", eInput;

function RejectName ()
{
	if (sSavedName == "")
		return;

	var eItem = eInput.parentNode;

	if (eItem.nodeName == "SPAN")
	{
		eItem.firstChild.nodeValue = sSavedName;
		sSavedName = "";
		eItem.removeChild(eInput);
		eItem.onmousedown = MouseDown;
	}
	if (eItem.nodeName == "LI")
	{
		eItem.childNodes[2].nodeValue = sSavedName;
		sSavedName = "";
		eItem.removeChild(eInput);
		eItem.firstChild.onmousedown = MouseDown;
	}
}

function KeyPress (e)
{
	var iCode = (e ? e : window.event).keyCode;

	// Enter
	if (iCode == 13)
	{
		var eItem = eInput.parentNode;

		if (eItem.nodeName == "SPAN")
		{
			SaveExpand();
			var Response = GETSyncRequest(sUrl + "action=rename_folder&path=" + buttonPanel.parentNode.getAttribute("path")+ "&name=" + eInput.value);
			ReleaseItem();

			if (Response.status == '200')
			{
				var a = Response.text.split('|');
				eItem.parentNode.parentNode.parentNode.parentNode.innerHTML = a[0];
				eFilePanel.innerHTML = a[1];
				SetCallbacks();
				LoadExpand();
			}
			sSavedName = "";
		}
		if (eItem.nodeName == "LI")
		{
			var Response = GETSyncRequest(sUrl + "action=rename_file&path=" + eItem.firstChild.getAttribute("fname") + "&name=" + eInput.value);
			if (Response.status == '200')
				eFilePanel.innerHTML = Response.text;
			sSavedName = "";
		}
	}
	// Escape
	if (iCode == 27)
		RejectName();
}

function EditItemName (item)
{
	if (item.nodeName == "SPAN" && item.getAttribute("path"))
	{
		sSavedName = item.firstChild.nodeValue;
		var iWidth = item.offsetWidth - item.lastChild.offsetWidth;

		eInput = document.createElement("INPUT");
		eInput.setAttribute("type", "text");
		eInput.onblur = RejectName;
		eInput.onkeypress = KeyPress;
		eInput.setAttribute("value", sSavedName);
		eInput.style.width = iWidth + "px";

		item.insertBefore(eInput, item.childNodes[1]);
		eInput.focus();
		eInput.select();
		item.firstChild.nodeValue = "";
		item.onmousedown = null;
	}

	if (item.nodeName == "LI")
	{
		sSavedName = item.childNodes[1].nodeValue;
		item.childNodes[1].nodeValue = "";

		eInput = document.createElement("INPUT");
		eInput.setAttribute("type", "text");
		eInput.onblur = RejectName;
		eInput.onkeypress = KeyPress;
		eInput.setAttribute("value", sSavedName);

		item.insertBefore(eInput, item.childNodes[1]);
		eInput.focus();
		eInput.select();
		item.firstChild.onmousedown = null;
	}

}

//=======================[Drag & drop]==========================================

var sourceElement, acceptorElement, sourceParent, sourceFElement;

var dragging;

function SetItemChildren (eSpan, sInnerHTML)
{
	var eLi = eSpan.parentNode.parentNode.parentNode;

	if (eLi.lastChild.nodeName == "UL")
		eLi.lastChild.innerHTML = sInnerHTML;
	else
	{
		var eUl = document.createElement("UL");

		eLi.className = "";
		eLi.appendChild(eUl);
		eUl.innerHTML = sInnerHTML;

		var eA = document.createElement("A");

		eA.setAttribute("href", "#");
		eA.className = "sc";
		eA.setAttribute("onclick", "return UnHide(this)");
		eA.innerHTML = '<img src="i/m.gif" alt="" />';

		eLi.firstChild.firstChild.insertBefore(eA, eLi.firstChild.firstChild.firstChild);
	}
	ExpandSavedItem(eSpan.id);
}

function SetParentChildren (eParentUl, str)
{
	if (str != "")
		eParentUl.innerHTML = str;
	else
	{
		var eLi = eParentUl.parentNode;
		eLi.removeChild(eLi.lastChild);
		eLi.firstChild.firstChild.removeChild(eLi.firstChild.firstChild.firstChild);
	}
}

function StartDrag ()
{
	dragging = true;

	if (!sourceElement)
	{
		draggableDiv.innerHTML = sourceFElement.innerHTML;
		draggableDiv.style.visibility = 'visible';
		return;
	}

	ReleaseItem();

	sourceElement.className = "source";

	draggableDiv.innerHTML = sourceElement.innerHTML;
	draggableDiv.style.visibility = 'visible';

	sourceParent = sourceElement.parentNode.parentNode.parentNode.parentNode;
}

function StopDrag ()
{
	dragging = false;
	MoveDraggableDiv(-99, -99);
	draggableDiv.style.visibility = "hidden";

	if (acceptorElement)
	{
		if (sourceFElement)
		{
			var Response = GETSyncRequest(sUrl + "action=move_file&spath=" + sourceFElement.getAttribute("fname") + "&dpath=" + acceptorElement.getAttribute("path"));
			if (Response.status == '200')
				eFilePanel.innerHTML = Response.text;
			acceptorElement.className = "";
		}
		else
		{
			SaveExpand();

			acceptorElement.className = "";

			var eItem = acceptorElement;
			var eSourceLi = sourceElement.parentNode.parentNode.parentNode;

			while (eItem)
			{
				if (eItem == eSourceLi)
				{
					alert(S2_LANG_NO_LOOPS_IMG);
					acceptorElement = null;
					return;
				}
				eItem = eItem.parentNode;
			}

			var Response = GETSyncRequest(sUrl + "action=drag&spath=" + sourceElement.getAttribute("path") + "&dpath=" + acceptorElement.getAttribute("path"));

			if (Response.status == '200')
			{
				var a = Response.text.split('|');
				SetParentChildren(sourceParent, a[1]); //source
				SetItemChildren(acceptorElement, a[0]); //destination
				SetCallbacks();
				LoadExpand();
			}
		}
		acceptorElement = null;
	}
}

//=======================[Mouse events]=========================================

var mouseX, mouseY, mouseStartX, mouseStartY = 0;

function MouseDown (e)
{
	var t = e ? e.target : window.event.srcElement;

	if (t.nodeName == "IMG")
		if (t.nextSibling)
			t = t.nextSibling;
		else
			t = t.parentNode;

	if (t.nodeName == "SPAN" && typeof(t.getAttribute("path")) == 'string')
		sourceElement = t;
	else if (t.nodeName == "SPAN" && t.getAttribute("fname"))
		sourceFElement = t;
	else
		return;

	var oCanvas = document.getElementsByTagName(
	(document.compatMode && document.compatMode == "CSS1Compat") ? "HTML" : "BODY"
	)[0];
	mouseStartX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseStartY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	if (bIE)
	{
		document.attachEvent ("onmousemove", MouseMove);
		document.attachEvent ("onmouseup", MouseUp);
		window.event.cancelBubble = true;
		window.event.returnValue = false;
	}
	if (bFF)
	{
		document.addEventListener ("mousemove", MouseMove, true);
		document.addEventListener ("mouseup", MouseUp, true);
		e.preventDefault();
	}
}

function MouseMove (e)
{
	if (!dragging && (Math.abs(mouseStartY - mouseY) > 2 || Math.abs(mouseStartX - mouseX) > 2))
		StartDrag();

	oCanvas = document.getElementsByTagName(
	(document.compatMode && document.compatMode == "CSS1Compat") ? "HTML" : "BODY"
	)[0];
	mouseX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	MoveDraggableDiv(mouseX, mouseY);
}

var idTimer, bIntervalPassed = true;

function MouseUp(e)
{
	if (sSavedName)
		RejectName();

	var is_drop = dragging;
	if (dragging)
		StopDrag();

	if (sourceFElement != null)
	{
		if (!bIntervalPassed)
		{
			// Double click
			eval(sExecDouble);

			clearTimeout(idTimer);
			bIntervalPassed = true;
		}
		else
		{
			// Single click
			if (sourceFElement.className == "but_panel")
			{
				if (!is_drop)
					EditItemName(sourceFElement.parentNode);
			}
			else
			{
				ReleaseItem();
				HighlightItem(sourceFElement);
			}

			bIntervalPassed = false;
			idTimer = setTimeout('bIntervalPassed = true;', 400);
		}

		sourceFElement = null;
	}
	else
	{
		if (sourceElement.className == "but_panel")
			EditItemName(sourceElement);
		else
		{
			ReleaseItem();
			HighlightItem(sourceElement);
		}
		sourceElement = null;
	}

	if (bIE)
	{
		document.detachEvent ("onmousemove", MouseMove);
		document.detachEvent ("onmouseup", MouseUp);
	}
	if (bFF)
	{
		document.removeEventListener ("mousemove", MouseMove, true);
		document.removeEventListener ("mouseup", MouseUp, true);
	}
}

//=======================[Rollovers]============================================

function MouseIn(e)
{
	var t = e ? e.target : window.event.srcElement;

	if ((sourceElement != null) && (t != acceptorElement) && (t != sourceElement) || (sourceFElement != null))
	{
		acceptorElement = t;
		t.className = "over_far";
	}
	if (bIE)
	{
		window.event.cancelBubble = true;
		window.event.returnValue = false;
	}
	if (bFF)
	{
		e.preventDefault();
	}
}

function MouseOut(e)
{
	var t = e ? e.target : window.event.srcElement;

	if ((sourceElement != null) && (t == acceptorElement) && (t != sourceElement) || (sourceFElement != null))
	{
		t.className = "";
		acceptorElement = null;
	}

	if (bIE)
	{
		window.event.cancelBubble = true;
		window.event.returnValue = false;
	}
	if (bFF)
	{
		e.preventDefault();
	}
}

//=======================[Button handlers]======================================

function DeleteFolder ()
{
	var eSpan = buttonPanel.parentNode;

	if (!confirm(str_replace('%s', eSpan.innerText ? eSpan.innerText : eSpan.textContent, S2_LANG_DELETE_ITEM)))
		return;

	ReleaseItem();
	var Response = GETSyncRequest(sUrl + "action=delete_folder&path=" + eSpan.getAttribute('path'));
	if (Response.status != '200')
		return;

	SaveExpand();
	SetParentChildren(eSpan.parentNode.parentNode.parentNode.parentNode, Response.text);
	SetCallbacks();
	LoadExpand();
	eFilePanel.innerHTML = '';
}

function DeleteFile (sName)
{
	if (!confirm(str_replace('%s', sPicturePrefix + sName, S2_LANG_DELETE_FILE)))
		return;

	var Response = GETSyncRequest(sUrl + "action=delete_file&path=" + sName);
	if (Response.status == '200')
		eFilePanel.innerHTML = Response.text;
}

function CreateSubFolder ()
{
	var eSpan = buttonPanel.parentNode;
	var eLi = eSpan.parentNode.parentNode.parentNode;

	//SaveExpand();
	ReleaseItem();
	var Response = GETSyncRequest(sUrl + "action=create_subfolder&path=" + eSpan.getAttribute('path'));
	if (Response.status != '200')
		return;

	SetItemChildren(eSpan, Response.text);
	SetCallbacks();
	//LoadExpand();

	eSpan = null;
	var eUl = eLi.lastChild;
	for (var i = eUl.childNodes.length; i-- ;)
		if (eUl.childNodes[i].firstChild.firstChild.childNodes[1].getAttribute('selected'))
		{
			eSpan = eUl.childNodes[i].firstChild.firstChild.childNodes[1];
			break;
		}

	if (eSpan != null)
	{
		HighlightItem(eSpan);
		EditItemName(eSpan);
	}
}

function Init ()
{
	eFileInfo = document.getElementById("finfo");
	eFilePanel = document.getElementById("files");
	eFilePanel.onmousedown = MouseDown;
	SetCallbacks();

	// Init tooltips
	if (bIE)
	{
		document.attachEvent("onmouseover", ShowTip);
		document.attachEvent("onmouseout", HideTip);
	}
	if (bFF)
	{
		document.addEventListener("mouseover", ShowTip, false);
		document.addEventListener("mouseout", HideTip, false);
	}
}

function SetWait (bWait)
{
	if (bWait)
		tooltip.h();
}

function UploadSubmit (eForm)
{
	eForm.dir.value = sCurDir;
}

function RefreshFiles ()
{
	var Response = GETSyncRequest(sUrl + "action=load_items&path=" + encodeURIComponent(sCurDir));
	if (Response.status == '200')
		eFilePanel.innerHTML = Response.text;
}