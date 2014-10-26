<?php
/**
 * RSS feed for blog.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use \Lang;


class Page_RSS extends \Page_RSS
{
	public function __construct (array $params = array())
	{
		Lang::load('s2_blog', function ()
		{
			if (file_exists(__DIR__ . '/../../lang/' . S2_LANGUAGE . '.php'))
				return require __DIR__ . '/../../lang/' . S2_LANGUAGE . '.php';
			else
				return require __DIR__ . '/../../lang/English.php';
		});

		parent::__construct($params);
	}
	/**
	 * @return array
	 */
	protected function content()
	{
		$posts = Lib::last_posts_array();
		$viewer = new \Viewer();
		$items = array();
		foreach ($posts as $post)
		{
			$items[] = array(
				'title'       => $post['title'],
				'text'        => $post['text'] .
					(empty($post['see_also']) ? '' : $viewer->render('see_also', array(
						'see_also' => $post['see_also']
					))) .
					(empty($post['tags']) ? '' : $viewer->render('tags', array(
						'title' => Lang::get('Tags:', 's2_blog'),
						'tags'  => $post['tags'],
					))),
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
		return str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).'/';
	}

	/**
	 * @return string
	 */
	protected function description()
	{
		return sprintf(Lang::get('RSS description', 's2_blog'), S2_BLOG_TITLE);
	}
}
