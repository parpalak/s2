/**
 * Helper functions for the page blocks extension
 *
 * @copyright (C) 2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_attachment
 */

var s2_page_blocks_name = false;
function s2_page_blocks_choose_file (sName)
{
	s2_page_blocks_name = sName;
	selectTab('#pict_tab');
	loadPictman();
}

$(document)
	.on('pagetext_image_start.s2,s2_wysiwyg_pictman_call.s2', function () { s2_page_blocks_name = false; console.log('erlkl;w'); });

Hooks.add('fn_return_image_start', function (data)
{
	if (!s2_page_blocks_name)
		return false;

	var s = data.s;
	s = encodeURI(s).
		replace(/&/g, '&amp;').
		replace(/</g, '&lt;').
		replace(/>/g, '&gt;').
		replace(/'/g, '&#039;').
		replace(/"/g, '&quot;');

	selectTab('#edit_tab');

	document.getElementsByName(s2_page_blocks_name)[0].value = '<img src="' + s + '" width="' + data.w + '" height="' + data.h +'" ' + 'alt="" />';

	return true;
}, true);
