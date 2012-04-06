/**
 * Main JS functions
 *
 * Drag & drop, event handlers for the admin panel
 *
 * @copyright (C) 2007-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

// Simple hooks managing

var Hooks = (function ()
{
	var hooks = [];

	return (
	{
		add: function (hook, code)
		{
			hooks[hook] = typeof(hooks[hook]) != 'string' ? code : hooks[hook] + code;
		},

		get: function (hook)
		{
			return hooks[hook] || null;
		}
	});
}());

// Helper functions

function str_replace (substr, newsubstr, str)
{
	newsubstr = newsubstr.replace(/\$/g, '$$$$');
	while (str.indexOf(substr) >= 0)
		str = str.replace(substr, newsubstr);
	return str;
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

var is_local_storage = false;
try
{
	is_local_storage = 'localStorage' in window && window['localStorage'] !== null;
}
catch (e)
{
	is_local_storage = false;
}

var ua = navigator.userAgent.toLowerCase();
var isIE = (ua.indexOf('msie') != -1 && ua.indexOf('opera') == -1);
var isSafari = ua.indexOf('safari') != -1;
var isGecko = (ua.indexOf('gecko') != -1 && !isSafari);

function Init ()
{
	Search.init();
	Changes.init();

	// Ctrl + S
	$(document).bind(isIE || isSafari ? 'keydown' : 'keypress', function (e)
	{
		var key = e.keyCode || e.which;
		key = (isGecko || (window.opera && window.opera.version() >= 11.10)) ? (key == 115 ? 1 : 0) : (key == 83 ? 1 : 0);
		if (e.ctrlKey && key)
		{
			eval(Hooks.get('fn_save_handler_start'));

			e.preventDefault();
			e.stopPropagation();

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
	});

	// Tooltips
	$(document).mouseover(function (e)
	{
		var eItem = e.target;
		var title = eItem.title;

		if (!title && eItem.nodeName == 'IMG')
			title = eItem.title = eItem.alt;

		if (title)
			window.status = title;
	})
	.mouseout(function ()
	{
		window.status = window.defaultStatus;
	});

	// Tags list
	var eTagValues = document.getElementById('tag_values');
	eTagValues.onmouseover = TagvaluesMouseIn;
	eTagValues.onmouseout = TagvaluesMouseOut;

	var eTagTable = document.getElementById('tag_table');
	var fTagSwitch = function ()
	{
		if (eTagTable.className == 'closed')
		{
			eTagTable.className = 'opened';
			GETAsyncRequest(sUrl + 'action=load_tagnames', function (http)
			{
				document.getElementById('tag_list').innerHTML = http.responseText;
			});
		}
		else
			eTagTable.className = 'closed';

		if (is_local_storage)
			localStorage.setItem('s2_tags_opened', eTagTable.className == 'closed' ? 0 : 1);
		return false;
	};

	if (is_local_storage && parseInt(localStorage.getItem('s2_tags_opened')))
		fTagSwitch();

	var aeI = eTagTable.getElementsByTagName('I');
	for (var i = aeI.length; i-- ;)
		aeI[i].onclick = fTagSwitch;

	// Prevent from loosing unsaved data
	window.onbeforeunload = function ()
	{
		if (document.artform && Changes.present(document.artform))
			return s2_lang.unsaved_exit;
	}

	TableSort();

	cur_page = document.location.hash;
	setInterval(CheckPage, 400);
	SetWait(false);
}

function Logout ()
{
	function DoLogout ()
	{
		GETAsyncRequest(sUrl + 'action=logout', function ()
		{
			document.location.reload();
		});
	}

	if (document.artform && Changes.present(document.artform))
	{
		PopupMessages.show(s2_lang.unsaved_exit, [
			{
				name: s2_lang.save_and_exit,
				action: (function ()
				{
					document.artform.onsubmit();
					setTimeout(DoLogout, 200);
				}),
				once: true
			},
			{
				name: s2_lang.discard_and_exit,
				action: (function ()
				{
					DoLogout();
				}),
				once: true
			}
		]);
		return false;
	}

	DoLogout();
	return false;
}

function CloseOtherSessions ()
{
	GETAsyncRequest(sUrl + 'action=close_other_sessions' /*, function () {} */);
	return false;
}

