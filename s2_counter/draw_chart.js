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
		if (!this.hits)
		{
			this.hits = new SWFObject("../_extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
			this.hits.addVariable("path", "../_extensions/s2_counter/");
			this.hits.addParam("wmode", "opaque");
			var settings_file = "../_extensions/s2_counter/traffic.xml?" + Math.random();

			(hook = Hooks.get('fn_s2_counter_draw_chart_pre_hits')) ? eval(hook) : null;

			this.hits.addVariable("settings_file", encodeURIComponent(settings_file));
		}
		this.hits.write("s2_counter_hits");

		if (!this.rss)
		{
			this.rss = new SWFObject("../_extensions/s2_counter/amstock/amstock.swf", "amstock", "100%", "400px", "8", "#FFFFFF");
			this.rss.addVariable("path", "../_extensions/s2_counter/");
			this.rss.addParam("wmode", "opaque");
			settings_file = "../_extensions/s2_counter/rss.xml?" + Math.random();

			(hook = Hooks.get('fn_s2_counter_draw_chart_pre_rss')) ? eval(hook) : null;

			this.rss.addVariable("settings_file", encodeURIComponent(settings_file));
		}
		this.rss.write("s2_counter_rss");
	},

}