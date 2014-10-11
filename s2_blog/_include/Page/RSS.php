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

		$viewer = new Viewer();
		$posts = Lib::last_posts_array($viewer);
		$items = array();
		foreach ($posts as $post)
		{
			if (!empty($post['tags']))
			{
				foreach ($post['tags'] as &$tag)
					$tag = '<a href="'.$tag['link'].'">'.$tag['title'].'</a>';
				unset($tag);
			}

			$items[] = array(
				'title'       => $post['title'],
				'text'        => $post['text'] . (!empty($post['tags']) ? '<p>' . sprintf($lang_s2_blog['Tags:'], implode(', ', $post['tags'])) . '</p>' : ''),
				'time'        => $post['create_time'],
				'modify_time' => $post['modify_time'],
				'rel_path'    => str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)) . date('/Y/m/d/', $post['create_time']) . urlencode($post['url']),
				'author'      => $post['author'],
			);
		}

		return $items;
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
