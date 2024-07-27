/**
 * Picture manager JS functions
 *
 * Drag & drop, event handlers for the picture manager
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license http://opensource.org/licenses/MIT MIT
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

var s2Retina = (function ()
{
	var is_local_storage = false;
	try
	{
		is_local_storage = 'localStorage' in window && window['localStorage'] !== null;
	}
	catch (e)
	{
		is_local_storage = false;
	}

	var is_retina = is_local_storage && !!(localStorage.getItem('s2_use_retina') - 0);

	return {
		'set': function (val)
		{
			is_retina = val;
			if (is_local_storage)
				localStorage.setItem('s2_use_retina', 0 + is_retina);
		},
		'get': function ()
		{
			return is_retina;
		}
	};
}());

var parentWnd = opener || window.top || null,
	fExecDouble = function () {};

$(function ()
{
	$(document).keydown(function (e)
	{
		if (e.which === 27) {
			parentWnd && parentWnd.ClosePictureDialog && parentWnd.ClosePictureDialog();
		}
	});

	var path = '',
		isRenaming = false;

	getCurDir = function ()
	{
		return path;
	};

	function createFolder ()
	{
		folderTree.jstree('create', null, 'first', {data : {title : 'new'}});
	}

	function initContext ()
	{
        $('#context_buttons').click(function (e) {
            if (e.target.id === 'context_add') {
                createFolder();
            } else if (e.target.id === 'context_delete') {
                folderTree.jstree('remove', folderTree.jstree('get_selected'));
            }
        });
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
					if (!d || !d.success)
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
					if (!d || !d.success)
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
					if (!d.success)
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
						if (!d || !d.success)
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

						if (!d || !d.success)
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
						return sUrl + 'action=load_folders' + (node.attr ? '&path=' + encodeURIComponent(node.attr('data-path')) : '');
					}
				}
			},
			crrm : {
				input_width_limit : 1000,
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

					str += "<br />" + s2_lang.color + d.rslt.obj.attr('data-bits');
					str += "<br />" + s2_lang.size + a[0] + "&times;" + a[1];
					str += '<span id="s2_retina_size" style="display: ' + (s2Retina.get() ? 'inline' : 'none') + ';">' + s2_lang.reduction + Math.round(a[0]/2) + "&times;" + Math.round(a[1]/2) + '</span>';

					str += '<br /><label><input type="checkbox" onclick="s2Retina.set(this.checked); $(\'#s2_retina_size\').toggle(); "' + (s2Retina.get() ? ' checked="checked"' : '') + '>' + s2_lang.retina_help + '</label>';

					fExecDouble = function ()
					{
						if (parentWnd.ReturnImage)
							parentWnd.ReturnImage(filePath, s2Retina.get() ? Math.round(a[0]/2) : a[0], s2Retina.get() ? Math.round(a[1]/2) : a[1]);
					};

					str += '<br /><input type="button" class="link-as-button" onclick="fExecDouble(); return false;" value="' + s2_lang.insert + '">';
				}
			}
			else
				fExecDouble = function () {};

			$('#finfo').html(str);
		})
		.bind('rename.jstree', function (e, data)
		{
			isRenaming = false;
			if (data.rslt.new_name === data.rslt.old_name) {
                return;
            }

            fetch(sUrl + 'action=rename_file&name=' + encodeURIComponent(data.rslt.new_name) + '&path=' + encodeURIComponent(path + '/' + data.rslt.obj.attr('data-fname')))
                .then(response => response.json())
                .then(d => {
                    fileTree.jstree('deselect_all');
                    if (!d.success) {
                        fileTree.jstree('refresh', -1);
                        if (d.message) {
                            PopupMessages.show(d.message);
                        }
                    } else {
                        data.rslt.obj.attr('data-fname', d.new_name);
                    }
                })
                .catch(() => {
                    fileTree.jstree('refresh', -1);
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
					if (!d || !d.success)
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
	if (!document.addEventListener)
		return;

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

        document.getElementById('brd').className = 'accept_drag';

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
		if (!dt || !dt.files) {
            return;
        }

		document.getElementById('brd').className = '';

		FileCounter(0, 0);
		var files = dt.files,
            not_sent = '';

		for (var i = files.length; i-- ;) {
            if (files[i].size <= iMaxFileSize) {
                SendDroppedFile(files[i]);
            }
            else {
                not_sent += '<br />' + files[i].name;
            }
        }

		if (not_sent !== '') {
            PopupMessages.show(str_replace('%s', sFriendlyMaxFileSize, s2_lang.files_too_big) + not_sent);
        }

		e.preventDefault();
	}, false);
}

var FileCounter = (function (inc, new_value)
{
	var i;

	return function (inc, new_value)
	{
		return (i = (typeof(new_value) == 'number' ? new_value : i + inc));
	}
}());

function SendDroppedFile(file) {
    var data = new FormData();
    data.append('pictures[]', file);
    data.append('dir', getCurDir());
    data.append('ajax', '1');

    handleFileUpload(data);
}

function handleFileUpload(data, callback) {
    const fileCounter = FileCounter(1);
    console.log('handleFileUpload start', fileCounter);
    SetWait(true);
    fetch(sUrl + 'action=upload', {
        method: 'POST',
        body: data
    })
        .then(response => response.json())
        .then(responseJson => {
            if (!responseJson.success) {
                if (responseJson.errors) {
                    PopupMessages.show(responseJson.errors.join("\n"));
                } else if (responseJson.message) {
                    PopupMessages.show(responseJson.message);
                } else {
                    PopupMessages.show('Unknown error');
                }
            }
            if (callback) {
                callback(responseJson);
            }
        })
        .catch(error => {
            console.error('An error occurred during the upload:', error);
        })
        .finally(() => {
            const fileCounter = FileCounter(-1);
            console.log('handleFileUpload finally', fileCounter);
            if (0 === fileCounter) {
                SetWait(false);
                refreshFiles();
            }
        });
}

function UploadSubmit(eForm) {
    eForm.dir.value = getCurDir();
    const data = new FormData(eForm);

    FileCounter(0, 0);
    handleFileUpload(data, () => {
        eForm['pictures[]'].value = '';
    });
}

function UploadChange (eItem)
{
    let eForm = eItem.form;
    setTimeout(function()
	{
		UploadSubmit(eForm);
	}, 0);
}

function SetWait (bWait)
{
    const eDiv = document.getElementById('loading_pict');
    if (!eDiv) {
        return;
    }
    eDiv.style.display = bWait ? 'block' : 'none';
    document.body.style.cursor = bWait ? 'progress' : 'default';
}
