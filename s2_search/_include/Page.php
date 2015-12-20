<?php
/**
 * Displays a page with search results
 *
 * @copyright (C) 2010-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;
use \Lang;


class Page extends \Page_HTML implements \Page_Routable
{
	protected $template_id = 'service.php';

	public function __construct (array $params = array())
	{
		$query = isset($_GET['q']) ? $_GET['q'] : '';
		$this->page_num = isset($_GET['p']) ? (int) $_GET['p'] : 1;

		Lang::load('s2_search', function ()
		{
			if (file_exists(__DIR__ . '/../lang/' . S2_LANGUAGE . '.php'))
				return require __DIR__ . '/../lang/' . S2_LANGUAGE . '.php';
			else
				return require __DIR__ . '/../lang/English.php';
		});

		$this->viewer = new \Viewer($this);
		parent::__construct($params);

		$this->build_page($query);
	}

	private function build_page ($query)
	{
		$content['query'] = $query;
		
		if ($query !== '')
		{
			$fetcher = new Fetcher();
			$finder = new Finder(S2_CACHE_DIR);

			list($weights, $toc) = $finder->find($query);

if (defined('DEBUG')) $start_time = microtime(true);

			$content += array(
				'num' => count($weights),
				'tags' => $this->findInTags($query),
			);

			($hook = s2_hook('s2_search_pre_results')) ? eval($hook) : null;

			if ($content['num'])
			{
				if (substr(S2_LANGUAGE, 0, 7) == 'Russian')
					// Well... Not pretty much. But it's nice to see phrases in human language.
					// Feel free to suggest the code for other languages.
					$content['num_info'] = sprintf(self::rus_plural($content['num'], 'Нашлось %d страниц.', 'Нашлась %d страница.', 'Нашлось %d страницы.'), $content['num']);
				else
					$content['num_info'] = sprintf(Lang::get('Found', 's2_search'), $content['num']);

				$items_per_page = S2_MAX_ITEMS ? S2_MAX_ITEMS : 10.0;
				$total_pages = ceil(1.0 * $content['num'] / $items_per_page);
				if ($this->page_num < 1 || $this->page_num > $total_pages)
					$this->page_num = 1;

				$i = 0;
				$output = array();
				foreach ($weights as $chapter => $weight)
				{
					$i++;
					if ($i <= ($this->page_num - 1) * $items_per_page)
						continue;
					if ($i > $this->page_num * $items_per_page)
						break;

					$output[$chapter] = $toc[$chapter];
				}

if (defined('DEBUG')) echo 'Страница: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

				$snippets = $finder->snippets(array_keys($output), $fetcher);

if (defined('DEBUG')) echo 'Сниппеты: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

				$content['output'] = '';
				foreach ($output as $id => &$chapter_info)
				{
					if (isset($snippets[$id]))
					{
						if (($snippets[$id]['rel'] > 0.6))
							$chapter_info['descr'] = $snippets[$id]['snippet'];
						elseif(!$chapter_info['descr'])
							$chapter_info['descr'] = $snippets[$id]['start_text'];
					}

					$content['output'] .= $this->renderPartial('search_result', $chapter_info);
				}
				unset($chapter_info);


				$link_nav = array();
				$content['paging'] = s2_paging($this->page_num, $total_pages, s2_link('/search', array('q='.str_replace('%', '%%', urlencode($query)), 'p=%d')), $link_nav);
				foreach ($link_nav as $rel => $href)
					$this->page['link_navigation'][$rel] = $href;
			}
		}

		$this->page['text'] = $this->renderPartial('search', $content);
		$this->page['title'] = Lang::get('Search', 's2_search');
		$this->page['path'] = array(
			array(
				'title' => \Model::main_page_title(),
				'link'  => s2_link('/'),
			),
			array(
				'title' => Lang::get('Search', 's2_search'),
			),
		);
	}

	private static function rus_plural ($number, $many, $one, $two)
	{
		$number = abs((int) $number);
		$num_2_dig = $number % 100;
		$num_1_dig = $number % 10;

		if ($num_2_dig == 1 || $num_2_dig > 20 && $num_1_dig == 1)
			return $one;

		if ($num_2_dig == 2 || $num_2_dig > 20 && $num_1_dig == 2)
			return $two;
		if ($num_2_dig == 3 || $num_2_dig > 20 && $num_1_dig == 3)
			return $two;
		if ($num_2_dig == 4 || $num_2_dig > 20 && $num_1_dig == 4)
			return $two;

		return $many;
	}

	// TODO think about html refactoring
	// TODO rename hooks
	private function findInTags ($query)
	{
		global $s2_db;

		$return = '';

		($hook = s2_hook('s2_search_pre_tags')) ? eval($hook) : null;

		if (!trim($query))
			return $return;

		$sql = array(
			'SELECT' => 'count(*)',
			'FROM'   => 'article_tag AS at',
			'JOINS'  => array(
				array(
					'INNER JOIN' => 'articles AS a',
					'ON'         => 'a.id = at.article_id'
				)
			),
			'WHERE'  => 'at.tag_id = t.tag_id AND a.published = 1',
			'LIMIT'  => '1'
		);
		($hook = s2_hook('s2_search_pre_find_tags_sub_qr')) ? eval($hook) : null;
		$s2_search_sub_sql = $s2_db->query_build($sql, true);

		$sql = array(
			'SELECT' => 'tag_id, name, url, (' . $s2_search_sub_sql . ') AS used',
			'FROM'   => 'tags AS t',
			'WHERE'  => 'name LIKE \'' . $s2_db->escape(trim($query)) . '%\'',
		);
		($hook = s2_hook('s2_search_pre_find_tags_qr')) ? eval($hook) : null;
		$s2_search_result = $s2_db->query_build($sql);

		$tags = array();
		while ($s2_search_row = $s2_db->fetch_assoc($s2_search_result))
		{
			($hook = s2_hook('s2_search_find_tags_get_res')) ? eval($hook) : null;

			if ($s2_search_row['used'])
				$tags[] = '<a href="' . s2_link('/' . S2_TAGS_URL . '/' . urlencode($s2_search_row['url']) . '/') . '">' . $s2_search_row['name'] . '</a>';
		}

		($hook = s2_hook('s2_search_find_tags_pre_mrg')) ? eval($hook) : null;

		if (!empty($tags))
			$return .= '<p class="s2_search_found_tags">' . sprintf(Lang::get('Found tags', 's2_search'), implode(', ', $tags)) . '</p>';

		($hook = s2_hook('s2_search_find_tags_end')) ? eval($hook) : null;

		return $return;
	}

} 