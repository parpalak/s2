/**
 * Loading and saving templates
 *
 * @copyright (C) 2012-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

var s2_tpl_edit = (function ()
{
	var instance = null;

	function detect_mode (filename)
	{
		switch (filename.split('.').pop())
		{
			case 'css':
				return 'text/css';
			case 'js':
				return 'text/javascript';
		}
		return 'application/x-httpd-php';
	}

	function name_change ()
	{
		instance.setOption("mode", detect_mode($(this).val()));
	}

	$(function ()
	{
		if (typeof CodeMirror != 'undefined')
		{
			var frm = document.forms['s2_tpl_edit_form'].elements;

			instance = CodeMirror.fromTextArea(frm['template[text]'],
				{mode: detect_mode(''), indentUnit: 4, indentWithTabs: true, lineWrapping: true});

			$(frm['template[filename]']).change(name_change);
		}

		$('#s2_tpl_edit_file_list').on('dragstart', function(e)
		{
			var link = $(e.target).attr('data-copy');
			if (link)
				e.originalEvent.dataTransfer.setData('text/plain', link);
		});
	});

	return (
	{
		load: function (s)
		{
			GETAsyncRequest(sUrl + 'action=s2_tpl_edit_load&filename=' + encodeURIComponent(s), function (http, data)
			{
				var frm = document.forms['s2_tpl_edit_form'].elements;
				frm['template[filename]'].value = data.filename;
				frm['template[text]'].value = data.text;
				$('#s2_tpl_edit_file_list').html(data.menu);

				if (instance)
				{
					instance.setValue(data.text);
					instance.setOption("mode", detect_mode(data.filename));
				}
			});
			return false;
		},

		save: function (sMessage, frm)
		{
			if (!/^[0-9a-zA-Z\._\-]+$/.test(frm.elements['template[filename]'].value))
			{
				PopupMessages.showUnique(sMessage, 's2_tpl_edit_wrong_filename');
				return false;
			}

			if (instance)
				instance.save();

			POSTAsyncRequest(sUrl + 'action=s2_tpl_edit_save', $(frm).serialize(), function (http, data)
			{
				$('#s2_tpl_edit_file_list').html(data);
			});
			return false;
		}
	});

}());
