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
	var hits = new SWFObject("../_extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
	hits.addVariable("path", "../_extensions/s2_counter/");
	var settings_file = "../_extensions/s2_counter/traffic.xml?" + Math.random();

	(hook = hooks['fn_s2_counter_draw_chart_pre_hits']) ? eval(hook) : null;

	hits.addVariable("settings_file", encodeURIComponent(settings_file));
	hits.write("s2_counter_hits");

	var rss = new SWFObject("../_extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
	rss.addVariable("path", "../_extensions/s2_counter/");
	settings_file = "../_extensions/s2_counter/rss.xml?" + Math.random();

	(hook = hooks['fn_s2_counter_draw_chart_pre_rss']) ? eval(hook) : null;

	rss.addVariable("settings_file", encodeURIComponent(settings_file));
	rss.write("s2_counter_rss");
}