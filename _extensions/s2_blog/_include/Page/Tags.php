<?php
/**
 * List of blog tags.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;

use \Lang;
use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class Page_Tags extends Page_HTML implements \Page_Routable
{
    public function handle(Request $request): ?Response
    {
        // Disable this block at tags page since it does not make any sense.
        $this->page['s2_blog_navigation'] = '';

        return parent::handle($request);
    }


    public function body (Request $request): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse(s2_link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

		if ($this->hasPlaceholder('<!-- s2_blog_calendar -->'))
			$this->page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		// The list of tags
		$this->all_tags();

		// Bread crumbs
		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);
		if (S2_BLOG_URL)
		{
			$this->page['path'][] = array(
				'title' => Lang::get('Blog', 's2_blog'),
				'link' => S2_BLOG_PATH,
			);
		}

		$this->page['path'][] = array(
			'title' => Lang::get('Tags'),
		);

		$this->page['head_title'] = $this->page['title'] = Lang::get('Tags');
		$this->page['link_navigation']['up'] = S2_BLOG_PATH;

        return null;
	}

	private function all_tags ()
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$query = array(
			'SELECT'	=> 'tag_id, name, url',
			'FROM'		=> 'tags'
		);
		($hook = s2_hook('fn_s2_blog_all_tags_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		while ($row = $s2_db->fetchAssoc($result))
		{
			$tag_name[$row['tag_id']] = $row['name'];
			$tag_url[$row['tag_id']] = $row['url'];
			$tag_count[$row['tag_id']] = 0;
		}

		$query = array(
			'SELECT'	=> 'pt.tag_id',
			'FROM'		=> 's2_blog_post_tag AS pt',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_posts AS p',
					'ON'			=> 'p.id = pt.post_id'
				)
			),
			'WHERE'		=> 'p.published = 1'
		);
		($hook = s2_hook('fn_s2_blog_all_tags_pre_get_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		while ($row = $s2_db->fetchRow($result))
			$tag_count[$row[0]] = isset($tag_count[$row[0]]) ? $tag_count[$row[0]] + 1 : 1;

		arsort($tag_count);

		$tags = array();
		foreach ($tag_count as $id => $num)
			if ($num)
				$tags[] = array(
					'title' => $tag_name[$id],
					'link'  => S2_BLOG_TAGS_PATH.urlencode($tag_url[$id]).'/',
					'num'   => $num,
				);

		($hook = s2_hook('fn_s2_blog_all_tags_end')) ? eval($hook) : null;

		$this->page['text'] = $this->renderPartial('tags_list', array('tags' => $tags));
	}
}