var SetBackground = (function ()
{
	var back_img = '', color = '#eee',
		_size = 150;

	function Noise ()
	{
		if (!!!document.createElement('canvas').getContext)
			return false;

		var canvas = document.createElement('canvas');
		canvas.width = canvas.height = _size;

		var ctx = canvas.getContext('2d'),
			imgData = ctx.createImageData(canvas.width, canvas.height),
			maxAlpha = 5.5,
			maxLine = 4;

		var repeat_num = 0;
 		for (var y = canvas.height; y--; )
		for (var x = canvas.width, index = (x + y * imgData.width) * 4; x--; )
		{
			var alpha = Math.random() * maxLine < repeat_num++ ? (repeat_num = 0) + ~~(Math.random() * maxAlpha) : alpha;
			index -= 4;
			imgData.data[index] = imgData.data[index + 1] = imgData.data[index + 2] = 0;
			imgData.data[index + 3] = alpha;
		}

		ctx.putImageData(imgData, 0, 0);

		back_img = 'url(' + canvas.toDataURL('image/png') + ')';

		set(color);
	}

	setTimeout(Noise, 0);

	var head = document.getElementsByTagName('head')[0],
		style = document.createElement('style');

	style.type = 'text/css';
	head.appendChild(style);

	var set = function (c)
	{
		color = c;
		var css_rule = 'body {background: ' + back_img + ' ' + color + '; background-attachment: local; background-size: ' + _size*8 + 'px ' + _size + 'px;} #tag_names li.cur_tag, .tabsheets > dt.active {background-color: ' + color + ';}';

		if (style.styleSheet)
			style.styleSheet.cssText = css_rule;
		else
		{
			if (style.firstChild)
				style.removeChild(style.firstChild);
			style.appendChild(document.createTextNode(css_rule));
		}
		return false;
	}

	return set;
}());


// Search field events handler

