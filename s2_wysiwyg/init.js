/**
 * WYSIWYG
 *
 * TinyMCE initialization and helper functions.
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_wysiwyg
 */


tinyMCE.init({
	mode: "exact",
	elements : "arttext",
	theme : "advanced",
	skin : "s2",
	gecko_spellcheck : true,
	convert_fonts_to_spans : true,
	language : "en",
	plugins : "table,preview,searchreplace,paste,fullscreen,visualchars,nonbreaking,save",
	theme_advanced_buttons1 : 'newdocument,save,|,cut,copy,paste,pastetext,pasteword,|,undo,redo,|,link,unlink,anchor,|,image,hr,nonbreaking,|,visualchars,cleanup,|,charmap,removeformat',
	theme_advanced_buttons2 : "formatselect,|,bold,italic,underline,strikethrough,|,sup,sub,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,outdent,indent,|,blockquote,|,forecolor,backcolor",
	theme_advanced_buttons3 : "visualaid,tablecontrols,|,search,replace,|,fullscreen,code,preview",
	theme_advanced_toolbar_location : "top",
	theme_advanced_toolbar_align : "left",
	theme_advanced_path_location : "bottom",
	theme_advanced_blockformats : "p,h2,h3,h4,pre",
	extended_valid_elements : "hr[class|width|size|noshade],br[clear],span[class|align|style],small,big,code[class],samp,kbd,s",
	file_browser_callback : "fileBrowserCallBack",
	remove_trailing_nbsp : true,
	relative_urls : false,
	remove_script_host : true
});

function SetDocumentPath (s)
{
	tinyMCE.settings.document_base_url = s;
}

var wFileBrowser = wImage = null;

function fileBrowserCallBack(field_name, url, type, win)
{
	if (type != "image")
		return;
	wImage = win;
	wFileBrowser = window.open(sPictUrl, 's2_wysiwyg_imagewindow', 'scrollbars=yes,toolbar=yes,width=750,height=500', 'True');
}

function ReturnImage(s, w, h)
{
	wFileBrowser.close();

	if (!wImage || wImage.closed)
		return;

	wImage.document.forms[0].elements['src'].value = s;
	wImage.document.forms[0].elements['width'].value = w;
	wImage.document.forms[0].elements['height'].value = h;
}

add_hook('fn_tab_switch_start', 'if (sType == "view_tab") tinyMCE.triggerSave();');
add_hook('fn_is_changed', 'tinyMCE.triggerSave();');
