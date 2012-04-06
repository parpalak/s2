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

$(document).ready(function()
{
	var path = '',
		isRenaming = false;

	function createFolder ()
	{
		folderTree.jstree('create', null, 'first', {data : {title : 'new'}});
	}

	function initContext ()
	{
		$('#context_add').click(createFolder);
		$('#context_delete').click(function () {folderTree.jstree('remove', folderTree.jstree('get_selected'));});
	}

	Init();

	var eButtons = $('<span><img src="i/1.gif" id="context_add" alt="' + s2_lang.create_subfolder + '" /><img src="i/1.gif" id="context_delete" alt="' + s2_lang.delete_folder + '" /></span>').attr('id', 'context_buttons');
	$('body').append(eButtons);
	initContext();
	eButtons.detach();

	function rollback (data)
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
			//console.log(data.func);
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
				url : sUrl + 'action=delete_folder&path=' + encodeURIComponent(data.rslt.obj.attr('data-path')),
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
				url : sUrl + 'action=create_subfolder&name=' + encodeURIComponent(data.rslt.name) + '&path=' + encodeURIComponent(data.rslt.parent.attr('data-path')),
				success : function (d)
				{
					if (!d.status)
						rollback(data.rlbk);
					else
					{
						data.rslt.obj.attr('data-path', d.path);
						folderTree.jstree('rename_node', data.rslt.obj, d.name);
					}
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
				'n' : function () { createFolder(); return false; }
			},
			json_data : {
				ajax : { url : sUrl + 'action=load_tree' }
			},
			core : {
				animation : 150,
				initially_open : ['node_1'],
				progressive_render : true,
				open_parents : false
			},
			plugins : ['json_data', 'dnd', 'ui', 'crrm', 'hotkeys']
		});

	function removeFiles ()
	{
		fileTree.jstree('remove');
	}

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
				if (!confirm(str_replace('%s', names.join(', '), s2_lang.delete_file)))
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
			fExecDouble = function () {};
			var str = '';

			if (fileTree.jstree('get_selected').length == 1)
			{
				str = s2_lang.file + sPicturePrefix + d.rslt.obj.attr('data-fname');
				if (d.rslt.obj.attr('data-fsize'))
					str += "<br />" + s2_lang.value + d.rslt.obj.attr('data-fsize');
				if (d.rslt.obj.attr('data-dim'))
				{
					var a = d.rslt.obj.attr('data-dim').split('*');
					str += "<br />" + s2_lang.size + a[0] + "&times;" + a[1];
					a[2] = sPicturePrefix + d.rslt.obj.attr('data-fname');
					fExecDouble = function ()
					{
						if (window.top.ReturnImage)
							window.top.ReturnImage(a[2], a[0],  a[1]);
						else if (opener.ReturnImage)
							opener.ReturnImage(a[2], a[0],  a[1]);
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
				url : sUrl + 'action=rename_file&name=' + encodeURIComponent(data.rslt.new_name) + '&path=' + encodeURIComponent(data.rslt.obj.attr('data-fname')),
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
				url : sUrl + 'action=delete_files&' + fileNames.join('&'),
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
/* 		.bind('move_node.jstree', function (e, data)
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
		}) */
		.jstree({
			ui : {
				select_limit : -1
			},
			hotkeys : {
				'del' : removeFiles
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
							sCurDir = path;
							$('#loadstatus').text('');
							return data;
						}
						$('#loadstatus').text(data.message ? data.message : 'Unknown error');
						return false;
					}
				}
			},
/* 			core : {
				animation : 0,
				progressive_render : true,
				open_parents : false
			},
 */			plugins : ['json_data', 'dnd', 'ui', 'crrm', 'hotkeys']
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
.ajaxStart(function (event, XMLHttpRequest, ajaxOptions)
{
	SetWait(true);
})
.ajaxStop(function (event, XMLHttpRequest, ajaxOptions)
{
	SetWait(false);
});

function str_replace(substr, newsubstr, str)
{
	while (str.indexOf(substr) >= 0)
		str = str.replace(substr, newsubstr);
	return str;
}

function Init ()
{
	if (document.addEventListener)
	{
		document.getElementById('brd').addEventListener('dragover', function (e)
		{
			e.preventDefault();
		}, false);

		document.getElementById('brd').addEventListener('dragenter', function (e)
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

		document.getElementById('brd').addEventListener('dragleave', function (e)
		{
			document.getElementById('brd').className = '';
			e.preventDefault();
		}, false);
		document.getElementById('brd').addEventListener('drop', function (e)
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
	data.append('dir', sCurDir);
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

	var body = window.frames['submit_result'].document.body.innerHTML;
	if (body.replace(/^\s\s*/, "").replace(/\s\s*$/, ""))
		PopupMessages.show(body);

	refreshFiles();
	was_upload = false;
}