var Search = (function ()
{
	var search_string = '';
	var eInput;

	return (
	{
		init: function ()
		{
			var DoSearch = function ()
			{
				$('#tree').jstree('refresh', -1);
			}

			var search_timer;
			eInput = document.getElementById('search_field');

			function NewSearch ()
			{
				// We have to wait a while for eInput.value to change
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
			eInput.oninput = NewSearch;

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
					NewSearch();
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
}());

// Turning animated icon on or off
function SetWait (bWait)
{
	document.getElementById('loading').style.display = bWait ? 'block' : 'none';
	document.body.style.cursor = bWait ? 'progress' : 'inherit';
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
		var new_page = document.location.hash.substring(1)
		if (new_page.indexOf('-') != -1)
			SelectTab(document.getElementById(new_page.split('-')[0] + '_tab'), false);
		SelectTab(document.getElementById(new_page + '_tab'), true);
	}
}

// Tracking editor content changes

var Changes = (function ()
{
	var saved_text = curr_md5 = '';

	function check_changes ()
	{
		if (!is_local_storage || !document.artform)
			return;

		eval(Hooks.get('fn_check_changes_start'));

		var new_text = document.artform['page[text]'].value;

		if  (saved_text != new_text)
			localStorage.setItem('s2_curr_text', new_text);
		else
			localStorage.removeItem('s2_curr_text');
	};

	function show_recovered (sText)
	{
		PopupWindow(s2_lang.recovered_text_alert, s2_lang.recovered_text, s2_lang.recovered_text_info, sText);
	}

	if (is_local_storage)
	{
		var old_text = localStorage.getItem('s2_curr_text');
		setInterval(check_changes, 5000);
	}

	return (
	{
		init: function ()
		{
			if (old_text)
				PopupMessages.show(s2_lang.recovered_text_alert, [{name: s2_lang.recovered_open, action: function (){ show_recovered(old_text); } }]);
		},

		commit: function (arg)
		{
			curr_md5 = hex_md5((typeof(arg) == 'string') ? arg : StringFromForm(arg));

			if (is_local_storage)
			{
				eval(Hooks.get('fn_changes_commit_pre_ls'));

				localStorage.removeItem('s2_curr_text');
				saved_text = document.artform['page[text]'].value;
			}
		},

		present: function (eForm)
		{
			eval(Hooks.get('fn_changes_present'));

			return curr_md5 != hex_md5(StringFromForm(eForm));
		}
	});
}());

// Table sorting
// originally written by paul sowden <paul@idontsmoke.co.uk> | http://idontsmoke.co.uk
// modified and localized by alexander shurkayev <alshur@ya.ru> | http://htmlcoder.visions.ru

var TableSort = (function ()
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

		aeTHead = (e ? e : document).getElementsByTagName('thead');
		for (var j = aeTHead.length; j-- ;)
		{
			var node, eTHead = aeTHead[j];
			if (eTHead.parentNode.className != 'sort')
				continue;

			aeTD = eTHead.getElementsByTagName('td');
			for (var i = aeTD.length; i-- ;)
			{
				eTD = aeTD[i];
				$(eTD).click(sort);
				eTD.title = eTD.title ? eTD.title : s2_lang.click_to_sort;
			}
		}
	}
}());

function CloseAll ()
{
	$('#tree').jstree('close_all').jstree('toggle_node', '#node_1');
}

function OpenAll ()
{
	$('#tree').jstree('open_all');
}

$(document).ready(function()
{
	var selectedId = -1,
		commentNum = 0,
		isRenaming = false;

	function createArticle ()
	{
		tree.jstree('create', null, 'last', {data : {title : s2_lang.new_page, attr : {'class' : 'Hidden'}}});
	}

	function editArticle ()
	{
		LoadArticle(sUrl + 'action=load&id=' + selectedId);
	}

	function showComments ()
	{
		if (commentNum)
			LoadComments(selectedId);
	}

	function initContext ()
	{
		$('#context_edit').click(editArticle);
		$('#context_comments').click(showComments);
		$('#context_add').click(createArticle);
		$('#context_delete').click(function () {tree.jstree('remove');});
	}

	Init();
	initContext();

	var eButtons = $('#context_buttons');
	eButtons.detach();

	function rollback (data)
	{
		eButtons.remove();
		eButtons = null;
		$.jstree.rollback(data);
		eButtons = $('#context_buttons');
	}

	var tree = $('#tree')
		.bind("before.jstree", function (e, data)
		{
			//console.log(data.func);
			if (data.func === "remove" && !confirm(str_replace('%s', tree.jstree('get_text', data.args[0]), s2_lang.delete_item)))
			{
				e.stopImmediatePropagation(); 
				return false; 
			}
		})
		.bind('refresh.jstree', function ()
		{
			setTimeout(initContext, 0);
		})
		.bind('dblclick.jstree', function (e)
		{
			if (!isRenaming && e.target.nodeName == 'A')
			{
				isRenaming = true;
				tree.jstree('rename', e.target);
			}
		})
		.bind('select_node.jstree', function (e, d)
		{
			if (!eButtons)
				return;

			var curId = d.rslt.obj.attr('id').replace('node_', '');
/* 			if (curId === selectedId)
				tree.jstree('rename', d.rslt.obj);
			else
 */			{
				eButtons.detach();
				selectedId = curId;
				commentNum = d.rslt.obj.attr('data-comments');
				$('.jstree-clicked').append(eButtons);
			}
		})
		.bind('deselect_node.jstree', function (e, d)
		{
			eButtons.detach();
		})
		.bind('rename.jstree', function (e, data)
		{
			isRenaming = false;
			if (data.rslt.new_name == data.rslt.old_name)
				return;

			$.ajax({
				type : 'POST',
				url : sUrl + 'action=rename&id=' + data.rslt.obj.attr('id').replace('node_', ''),
				data : {title : data.rslt.new_name},
				success : function (d)
				{
					if (!d.status)
						rollback(data.rlbk);
				},
				error : function ()
				{
					rollback(data.rlbk);
				}
			});
		})
		.bind('remove.jstree', function (e, data)
		{
			$.ajax({
				url : sUrl + 'action=delete&id=' + data.rslt.obj.attr('id').replace('node_', ''),
				success : function (d)
				{
					if (!d || !d.status)
						rollback(data.rlbk);
				},
				error : function ()
				{
					rollback(data.rlbk);
				}
			});
		})
		.bind('create.jstree', function (e, data)
		{
			$.ajax({
				url : sUrl + 'action=create&id=' + data.rslt.parent.attr('id').replace('node_', ''),
				data : {title : data.rslt.name},
				success : function (d)
				{
					if (!d.status)
						rollback(data.rlbk);
					else
						data.rslt.obj.attr('id', d.id);
				},
				error : function ()
				{
					rollback(data.rlbk);
				}
			});
		})
		.bind('move_node.jstree', function (e, data)
		{
			$.ajax({
				url : sUrl + 'action=move&source_id=' + data.rslt.o.attr('id').replace('node_', '') + '&new_parent_id=' + data.rslt.np.attr('id').replace('node_', '') + '&new_pos=' + data.rslt.cp,
				success : function (d)
				{
					if (!d.status)
						rollback(data.rlbk);
				},
				error : function ()
				{
					rollback(data.rlbk);
				}
			});
		})
		.jstree({
			crrm : {
				input_width_limit : 1000
			},
			ui : {
				select_limit : 1,
				initially_select : ['node_1']
			},
			hotkeys : {
				'e' : editArticle,
				'c' : showComments,
				'n' : createArticle
			},
			json_data : {
				ajax : {
					url : function (node)
					{
						return sUrl + 'action=load_tree&id=0&search=' + encodeURIComponent(Search.string());
					}
				}
			},
			core : {
				animation : 150,
				initially_open : ['node_1'],
				progressive_render : true,
				open_parents : false
			},
			plugins : ["json_data", 'dnd', "ui", "crrm", "hotkeys"]
		});
})
.ajaxStart(function (event, XMLHttpRequest, ajaxOptions)
{
	SetWait(true);
})
.ajaxStop(function (event, XMLHttpRequest, ajaxOptions)
{
	SetWait(false);
});

function RefreshTree ()
{
	Search.reset();
	$('#tree').jstree('refresh', -1);
}

//=======================[Tree button handlers]=================================

var LoadArticle, ReloadArticle;

(function ()
{
	var sLoadedURI;

	function RequestArticle (sURI)
	{
		//console.log('pre-request');
		GETAsyncRequest(sURI, function (http)
		{
			//console.log('callback-start');
			eval(Hooks.get('request_article_start'));

			document.getElementById('form_div').innerHTML = http.responseText;
			Changes.commit(document.artform);
			SelectTab(document.getElementById('edit_tab'), true);
			sLoadedURI = sURI;

			eval(Hooks.get('request_article_end'));
			//console.log('callback-end');
		});
	}

	LoadArticle = function (sURI)
	{
		if (document.artform && Changes.present(document.artform))
		{
			SelectTab(document.getElementById('edit_tab'), true);
			PopupMessages.show(s2_lang.unsaved, [
				{
					name: s2_lang.save_and_open,
					action: (function ()
					{
						document.artform.onsubmit();
						RequestArticle(sURI);
					}),
					once: true
				},
				{
					name: s2_lang.discard_and_open,
					action: (function ()
					{
						RequestArticle(sURI);
					}),
					once: true
				}
			]);
			return false;
		}

		RequestArticle(sURI);
	}

	ReloadArticle = function ()
	{
		RequestArticle(sLoadedURI);
	}
}());

function EditArticle (iId)
{
	LoadArticle(sUrl + 'action=load&id=' + iId);
	return false;
}

function LoadComments (iId)
{
	GETAsyncRequest(sUrl + 'action=load_comments&id=' + iId, function (http)
	{
		var eItem = document.getElementById('comm_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
		SelectTab(document.getElementById('comm_tab'), true);
	});
	return false;
}

//=======================[Editor button handlers]===============================

function SaveArticle(sAction)
{
	eval(Hooks.get('fn_save_article_start'));

	document.forms['artform'].setAttribute('data-save-process', 1);

	var sRequest = StringFromForm(document.forms['artform']),
		sPagetext = document.forms['artform'].elements['page[text]'].value;

	POSTAsyncRequest(sUrl + 'action=' + sAction, sRequest, function(http)
	{
		if (http.responseXML)
		{
			var xmldoc = http.responseXML,
				sStatus = xmldoc.getElementsByTagName('status')[0].firstChild.nodeValue;

			if (sStatus == 'conflict')
			{
				PopupMessages.show(s2_lang.conflicted_revisions, [
					{
						name: s2_lang.conflicted_action,
						action: (function ()
						{
							PopupWindow(s2_lang.conflicted_text, s2_lang.conflicted_text, s2_lang.conflicted_text_info, sPagetext);
							ReloadArticle();
						}),
						once: true
					}
				]);
				return;
			}

			// If the form was reloaded, we do not have to update it.
			// (for example, if user had modified the page
			// then opened another page and chose "Save and open") 
			if (!document.forms['artform'].getAttribute('data-save-process'))
				return;

			var sUrlStatus = xmldoc.getElementsByTagName('url_status')[0].firstChild.nodeValue,
				sRevision = xmldoc.getElementsByTagName('revision')[0].firstChild.nodeValue;

			var eItem = document.getElementById("url_input_label");
			if (sUrlStatus == 'empty')
			{
				eItem.className = 'error';
				eItem.title = eItem.getAttribute('title_empty');
			}
			else if (sUrlStatus == 'not_unique')
			{
				eItem.className = 'error';
				eItem.title = eItem.getAttribute('title_unique');
			}
			else
			{
				eItem.className = '';
				eItem.title = '';
			}

			document.forms['artform'].elements['page[revision]'].value = sRevision;

			eItem = document.getElementById('pub');
			eItem.parentNode.className = eItem.checked ? 'ok' : '';
			document.getElementById('preview_link').style.display = eItem.checked ? 'inline' : 'none';

			Changes.commit(document.forms['artform']);

			eval(Hooks.get('fn_save_article_end'));
		}
		else if (http.responseText != '')
			alert(http.responseText);
	});
}

function ChangeSelect (eSelect, sHelp, sDefault)
{
	if (eSelect[eSelect.selectedIndex].value == '+')
	{
		// Adding new item
		// Ask for the value
		var sItem = prompt(sHelp, sDefault);
		if (typeof(sItem) != 'string')
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
				if (eSelect[i].value == sItem)
				{
					isItem = true;
					break;
				}

			if (!isItem)
			{
				// Add new item to the dropdown list
				var eOption = document.createElement('OPTION');
				eOption.setAttribute('value', sItem);
				eOption.appendChild(document.createTextNode(sItem));

				var eLastOption = eSelect.lastChild;
				while (eLastOption.nodeName != 'OPTION')
					eLastOption = eLastOption.previousSibling;

				eSelect.insertBefore(eOption, eLastOption);
			}

			eSelect.value = sItem;
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
		return { start: e.selectionStart, end: e.selectionEnd, length: l, text: e.value.substring(e.selectionStart, e.selectionEnd) };
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

function SmartParagraphs (sText)
{
	sText = sText.replace(/(\r\n|\r|\n)/g, '\n');
	var asParagraphs = sText.split(/\n{2,}/); // split on empty lines

	for (var i = asParagraphs.length; i-- ;)
	{
		// We are working with non-empty contents
		if (asParagraphs[i].replace(/^\s+|\s+$/g, '') == '')
			continue;

		// rtrim
		asParagraphs[i] = asParagraphs[i].replace(/\s+$/gm, '');

		// Do not touch special tags
		if (/<\/?(?:pre|script|style|ol|ul|li)[^>]*>/.test(asParagraphs[i]))
			continue;

		// Put <br /> if there are no closing tag like </h2>

		// Remove old tag
		asParagraphs[i] = asParagraphs[i].replace(/<br \/>$/gm, '').
			// A hack. Otherwise the next regex works twice.
			replace(/$/gm, '-').
			// Put new tag
			replace(/(<\/(?:blockquote|p|h[2-4])>)?-$/gm, function ($0, $1) {return $1 ? $1 : '<br />';}).
			// Remove unnecessary last tag
			replace(/(?:<br \/>)?$/g, '');

		// Put <p>...</p> tags
		if (!/<\/?(?:blockquote|h[2-4])[^>]*>/.test(asParagraphs[i]))
		{
			if (!/<\/p>\s*$/.test(asParagraphs[i]))
				asParagraphs[i] = asParagraphs[i].replace(/\s*$/g, '</p>');
			if (!/^\s*<p[^>]*>/.test(asParagraphs[i]))
				asParagraphs[i] = asParagraphs[i].replace(/^\s*/g, '<p>');
		}
	}

	return asParagraphs.join("\n\n");
}

function InsertParagraph (sType)
{
	if (sType == 'h2' || sType == 'h3' || sType == 'h4' || sType == 'blockquote')
		var sOpenTag = '<' + sType + '>', sCloseTag = '</' + sType + '>';
	else
		var sOpenTag = '<p' + (sType ? ' align="' + sType + '"' : '') + '>', sCloseTag = '</p>';

	var result = eval(Hooks.get('fn_insert_paragraph_start'));
	if (result)
		return;

	var eTextarea = document.artform['page[text]'],
		selection = get_selection(eTextarea),
		sText = eTextarea.value;

	if (eTextarea && typeof(eTextarea.scrollTop) != 'undefined')
		var iScrollTop = eTextarea.scrollTop;

	if (selection.length)
	{
		var replace_str = sOpenTag + selection.text + sCloseTag,
			start_pos = selection.start,
			end_pos = start_pos + replace_str.length;

		eTextarea.value = sText.substring(0, start_pos) + replace_str + sText.substring(selection.end);
		set_selection(eTextarea, start_pos, end_pos);
	}
	else
	{
		var start_pos = sText.lastIndexOf('\r\n\r\n', selection.start - 1) + 1; // First char on the new line (incl. -1 + 1 = 0)
		if (start_pos)
			start_pos += 3;
		else
		{
			start_pos = sText.lastIndexOf('\n\n', selection.start - 1) + 1; // First char on the new line (incl. -1 + 1 = 0)
			if (start_pos)
				start_pos++;
		}

		if (selection.start < start_pos)
		{
			// Ignore empty line
			set_selection(eTextarea, selection.start, selection.start);
			return false;
		}

		var end_pos = sText.indexOf('\r\n\r\n', selection.start);
		if (end_pos == -1)
			end_pos = sText.indexOf('\n\n', selection.start);
		if (end_pos == -1)
			end_pos = sText.length;

		var sEnd = sText.substring(start_pos, end_pos);
		var old_length = sEnd.length;
		var start_len_diff = sEnd.replace(/(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)?/, sOpenTag).length - old_length;

		// Move cursor right if needed to put inside the tag
		var new_cursor = Math.max(sOpenTag.length + start_pos, start_len_diff + selection.start);

		sEnd = sEnd.replace(/(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)?([\s\S]*?)(?:<\/(?:p|blockquote|h[2-4])>)?[ ]*$/, sOpenTag + '$1' + sCloseTag);

		// Move cursor left if needed to put inside the tag
		new_cursor = Math.min(end_pos + (sEnd.length - old_length) - sCloseTag.length, new_cursor);

		eTextarea.value = sText.substring(0, start_pos) + sEnd + sText.substring(end_pos);

		set_selection(eTextarea, new_cursor, new_cursor);
	}

	// Buggy in Opera 11.61 build 1250
	eTextarea.scrollTop = iScrollTop;

	return false;
}

function InsertTag (sOpenTag, sCloseTag, selection)
{
	var result = eval(Hooks.get('fn_insert_tag_start'));
	if (result)
		return;

	var eTextarea = document.artform['page[text]'];
	if (selection == null)
		selection = get_selection(eTextarea);

	if (selection.text.substring(0, sOpenTag.length) == sOpenTag && selection.text.substring(selection.text.length - sCloseTag.length) == sCloseTag)
		var replace_str = selection.text.substring(sOpenTag.length, selection.text.length - sCloseTag.length);
	else
		var replace_str = sOpenTag + selection.text + sCloseTag;

	var start_pos = selection.start;
	var end_pos = start_pos + replace_str.length;

	if (eTextarea && typeof(eTextarea.scrollTop) != 'undefined')
		var iScrollTop = eTextarea.scrollTop;

	eTextarea.value = eTextarea.value.substring(0, start_pos) + replace_str + eTextarea.value.substring(selection.end);
	set_selection(eTextarea, start_pos, end_pos);

	// Buggy in Opera 11.61 build 1250
	eTextarea.scrollTop = iScrollTop;

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
	var result = eval(Hooks.get('fn_paragraph_start'));
	if (result)
		return;

	document.artform['page[text]'].value = SmartParagraphs(document.artform['page[text]'].value);
}

//=======================[Comment management]===================================

function DeleteComment (iId, sMode)
{
	if (!confirm(s2_lang.delete_comment))
		return false;

	GETAsyncRequest(sUrl + 'action=delete_comment&id=' + iId + '&mode=' + sMode, function (http)
	{
		var eItem = document.getElementById('comm_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});

	return false;
}

function SaveComment (sType)
{
	var sRequest = StringFromForm(document.commform);
	POSTAsyncRequest(sUrl + 'action=save_comment&type=' + sType, sRequest, function (http)
	{
		var eItem = document.getElementById('comm_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}

function LoadTable (sAction, sID)
{
	GETAsyncRequest(sUrl + 'action=' + sAction, function (http)
	{
		var eItem = document.getElementById(sID);
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}

function LoadCommentsTable (sAction, iId, sMode)
{
	GETAsyncRequest(sUrl + 'action=' + sAction + '&id=' + iId + '&mode=' + sMode, function (http)
	{
		var eItem = document.getElementById('comm_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}


//=======================[Tags for articles]====================================

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
	iCurrentTagId = eCurrentTag.getAttribute('data-tagid');

	GETAsyncRequest(sUrl + 'action=load_tagvalues&id=' + iCurrentTagId, function (http)
	{
		document.getElementById('tag_values').innerHTML = http.responseText;
	});

	return false;
}

var bMouseInTagvalues = false;

function TagvaluesMouseIn ()
{
/* 	if (!sourceElement || !eCurrentTag)
		return;

	bMouseInTagvalues = true;
	var aName = eCurrentTag.innerHTML.split(' (');
	Drag.set_hint(str_replace('%s', aName[aName.length - 2], s2_lang.add_to_tag));

	document.getElementById('tag_values').style.backgroundColor = '#d2e5fc';
	eCurrentTag.style.backgroundColor = '#d2e5fc'; */
}

function TagvaluesMouseOut ()
{
/* 	bMouseInTagvalues = false;
	if (sourceElement)
		Drag.set_hint('');

	document.getElementById('tag_values').style.backgroundColor = '';

	if (eCurrentTag)
		eCurrentTag.style.backgroundColor = ''; */
}

function AddArticleToTag (iId)
{
	GETAsyncRequest(sUrl + 'action=add_to_tag&tag_id=' + iCurrentTagId + '&article_id=' + iId, function (http)
	{
		document.getElementById('tag_values').innerHTML = http.responseText;
		eCurrentTag.childNodes[1].innerHTML = parseInt(eCurrentTag.childNodes[1].innerHTML) + 1;
	});
	return false;
}

function DeleteArticleFromTag (iId, e)
{
	if (!confirm(s2_lang.delete_tag_link))
		return false;

	if (e.stopPropagation)
		e.stopPropagation();
	e.cancelBubble = true;

	GETAsyncRequest(sUrl + 'action=delete_from_tag&id=' + iId, function (http)
	{
		document.getElementById('tag_values').innerHTML = http.responseText;
		eCurrentTag.childNodes[1].innerHTML = parseInt(eCurrentTag.childNodes[1].innerHTML) - 1;
	});
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

	s = encodeURI(s).
		replace(/&/g, '&amp;').
		replace(/</g, '&lt;').
		replace(/>/g, '&gt;').
		replace(/'/g, '&#039;').
		replace(/"/g, '&quot;');
 
	SelectTab(document.getElementById('edit_tab'), true);
	var sOpenTag = '<img src="' + s + '" width="' + w + '" height="' + h +'" ' + 'alt="', sCloseTag = '" />';
	InsertTag(sOpenTag, sCloseTag, slEditorSelection);
}

//=======================[Preview]==============================================

function Preview ()
{
	if (!document.artform || !document.artform['page[text]'])
		return;

	eval(Hooks.get('fn_preview_start'));

	var s = str_replace('<!-- s2_text -->', document.artform['page[text]'].value, template);
	s = str_replace('<!-- s2_title -->', '<h1>' + document.artform['page[title]'].value + '</h1>', s);
	window.frames['preview_frame'].document.open();
	window.frames['preview_frame'].document.write(s);
	window.frames['preview_frame'].document.close();
}

//=======================[Users tab]============================================

function LoadUserList ()
{
	LoadTable('load_userlist', 'user_div');
}

function AddUser (eForm)
{
	var sUser = eForm.userlogin.value;
	if (sUser == '')
		return false;

	if (sUser.length > 25)
	{
		PopupMessages.showUnique(s2_lang.login_too_long, 'login_too_long');
		return false;
	}

	GETAsyncRequest(sUrl + 'action=add_user&name=' + encodeURIComponent(sUser), function (http)
	{
		eForm.userlogin.value = '';
		var eItem = document.getElementById('user_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});

	return false;
}

function SetPermission (sUser, sPermission)
{
	GETAsyncRequest(sUrl + 'action=user_set_permission&name=' + encodeURIComponent(sUser) + '&permission=' + sPermission, function (http)
	{
		var eItem = document.getElementById('user_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}

function SetUserPassword (sUser)
{
	var s = prompt(str_replace('%s', sUser, s2_lang.new_password));
	if (typeof s != 'string')
		return false;

	POSTAsyncRequest(sUrl + 'action=user_set_password&name=' + encodeURIComponent(sUser), 'pass=' + encodeURIComponent(hex_md5(s + 'Life is not so easy :-)')), function (http)
	{
		PopupMessages.show(http.responseText, false, 3);
	});
	return false;
}

function SetUserEmail (sUser, sEmail)
{
	var s = prompt(str_replace('%s', sUser, s2_lang.new_email), sEmail);
	if (typeof s != 'string')
		return false;

	GETAsyncRequest(sUrl + 'action=user_set_email&login=' + encodeURIComponent(sUser) + '&email=' + encodeURIComponent(s), function (http)
	{
		var eItem = document.getElementById('user_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}

function SetUserName (sUser, sName)
{
	var s = prompt(str_replace('%s', sUser, s2_lang.new_name), sName);
	if (typeof s != 'string')
		return false;

	GETAsyncRequest(sUrl + 'action=user_set_name&login=' + encodeURIComponent(sUser) + '&name=' + encodeURIComponent(s), function (http)
	{
		var eItem = document.getElementById('user_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}

function DeleteUser (sUser)
{
	if (!confirm(str_replace('%s', sUser, s2_lang.delete_user)))
		return false;

	GETAsyncRequest(sUrl + 'action=delete_user&name=' + encodeURIComponent(sUser), function (http)
	{
		var eItem = document.getElementById('user_div');
		eItem.innerHTML = http.responseText;
		TableSort(eItem);
	});
	return false;
}

//=======================[Tags tab]=============================================

function LoadTags ()
{
 	var eDiv = document.getElementById('tag_div');

	if (eDiv.innerHTML != '')
		return false;

	GETAsyncRequest(sUrl + 'action=load_tags', function (http)
	{
		document.getElementById('tag_div').innerHTML = http.responseText;
	});
	return false;
}

function LoadTag (iId)
{
	GETAsyncRequest(sUrl + 'action=load_tag&id=' + iId, function (http)
	{
		document.getElementById('tag_div').innerHTML = http.responseText;
	});
	return false;
}

function SaveTag ()
{
	if (document.tagform['tag[name]'].value == '')
	{
		PopupMessages.showUnique(s2_lang.empty_tag, 'tag_without_name');
		return false;
	}

	var sRequest = StringFromForm(document.forms['tagform']);
	POSTAsyncRequest(sUrl + 'action=save_tag', sRequest, function (http)
	{
		document.getElementById('tag_div').innerHTML = http.responseText;
	});
	return false;
}

function DeleteTag (iId, sName)
{
	if (!confirm(str_replace('%s', sName, s2_lang.delete_tag)))
		return false;

	GETAsyncRequest(sUrl + 'action=delete_tag&id=' + iId, function (http)
	{
		document.getElementById('tag_div').innerHTML = http.responseText;
	});
	return false;
}

//=======================[Options tab]==========================================

function LoadOptions ()
{
	GETAsyncRequest(sUrl + 'action=load_options', function (http)
	{
		document.getElementById('opt_div').innerHTML = http.responseText;
	});
	return false;
}

function SaveOptions ()
{
	var sRequest = StringFromForm(document.optform);
	POSTAsyncRequest(sUrl + 'action=save_options', sRequest);
	return false;
}

//=======================[Extensions tab]=======================================

function LoadExtensions ()
{
	GETAsyncRequest(sUrl + 'action=load_extensions', function (http)
	{
		document.getElementById('ext_div').innerHTML = http.responseText;
	});
	return false;
}

function FlipExtension (sId)
{
	GETAsyncRequest(sUrl + 'action=flip_extension&id=' + sId, function (http)
	{
		document.getElementById('ext_div').innerHTML = http.responseText;
	});
	return false;
}

function UninstallExtension (sId, sMessage)
{
	if (!confirm(str_replace('%s', sId, s2_lang.delete_extension)))
		return false;

	if (sMessage != '' && !confirm(str_replace('%s', sMessage, s2_lang.uninstall_message)))
		return false;

	GETAsyncRequest(sUrl + 'action=uninstall_extension&id=' + sId, function (http)
	{
		document.getElementById('ext_div').innerHTML = http.responseText;
	});

	return false;
}

function InstallExtension (sId, sMessage)
{
	if (!confirm((sMessage != '' ? str_replace('%s', sMessage, s2_lang.install_message) : '') + str_replace('%s', sId, s2_lang.install_extension)))
		return false;

	GETAsyncRequest(sUrl + 'action=install_extension&id=' + sId, function (http)
	{
		document.getElementById('ext_div').innerHTML = http.responseText;
	});

	return false;
}

//=======================[Stat tab]=============================================

function LoadStatInfo ()
{
	GETAsyncRequest(sUrl + 'action=load_stat_info', function (http)
	{
		document.getElementById('stat_div').innerHTML = http.responseText;
	});
	return false;
}