/**
 * Loading and saving templates
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

var s2_tpl_edit = (function ()
{
	var instance, scrolltop = null;

	return (
	{
		load: function (sFilename)
		{
			GETAsyncRequest(sUrl + 'action=s2_tpl_edit_load&filename=' + encodeURIComponent(sFilename), function (http)
			{
				document.getElementById('s2_tpl_edit_div').innerHTML = http.responseText;
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

			var sRequest = StringFromForm(document.forms['s2_tpl_edit_form']);
			POSTAsyncRequest(sUrl + 'action=s2_tpl_edit_save', sRequest, function (http)
			{
				document.getElementById('s2_tpl_edit_div').innerHTML = http.responseText;
			});
			return false;
		},

		key_handler: function ()
		{
			if (document.forms['s2_tpl_edit_form'] && '#admin-tpl' == cur_page)
				s2_tpl_edit.save();
		}
	});

}());

Hooks.add('fn_save_handler_start', 's2_tpl_edit.key_handler();');
