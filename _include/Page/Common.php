<?php

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Rose\Entity\ExternalId;

/**
 * Displays a page stored in DB.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Common extends Page_HTML implements Page_Routable
{
	public function __construct (array $params = array())
	{
		parent::__construct($params);
		$this->parse_page_url($params['request_uri']);
	}

	private function tagged_articles ($id)
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$query = array(
			'SELECT'	=> 't.tag_id as tag_id, name, t.url as url',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS atg',
					'ON'			=> 'atg.tag_id = t.tag_id'
				)
			),
			'WHERE'		=> 'atg.article_id = '.$id
		);
		($hook = s2_hook('fn_tagged_articles_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$tag_names = $tag_urls = array();
		while ($row = $s2_db->fetchAssoc($result))
		{
			($hook = s2_hook('fn_tagged_articles_loop_get_tags')) ? eval($hook) : null;

			$tag_names[$row['tag_id']] = $row['name'];
			$tag_urls[$row['tag_id']] = $row['url'];
		}

		if (empty($tag_urls))
			return '';

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = atg.article_id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->build($subquery);

		$query = array(
			'SELECT'	=> 'title, tag_id, parent_id, url, a.id AS id, ('.$raw_query1.') IS NOT NULL AS children_exist',
			'FROM'		=> 'articles AS a',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS atg',
					'ON'			=> 'a.id = atg.article_id'
				),
			),
			'WHERE'		=> 'atg.tag_id IN ('.implode(', ', array_keys($tag_names)).') AND a.published = 1'
//		'ORDER BY'	=> 'create_time'  // no temp table is created but order by ID is almost the same
		);
		($hook = s2_hook('fn_tagged_articles_pre_get_articles_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		// Build article lists that have the same tags as our article

		$create_tag_list = false;

		$titles = $parent_ids = $urls = $tag_ids = $original_ids = array();
		while ($row = $s2_db->fetchAssoc($result))
		{
			($hook = s2_hook('fn_tagged_articles_get_articles_loop')) ? eval($hook) : null;

			if ($id <> $row['id'])
				$create_tag_list = true;
			$titles[] = $row['title'];
			$parent_ids[] = $row['parent_id'];
			$urls[] = urlencode($row['url']).(S2_USE_HIERARCHY && $row['children_exist'] ? '/' : '');
			$tag_ids[] = $row['tag_id'];
			$original_ids[] = $row['id'];
		}

		if (empty($urls))
			return '';

		if ($create_tag_list)
			$urls = Model::get_group_url($parent_ids, $urls);

		// Sorting all obtained article links into groups by each tag
		$art_by_tags = array();

		foreach ($urls as $k => $url)
			$art_by_tags[$tag_ids[$k]][] = array(
				'title'      => $titles[$k],
				'link'       => $url,
				'is_current' => $original_ids[$k] == $id,
			);

		($hook = s2_hook('fn_tagged_articles_pre_art_by_tags_merge')) ? eval($hook) : null;

		// Remove tags that have only one article
		foreach ($art_by_tags as $tag_id => $title_array)
			if (count($title_array) <= 1)
				unset($art_by_tags[$tag_id]);

		$output = array();
		($hook = s2_hook('fn_tagged_articles_pre_menu_merge')) ? eval($hook) : null;
		foreach ($art_by_tags as $tag_id => $articles)
			$output[] = $this->renderPartial('menu_block', array(
				'title' => sprintf(Lang::get('With this tag'), '<a href="'.s2_link('/'.S2_TAGS_URL.'/'.urlencode($tag_urls[$tag_id]).'/').'">'.$tag_names[$tag_id].'</a>'),
				'menu'  => $articles,
				'class' => 'article_tags',
			));

		($hook = s2_hook('fn_tagged_articles_end')) ? eval($hook) : null;
		return !empty($output) ? implode("\n", $output) : '';
	}

	private function get_tags ($id)
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$query = array(
			'SELECT'	=> 'name, url',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS at',
					'ON'			=> 'at.tag_id = t.tag_id'
				)
			),
			'WHERE'		=> 'at.article_id = '.$id
		);
		($hook = s2_hook('fn_tags_list_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$tags = array();
		while ($row = $s2_db->fetchAssoc($result))
			$tags[] = array(
				'title' => $row['name'],
				'link'  => s2_link('/'.S2_TAGS_URL.'/'.urlencode($row['url']).'/'),
			);

		if (empty($tags))
			return '';

		return $this->renderPartial('tags', array(
			'title' => Lang::get('Tags'),
			'tags'  => $tags,
		));
	}


	// Processes site pages
	private function parse_page_url ($request_uri)
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$page = &$this->page;

		$request_array = explode('/', $request_uri);   //   []/[dir1]/[dir2]/[dir3]/[file1]

		// Correcting trailing slash and the rest of URL
		if (!S2_USE_HIERARCHY && count($request_array) > 2)
			s2_permanent_redirect('/'.$request_array[1]);

		$was_end_slash = '/' == substr($request_uri, -1);

		$bread_crumbs = array();

		$parent_path = '';
		$parent_id = Model::ROOT_ID;
		$parent_num = count($request_array) - 1 - (int) $was_end_slash;

		$this->template_id = '';

		($hook = s2_hook('fn_s2_parse_page_url_start')) ? eval($hook) : null;

		if (S2_USE_HIERARCHY)
		{
			$urls = array_unique($request_array);
			$urls = array_map(array($s2_db, 'escape'), $urls);

			$query = array(
				'SELECT'	=> 'id, parent_id, title, template',
				'FROM'		=> 'articles',
				'WHERE'		=> 'url IN (\''.implode('\', \'', $urls).'\') AND published=1'
			);
			($hook = s2_hook('fn_s2_parse_page_url_loop_pre_get_parents_query')) ? eval($hook) : null;
			$result = $s2_db->buildAndQuery($query);

			$nodes = $s2_db->fetchAssocAll($result);

			// Walking through the page parents
			// 1. We ensure all of them are published
			// 2. We build "bread crumbs"
			// 3. We determine the template of the page
			for ($i = 0; $i < $parent_num; $i++)
			{
				$parent_path .= urlencode($request_array[$i]).'/';

				$cur_node = array();
				$found_node_num = 0;
				foreach ($nodes as $node)
				{
					if ($node['parent_id'] == $parent_id)
					{
						$cur_node = $node;
						$found_node_num++;
					}
				}

				if ($found_node_num == 0)
					$this->error_404();
				if ($found_node_num > 1)
					error(Lang::get('DB repeat items') . (defined('S2_DEBUG') ? ' (parent_id='.$parent_id.', url="'.s2_htmlencode($request_array[$i]).'")' : ''));

				($hook = s2_hook('fn_s2_parse_page_url_loop_pre_build_stuff')) ? eval($hook) : null;

				$parent_id = $cur_node['id'];
				if ($cur_node['template'] != '')
					$this->template_id = $cur_node['template'];

				$bread_crumbs[] = array(
					'link'  => s2_link($parent_path),
					'title' => $cur_node['title']
				);
			}
		}
		else
		{
			$parent_path = '/';
			$i = 1;
		}
		// Path to the requested page (without trailing slash)
		$current_path = $parent_path.urlencode($request_array[$i]);

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query_children = $s2_db->build($subquery);

		$subquery = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = a.user_id'
		);
		$raw_query_author = $s2_db->build($subquery);

		$query = array(
			'SELECT'	=> 'a.id, a.title, a.meta_keys as meta_keywords, a.meta_desc as meta_description, a.excerpt as excerpt, a.pagetext as text, a.create_time as date, favorite, commented, template, ('.$raw_query_children.') IS NOT NULL AS children_exist, ('.$raw_query_author.') AS author',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'url=\''.$s2_db->escape($request_array[$i]).'\''.(S2_USE_HIERARCHY ? ' AND parent_id='.$parent_id : '').' AND published=1'
		);
		($hook = s2_hook('fn_s2_parse_page_url_pre_get_page')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$page = $s2_db->fetchAssoc($result);

		// Error handling
		if (!$page)
			$this->error_404();
		if ($s2_db->fetchAssoc($result))
			error(Lang::get('DB repeat items') . (defined('S2_DEBUG') ? ' (parent_id='.$parent_id.', url="'.$request_array[$i].'")' : ''));

		if ($page['template'])
			$this->template_id = $page['template'];

		if (!$this->template_id)
		{
			if (S2_USE_HIERARCHY)
			{
				$bread_crumbs[] = array(
					'link'  => s2_link($parent_path),
					'title' => $page['title'],
				);

				error(sprintf(Lang::get('Error no template'), implode('<br />', array_map(function ($a)
				{
					return '<a href="'.$a['link'].'">'.s2_htmlencode($a['title']).'</a>';
				}, $bread_crumbs))));
			}
			else
				error(Lang::get('Error no template flat'));
		}

		if (S2_USE_HIERARCHY && $parent_num && $page['children_exist'] != $was_end_slash)
			s2_permanent_redirect($current_path.(!$was_end_slash ? '/' : ''));

		$page['canonical_path'] = $current_path.($was_end_slash ? '/' : '');

		$id = $page['id'];
		$bread_crumbs[] = array(
			'title' => $page['title']
		);
		$page['title'] = s2_htmlencode($page['title']);

		if (!empty($page['author']))
			$page['author'] = s2_htmlencode($page['author']);

		if (S2_USE_HIERARCHY)
		{
			$page['path'] = $bread_crumbs;

			$page['link_navigation']['top'] = s2_link('/');
			if (count($bread_crumbs) > 1)
			{
				$page['link_navigation']['up'] = s2_link($parent_path);
				$page['section_link'] = '<a href="'.s2_link($parent_path).'">'.$bread_crumbs[count($bread_crumbs) - 2]['title'].'</a>';
			}
		}

		($hook = s2_hook('fn_s2_parse_page_url_pre_get_tpl')) ? eval($hook) : null;

		// Dealing with sections, subsections, neighbours
		if (S2_USE_HIERARCHY && $page['children_exist'] && ($this->inTemplate('<!-- s2_subarticles -->') || $this->inTemplate('<!-- s2_menu_children -->')|| $this->inTemplate('<!-- s2_menu_subsections -->') || $this->inTemplate('<!-- s2_navigation_link -->')))
		{
			// It's a section. We have to fetch subsections and articles.

			// Fetching children
			$subquery = array(
				'SELECT'	=> 'a1.id',
				'FROM'		=> 'articles AS a1',
				'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
				'LIMIT'		=> '1'
			);
			$raw_query1 = $s2_db->build($subquery);

			$sort_order = SORT_DESC;
			$query = array(
				'SELECT'	=> 'title, url, ('.$raw_query1.') IS NOT NULL AS children_exist, id, excerpt, favorite, create_time, parent_id',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'parent_id = '.$id.' AND published = 1',
				'ORDER BY'	=> 'priority'
			);
			($hook = s2_hook('fn_s2_parse_page_url_pre_get_children_qr')) ? eval($hook) : null;
			$result = $s2_db->buildAndQuery($query);

			$subarticles = $subsections = $sort_array = array();
			while ($row = $s2_db->fetchAssoc($result))
			{
				if ($row['children_exist'])
				{
					// The child is a subsection
					$item = array(
						'id'       => $row['id'],
						'title'    => $row['title'],
						'link'     => s2_link($current_path . '/' . urlencode($row['url']) . '/'),
						'date'     => s2_date($row['create_time']),
						'excerpt'  => $row['excerpt'],
						'favorite' => $row['favorite'],
					);

					($hook = s2_hook('pc_parse_page_url_add_subsection')) ? eval($hook) : null;

					$subsections[] = $item;
				}
				else
				{
					// The child is an article
					$item = array(
						'id'       => $row['id'],
						'title'    => $row['title'],
						'link'     => s2_link($current_path . '/' . urlencode($row['url'])),
						'date'     => s2_date($row['create_time']),
						'excerpt'  => $row['excerpt'],
						'favorite' => $row['favorite'],
					);
					$sort_field = $row['create_time'];

					($hook = s2_hook('pc_parse_page_url_add_subarticle')) ? eval($hook) : null;

					$subarticles[] = $item;
					$sort_array[] = $sort_field;
				}
			}

			$sections_text = '';
			if (!empty($subsections))
			{
				// There are subsections in the section
				$page['menu_subsections'] = $this->renderPartial('menu_block', array(
					'title' => Lang::get('Subsections'),
					'menu'  => $subsections,
					'class' => 'menu_subsections',
				));

				foreach ($subsections as $item)
					$sections_text .= $this->renderPartial('subarticles_item', $item);
			}

			$articles_text = '';
			if (!empty($subarticles))
			{
				// There are articles in the section
				$page['menu_children'] = $this->renderPartial('menu_block', array(
					'title' => Lang::get('In this section'),
					'menu'  => $subarticles,
					'class' => 'menu_children',
				));

				($sort_order == SORT_DESC) ? arsort($sort_array) : asort($sort_array);

				if (S2_MAX_ITEMS)
				{
					// Paging navigation
					$page_num = isset($_GET['p']) ? intval($_GET['p']) - 1 : 0;
					if ($page_num < 0)
						$page_num = 0;

					$start = $page_num * S2_MAX_ITEMS;
					if ($start >= count($subarticles))
						$page_num = $start = 0;

					$total_pages = ceil(1.0 * count($subarticles) / S2_MAX_ITEMS);

					$link_nav = array();
					$paging = s2_paging($page_num + 1, $total_pages, s2_link(str_replace('%', '%%', $current_path.'/'), array('p=%d')), $link_nav)."\n";
					foreach ($link_nav as $rel => $href)
						$page['link_navigation'][$rel] = $href;

					$i = 0;
					foreach ($sort_array as $index => $value)
					{
						if ($i < $start || $i >= $start + S2_MAX_ITEMS)
							unset($sort_array[$index]);
						$i++;
					}
				}

				foreach ($sort_array as $index => $value)
					$articles_text .= $this->renderPartial('subarticles_item', $subarticles[$index]);

				if (S2_MAX_ITEMS)
					$articles_text .= $paging;
			}

			$page['subcontent'] = $this->renderPartial('subarticles', array(
				'articles' => $articles_text,
				'sections' => $sections_text,
			));
		}

		if (S2_USE_HIERARCHY && !$page['children_exist'] && ($this->inTemplate('<!-- s2_menu_siblings -->') || $this->inTemplate('<!-- s2_back_forward -->') || $this->inTemplate('<!-- s2_navigation_link -->')))
		{
			// It's an article. We have to fetch other articles in the parent section

			// Fetching "siblings"
			$subquery = array(
				'SELECT'	=> '1',
				'FROM'		=> 'articles AS a2',
				'WHERE'		=> 'a2.parent_id = a.id AND a2.published = 1',
				'LIMIT'		=> '1'
			);
			$raw_query_child_num = $s2_db->build($subquery);

			$query = array(
				'SELECT'	=> 'title, url, id, excerpt, create_time, parent_id',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'parent_id = '.$parent_id.' AND published=1 AND ('.$raw_query_child_num.') IS NULL',
				'ORDER BY'	=> 'priority'
			);
			($hook = s2_hook('fn_s2_parse_page_url_pre_get_neighbours_qr')) ? eval($hook) : null;
			$result = $s2_db->buildAndQuery($query);

			$neighbour_urls = $menu_articles = array();
			$i = 0;
			$curr_item = -1;
			while ($row = $s2_db->fetchAssoc($result))
			{
				// A neighbour
				$url = s2_link($parent_path.urlencode($row['url']));

				$menu_articles[] = array(
					'title'      => $row['title'],
					'link'       => $url,
					'is_current' => $id == $row['id'],
				);

				if ($id == $row['id'])
					$curr_item = $i;

				$neighbour_urls[] = $url;

				($hook = s2_hook('fn_s2_parse_page_url_add_neighbour')) ? eval($hook) : null;

				$i++;
			}

			if (count($bread_crumbs) > 1)
				$page['menu_siblings'] = $this->renderPartial('menu_block', array(
					'title' => sprintf(Lang::get('More in this section'), '<a href="'.s2_link($parent_path).'">'.$bread_crumbs[count($bread_crumbs) - 2]['title'].'</a>'),
					'menu'  => $menu_articles,
					'class' => 'menu_siblings',
				));

			if ($curr_item != -1)
			{
				if (isset($neighbour_urls[$curr_item - 1]))
					$page['link_navigation']['prev'] = $neighbour_urls[$curr_item - 1];
				if (isset($neighbour_urls[$curr_item + 1]))
					$page['link_navigation']['next'] = $neighbour_urls[$curr_item + 1];

				$page['back_forward'] = array(
					'up'      => count($bread_crumbs) <= 1 ? null : array(
						'title' => $bread_crumbs[count($bread_crumbs) - 2]['title'],
						'link'  => s2_link($parent_path),
					),
					'back'    => empty($menu_articles[$curr_item - 1]) ? null : array(
						'title' => $menu_articles[$curr_item - 1]['title'],
						'link'  => $menu_articles[$curr_item - 1]['link'],
					),
					'forward' => empty($menu_articles[$curr_item + 1]) ? null : array(
						'title' => $menu_articles[$curr_item + 1]['title'],
						'link'  => $menu_articles[$curr_item + 1]['link'],
					),
				);
			}
		}

		// Tags
		if ($this->inTemplate('<!-- s2_article_tags -->'))
			$page['article_tags'] = $this->tagged_articles($id);

		if ($this->inTemplate('<!-- s2_tags -->'))
			$page['tags'] = $this->get_tags($id);

        // Recommendations
        if ($this->inTemplate('<!-- s2_recommendations -->')) {
            /** @var RecommendationProvider $recommendationProvider */
            $recommendationProvider = \Container::get(RecommendationProvider::class);
            global $request_uri;

            [$recommendations, $log, $rawRecommendations] = $recommendationProvider->getRecommendations($request_uri, new ExternalId($id));
            $this->page['recommendations'] = $this->renderPartial('recommendations', [
                'raw'     => $rawRecommendations,
                'content' => $recommendations,
                'log'     => $log,
            ]);
        }

		// Comments
		if ($page['commented'] && S2_SHOW_COMMENTS && $this->inTemplate('<!-- s2_comments -->'))
		{
			$query = array(
				'SELECT'	=> 'nick, time, email, show_email, good, text',
				'FROM'		=> 'art_comments',
				'WHERE'		=> 'article_id = '.$id.' AND shown = 1',
				'ORDER BY'	=> 'time'
			);
			($hook = s2_hook('fn_s2_parse_page_url_pre_get_comm_qr')) ? eval($hook) : null;
			$result = $s2_db->buildAndQuery($query);

			$comments = '';

			for ($i = 1; $row = $s2_db->fetchAssoc($result); $i++)
			{
				$row['i'] = $i;
				$comments .= $this->renderPartial('comment', $row);
			}

			if ($comments)
				$page['comments'] = $this->renderPartial('comments', array('comments' => $comments));
		}

		($hook = s2_hook('fn_s2_parse_page_url_end')) ? eval($hook) : null;
	}
}
