/**
 * Picture manager JS functions
 *
 * Drag & drop, event handlers for the picture manager
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function str_replace(substr, newsubstr, str)
{
	while (str.indexOf(substr) >= 0)
		str = str.replace(substr, newsubstr);
	return str;
}

var ua = navigator.userAgent.toLowerCase();
var isIE = (ua.indexOf('msie') != -1 && ua.indexOf('opera') == -1);
var isSafari = ua.indexOf('safari') != -1;
var isGecko = (ua.indexOf('gecko') != -1 && !isSafari);

var bIE = document.attachEvent != null;
var bFF = !document.attachEvent && document.addEventListener;

if (bIE)
	attachEvent("onload", Init);
if (bFF)
	addEventListener("load", Init, true);

//=======================[Expanding tree]=======================================

var asExpanded = [];

function ExpandSavedItem (sId)
{
	asExpanded[sId] = true;
}

function SaveExpand ()
{
	var aSpan = document.getElementById("tree_div").getElementsByTagName("SPAN");
	var sPath;

	for (var i = aSpan.length; i-- ;)
		if (sPath = aSpan[i].getAttribute("path"))
			asExpanded[sPath] = aSpan[i].parentNode.parentNode.className.indexOf('ExpandOpen') != -1;
}

function LoadExpand ()
{
	var aSpan = document.getElementById("tree_div").getElementsByTagName("SPAN");
	var sPath, eLi;

	for (var i = aSpan.length; i-- ;)
		if ((sPath = aSpan[i].getAttribute("path")) && asExpanded[sPath])
		{
			eLi = aSpan[i].parentNode.parentNode;
			eLi.className = str_replace('ExpandClosed', 'ExpandOpen', eLi.className);
		}
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
		buttonPanel.setAttribute("id", "context_buttons");
		buttonPanel.innerHTML = '<img class="add" src="i/1.gif" onclick="return CreateSubFolder();" alt="' + s2_lang.create_subfolder + '" /><img src="i/1.gif" class="delete" onclick="return DeleteFolder();" alt="' + s2_lang.delete_folder + '" />';
	}
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
		var Response = GETSyncRequest(sUrl + "action=load_items&path=" + encodeURIComponent(item.getAttribute("path")));
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
		var str = s2_lang.file + sPicturePrefix + item.getAttribute("fname");
		if (item.getAttribute("fval"))
			str += "<br />" + s2_lang.value + item.getAttribute("fval");
		if (item.getAttribute("fsize"))
		{
			var a = item.getAttribute("fsize").split('*');
			str += "<br />" + s2_lang.size + a[0] + "&times;" + a[1];
			sExecDouble = '(window.top.ReturnImage ? window.top : opener).ReturnImage(\'' + sPicturePrefix + item.getAttribute('fname') + '\', \'' + a[0] + '\', \'' + a[1] + '\');'
			str += '<br /><input type="button" onclick="' + sExecDouble + ' return false;" value="' + s2_lang.insert + '">';
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
	}
	if (eItem.nodeName == "LI")
	{
		eItem.childNodes[2].nodeValue = sSavedName;
		sSavedName = "";
		eItem.removeChild(eInput);
	}
}

function EditItemName (eItem)
{
	if (eItem.nodeName == "SPAN" && eItem.getAttribute("path"))
	{
		sSavedName = eItem.firstChild.nodeValue;

		eInput = document.createElement("INPUT");
		eInput.setAttribute("type", "text");
		eInput.onblur = RejectName;

		var KeyDown = function (e)
		{
			var iCode = (e ? e : window.event).keyCode;

			if (iCode == 13)
			{
				// Enter
				SaveExpand();
				var Response = GETSyncRequest(sUrl + "action=rename_folder&path=" + encodeURIComponent(eItem.getAttribute("path")) + "&name=" + encodeURIComponent(eInput.value));

				if (Response.status == '200')
				{
					var a = Response.text.split('|');
					var eUl = eItem.parentNode.parentNode.parentNode;
					eUl.innerHTML = a[0];
					eFilePanel.innerHTML = a[1];
					LoadExpand();

					var eSpan = null;
					for (var i = eUl.childNodes.length; i-- ;)
						if (eUl.childNodes[i].childNodes[1].lastChild.getAttribute('selected'))
						{
							eSpan = eUl.childNodes[i].childNodes[1].lastChild;
							break;
						}

					if (eSpan != null)
						HighlightItem(eSpan);
				}
				else
					HighlightItem(eItem);
				sSavedName = "";
			}
			// Escape
			else if (iCode == 27)
			{
				RejectName();
				HighlightItem(eItem);
			}
			else
				setTimeout(function ()
				{
					if (sSavedName)
						eItem.firstChild.nodeValue = '___' + str_replace(' ', ' ', eInput.value);
				}, 0);
		}

		if (isIE || isSafari)
			eInput.onkeydown = KeyDown;
		else
			eInput.onkeypress = KeyDown;
		eInput.value = sSavedName;

		eItem.insertBefore(eInput, eItem.childNodes[1]);
		eInput.focus();
		eInput.select();
		eItem.firstChild.nodeValue += '___';
		ReleaseItem();
	}

	if (eItem.nodeName == "LI")
	{
		sSavedName = eItem.childNodes[1].nodeValue;
		eItem.childNodes[1].nodeValue = "";

		eInput = document.createElement("INPUT");
		eInput.setAttribute("type", "text");
		eInput.onblur = RejectName;
		var KeyDown = function (e)
		{
			var iCode = (e ? e : window.event).keyCode;

			if (iCode == 13)
			{
				// Enter
				var Response = GETSyncRequest(sUrl + "action=rename_file&path=" + encodeURIComponent(eItem.firstChild.getAttribute("fname")) + "&name=" + encodeURIComponent(eInput.value));
				if (Response.status == '200')
					eFilePanel.innerHTML = Response.text;
				sSavedName = "";
			}
			if (iCode == 27)
				// Escape
				RejectName();
		}

		if (isIE || isSafari)
			eInput.onkeydown = KeyDown;
		else
			eInput.onkeypress = KeyDown;
		eInput.setAttribute("value", sSavedName);

		eItem.insertBefore(eInput, eItem.childNodes[1]);
		eInput.focus();
		eInput.select();
		eItem.firstChild.onmousedown = null;
	}

}

//=======================[Drag & drop]==========================================

var sourceElement, acceptorElement, sourceParent, sourceFElement;

var dragging = false;

function SetItemChildren (eSpan, sInnerHTML)
{
	var eLi = eSpan.parentNode.parentNode;

	if (eLi.lastChild.nodeName == "UL")
		eLi.lastChild.innerHTML = sInnerHTML;
	else
	{
		var eUl = document.createElement("UL");

		eLi.className = str_replace('ExpandLeaf', 'ExpandOpen', eLi.className);
		eLi.appendChild(eUl);
		eUl.innerHTML = sInnerHTML;
	}
	ExpandSavedItem(eSpan.getAttribute("path"));
}

function SetParentChildren (eParentUl, str)
{
	if (str != "")
		eParentUl.innerHTML = str;
	else
	{
		var eLi = eParentUl.parentNode;
		eLi.removeChild(eLi.lastChild);
		eLi.className = str_replace('ExpandOpen', 'ExpandLeaf', eLi.className);
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

	sourceElement.className = 'source';

	draggableDiv.innerHTML = sourceElement.innerHTML;
	draggableDiv.style.visibility = 'visible';

	sourceParent = sourceElement.parentNode.parentNode.parentNode;
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
			var Response = GETSyncRequest(sUrl + "action=move_file&spath=" + encodeURIComponent(sourceFElement.getAttribute("fname")) + "&dpath=" + encodeURIComponent(acceptorElement.getAttribute("path")));
			if (Response.status == '200')
				eFilePanel.innerHTML = Response.text;
			acceptorElement.className = "";
		}
		else
		{
			SaveExpand();

			acceptorElement.className = "";

			var eItem = acceptorElement;
			var eSourceLi = sourceElement.parentNode.parentNode;

			while (eItem)
			{
				if (eItem == eSourceLi)
				{
					alert(s2_lang.no_loops_img);
					acceptorElement = null;
					return;
				}
				eItem = eItem.parentNode;
			}

			var Response = GETSyncRequest(sUrl + "action=drag&spath=" + encodeURIComponent(sourceElement.getAttribute("path")) + "&dpath=" + encodeURIComponent(acceptorElement.getAttribute("path")));

			if (Response.status == '200')
			{
				var a = Response.text.split('|');
				SetParentChildren(sourceParent, a[1]); //source
				SetItemChildren(acceptorElement, a[0]); //destination
				LoadExpand();
			}
		}
		acceptorElement = null;
	}
}

//=======================[Mouse events]=========================================

var mouseX, mouseY, mouseStartX, mouseStartY;

function MouseDown (e)
{
	var t = window.event ? window.event.srcElement : e.target;

	if (t.nodeName == "IMG")
		if (t.nextSibling)
			t = t.nextSibling;
		else
			t = t.parentNode;

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
	else if (t.nodeName == "SPAN" && typeof(t.getAttribute("path")) == 'string')
		sourceElement = t;
	else if (t.nodeName == "SPAN" && t.getAttribute("fname"))
		sourceFElement = t;
	else if (t.nodeName == "LI" && t.firstChild.getAttribute("fname"))
		sourceFElement = t.firstChild;
	else
		return;

	var oCanvas = document.getElementsByTagName("HTML")[0];
	mouseStartX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseStartY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	if (bIE)
	{
		document.attachEvent("onmousemove", MouseMove);
		document.attachEvent("onmouseup", MouseUp);
		document.getElementById('tree_div').attachEvent('onmouseover', MouseIn);
		document.getElementById('tree_div').attachEvent('onmouseout', MouseOut);
		window.event.cancelBubble = true;
		window.event.returnValue = false;
		t.unselectable = true;
	}
	if (bFF)
	{
		document.addEventListener("mousemove", MouseMove, true);
		document.addEventListener("mouseup", MouseUp, true);
		document.getElementById('tree_div').addEventListener('mouseover', MouseIn, false);
		document.getElementById('tree_div').addEventListener('mouseout', MouseOut, false);
		e.preventDefault();
	}
}

function MouseMove (e)
{
	var oCanvas = document.getElementsByTagName("HTML")[0];
	mouseX = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
	mouseY = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;

	if (!dragging && (Math.abs(mouseStartY - mouseY) > 5 || Math.abs(mouseStartX - mouseX) > 5))
		StartDrag();

	MoveDraggableDiv(mouseX, mouseY);

	if (bIE)
		window.event.returnValue = false;
	if (bFF)
		e.preventDefault();
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
		document.detachEvent("onmousemove", MouseMove);
		document.detachEvent("onmouseup", MouseUp);
		document.getElementById('tree_div').detachEvent('onmouseover', MouseIn);
		document.getElementById('tree_div').detachEvent('onmouseout', MouseOut);
	}
	if (bFF)
	{
		document.removeEventListener("mousemove", MouseMove, true);
		document.removeEventListener("mouseup", MouseUp, true);
		document.getElementById('tree_div').removeEventListener('mouseover', MouseIn, false);
		document.getElementById('tree_div').removeEventListener('mouseout', MouseOut, false);
	}
}

// Rollovers

function MouseIn(e)
{
	var t = window.event ? window.event.srcElement : e.target;

	if (t.nodeName == 'SPAN' && typeof(t.getAttribute("path")) == 'string' && (sourceElement != null && t != acceptorElement && t != sourceElement || sourceFElement != null))
	{
		acceptorElement = t;
		t.className = "over_far";
	}
}

function MouseOut(e)
{
	var t = window.event ? window.event.srcElement : e.target;

	if (t == acceptorElement)
	{
		t.className = "";
		acceptorElement = null;
	}
}

//=======================[Button handlers]======================================

function DeleteFolder ()
{
	var eSpan = buttonPanel.parentNode;

	if (!confirm(str_replace('%s', eSpan.innerText ? eSpan.innerText : eSpan.textContent, s2_lang.delete_item)))
		return false;

	ReleaseItem();
	var Response = GETSyncRequest(sUrl + "action=delete_folder&path=" + encodeURIComponent(eSpan.getAttribute('path')));
	if (Response.status != '200')
		return false;

	SaveExpand();
	SetParentChildren(eSpan.parentNode.parentNode.parentNode, Response.text);
	LoadExpand();
	eFilePanel.innerHTML = '';
	return false;
}

function DeleteFile (sName)
{
	if (!confirm(str_replace('%s', sPicturePrefix + sName, s2_lang.delete_file)))
		return;

	var Response = GETSyncRequest(sUrl + "action=delete_file&path=" + encodeURIComponent(sName));
	if (Response.status == '200')
	{
		eFilePanel.innerHTML = Response.text;
		eFileInfo.innerHTML = '';
	}
}

function CreateSubFolder ()
{
	var eSpan = buttonPanel.parentNode;
	var eLi = eSpan.parentNode.parentNode;

	ReleaseItem();
	var Response = GETSyncRequest(sUrl + "action=create_subfolder&path=" + encodeURIComponent(eSpan.getAttribute('path')));
	if (Response.status != '200')
		return false;

	SetItemChildren(eSpan, Response.text);

	var eSpan = null;
	var eUl = eLi.lastChild;
	for (var i = eUl.childNodes.length; i-- ;)
		if (eUl.childNodes[i].childNodes[1].lastChild.getAttribute('selected'))
		{
			eSpan = eUl.childNodes[i].childNodes[1].lastChild;
			break;
		}

	if (eSpan != null)
	{
		HighlightItem(eSpan);
		EditItemName(eSpan);
	}
	return false;
}

function Init ()
{
	InitMovableDivs();

	eFileInfo = document.getElementById("finfo");
	eFilePanel = document.getElementById("files");

	// Init tooltips
	if (bIE)
	{
		document.attachEvent("onmouseover", ShowTip);
		document.attachEvent("onmouseout", HideTip);
		document.getElementById('tree_div').attachEvent('onmousedown', MouseDown);
	}
	if (bFF)
	{
		document.addEventListener("mouseover", ShowTip, false);
		document.addEventListener("mouseout", HideTip, false);
		document.getElementById('tree_div').addEventListener('mousedown', MouseDown, false);
	}

	var aeItems = document.getElementById('tree_div').getElementsByTagName('span');
	for (var i = aeItems.length; i-- ;)
		if (typeof(aeItems[i].getAttribute('path')) == 'string' && aeItems[i].getAttribute('path') == '')
		{
			HighlightItem(aeItems[i]);
			break;
		}
}

function SetWait (bWait)
{
	document.getElementsByTagName('body')[0].style.cursor = bWait ? 'progress' : 'default';
}

var was_upload = false;

function UploadSubmit (eForm)
{
	eForm.dir.value = sCurDir;
	was_upload = true;
}

function UploadChange (eItem)
{
	var eForm = eItem.form;
	setTimeout(function()
	{
		UploadSubmit(eForm);
		eForm.submit();
	}, 0);
}

function FileUploaded ()
{
	if (!was_upload)
		return;

	var head = window.frames['submit_result'].document.getElementsByTagName('head')[0].innerHTML,
		body = window.frames['submit_result'].document.getElementsByTagName('body')[0].innerHTML;
	if (head.indexOf('S2-State-Success') >= 0 && !body.replace(/^\s\s*/, "").replace(/\s\s*$/, ""))
	{
		var Response = GETSyncRequest(sUrl + "action=load_items&path=" + encodeURIComponent(sCurDir));
		if (Response.status == '200')
		{
			eFilePanel.innerHTML = Response.text;
			document.getElementById('file_upload_input').innerHTML = document.getElementById('file_upload_input').innerHTML;
		}
	}
	else
		DisplayError(body);
}

// Tooltips

function ShowTip (e)
{
	var eItem = window.event ? window.event.srcElement : e.target;
	var title = eItem.getAttribute('title');

	if (!title && eItem.nodeName == "IMG")
	{
		title = eItem.getAttribute('alt');
		eItem.setAttribute('title', title);
	}

	if (title)
		window.status = title;
}

function HideTip (e)
{
	window.status = window.defaultStatus;
}
