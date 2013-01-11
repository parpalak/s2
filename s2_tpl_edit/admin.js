/**
 * Loading and saving templates
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

var s2_tpl_edit = (function ()
{
	var instance = null;

	return (
	{
		render: function (data)
		{
			$('#s2_tpl_edit_div').html(data);
			if (typeof CodeMirror != 'undefined')
			{
				var frm = document.forms['s2_tpl_edit_form'].elements,
					filename = $(frm['template[filename]'])
						.change(function () { s2_tpl_edit.update_style($(this).val()) })
						.val();

				instance = CodeMirror.fromTextArea(frm['template[text]'],
					{mode: s2_tpl_edit.detect_mode(filename), indentUnit: 4, indentWithTabs: true, lineWrapping: true});
			}
		},

		detect_mode: function (filename)
		{
			switch (filename.split('.').pop())
			{
				case 'css':
					return 'text/css';
				case 'js':
					return 'text/javascript';
			}
			return 'application/x-httpd-php';
		},

		update_style: function (filename)
		{
			instance.setOption("mode", s2_tpl_edit.detect_mode(filename));
		},

		load: function (s)
		{
			GETAsyncRequest(sUrl + 'action=s2_tpl_edit_load&filename=' + encodeURIComponent(s), function (http, data)
			{
				s2_tpl_edit.render(data);
			});
			return false;
		},

		save: function (sMessage)
		{
			var frm = document.forms['s2_tpl_edit_form'];
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
