<?php
/**
 * RSS feed for blog.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_RSS extends \Page_RSS
{
	public function __construct (array $params = array())
	{
		global $lang_s2_blog;

		if (file_exists(__DIR__ . '/../../lang/' . S2_LANGUAGE . '.php'))
			require __DIR__ . '/../../lang/' . S2_LANGUAGE . '.php';
		else
			require __DIR__ . '/../../lang/English.php';

		parent::__construct($params);
	}
	/**
	 * @return array
	 */
	protected function content()
	{
		global $lang_s2_blog;

		$s2_blog_posts = Lib::last_posts_array();
		$s2_blog_items = array();
		foreach ($s2_blog_posts as $s2_blog_post)
			$s2_blog_items[] = array(
				'title'			=> $s2_blog_post['title'],
				'text'			=> $s2_blog_post['text'].(!empty($s2_blog_post['tags']) ? '<p>'.sprintf($lang_s2_blog['Tags:'], $s2_blog_post['tags']).'</p>' : ''),
				'time'			=> $s2_blog_post['create_time'],
				'modify_time'	=> $s2_blog_post['modify_time'],
				'rel_path'		=> str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).date('/Y/m/d/', $s2_blog_post['create_time']).urlencode($s2_blog_post['url']),
				'author'		=> $s2_blog_post['author'],
			);
		return $s2_blog_items;
	}

	/**
	 * @return string
	 */
	protected function title()
	{
		return S2_BLOG_TITLE;
	}

	/**
	 * @return null|string
	 */
	protected function link()
	{
		return s2_abs_link(str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).'/');
	}

	/**
	 * @return string
	 */
	protected function description()
	{
		global $lang_s2_blog;
		return sprintf($lang_s2_blog['RSS description'], S2_BLOG_TITLE);
	}
}
