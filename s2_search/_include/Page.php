<?php
/**
 * Displays a page with search results
 *
 * @copyright (C) 2010-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;


class Page extends \Page_Abstract
{
	protected $template_id = 'service.php';

	public function __construct (array $params = array())
	{
		global $lang_s2_search;

		$this->query = isset($_GET['q']) ? $_GET['q'] : '';
		$this->page_num = isset($_GET['p']) ? (int) $_GET['p'] : 1;

		if (file_exists(__DIR__ . '/../lang/' . S2_LANGUAGE . '.php'))
			require __DIR__ . '/../lang/' . S2_LANGUAGE . '.php';
		else
			require __DIR__ . '/../lang/English.php';

		$this->build_page();
	}

	private function build_page ()
	{
		global $lang_s2_search, $s2_db;

		ob_start();

?>
<div class="search-results">
	<form method="get" action="<?php echo S2_URL_PREFIX ? S2_PATH.S2_URL_PREFIX : S2_PATH.'/search'; ?>">
		<div class="button">
			<input type="submit" name="search" value="<?php echo $lang_s2_search['Search button']; ?>" />
		</div>
		<div class="wrap">
			<input id="s2_search_input_ext" type="text" name="q" value="<?php echo s2_htmlencode($this->query); ?>" />
		</div>
	</form>
<?php

		if ($this->query !== '')
		{
			$fetcher = new Fetcher();
			$finder = new Finder(S2_CACHE_DIR);

			list($weights, $toc) = $finder->find($this->query);

if (defined('DEBUG')) $start_time = microtime(true);

			$item_num = count($weights);
			$not_found = !$item_num;

			($hook = s2_hook('s2_search_pre_tags')) ? eval($hook) : null;

			if (trim($this->query))
			{
				$sql = array(
					'SELECT'	=> 'count(*)',
					'FROM'		=> 'article_tag AS at',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'articles AS a',
							'ON'			=> 'a.id = at.article_id'
						)
					),
					'WHERE'		=> 'at.tag_id = t.tag_id AND a.published = 1',
					'LIMIT'		=> '1'
				);
				($hook = s2_hook('s2_search_pre_find_tags_sub_qr')) ? eval($hook) : null;
				$s2_search_sub_sql = $s2_db->query_build($sql, true) or error(__FILE__, __LINE__);

				$sql = array(
					'SELECT'	=> 'tag_id, name, url, ('.$s2_search_sub_sql.') AS used',
					'FROM'		=> 'tags AS t',
					'WHERE'		=> 'name LIKE \''.$s2_db->escape(trim($this->query)).'%\'',
				);
				($hook = s2_hook('s2_search_pre_find_tags_qr')) ? eval($hook) : null;
				$s2_search_result = $s2_db->query_build($sql) or error(__FILE__, __LINE__);

				$s2_search_found_tags = array();
				while ($s2_search_row = $s2_db->fetch_assoc($s2_search_result))
				{
					($hook = s2_hook('s2_search_find_tags_get_res')) ? eval($hook) : null;

					if ($s2_search_row['used'])
						$s2_search_found_tags[] = '<a href="'.s2_link('/'.S2_TAGS_URL.'/'.urlencode($s2_search_row['url']).'/').'">'.$s2_search_row['name'].'</a>';
				}

				($hook = s2_hook('s2_search_find_tags_pre_mrg')) ? eval($hook) : null;

				if (!empty($s2_search_found_tags))
					echo '<p class="s2_search_found_tags">'.sprintf($lang_s2_search['Found tags'], implode(', ', $s2_search_found_tags)).'</p>';

				($hook = s2_hook('s2_search_find_tags_end')) ? eval($hook) : null;
			}

			($hook = s2_hook('s2_search_pre_results')) ? eval($hook) : null;

			if ($item_num)
			{
				if (substr(S2_LANGUAGE, 0, 7) == 'Russian')
					// Well... Not pretty much. But it's nice to see phrases in human language.
					// Feel free to suggest the code for other languages.
					$result_num_str = sprintf(self::rus_plural($item_num, 'Нашлось %d страниц.', 'Нашлась %d страница.', 'Нашлось %d страницы.'), $item_num);
				else
					$result_num_str = sprintf($lang_s2_search['Found'], $item_num);
				echo '<p class="s2_search_found_num">'.$result_num_str.'</p>';

				$items_per_page = S2_MAX_ITEMS ? S2_MAX_ITEMS : 10.0;
				$total_pages = ceil(1.0 * $item_num / $items_per_page);
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

					$output[$chapter]['title'] = '<a class="title" href="'.s2_link($toc[$chapter]['url']).'">'.s2_htmlencode($toc[$chapter]['title']).'</a>';
					$output[$chapter]['descr'] = trim($toc[$chapter]['descr']);
					$output[$chapter]['info'] = '<small><a class="url" href="'.s2_link($toc[$chapter]['url']).'">'.self::display_url(s2_abs_link($toc[$chapter]['url'])).'</a>'.($toc[$chapter]['time'] ? ' &mdash; '.s2_date($toc[$chapter]['time']) : '').'</small>';
				}

if (defined('DEBUG')) echo 'Страница: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

				$snippets = $finder->snippets(array_keys($output), $fetcher);

if (defined('DEBUG')) echo 'Сниппеты: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

				foreach ($output as $id => &$chapter_info)
				{
					if (isset($snippets[$id]))
					{
						if (($snippets[$id]['rel'] > 0.6))
							$chapter_info['descr'] = $snippets[$id]['snippet'];
						elseif(!$chapter_info['descr'])
							$chapter_info['descr'] = $snippets[$id]['start_text'];
					}

					echo '<p>'.implode('<br />', $chapter_info).'<p>';
				}

				$link_nav = array();
				echo s2_paging($this->page_num, $total_pages, s2_link('/search', array('q='.str_replace('%', '%%', urlencode($this->query)), 'p=%d')), $link_nav);
				foreach ($link_nav as $rel => $href)
					$this->page['link_navigation'][$rel] = $href;
			}

			if ($not_found)
				echo '<p class="s2_search_not_found">'.$lang_s2_search['Not found'].'</p>';

		}

?>
</div>
<?php

		$this->page['text'] = ob_get_clean();
		$this->page['title'] = $lang_s2_search['Search'];
		$this->page['path'] = sprintf($lang_s2_search['Crumbs'], \Model::main_page_title(), s2_link('/'), $lang_s2_search['Search']);
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

	private static function display_url ($s)
	{
		$a = explode('/', $s);
		foreach ($a as $k => $v)
			$a[$k] = urldecode($v);

		return implode('/', $a);
	}

} 