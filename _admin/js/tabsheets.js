/*
// 2005-03-21
// Copyright (c) Art. Lebedev | http://www.artlebedev.ru/
// Author - Vladimir Tokmakov
// Modified by Roman Parpalak
*/

function Make_Tabsheet ()
{
	var eToSwitch = false;
	var aeDl = document.getElementsByTagName("DL");
	var sActiveTab = document.location.hash + '_tab';

	if (sActiveTab.indexOf('-') != -1)
		sActiveTab += sActiveTab.split('-')[0] + '_tab';

	for (var i = aeDl.length; i-- ;)
	{
		if (aeDl[i].className == "tabsheets")
		{
			var aeDL_child = aeDl[i].childNodes;
			var bActivated = false;
			for (var j = aeDL_child.length; j-- ;)
			{
				if (aeDL_child[j].nodeName == "DT")
				{
					var eDT = aeDL_child[j];
					eDT.unselectable = true;
					eDT.onmousedown = function (e)
					{
						var eTab = e ? e.target : window.event.srcElement;
						SelectTab(eTab, true);
						return false;
					}

					var eDD = eDT;
					while (eDD = eDD.nextSibling)
					{
						if (eDD.nodeName == "DD")
						{
							if (bActivated || (-1 == sActiveTab.indexOf(eDT.id) && j > 4))
								eDD.className = "inactive";
							else
							{
								eDT.className = "active";
								if (-1 == eDT.id.indexOf('-'))
								{
									eToSwitch = eDT;
									if (sActiveTab == '_tab')
										SetPage(eDT.id);
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
var iPreviewHtmlScrollTop = null, iPreviewBodyScrollTop = null;

function OnSwitch (eTab)
{
	var sType = eTab.id;

	(hook = Hooks.get('fn_tab_switch_start')) ? eval(hook) : null;

	if (sType == 'view_tab')
	{
		Preview();
		if (typeof iPreviewHtmlScrollTop == 'number' || typeof iPreviewBodyScrollTop == 'number')
		{
			var try_num = 33;
			var repeater = function ()
			{
				if (typeof iPreviewHtmlScrollTop == 'number' && iPreviewHtmlScrollTop)
				{
					window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop = iPreviewHtmlScrollTop;
					if (try_num-- > 0 && window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop != iPreviewHtmlScrollTop)
						setTimeout(repeater, 30);
				}
				else if (typeof iPreviewBodyScrollTop == 'number' && iPreviewBodyScrollTop)
				{
					window.frames['preview_frame'].document.body.scrollTop = iPreviewBodyScrollTop;
					if (try_num-- > 0 && window.frames['preview_frame'].document.body.scrollTop != iPreviewBodyScrollTop)
						setTimeout(repeater, 30);
				}
			}
			repeater();
		}
	}
	else if (sType == 'edit_tab')
	{
		if (document.artform && document.artform['page[text]'] && iEditorScrollTop)
			document.artform['page[text]'].scrollTop = iEditorScrollTop;
	}
	else if (sType == 'list_tab')
	{
		if (document.getElementById('tree').innerHTML == '')
		{
			document.getElementById('tree').innerHTML = '<br />&nbsp;&nbsp;&nbsp;&nbsp;' + s2_lang.load_tree;
			RefreshTree();
		}
	}
	else if (sType == 'pict_tab')
	{
		LoadPictureManager();
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
function OnBeforeSwitch (eTab)
{
	var sType = eTab.id;

	(hook = Hooks.get('fn_before_switch_start')) ? eval(hook) : null;

	if (sType != 'edit_tab' && document.artform && document.artform['page[text]'] && typeof(document.artform['page[text]'].scrollTop) != 'undefined')
		iEditorScrollTop = document.artform['page[text]'].scrollTop;

	if (sType != 'view_tab')
	{
		if (typeof (window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop) != 'undefined')
			iPreviewHtmlScrollTop = window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop;
		if (typeof (window.frames['preview_frame'].document.body.scrollTop) != 'undefined')
			iPreviewBodyScrollTop = window.frames['preview_frame'].document.body.scrollTop;
	}
}

function SelectTab(eTab, bAddToHistory)
{
	var eSheet = eTab;

	OnBeforeSwitch(eTab);

	while (eSheet.nextSibling)
	{
		eSheet = eSheet.nextSibling;
		if (eSheet.nodeName == "DD")
			break;
	}

	if (eSheet.className == "inactive")
	{
		eTab.className = "on";
		var aeDL_child = eTab.parentNode.childNodes;
		for (var i = aeDL_child.length; i-- ;)
			if (aeDL_child[i].nodeName == "DT" && aeDL_child[i].className != "on")
				aeDL_child[i].className = "";
			else if (aeDL_child[i].nodeName == "DD" && aeDL_child[i].className != "inactive")
			{
				var aeDD_child = aeDL_child[i].childNodes;
				for (var j = aeDD_child.length; j-- ;)
					if (aeDD_child[j].nodeName == "DIV")
						aeDD_child[j].setAttribute('data-scroll', aeDD_child[j].scrollTop);

				aeDL_child[i].className = "inactive";
			}
		eTab.className = "active";
		eSheet.className = "active";
		aeDD_child = eSheet.childNodes;
		for (j = aeDD_child.length; j-- ;)
			if (aeDD_child[j].nodeName == "DIV" && aeDD_child[j].getAttribute('data-scroll'))
				aeDD_child[j].scrollTop = aeDD_child[j].getAttribute('data-scroll');
	}
	if (bAddToHistory)
		SetPage(eTab.id);

	OnSwitch(eTab);
}
