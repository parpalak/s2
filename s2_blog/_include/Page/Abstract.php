<?php
/**
 * General blog page.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


abstract class Page_Abstract extends \Page_Abstract
{
	public $template_id = 'blog.php';

	public function init()
	{
		global $page, $lang_s2_blog, $s2_blog_fav_link;

		$s2_blog_fav_link = '<a href="'.S2_BLOG_PATH.urlencode(S2_FAVORITE_URL).'/" class="favorite-star" title="'.$lang_s2_blog['Favorite'].'">*</a>';

		if (file_exists(__DIR__ . '/../../lang/' . S2_LANGUAGE . '.php'))
			require __DIR__ . '/../../lang/' . S2_LANGUAGE . '.php';
		else
			require __DIR__ . '/../../lang/English.php';

		$page['commented'] = 0;
	}

	abstract public function body (array $params);

	public function __construct (array $params = array())
	{
		$this->init();
		$this->body($params);
		$this->done();
	}

	public function done()
	{
		global $page, $lang_s2_blog;

		if (isset($page['path']))
			$page['path'] = implode('&nbsp;&rarr; ', $page['path']);
		$page['meta_description'] = S2_BLOG_TITLE;
		$page['head_title'] = empty($page['head_title']) ? S2_BLOG_TITLE : $page['head_title'] . ' - ' . S2_BLOG_TITLE;

		if (strpos($this->template, '<!-- s2_menu -->') !== false)
			$page['menu']['s2_blog_navigation'] = '<div class="header">' . $lang_s2_blog['Navigation'] . '</div>' . Placeholder::blog_navigation();

		define('S2_BLOG_HANDLED', 1);
	}
}
