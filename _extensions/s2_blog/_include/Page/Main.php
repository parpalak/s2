<?php
/**
 * Main blog page with last posts.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;

use \Lang;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class Page_Main extends Page_HTML implements \Page_Routable
{
    public function body (Request $request): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(s2_link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $params = $request->attributes->all();
		$s2_blog_skip = !empty($params['page']) ? (int) $params['page'] : 0;

		$this->template_id = $s2_blog_skip ? 'blog.php' : 'blog_main.php';

		if ($this->inTemplate('<!-- s2_blog_calendar -->'))
			$this->page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		$this->last_posts($s2_blog_skip);

		// Bread crumbs
		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);
		if (S2_BLOG_URL)
		{
			$this->page['path'][] = array(
				'title' => Lang::get('Blog', 's2_blog'),
				'link' => $s2_blog_skip ? S2_BLOG_PATH : null,
			);
		}

		if ($s2_blog_skip) {
            $this->page['link_navigation']['up'] = S2_BLOG_PATH;
        } else {
            $this->page['meta_description'] = S2_BLOG_TITLE;
            if (S2_BLOG_URL) {
                   $this->page['link_navigation']['up'] = s2_link('/');
            }
        }

        return null;
	}

	private function last_posts ($skip = 0)
	{
		if ($skip < 0)
			$skip = 0;

		$posts_per_page = S2_MAX_ITEMS ? S2_MAX_ITEMS : 10;
		$posts = Lib::last_posts_array($posts_per_page, $skip, true);

		$output = '';
		$i = 0;
		foreach ($posts as $post)
		{
			$i++;
			if ($i > $posts_per_page)
				break;

			$output .= $this->renderPartial('post', $post);
		}

		$paging = '';

		$link_nav = array();
		if ($skip > 0)
		{
			$link_nav['prev'] = S2_BLOG_PATH.($skip > $posts_per_page ? 'skip/'.($skip - $posts_per_page) : '');
			$paging = '<a href="'.$link_nav['prev'].'">'.Lang::get('Here').'</a> ';
			// TODO think about back_forward
		}
		if ($i > $posts_per_page)
		{
			$link_nav['next'] = S2_BLOG_PATH.'skip/'.($skip + $posts_per_page);
			$paging .= '<a href="'.$link_nav['next'].'">'.Lang::get('There').'</a>';
		}

		if ($paging)
			$output .= '<p class="s2_blog_pages">'.$paging.'</p>';

		$this->page['text'] = $output;
		$this->page['link_navigation'] = $link_nav;
	}
}
