/*
// 2005-03-21
// Copyright (c) Art. Lebedev | http://www.artlebedev.ru/
// Author - Vladimir Tokmakov
// Modified by Roman Parpalak
*/

function Make_Tabsheet ()
{
	var i, j, eDD, eDT, aeDL_child, eToSwitch = false, bActivated;
	var aeDl = document.getElementsByTagName("DL");
	var sActiveTab = document.location.hash + '_tab';

	if (sActiveTab.indexOf('-') != -1)
		sActiveTab += sActiveTab.split('-')[0] + '_tab';

	for (i = aeDl.length; i-- ;)
	{
		if (aeDl[i].className == "tabsheets")
		{
			aeDL_child = aeDl[i].childNodes;
			bActivated = false;
			for (j = aeDL_child.length; j-- ;)
			{
				if (aeDL_child[j].nodeName == "DT")
				{
					eDT = aeDL_child[j];
//					eDT.unselectable = true
					eDT.onmousedown = Switch_sheet;
					eDD = eDT;
					while (eDD.nextSibling)
					{
						eDD = eDD.nextSibling;
						if (eDD.nodeName == "DD")
						{
							if (bActivated || (-1 == sActiveTab.indexOf(eDT.getAttribute('id')) && j > 4))
								eDD.className = "inactive";
							else
							{
								eDT.className = "active";
								if (-1 == eDT.getAttribute('id').indexOf('-'))
								{
									eToSwitch = eDT;
									if (sActiveTab == '_tab')
										SetPage(eDT.getAttribute('id'));
								}
								bActivated = true;
							}
							break
						}
					}
				}
			}
			if (eToSwitch)
				OnSwitch(eToSwitch);
		}
	}
	return true;
}

var iEditorScrollTop = 0;
var iPreviewScrollTop = 0;

function OnSwitch (eTab)
{
	var sType = eTab.getAttribute('id');

	(hook = hooks['fn_tab_switch_start']) ? eval(hook) : null;

	if (sType == 'view_tab')
	{
		Preview();
		if (iPreviewScrollTop)
			window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop = iPreviewScrollTop;
	}
	else if (sType == 'edit_tab')
	{
		if (eTextarea && iEditorScrollTop)
			eTextarea.scrollTop = iEditorScrollTop;
	}
	else if (sType == 'list_tab')
	{
		if (document.getElementById('tree').innerHTML == '')
		{
			document.getElementById('tree').innerHTML = '<br />&nbsp;&nbsp;&nbsp;&nbsp;' + S2_LANG_LOAD_TREE;
			RefreshTree();
		}
	}
	else if (sType == 'pict_tab')
	{
	}
	else if (sType == 'admin-user_tab')
	{
		LoadUserList();
	}
	else if (sType == 'tag_tab')
	{
		LoadTags();
	}
	else if (sType == 'admin-opt_tab')
	{
		LoadOptions();
	}
	else if (sType == 'admin-ext_tab')
	{
		LoadExtensions();
	}
	else if (sType == 'admin-stat_tab')
	{
		LoadStatInfo();
	}
	else if (sType == 'admin_tab')
	{
		var aeDT = document.getElementById('admin_div').getElementsByTagName('DT');

		for (var i = aeDT.length; i-- ;)
			if (aeDT[i].className == 'active')
			{
				OnSwitch(aeDT[i]);
				break;
			}
	}
}

function SelectTab(eTab, bAddToHistory)
{
	var eSheet = eTab;

	while (eSheet.nextSibling)
	{
		eSheet = eSheet.nextSibling;
		if (eSheet.nodeName == "DD")
			break;
	}

	if (eTextarea.scrollTop)
		iEditorScrollTop = eTextarea.scrollTop;

	if (window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop)
		iPreviewScrollTop = window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop;

	if (eSheet.className == "inactive")
	{
		eTab.className = "on";
		var aeDL_child = eTab.parentNode.childNodes;
		for (var i = aeDL_child.length; i-- ;)
			if (aeDL_child[i].nodeName == "DT" && aeDL_child[i].className != "on")
				aeDL_child[i].className = "";
			else if (aeDL_child[i].nodeName == "DD")
				aeDL_child[i].className = "inactive";
		eSheet.className = "active";
		eTab.className = "active";
	}
	if (bAddToHistory)
		SetPage(eTab.getAttribute('id'));

	OnSwitch(eTab);
}

function Switch_sheet (e)
{
	var eTab = e ? e.target : window.event.srcElement

	if (eTab.nodeType == 3)
		eTab = eTab.parentNode;

	SelectTab(eTab, true);
	return false
}