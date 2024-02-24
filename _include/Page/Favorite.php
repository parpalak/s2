<?php

use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;

/**
 * Displays the list of favorite pages and excerpts.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */


class Page_Favorite extends Page_HTML implements Page_Routable
{
    public function render(Request $request): void
    {
        if ($request->attributes->get('slash') !== '/') {
            s2_permanent_redirect($request->getPathInfo() . '/');
        }
        $this->page = $this->make_favorite_page() + $this->page;
        parent::render($request);
    }

    //
	// Builds favorite page
	//
	private function make_favorite_page ()
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->build($subquery);

		$sort_order = SORT_DESC; // SORT_ASC also possible
		$query = array(
			'SELECT'	=> 'a.title, a.url, ('.$raw_query1.') IS NOT NULL AS children_exist, a.id, a.excerpt, a.create_time, a.parent_id',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'a.favorite = 1 AND a.published = 1'
		);
		($hook = s2_hook('fn_s2_make_favorite_page_pre_get_arts_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$urls = $parent_ids = $rows = array();
		while ($row = $s2_db->fetchAssoc($result))
		{
			$rows[] = $row;
			$urls[] = urlencode($row['url']);
			$parent_ids[] = $row['parent_id'];
		}

		$urls = Model::get_group_url($parent_ids, $urls);

		$sections = $articles = $articles_sort_array = $sections_sort_array = array();
		foreach ($urls as $k => $url)
		{
			$row = $rows[$k];
			if ($row['children_exist'])
			{
				$item = array(
					'id'       => $row['id'],
					'title'    => $row['title'],
					'link'     => s2_link($url.(S2_USE_HIERARCHY ? '/' : '')),
					'date'     => s2_date($row['create_time']),
					'excerpt'  => $row['excerpt'],
					'favorite' => 2,
				);
				$sort_field = $row['create_time'];

				($hook = s2_hook('pf_make_favorite_page_add_section')) ? eval($hook) : null;

				$sections[] = $item;
				$sections_sort_array[] = $sort_field;
			}
			else
			{
				$item = array(
					'id'       => $row['id'],
					'title'    => $row['title'],
					'link'     => s2_link($url),
					'date'     => s2_date($row['create_time']),
					'excerpt'  => $row['excerpt'],
					'favorite' => 2,
				);
				$sort_field = $row['create_time'];

				($hook = s2_hook('pf_make_favorite_page_add_article')) ? eval($hook) : null;

				$articles[] = $item;
				$articles_sort_array[] = $sort_field;
			}
		}

		($hook = s2_hook('fn_s2_make_favorite_page_pre_merge')) ? eval($hook) : null;

		// There are favorite sections
		$section_text = '';
		if (!empty($sections))
		{
			// There are sections having the tag
			array_multisort($sections_sort_array, $sort_order, $sections);
			foreach ($sections as $item)
				$section_text .= $this->renderPartial('subarticles_item', $item);
		}

		$article_text = '';
		if (!empty($articles))
		{
			// There are favorite articles
			array_multisort($articles_sort_array, $sort_order, $articles);
			foreach ($articles as $item)
				$article_text .= $this->renderPartial('subarticles_item', $item);
		}

		$page = array(
			'path'  => array(
				array(
					'title' => Model::main_page_title(),
					'link'  => s2_link('/'),
				),
				array(
					'title' => Lang::get('Favorite'),
				),
			),
			'title' => Lang::get('Favorite'),
			'date'  => '',
			'text'  => $this->renderPartial('list_text', array(
				'articles' => $article_text,
				'sections' => $section_text,
			)),
		);

		($hook = s2_hook('fn_s2_make_favorite_page_end')) ? eval($hook) : null;

		return $page;
	}
}
