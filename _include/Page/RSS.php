<?php
/**
 * Creates RSS feeds.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_RSS extends Page_Abstract implements Page_Routable
{
	private $url = '';
	public function __construct (array $params = array())
	{
		$this->url = $params['url'];
		parent::__construct();
	}

	public function render ()
	{
		$return = ($hook = s2_hook('fn_do_rss_start')) ? eval($hook) : null;
		if ($return)
			return;

		ob_start();
		if (S2_COMPRESS)
			ob_start('ob_gzhandler');

		$rss_title = $this->title();
		$rss_link = $this->link();
		$self_link = $this->url;
		$rss_description = $this->description();

		($hook = s2_hook('fn_do_rss_pre_output')) ? eval($hook) : null;

		echo '<?xml version="1.0" encoding="utf-8"?>'."\n".
			'<?xml-stylesheet href="http://www.w3.org/2000/08/w3c-synd/style.css" type="text/css"?>'."\n";

?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title><?php echo s2_htmlencode($rss_title); ?></title>
		<link><?php echo $rss_link; ?></link>
		<description><?php echo s2_htmlencode($rss_description); ?></description>
		<generator>S2 <?php echo S2_VERSION; ?></generator>
		<ttl>10</ttl>
		<atom:link href="<?php echo s2_abs_link($self_link); ?>" rel="self" type="application/rss+xml" />
<?php

		$last_date = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;

		$max_time = 0;
		$items = '';

		$articles = $this->content();

		foreach ($articles as $item)
		{
			if (max($item['modify_time'], $item['time']) <= $last_date)
				continue;

			$max_time = max($max_time, $item['modify_time'], $item['time']);

			// Fixing URLs without a domain
			$item['text'] = str_replace('href="'.S2_PATH.'/', 'href="'.S2_BASE_URL.'/', $item['text']);
			$item['text'] = str_replace('src="'.S2_PATH.'/', 'src="'.S2_BASE_URL.'/', $item['text']);

			if (empty($item['author']) && S2_WEBMASTER)
				$item['author'] = S2_WEBMASTER;

			($hook = s2_hook('fn_do_rss_loop_pre_output')) ? eval($hook) : null;

			ob_start();

?>
		<item>
			<title><?php echo s2_htmlencode(s2_htmlencode($item['title'])); ?></title>
			<link><?php echo s2_abs_link($item['rel_path']); ?></link>
			<description><?php echo s2_htmlencode($item['text']); ?></description>
<?php

			if (!empty($item['author']))
			{

?>
			<author><?php echo s2_htmlencode($item['author']); ?></author>
<?php

			}

?>
			<guid isPermaLink="true"><?php echo s2_abs_link($item['rel_path']); ?></guid>
			<pubDate><?php echo gmdate('D, d M Y H:i:s', $item['time']).' GMT'; ?></pubDate>
			<comments><?php echo s2_abs_link($item['rel_path']).'#comment'; ?></comments>
		</item>
<?php
			$items .= ob_get_clean();
		}
?>
		<lastBuildDate><?php echo gmdate('D, d M Y H:i:s', $max_time).' GMT'; ?></lastBuildDate>
<?php echo $items; ?>
	</channel>
</rss>
<?php

		global $s2_db;
		$s2_db->close();

		if (!$items && $last_date)
		{
			($hook = s2_hook('fn_do_rss_pre_not_modified')) ? eval($hook) : null;

			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');

			ob_end_clean();
			if (S2_COMPRESS)
				ob_end_clean();
			exit;
		}

		($hook = s2_hook('fn_do_rss_output_end')) ? eval($hook) : null;

		if (S2_COMPRESS)
			ob_end_flush();

		header('Content-Length: '.ob_get_length());
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $max_time).' GMT');
		header('Content-Type: text/xml; charset=utf-8');

		ob_end_flush();
	}

	/**
	 * @return array
	 */
	protected function content()
	{
		return Placeholder::last_articles_array(10);
	}

	/**
	 * @return string
	 */
	protected function title()
	{
		return S2_SITE_NAME;
	}

	/**
	 * @return null|string
	 */
	protected function link()
	{
		return s2_abs_link('/');
	}

	/**
	 * @return string
	 */
	protected function description()
	{
		global $lang_common;
		return sprintf($lang_common['RSS description'], S2_SITE_NAME);
	}
}
 