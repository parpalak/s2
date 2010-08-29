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
	var hits = new SWFObject("../extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
	hits.addVariable("path", "../extensions/s2_counter/");
	hits.addVariable("settings_file", encodeURIComponent("../extensions/s2_counter/traffic.xml?" + Math.random()));

	(hook = hooks['fn_s2_counter_draw_chart_pre_hits']) ? eval(hook) : null;

	hits.write("s2_counter_hits");

	var rss = new SWFObject("../extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
	rss.addVariable("path", "../extensions/s2_counter/");
	rss.addVariable("settings_file", encodeURIComponent("../extensions/s2_counter/rss.xml?" + Math.random()));

	(hook = hooks['fn_s2_counter_draw_chart_pre_rss']) ? eval(hook) : null;

	rss.write("s2_counter_rss");
}