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
		$return = ($hook = s2_hook('pr_render_start')) ? eval($hook) : null;
		if ($return)
			return;

		$content = array(
			'rss_title'       => $this->title(),
			'rss_link'        => s2_abs_link($this->link()),
			'self_link'       => $this->url,
			'rss_description' => $this->description(),
		);

		$last_date = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;

		$max_time = 0;
		$items = '';

		($hook = s2_hook('pr_render_pre_get_content')) ? eval($hook) : null;

		foreach ($this->content() as $item)
		{
			if (max($item['modify_time'], $item['time']) <= $last_date)
				continue;

			$max_time = max($max_time, $item['modify_time'], $item['time']);

			// Fixing URLs without a domain
			$item['text'] = str_replace('href="'.S2_PATH.'/', 'href="'.S2_BASE_URL.'/', $item['text']);
			$item['text'] = str_replace('src="'.S2_PATH.'/', 'src="'.S2_BASE_URL.'/', $item['text']);

			if (empty($item['author']) && S2_WEBMASTER)
				$item['author'] = S2_WEBMASTER;

			$item['link'] = s2_abs_link($item['rel_path']);

			($hook = s2_hook('pr_render_pre_item_render')) ? eval($hook) : null;

			$items .= $this->renderPartial('rss_item', $item);
		}

        /** @var ?\DBLayer_Abstract $s2_db */
        $s2_db = \Container::getIfInstantiated('db');
        if ($s2_db) {
            $s2_db->close();
        }

		if (!$items && $last_date)
		{
			($hook = s2_hook('pr_render_pre_not_modified')) ? eval($hook) : null;

			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
			exit;
		}

		$output = $this->renderPartial('rss', $content + compact('items', 'max_time'));

		($hook = s2_hook('pr_render_output_end')) ? eval($hook) : null;

		ob_start();

		if (S2_COMPRESS)
			ob_start('ob_gzhandler');

		echo $output;

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
		return '/';
	}

	/**
	 * @return string
	 */
	protected function description()
	{
		return sprintf(Lang::get('RSS description'), S2_SITE_NAME);
	}
}
