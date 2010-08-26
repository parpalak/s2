/**
 * Counter
 *
 * Creates chart on the client side
 *
 * @copyright (C) 2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

function s2_counter_draw_chart ()
{
	var so = new SWFObject("../extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
	so.addVariable("path", "../extensions/s2_counter/");
	so.addVariable("settings_file", encodeURIComponent("../extensions/s2_counter/amstock_settings.xml?" + Math.random()));
	so.write("flashcontent");
}