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
		add: function (hook, func)
		{
 			if (typeof hooks[hook] == 'undefined')
				hooks[hook] = [];
			hooks[hook].push(func);
		},

		run: function (hook, data)
		{
			if (!hooks[hook])
				return null;

			for (var i = hooks[hook].length; i-- ;)
				var result = result || hooks[hook][i](data);

			return result;
		}
	});
}());

// Helper functions

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

var selectTabN = function () {};

$(function ()
{
	Changes.init();

	$(document).keydown(function (e)
	{
		var ch = String.fromCharCode(e.which).toLowerCase();

		// Ctrl + S
		if (e.ctrlKey && ch == 's')
		{
			e.preventDefault();
			e.stopPropagation();

			if (document.activeElement && document.activeElement.form && document.activeElement.form.onsubmit)
				document.activeElement.form.onsubmit();

			return false;
		}
		else if (e.ctrlKey && tab_ids[ch])
		{
			e.preventDefault();
			e.stopPropagation();

			selectTabN(ch);

			return false;
		}
	});

	var tab_ids = [];
	$('body > dl.tabsheets > dt').each(function (i) { tab_ids[(i + 1).toString()] = this.id; });
	selectTabN = function  (ch) 
	{
		if (tab_ids[ch])
			selectTab('#' + tab_ids[ch]);
	};

	$('body').on('keydown', '.full_tab_form input[type="text"], .full_tab_form input[type="checkbox"], .full_tab_form select', function(e)
	{
		if (e.which == 13)
			return false;
	});

	// Tooltips
	$(document).mouseover(function (e)
	{
		var eItem = e.target,
			title = eItem.title;

		if (!title && eItem.nodeName == 'IMG')
			title = eItem.title = eItem.alt;

		if (title)
			window.status = title;
	})
	.mouseout(function ()
	{
		window.status = window.defaultStatus;
	});

	// Prevent from loosing unsaved data
	window.onbeforeunload = function ()
	{
		if (document.forms['artform'] && Changes.present(document.forms['artform']))
			return s2_lang.unsaved_exit;
	};

	cur_page = document.location.hash;
	setInterval(CheckPage, 400);
	SetWait(false);
});

