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
			var eDiv = $('#s2_tpl_edit_div').html(data);
			if (CodeMirror)
				instance = CodeMirror.fromTextArea(eDiv.find('textarea')[0],
					{mode: "application/x-httpd-php", smartIndent: false, indentUnit: 4, indentWithTabs: true, lineWrapping: true});
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
			if (!/^[0-9a-zA-Z\._\-]+$/.test(document.forms['s2_tpl_edit_form'].elements['template[filename]'].value))
			{
				PopupMessages.showUnique(sMessage, 's2_tpl_edit_wrong_filename');
				return false;
			}

			if (instance)
			{
				instance.toTextArea();
				instance = null;
			}

			POSTAsyncRequest(sUrl + 'action=s2_tpl_edit_save', $(document.forms['s2_tpl_edit_form']).serialize(), function (http, data)
			{
				s2_tpl_edit.render(data);
			});
			return false;
		}
	});

}());
