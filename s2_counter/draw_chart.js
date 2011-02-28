/**
 * Counter
 *
 * Creates chart on the client side
 *
 * @copyright (C) 2010-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */


var s2_counter = {

	hits: null,
	rss: null,
	draw_chart: function ()
	{
		if (!s2_counter.hits)
		{
			s2_counter.hits = new SWFObject("../_extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
			s2_counter.hits.addVariable("path", "../_extensions/s2_counter/");
			var settings_file = "../_extensions/s2_counter/traffic.xml?" + Math.random();

			(hook = hooks['fn_s2_counter_draw_chart_pre_hits']) ? eval(hook) : null;

			s2_counter.hits.addVariable("settings_file", encodeURIComponent(settings_file));
		}
		s2_counter.hits.write("s2_counter_hits");

		if (!s2_counter.rss)
		{
			s2_counter.rss = new SWFObject("../_extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
			s2_counter.rss.addVariable("path", "../_extensions/s2_counter/");
			settings_file = "../_extensions/s2_counter/rss.xml?" + Math.random();

			(hook = hooks['fn_s2_counter_draw_chart_pre_rss']) ? eval(hook) : null;

			s2_counter.rss.addVariable("settings_file", encodeURIComponent(settings_file));
		}
		s2_counter.rss.write("s2_counter_rss");
	},

}