/**
 * WYSIWYG
 *
 * TinyMCE initialization and helper functions.
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_wysiwyg
 */

var s2_wysiwyg_params = [

// "Advanced" editor
{
	mode: "exact",
	elements : "arttext",
	theme : "advanced",
	skin : "s2",
	gecko_spellcheck : true,
	convert_fonts_to_spans : true,
	language : "en",
	plugins : "table,preview,searchreplace,paste,fullscreen,visualchars,nonbreaking,save,media",
	theme_advanced_buttons1 : 'newdocument,save,|,cut,copy,paste,pastetext,pasteword,|,undo,redo,|,link,unlink,anchor,|,image,media,hr,|,charmap,nonbreaking,visualchars,|,cleanup,removeformat',
	theme_advanced_buttons2 : "formatselect,|,bold,italic,underline,strikethrough,|,sup,sub,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,outdent,indent,|,blockquote,|,forecolor,backcolor",
	theme_advanced_buttons3 : "visualaid,tablecontrols,|,search,replace,|,fullscreen,code,preview",
	theme_advanced_toolbar_location : "top",
	theme_advanced_toolbar_align : "left",
	theme_advanced_path_location : "bottom",
	theme_advanced_blockformats : "p,h2,h3,h4,pre,code",
	extended_valid_elements : "hr[class|width|size|noshade],br[clear],span[class|align|style],small,big,code[class],samp,kbd,s",
	file_browser_callback : "s2_wysiwyg_filebrowser_callback",
	remove_trailing_nbsp : true,
	relative_urls : false,
	remove_script_host : true
},

// "Simplified" editor
{
	mode: "exact",
	elements : "arttext",
	theme : "advanced",
	skin : "s2",
	gecko_spellcheck : true,
	convert_fonts_to_spans : true,
	language : "en",
	plugins : "save",
	theme_advanced_buttons1 : 'newdocument,save,|,undo,redo,|,bold,italic,|,link,unlink,image,|,formatselect,blockquote,bullist,numlist,',
	theme_advanced_buttons2 : '',
	theme_advanced_buttons3 : '',
	theme_advanced_toolbar_location : "top",
	theme_advanced_toolbar_align : "left",
	theme_advanced_path_location : "bottom",
	theme_advanced_blockformats : "p,h2,h3,h4,pre",
	extended_valid_elements : "hr[class|width|size|noshade],br[clear],span[class|align|style],small,big,code[class],samp,kbd,s",
	file_browser_callback : "s2_wysiwyg_filebrowser_callback",
	remove_trailing_nbsp : true,
	relative_urls : false,
	remove_script_host : true
}
];

tinyMCE.init(s2_wysiwyg_params[s2_wysiwyg_type]);

function s2_wysiwyg_set_path (s)
{
	tinyMCE.settings.document_base_url = s;
}

var s2_wysiwyg_wFileBrowser = s2_wysiwyg_wImage = null;

function s2_wysiwyg_filebrowser_callback (field_name, url, type, win)
{
	if (type != "image")
		return;
	s2_wysiwyg_wImage = win;
	s2_wysiwyg_wFileBrowser = window.open(s2_wysiwyg_pict_url, 's2_wysiwyg_imagewindow', 'scrollbars=yes,toolbar=yes,width=750,height=500', 'True');
}

function ReturnImage (s, w, h)
{
	s2_wysiwyg_wFileBrowser.close();

	if (!s2_wysiwyg_wImage || s2_wysiwyg_wImage.closed)
		return;

	s2_wysiwyg_wImage.document.forms[0].elements['src'].value = s;
	s2_wysiwyg_wImage.document.forms[0].elements['width'].value = w;
	s2_wysiwyg_wImage.document.forms[0].elements['height'].value = h;
}

add_hook('fn_tab_switch_start', 'if (sType == "view_tab") tinyMCE.triggerSave();');
add_hook('fn_is_changed', 'tinyMCE.triggerSave();');