function Logout ()
{
	function DoLogout ()
	{
		GETAsyncRequest(sUrl + 'action=logout', function ()
		{
			document.location.reload();
		});
	}

	if (document.forms['artform'] && Changes.present(document.forms['artform']))
	{
		PopupMessages.show(s2_lang.unsaved_exit, [
			{
				name: s2_lang.save_and_exit,
				action: (function ()
				{
					document.forms['artform'].onsubmit();
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
	GETAsyncRequest(sUrl + 'action=close_other_sessions');
	return false;
}

function PopupWindow (sTitle, sHeader, sInfo, sText)
{
	// HTML encode for textarea
	var div = document.createElement('div');
	div.appendChild(document.createTextNode(sText));
	sText = div.innerHTML;

	var wnd = window.open('about:blank', '', '', 'True');
	wnd.document.open();

	var color = '#eee';
	try
	{
		if (window.getComputedStyle) // All the cool bro
			color = window.getComputedStyle(document.body, null).backgroundColor;
		else if (document.body.currentStyle) // Heh, IE8
			color = document.body.currentStyle.backgroundColor;
	}
	catch (e)
	{
		color = '#eee';
	}

	var head = '<title>' + sTitle + '</title>' +
		'<style>html {height: 100%; margin: 0;} body {margin: 0 auto; padding: 9em 10% 1em; height: 100%; background: ' + color + '; font: 75% Verdana, sans-serif;} body, textarea {-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box;} h1 {margin: 0; padding: 0.5em 0 0;} textarea {width: 100%; height: 100%;} .text {position: absolute; top: 0; width: 80%;}</style>';
	var body = '<div class="text"><h1>' + sHeader + '</h1>' + 
		'<p>' + sInfo + '</p></div><textarea readonly="readonly">' + sText + '</textarea>';

	var result = Hooks.run('fn_popup_window_filter_head', head);
	if (result)
		head = result;

	result = Hooks.run('fn_popup_window_filter_body', body);
	if (result)
		body = result;

	var text = '<!DOCTYPE html><html><head>' + head + '</head><body>' + body + '</body></html>';

	wnd.document.write(text);
	wnd.document.close();
}

// Search field events handler

var Search = (function ()
{
	var sSearch = '',
		eInput;

	function DoSearch ()
	{
		$(document).trigger('do_search.s2');
	}

	$(document).on('tree_reload.s2', function ()
	{
		// Cancel search mode
		if (!eInput)
			return;
		eInput.value = eInput.defaultValue;
		eInput.className = 'inactive';
		sSearch = '';
	});

	$(function ()
	{
		var iTimer;
		eInput = document.getElementById('search_field');

		function NewSearch ()
		{
			// We have to wait a while for eInput.value to change
			setTimeout(function ()
			{
				if (sSearch == eInput.value || eInput.className == 'inactive')
					return;

				sSearch = eInput.value;
				SetWait(true);
				clearTimeout(iTimer);
				iTimer = setTimeout(DoSearch, 250);
			}, 0);
		}

		$(eInput).on('input', NewSearch)
		.keydown(function (e)
		{
			if (e.which == 13)
			{
				// Immediate search on enter press
				clearTimeout(iTimer);
				sSearch = this.value;
				DoSearch();
			}
			else
				// We have to wait a little for eInput.value to change
				NewSearch();
		})
		// Search field placeholder
		.focus(function ()
		{
			if (this.className == 'inactive')
			{
				this.className = '';
				this.value = '';
			}
		})
		.blur(function ()
		{
			if (this.value == '')
			{
				this.className = 'inactive';
				this.value = this.defaultValue;
			}
		});
	});

	return (
	{
		// Get search string
		string: function ()
		{
			return sSearch;
		}
	})
}());

// Turning animated icon on or off
function SetWait (bWait)
{
	$('#loading').css('display', bWait ? 'block' : 'none');
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
		var new_page = document.location.hash.substring(1);
		if (new_page.indexOf('-') != -1)
			selectTab('#' + new_page.split('-')[0] + '_tab', false);
		if (selectTab('#' + new_page + '_tab') === false)
			document.location.hash = cur_page;
	}
}

// Tracking editor content changes

var Changes = (function ()
{
	var saved_text = '', curr_md5 = '';

	function check_changes ()
	{
		if (!is_local_storage || !document.forms['artform'])
			return;

		Hooks.run('fn_check_changes_start');

		var new_text = document.forms['artform'].elements['page[text]'].value;

		if  (saved_text != new_text)
			localStorage.setItem('s2_curr_text', new_text);
		else
			localStorage.removeItem('s2_curr_text');
	}

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
			curr_md5 = hex_md5((typeof arg == 'string') ? arg : $(arg).serialize());

			if (is_local_storage)
			{
				Hooks.run('fn_changes_commit_pre_ls');

				localStorage.removeItem('s2_curr_text');
				saved_text = document.forms['artform'].elements['page[text]'].value;
			}
		},

		present: function (eForm)
		{
			Hooks.run('fn_changes_present');

			return curr_md5 != hex_md5($(eForm).serialize());
		}
	});
}());

// Table sorting
// originally written by paul sowden <paul@idontsmoke.co.uk> | http://idontsmoke.co.uk
// modified and localized by alexander shurkayev <alshur@ya.ru> | http://htmlcoder.visions.ru

$(function ()
{
	var sort_case_sensitive = false;

	function _sort(a, b)
	{
		var a = a[0], b = b[0], _a = (a + '').replace(/,/, '.'), _b = (b + '').replace(/,/, '.');
		if (Number(_a) && Number(_b))
			return sort_numbers(_a, _b);
		else if (!sort_case_sensitive)
			return sort_insensitive(a, b);
		else
			return sort_sensitive(a, b);
	}

	function sort_numbers(a, b)
	{
		return a - b;
	}

	function sort_insensitive(a, b)
	{
		var anew = a.toLowerCase(), bnew = b.toLowerCase();
		if (anew < bnew)
			return -1;
		if (anew > bnew)
			return 1;
		return 0;
	}

	function sort_sensitive(a, b)
	{
		if (a < b)
			return -1;
		if (a > b)
			return 1;
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
		var el = e.currentTarget;
		while (el.tagName.toLowerCase() != "td")
			el = el.parentNode;

		var dad = el.parentNode,
			table = dad.parentNode.parentNode,
			up,
			curcol;

		el = $(el);

		$(dad).children('td').each(function (i)
		{
			if (this == el[0])
			{
				curcol = i;
				up = el.hasClass('curcol_down');
				if (up)
					el.removeClass('curcol_down').addClass('curcol_up');
				else if (el.hasClass('curcol_up'))
					el.removeClass('curcol_up').addClass('curcol_down');
				else
					el.addClass('curcol_down');
			}
			else
				$(this).removeClass('curcol_down').removeClass('curcol_up');
		});

		var a = [],
			tbody = table.getElementsByTagName("tbody")[0],
			aeTR = tbody.getElementsByTagName("tr"),
			size = aeTR.length;

		for (i = 0; i < size; i++)
		{
			var node = aeTR[i];
			a[i] = [getConcatenedTextContent(node.getElementsByTagName("td")[curcol]), node];
		}

		a.sort(_sort);
		if (up) a.reverse();
		for (i = 0; i < size; i++)
			tbody.appendChild(a[i][1]);
	}

	$('body').on('click', 'td.sortable', sort);
});

function CloseAll ()
{
	$('#tree').jstree('close_all').jstree('open_node', '#node_1', false, true);
}

function OpenAll ()
{
	$('#tree').jstree('open_all');
}

$(function()
{
	var selectedId = -1,
		commentNum = 0,
		isRenaming = false;

	function createArticle ()
	{
		tree.jstree('create', null, (new_page_pos == '1') ? 'first' : 'last', {data : {title : s2_lang.new_page, attr : {'class' : 'Hidden'}}});
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

	$('.toolbar .refresh').click(refreshTree);

	var eButtons = $('#context_buttons');
	eButtons.detach();

	function run_search ()
	{
		tree.jstree('refresh', -1);
	}

	function refreshTree ()
	{
		$(document).trigger('tree_reload.s2');
		run_search();
	}

	$(document).on('do_search.s2', run_search);

	function rollback (data)
	{
		eButtons.remove();
		eButtons = null;
		$.jstree.rollback(data);
		eButtons = $('#context_buttons');
	}

	var tree = $('#tree')
		.bind('before.jstree', function (e, data)
		{
			if (data.func === 'remove' && !confirm(str_replace('%s', tree.jstree('get_text', data.args[0]), s2_lang.delete_item)))
			{
				e.stopImmediatePropagation(); 
				return false; 
			}
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

			eButtons.detach();
			selectedId = d.rslt.obj.attr('id').replace('node_', '');
			commentNum = d.rslt.obj.attr('data-comments');
			$('.jstree-clicked').append(eButtons);
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
						data.rslt.obj.attr('id', 'node_' + d.id);
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
		.bind('loaded.jstree', function (e, data)
		{
			tree.jstree('open_node', '#node_1');
		})
		.bind('reselect.jstree', function (e, data)
		{
			var e = data.inst.get_selected().first();
			e = e.length ? e : $('.Search.Match').first().parent();
			e = e.length ? e : $('#node_1');

			data.inst.hover_node(e);
		})
		.on('click', '#context_edit, #context_comments, #context_add, #context_delete', function (e, data)
		{
			// Context buttons
			var id = this.id;
			if (id == 'context_edit')
				editArticle();
			else if (id == 'context_comments')
				showComments();
			else if (id == 'context_add')
				createArticle();
			else if (id == 'context_delete')
				tree.jstree('remove');
		})
		.jstree({
			crrm : {
				input_width_limit : 1000,
				move : {
					check_move : function (m) { return (typeof(m.np.attr('id')) != 'undefined' && m.np.attr('id').substring(0, 5) == 'node_'); }
				}
			},
			ui : {
				select_limit : 1,
				initially_select : ['node_1']
			},
			hotkeys : {
				'e' : editArticle,
				'c' : showComments,
				'n' : function () { createArticle(); return false; },
				'f' : function () { $('#search_field').focus(); return false; },
				'r' : refreshTree,
				'f2' : function () { this.rename(this.data.ui.last_selected || this.data.ui.hovered); return false;}
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
				animation : 200,
				progressive_render : true,
				open_parents : false,
				strings : {
					loading : s2_lang.load_tree,
					new_node : s2_lang.new_page
				}
			},
			plugins : ['json_data', 'dnd', 'ui', 'crrm', 'hotkeys']
		});
})
.ajaxStart(function ()
{
	SetWait(true);
})
.ajaxStop(function ()
{
	SetWait(false);
});

$.ajaxPrefilter(function (options, originalOptions, jqXHR)
{
	var successCheck = function (data, textStatus, jqXHR) { checkAjaxStatus(jqXHR); },
		errorCheck = function (jqXHR, textStatus, errorThrown) { checkAjaxStatus(jqXHR); };

	options.success = options.success instanceof Array ? options.success.unshift(successCheck) : (typeof(options.success) == 'function' ? [successCheck, options.success] : successCheck);
	options.error = options.error instanceof Array ? options.error.unshift(errorCheck) : (typeof(options.error) == 'function' ? [errorCheck, options.error] : errorCheck);
});

//=======================[Tree button handlers]=================================

var LoadArticle, ReloadArticle;

(function ()
{
	var sLoadedURI;
	var s2Tags = [];

	function RequestArticle (sURI)
	{
		GETAsyncRequest(sURI, function (http, data)
		{
			Hooks.run('request_article_start');

			s2Tags = data.tags;
			$('#form_div').html(data.form);

			$(document.forms['artform'].elements['page[tags]'])
				.autocomplete({
					delay: 0,
					minLength: 0,
					open: function( e, ui ) { $('.ui-autocomplete').css({width: $(this).css('width')}); },
					source: function (request, response)
					{
						var a = this.element[0].value.split(','),
							offset = -1,
							pos = get_selection(this.element[0]).start;

						for (var i = 0; i < a.length; i++)
						{
							offset += a[i].length + 1;
							if (offset >= pos)
								break;
						}

						response($.ui.autocomplete.filter(s2Tags, $.trim(a[i])));
					},
					focus: function ()
					{
						return false;
					},
					select: function (e, ui)
					{
						var a = this.value.split(','),
							offset = -1,
							pos = get_selection(this).start;

						for (var i = 0; i < a.length; i++)
						{
							offset += a[i].length + 1;
							if (offset >= pos)
								break;
						}

						a[i] = (i ? ' ' : '') + ui.item.value;
						if ($.trim(a[a.length - 1]))
							a.push(' ');

						this.value = a.join(',');

						return false;
					}
				});

			$(document.forms['artform'].elements['page[text]']).keydown(function (e)
			{
				var ch = String.fromCharCode(e.which).toLowerCase();

				if (e.ctrlKey)
				{
					if (ch == 'i')
						TagSelection('em');
					else if (ch == 'b')
						TagSelection('strong');
					else if (ch == 'q')
						InsertParagraph('blockquote');
					else if (ch == 'l')
						InsertParagraph('');
					else if (ch == 'e')
						InsertParagraph('center');
					else if (ch == 'r')
						InsertParagraph('right');
					else if (ch == 'j')
						InsertParagraph('justify');
					else if (ch == 'k')
						InsertTag('<a href="">', '</a>');
					else if (ch == 'p')
						GetImage();
					else
						return;
					return false;
				}
			});

			Changes.commit(document.artform);
			selectTab('#edit_tab');
			sLoadedURI = sURI;

			Hooks.run('request_article_end');
		});
	}

	LoadArticle = function (sURI)
	{
		if (document.artform && Changes.present(document.artform))
		{
			selectTab('#edit_tab');
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
	GETAsyncRequest(sUrl + 'action=load_comments&id=' + iId, function (http, data)
	{
		$('#comm_div').html(data);
		selectTab('#comm_tab');
	});
	return false;
}

//=======================[Editor button handlers]===============================

function SaveArticle(sAction)
{
	Hooks.run('fn_save_article_start', sAction);

	document.forms['artform'].setAttribute('data-save-process', 1);

	var sRequest = $(document.forms['artform']).serialize(),
		sPagetext = document.forms['artform'].elements['page[text]'].value;

	POSTAsyncRequest(sUrl + 'action=' + sAction, sRequest, function (http, data)
	{
		if (typeof data.status != undefined)
		{
			if (data.status == 'conflict')
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

			var eItem = $('#url_input_label');
			if (data.url_status == 'empty')
				eItem.attr('class', 'error').attr('title', eItem.attr('title_empty'));
			else if (data.url_status == 'not_unique')
				eItem.attr('class', 'error').attr('title', eItem.attr('title_unique'));
			else
				eItem.attr('class', '').attr('title', '');

			document.forms['artform'].elements['page[revision]'].value = data.revision;

			eItem = $('#publiched_checkbox')[0];
			eItem.parentNode.className = eItem.checked ? 'ok' : '';
			$('#preview_link').css('display', eItem.checked ? 'inline' : 'none');

			Changes.commit(document.forms['artform']);

			Hooks.run('fn_save_article_end', sAction);
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
	if ('selectionStart' in e)
	{
		var l = e.selectionEnd - e.selectionStart;
		return { start: e.selectionStart, end: e.selectionEnd, length: l, text: e.value.substring(e.selectionStart, e.selectionEnd) };
	}
	else if (document.selection)
	{
		e.focus();
		var r = document.selection.createRange(),
			tr = e.createTextRange(),
			tr2 = tr.duplicate();

		tr2.moveToBookmark(r.getBookmark());
		tr.setEndPoint('EndToStart', tr2);
		if (r == null || tr == null)
			return { start: e.value.length, end: e.value.length, length: 0, text: '' };

		//for some reason IE doesn't always count the \n and \r in the length
		var text_part = r.text.replace(/[\r\n]/g, '.'),
			text_whole = e.value.replace(/[\r\n]/g, '.'),
			the_start = text_whole.indexOf(text_part, tr.text.length);

		return { start: the_start, end: the_start + text_part.length, length: text_part.length, text: r.text };
	}
	else
		return { start: e.value.length, end: e.value.length, length: 0, text: '' };
}

function set_selection (e, start_pos, end_pos)
{
	if ('selectionStart' in e)
	{
		e.focus();
		e.selectionStart = start_pos;
		e.selectionEnd = end_pos;
	}
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
	var asParag = sText.split(/\n{2,}/); // split on empty lines

	for (var i = asParag.length; i-- ;)
	{
		// We are working with non-empty contents
		if (asParag[i].replace(/^\s+|\s+$/g, '') == '')
			continue;

		// rtrim
		asParag[i] = asParag[i].replace(/\s+$/gm, '');

		// Do not touch special tags
		if (/<\/?(?:pre|script|style|ol|ul|li|cut)[^>]*>/.test(asParag[i]))
			continue;

		// Put <br /> if there are no closing tag like </h2>

		// Remove old tag
		asParag[i] = asParag[i].replace(/<br \/>$/gm, '').
			// A hack. Otherwise the next regex works twice.
			replace(/$/gm, '-').
			// Put new tag
			replace(/(<\/(?:blockquote|p|h[2-4])>)?-$/gm, function ($0, $1) {return $1 ? $1 : '<br />';}).
			// Remove unnecessary last tag
			replace(/(?:<br \/>)?$/g, '');

		// Put <p>...</p> tags
		if (!/<\/?(?:blockquote|h[2-4])[^>]*>/.test(asParag[i]))
		{
			if (!/<\/p>\s*$/.test(asParag[i]))
				asParag[i] = asParag[i].replace(/\s*$/g, '</p>');
			if (!/^\s*<p[^>]*>/.test(asParag[i]))
				asParag[i] = asParag[i].replace(/^\s*/g, '<p>');
		}
	}

	return asParag.join("\n\n");
}

function InsertParagraph (sType)
{
	if (sType == 'h2' || sType == 'h3' || sType == 'h4' || sType == 'blockquote')
		var sOpenTag = '<' + sType + '>', sCloseTag = '</' + sType + '>';
	else
		var sOpenTag = '<p' + (sType ? ' align="' + sType + '"' : '') + '>', sCloseTag = '</p>';

	var result = Hooks.run('fn_insert_paragraph_start', {openTag: sOpenTag, closeTag: sCloseTag});
	if (result)
		return result;

	var eTextarea = document.artform['page[text]'],
		selection = get_selection(eTextarea),
		sText = eTextarea.value,
		iScrollTop = eTextarea && eTextarea.scrollTop || 0;

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
	var result = Hooks.run('fn_insert_tag_start', {openTag: sOpenTag, closeTag: sCloseTag});
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
	selectTab('#pict_tab');
	loadPictman();
	return false;
}

function Paragraph ()
{
	var result = Hooks.run('fn_paragraph_start');
	if (result)
		return;

	document.artform['page[text]'].value = SmartParagraphs(document.artform['page[text]'].value);
}

//=======================[Comment management]===================================

function DeleteComment (iId, sMode)
{
	if (!confirm(s2_lang.delete_comment))
		return false;

	GETAsyncRequest(sUrl + 'action=delete_comment&id=' + iId + '&mode=' + sMode, function (http, data)
	{
		$('#comm_div').html(data);
	});

	return false;
}

function SaveComment (sType)
{
	POSTAsyncRequest(sUrl + 'action=save_comment&type=' + sType, $(document.forms['commform']).serialize(), function (http, data)
	{
		$('#comm_div').html(data);
	});
	return false;
}

function LoadTable (sAction, sID)
{
	GETAsyncRequest(sUrl + 'action=' + sAction, function (http, data)
	{
		$('#' + sID).html(data);
	});
	return false;
}

function LoadCommentsTable (sAction, iId, sMode)
{
	GETAsyncRequest(sUrl + 'action=' + sAction + '&id=' + iId + '&mode=' + sMode, function (http, data)
	{
		$('#comm_div').html(data);
	});
	return false;
}

//=======================[Inserting pictures]===================================


var loadPictman = (function ()
{
	var wnd = null;
	return function ()
	{
		if (!wnd)
			wnd = window.open('pictman.php', 'pict_frame', '', 'True');
		wnd.focus();
		wnd.document.body.focus();
	};
}());

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
 
	selectTab('#edit_tab');
	var sOpenTag = '<img src="' + s + '" width="' + w + '" height="' + h +'" ' + 'alt="', sCloseTag = '" />';
	InsertTag(sOpenTag, sCloseTag, slEditorSelection);
}

//=======================[Preview]==============================================

function Preview ()
{
	if (!document.artform || !document.artform['page[text]'])
		return;

	Hooks.run('fn_preview_start');

	var s = str_replace('<!-- s2_text -->', document.artform['page[text]'].value, template);
	s = str_replace('<!-- s2_title -->', '<h1>' + document.artform['page[title]'].value + '</h1>', s);

	var d = window.frames['preview_frame'].document;
	d.open();
	d.write(s);
	d.close();
}

//=======================[Users tab]============================================

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

	GETAsyncRequest(sUrl + 'action=add_user&name=' + encodeURIComponent(sUser), function (http, data)
	{
		eForm.userlogin.value = '';
		$('#user_div').html(data);
	});

	return false;
}

function SetPermission (sUser, sPermission)
{
	GETAsyncRequest(sUrl + 'action=user_set_permission&name=' + encodeURIComponent(sUser) + '&permission=' + sPermission, function (http, data)
	{
		$('#user_div').html(data);
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

	GETAsyncRequest(sUrl + 'action=user_set_email&login=' + encodeURIComponent(sUser) + '&email=' + encodeURIComponent(s), function (http, data)
	{
		$('#user_div').html(data);
	});
	return false;
}

function SetUserName (sUser, sName)
{
	var s = prompt(str_replace('%s', sUser, s2_lang.new_name), sName);
	if (typeof s != 'string')
		return false;

	GETAsyncRequest(sUrl + 'action=user_set_name&login=' + encodeURIComponent(sUser) + '&name=' + encodeURIComponent(s), function (http, data)
	{
		$('#user_div').html(data);
	});
	return false;
}

function DeleteUser (sUser)
{
	if (!confirm(str_replace('%s', sUser, s2_lang.delete_user)))
		return false;

	GETAsyncRequest(sUrl + 'action=delete_user&name=' + encodeURIComponent(sUser), function (http, data)
	{
		$('#user_div').html(data);
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
	if (document.forms['tagform'].elements['tag[name]'].value == '')
	{
		PopupMessages.showUnique(s2_lang.empty_tag, 'tag_without_name');
		return false;
	}

	POSTAsyncRequest(sUrl + 'action=save_tag', $(document.forms['tagform']).serialize(), function (http, data)
	{
		$('#tag_div').html(data);
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

//=======================[Admin tabs]===========================================

function SaveOptions ()
{
	POSTAsyncRequest(sUrl + 'action=save_options', $(document.forms['optform']).serialize(), function ()
	{
		new_page_pos = document.forms['optform'].elements['opt[S2_ADMIN_NEW_POS]'].checked ? '1' : '0';
	});
	return false;
}

function changeExtension (sAction, sId, sMessage, iAdminAffected)
{
	if (sAction == 'install_extension')
	{
		if (!confirm((sMessage != '' ? str_replace('%s', sMessage, s2_lang.install_message) : '') + str_replace('%s', sId, s2_lang.install_extension)))
			return false;
	}
	else if (sAction == 'uninstall_extension')
	{
		if (!confirm(str_replace('%s', sId, s2_lang.delete_extension)))
			return false;

		if (sMessage != '' && !confirm(str_replace('%s', sMessage, s2_lang.uninstall_message)))
			return false;
	}

	GETAsyncRequest(sUrl + 'action=' + sAction + '&id=' + sId, function (http, data)
	{
		$('#ext_div').html(data);
		if (iAdminAffected)
			PopupMessages.show(str_replace('%s', sId, s2_lang.reload_required), [
				{
					name: s2_lang.reload_now,
					action: function () { document.location.reload() },
					once: true
				}
			], 0, 'extensions.' + sId);
	});

	return false;
}

//=======================[Tabs management]======================================
// Based on http://www.artlebedev.ru/tools/technogrette/js/tabsheets/

function makeTabsheet ()
{
	var sActiveTab = document.location.hash + '_tab';
	if (sActiveTab.indexOf('-') != -1)
		sActiveTab += sActiveTab.split('-')[0] + '_tab';

	$('dl.tabsheets').each(function (j)
	{
		var eActive = null,
			eFirst = null;

		$(this).children('dt').each(function (i)
		{
			this.unselectable = true;
			$(this)
				.click(function (e) {selectTab(e.target)})
				.mousedown(function () {return false;});

			if (!i)
				eFirst = this;
			if (sActiveTab.indexOf(this.id) != -1)
				eActive = this;
		});

		eActive = eActive || eFirst;
		eActive.className = $(eActive).next('dd')[0].className = 'active';

		if (!j || eActive.id.indexOf(document.location.hash.substring(1)) != -1)
		{
			OnSwitch(eActive);
			if (sActiveTab == '_tab')
				SetPage(eActive.id);
		}
	});
}

var iEditorScrollTop = 0, iPreviewHtmlScrollTop = null, iPreviewBodyScrollTop = null;

function OnSwitch (eTab)
{
	var sType = eTab.id;

	Hooks.run('fn_tab_switch_start', sType);

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
	else if (sType == 'pict_tab')
	{
		loadPictman();
	}
	else if (sType == 'admin-user_tab')
	{
		GETAsyncRequest(sUrl + 'action=load_userlist', function (http, data)
		{
			var eItem = $('#user_div');
			if (eItem.html() == '')
				eItem.hide();
			eItem.html(data).slideDown('fast');
		});
	}
	else if (sType == 'tag_tab')
	{
		if ($('#tag_div').html() == '')
			GETAsyncRequest(sUrl + 'action=load_tags', function (http, data)
			{
				$('#tag_div').html(data);
			});
	}
	else if (sType == 'admin-opt_tab')
	{
		GETAsyncRequest(sUrl + 'action=load_options', function (http, data)
		{
			$('#opt_div').html(data);
		});
	}
	else if (sType == 'admin-ext_tab')
	{
		GETAsyncRequest(sUrl + 'action=load_extensions', function (http, data)
		{
			$('#ext_div').html(data);
		});
	}
	else if (sType == 'admin-stat_tab')
	{
		GETAsyncRequest(sUrl + 'action=load_stat_info', function (http, data)
		{
			$('#stat_div').html(data);
			Hooks.run('stat_tab_loaded');
		});
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

	Hooks.run('fn_before_switch_start', sType);

	if (sType != 'edit_tab' && document.artform && document.artform['page[text]'] && typeof(document.artform['page[text]'].scrollTop) != 'undefined')
		iEditorScrollTop = document.artform['page[text]'].scrollTop;

	if (sType != 'view_tab')
	{
		if (typeof (window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop) != 'undefined')
			iPreviewHtmlScrollTop = window.frames['preview_frame'].document.getElementsByTagName('html')[0].scrollTop;
		if (typeof (window.frames['preview_frame'].document.body.scrollTop) != 'undefined')
			iPreviewBodyScrollTop = window.frames['preview_frame'].document.body.scrollTop;
	}

	$('#tree').jstree(sType != 'list_tab' ? 'disable_hotkeys' : 'enable_hotkeys');
}

function selectTab (tab, bAddToHistory)
{
	var eTab = $(tab)[0];
	if (typeof eTab == 'undefined')
		return false;

	var eSheet = $(tab).next('dd');

	OnBeforeSwitch(eTab);

	if (eSheet.hasClass('inactive'))
	{
		eSheet.siblings('dt').attr('class', '');
		eTab.className = 'active';

		eSheet.siblings('dd.active').children('div').each(function ()
		{
			$(this).attr('data-scroll', this.scrollTop);
		})
		.end().attr('class', 'inactive');

		eSheet.attr('class', 'active').children('div').each(function ()
		{
			this.scrollTop = $(this).attr('data-scroll') || 0;
		});
	}
	if (bAddToHistory !== false)
		SetPage(eTab.id);

	OnSwitch(eTab);

	return true;
}
