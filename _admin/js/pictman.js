/**
 * Picture manager JS functions
 *
 * Drag & drop, event handlers for the picture manager
 *
 * @copyright (C) 2007-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

var refreshFiles = function () {};
var getCurDir = function () {};

function strNatCmp (a, b)
{
	function chunkify(t)
	{
		var tz = [], x = 0, y = -1, n = 0, i, j;

		while (i = (j = t.charAt(x++)).charCodeAt(0))
		{
			var m = (i == 46 || (i >=48 && i <= 57));
			if (m !== n)
			{
				tz[++y] = "";
				n = m;
			}
			tz[y] += j;
		}
		return tz;
	}

	var aa = chunkify(a.toLowerCase());
	var bb = chunkify(b.toLowerCase());
  
	for (x = 0; aa[x] && bb[x]; x++)
		if (aa[x] !== bb[x])
		{
			var c = Number(aa[x]), d = Number(bb[x]);
			if (c == aa[x] && d == bb[x])
				return c - d;
			else
				return (aa[x] > bb[x]) ? 1 : -1;
		}

	return aa.length - bb.length;
}

var parentWnd = opener || window.top || null;

$(function ()
{
	$(document).bind('keydown', function (e)
	{
		var key = e.keyCode || e.which,
			ch = String.fromCharCode(key).toLowerCase();

		if (e.ctrlKey && ch >= '1' && ch <= '9')
		{
			e.preventDefault();
			e.stopPropagation();

			if (parentWnd)
			{
				parentWnd.selectTabN(ch);
				parentWnd.focus();
				parentWnd.document.body.focus();
			}

			return false;
		}
	});
});

$(function()
{
	var path = '',
		isRenaming = false;

	getCurDir = function ()
	{
		return path;
	}

	function createFolder ()
	{
		folderTree.jstree('create', null, 'first', {data : {title : 'new'}});
	}

	function initContext ()
	{
		$('#context_add').click(createFolder);
		$('#context_delete').click(function () {folderTree.jstree('remove', folderTree.jstree('get_selected'));});
	}

	initFileDrop();

	var eButtons = $('<span><img src="i/1.gif" id="context_add" alt="' + s2_lang.create_subfolder + '" /><img src="i/1.gif" id="context_delete" alt="' + s2_lang.delete_folder + '" /></span>').attr('id', 'context_buttons');
	$('body').append(eButtons);
	initContext();
	eButtons.detach();

	function folderRollback (data)
	{
		eButtons.remove();
		eButtons = null;
		$.jstree.rollback(data);
		eButtons = $('#context_buttons');
		initContext();
	}

	var folderTree = $('#folders')
		.bind('before.jstree', function (e, data)
		{
			if (data.func === 'remove' && (!data.args[0].attr('data-path') || !confirm(str_replace('%s', folderTree.jstree('get_text', data.args[0]), s2_lang.delete_item))))
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
				folderTree.jstree('rename', e.target);
			}
		})
		.bind('select_node.jstree', function (e, d)
		{
			folderTree.jstree('set_focus');

			if (eButtons)
			{
				eButtons.detach();
				folderTree.find('.jstree-clicked').append(eButtons);
			}

			var newPath = d.rslt.obj.attr('data-path');

			if (path != newPath)
			{
				path = newPath;
				fileTree.jstree('refresh', -1);
				$('#fold_name').html('<b>' + folderTree.jstree('get_text', d.rslt.obj) + '</b>');
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
				url : sUrl + 'action=rename_folder&name=' + encodeURIComponent(data.rslt.new_name) + '&path=' + encodeURIComponent(data.rslt.obj.attr('data-path')),
				success : function (d)
				{
					if (!d || !d.status)
					{
						folderRollback(data.rlbk);
						return;
					}

					var len = data.rslt.obj.attr('data-path').length;
					data.rslt.obj.attr('data-path', d.new_path).find('li').each(function ()
					{
						$(this).attr('data-path', d.new_path + $(this).attr('data-path').substring(len));
					});

					var eSelected = folderTree.jstree('get_selected');
					path = eSelected.attr('data-path');
					$('#fold_name').html('<b>' + folderTree.jstree('get_text', eSelected) + '</b>');
				},
				error : function ()
				{
					folderRollback(data.rlbk);
				}
			});
		})
		.bind('remove.jstree', function (e, data)
		{
			$.ajax({
				url : sUrl + 'action=delete_folder&path=' + encodeURIComponent(data.rslt.obj.attr('data-path')),
				success : function (d)
				{
					if (!d || !d.status)
						folderRollback(data.rlbk);
				},
				error : function ()
				{
					folderRollback(data.rlbk);
				}
			});
		})
		.bind('create.jstree', function (e, data)
		{
			$.ajax({
				url : sUrl + 'action=create_subfolder&name=' + encodeURIComponent(data.rslt.name) + '&path=' + encodeURIComponent(data.rslt.parent.attr('data-path')),
				success : function (d)
				{
					if (!d.status)
						folderRollback(data.rlbk);
					else
					{
						data.rslt.obj.attr('data-path', d.path);
						folderTree.jstree('rename_node', data.rslt.obj, d.name);
					}
				},
				error : function ()
				{
					folderRollback(data.rlbk);
				}
			});
		})
		.bind('move_node.jstree', function (e, data)
		{
			if (typeof(data.rslt.o.attr('data-path')) != 'undefined')
				$.ajax({
					url : sUrl + 'action=move_folder&spath=' + encodeURIComponent(data.rslt.o.attr('data-path')) + '&dpath=' + encodeURIComponent(data.rslt.np.attr('data-path')),
					success : function (d)
					{
						if (!d || !d.status)
							folderRollback(data.rlbk);
						else
						{
							var len = data.rslt.o.attr('data-path').length;
							data.rslt.o.attr('data-path', d.new_path).find('li').each(function ()
							{
								$(this).attr('data-path', d.new_path + $(this).attr('data-path').substring(len)); 
							});
							path = folderTree.jstree('get_selected').attr('data-path');
						}
					},
					error : function ()
					{
						folderRollback(data.rlbk);
					}
				});
			else
			{
				var fileNames = [];
				data.rslt.o.each(function () { fileNames.push('fname[]=' + encodeURIComponent($(this).attr('data-fname'))); });

				$.ajax({
					url : sUrl + 'action=move_files&spath=' + encodeURIComponent(path) + '&dpath=' + encodeURIComponent(data.rslt.np.attr('data-path')) + '&' + fileNames.join('&'),
					success : function (d)
					{
						folderRollback(data.rlbk);

						if (!fileTree.children().length)
							fileTree.html('<ul></ul>'); // jstree fix (doesn't work after all roots disappearing)

						if (!d || !d.status)
							fileTree.jstree('refresh', -1);
					},
					error : function ()
					{
						folderRollback(data.rlbk);
						fileTree.jstree('refresh', -1);
					}
				});
			}
		})
		.bind('focus', function ()
		{
			folderTree.jstree('set_focus');
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
				'n' : function () { createFolder(); return false; },
				'f2' : function () { this.rename(this.data.ui.last_selected || this.data.ui.hovered); return false;}
			},
			json_data : {
				ajax : {
					url : function (node)
					{
						return sUrl + 'action=load_tree' + (node.attr ? '&path=' + encodeURIComponent(node.attr('data-path')) : '');
					}
				}
			},
			crrm : {
				move : {
					check_move : function (m) { return (typeof(m.np.attr('data-path')) != 'undefined' && m.np.attr('data-path') != path); }
				}
			},
			core : {
				animation : 150,
				initially_open : ['node_1'],
				progressive_render : true,
				open_parents : false,
				strings : {
					loading : s2_lang.load,
					new_node : 'new'
				}
			},
			sort : function (a, b) { return strNatCmp(this.get_text(a), this.get_text(b)); },
			plugins : ['json_data', 'dnd', 'ui', 'crrm', 'hotkeys' , 'sort']
		});

	refreshFiles = function ()
	{
		fileTree.jstree('refresh', -1);
	};

	var fileTree = $('#files')
		.bind('before.jstree', function (e, data)
		{
			if (data.func === 'remove')
			{
				var names = [];
				fileTree.jstree('get_selected').each(function () { names.push(fileTree.jstree('get_text', this)); })
				if (names.length && !confirm(str_replace('%s', names.join(', '), s2_lang.delete_file)))
				{
					e.stopImmediatePropagation(); 
					return false; 
				}
			}
		})
		.bind('dblclick.jstree', function (e)
		{
			if (!isRenaming && (e.target.nodeName == 'A' || e.target.nodeName == 'INS'))
			{
				isRenaming = true;
				fileTree.jstree('rename', e.target);
			}
		})
		.bind('select_node.jstree', function (e, d)
		{
			fileTree.jstree('set_focus');

			fExecDouble = function () {};
			var str = '';

			if (fileTree.jstree('get_selected').length == 1)
			{
				var filePath = sPicturePrefix + path + '/' + d.rslt.obj.attr('data-fname');
				str = s2_lang.file + '<a href="' + filePath + '" target="_blank">' + filePath + ' &uarr;</a>';
				if (d.rslt.obj.attr('data-fsize'))
					str += "<br />" + s2_lang.value + d.rslt.obj.attr('data-fsize');
				if (d.rslt.obj.attr('data-dim'))
				{
					var a = d.rslt.obj.attr('data-dim').split('*');
					str += "<br />" + s2_lang.size + a[0] + "&times;" + a[1];
					fExecDouble = function ()
					{
						if (parentWnd.ReturnImage)
							parentWnd.ReturnImage(filePath, a[0], a[1]);
					}
					str += '<br /><input type="button" onclick="fExecDouble(); return false;" value="' + s2_lang.insert + '">';
				}
			}

			$('#finfo').html(str);
		})
		.bind('rename.jstree', function (e, data)
		{
			isRenaming = false;
			if (data.rslt.new_name == data.rslt.old_name)
				return;

			$.ajax({
				url : sUrl + 'action=rename_file&name=' + encodeURIComponent(data.rslt.new_name) + '&path=' + encodeURIComponent(path + '/' + data.rslt.obj.attr('data-fname')),
				success : function (d)
				{
					fileTree.jstree('deselect_all');
					if (!d.status)
						fileTree.jstree('refresh', -1);
				},
				error : function ()
				{
					fileTree.jstree('refresh', -1);
				}
			});
		})
		.bind('remove.jstree', function (e, data)
		{
			var fileNames = [];
			data.rslt.obj.each(function () { fileNames.push('fname[]=' + encodeURIComponent($(this).attr('data-fname'))); });

			$.ajax({
				url : sUrl + 'action=delete_files&path=' + encodeURIComponent(path) + '&' + fileNames.join('&'),
				success : function (d)
				{
					if (!d || !d.status)
						fileTree.jstree('refresh', -1);
				},
				error : function ()
				{
					fileTree.jstree('refresh', -1);
				}
			});
		})
		.bind('focus', function ()
		{
			fileTree.jstree('set_focus');
		})
		.jstree({
			ui : {
				select_limit : -1
			},
			hotkeys : {
				'del' : function ()
				{
					fileTree.jstree('remove');
				},
				'ctrl+a' : function ()
				{
					$.jstree._reference(fileTree)._get_children(-1).each(function ()
					{
						fileTree.jstree('select_node', this);
					});
					return false;
				},
				'f2' : function () { this.rename(this.data.ui.last_selected || this.data.ui.hovered); return false;}
			},
			json_data : {
				ajax : {
					url : function ()
					{
						return sUrl + 'action=load_files&path=' + encodeURIComponent(path);
					},
					success : function (data)
					{
						if (data.length)
						{
							$('#loadstatus').text('');
							return data;
						}
						$('#loadstatus').text(data.message || s2_lang.unknown_error);
						return false;
					}
				}
			},
			crrm : { 
				move : {
					check_move : function (m) { return false; }
				}
			},
			core : {
				strings : {
					loading : s2_lang.load,
					multiple_selection : s2_lang.multiple_files
				}
			},
			sort : function (a, b) { return strNatCmp(this.get_text(a), this.get_text(b)); },
			plugins : ['json_data', 'dnd', 'ui', 'crrm', 'hotkeys' , 'sort']
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

function initFileDrop ()
{
	if (document.addEventListener)
	{
		var brd = document.getElementById('brd');
		brd.addEventListener('dragover', function (e)
		{
			e.preventDefault();
		}, false);

		brd.addEventListener('dragenter', function (e)
		{
			var dt = e.dataTransfer;
			if (!dt)
				return;

			if (dt.types.contains && !dt.types.contains("Files")) //FF
				return;
			if (dt.types.indexOf && dt.types.indexOf("Files") == -1) //Chrome
				return;

			$('#brd').addClass('accept_drag');
			setTimeout(function () {$('#brd').removeClass('accept_drag');}, 200);
			setTimeout(function () {$('#brd').removeClass('accept_drag');}, 600);
			setTimeout(function () {$('#brd').removeClass('accept_drag');}, 1000);
			setTimeout(function () {$('#brd').addClass('accept_drag');}, 400);
			setTimeout(function () {$('#brd').addClass('accept_drag');}, 800);

			e.preventDefault();
		}, false);

		brd.addEventListener('dragleave', function (e)
		{
			document.getElementById('brd').className = '';
			e.preventDefault();
		}, false);
		brd.addEventListener('drop', function (e)
		{
			var dt = e.dataTransfer;
			if (!dt || !dt.files)
				return;

			document.getElementById('brd').className = '';

			FileCounter(0, 0);
			var files = dt.files, not_sent = '';
			for (var i = files.length; i-- ;)
				if (files[i].size <= iMaxFileSize)
					SendDroppedFile(files[i]);
				else
					not_sent += '<br />' + files[i].fileName;

			if (not_sent != '')
				PopupMessages.show(str_replace('%s', sFriendlyMaxFileSize, s2_lang.files_too_big) + not_sent);

			e.preventDefault();
		}, false);
	}
}

var FileCounter = (function (inc, new_value)
{
	var i;

	return function (inc, new_value)
	{
		return (i = (typeof(new_value) == 'number' ? new_value : i + inc));
	}
}());

function DroppedFileUploaded ()
{
	if (this.readyState == 4)
	{
		var s2_status = this.getResponseHeader('X-S2-Status');

		if (s2_status && s2_status != 'Success')
		{
			if (0 == FileCounter(-1))
			{
				SetWait(false);
				if (this.responseText)
					PopupMessages.show(this.responseText);
			}
			return;
		}

		if (this.responseText)
			PopupMessages.show(this.responseText);

		if (0 == FileCounter(-1))
		{
			SetWait(false);
			refreshFiles();
		}
	}
}

function SendDroppedFile (file)
{
	var xhr = new XMLHttpRequest();

	FileCounter(1);
	SetWait(true);

	xhr.onreadystatechange = DroppedFileUploaded;

	var data = new FormData();
	data.append('pictures[]', file);
	data.append('dir', getCurDir());
	data.append('ajax', '1');

	xhr.open('POST', sUrl + 'action=upload');
	xhr.send(data);
}

function SetWait (bWait)
{
	document.body.style.cursor = bWait ? 'progress' : 'default';
}

var was_upload = false;

function UploadSubmit (eForm)
{
	eForm.dir.value = getCurDir();
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

	var body = window.frames['submit_result'].document.body.innerHTML;
	if (body.replace(/^\s\s*/, "").replace(/\s\s*$/, ""))
		PopupMessages.show(body);

	refreshFiles();
	was_upload = false;
}
