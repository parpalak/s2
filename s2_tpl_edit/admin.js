/**
 * Loading and saving templates
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

var s2_tpl_edit = (function ()
{
	return (
	{
		load: function (sFilename)
		{
			GETAsyncRequest(sUrl + 'action=s2_tpl_edit_load&filename=' + encodeURIComponent(sFilename), function (http, data)
			{
				$('#s2_tpl_edit_div').html(data);
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

			POSTAsyncRequest(sUrl + 'action=s2_tpl_edit_save', $(document.forms['s2_tpl_edit_form']).serialize(), function (http, data)
			{
				$('#s2_tpl_edit_div').html(data);
			});
			return false;
		}
	});

}());
