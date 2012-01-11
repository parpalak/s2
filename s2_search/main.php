<?php
/**
 * Search results page
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


function s2_search_rus_plural ($number, $many, $one, $two)
{
	$number = abs((int) $number);

	if ($number % 100 == 1 || $number % 100 > 20 && $number % 10 == 1)
		return $one;
	if ($number % 100 == 2 || $number % 100 > 20 && $number % 10 == 2)
		return $two;
	if ($number % 100 == 3 || $number % 100 > 20 && $number % 10 == 3)
		return $two;
	if ($number % 100 == 4 || $number % 100 > 20 && $number % 10 == 4)
		return $two;

	return $many;
}

function s2_search_display_url ($s)
{
	$a = explode('/', $s);
	foreach ($a as $k => $v)
		$a[$k] = urldecode($v);

	return implode('/', $a);
}

$s2_search_query = isset($_GET['q']) ? $_GET['q'] : '';
$cur_page = isset($_GET['p']) ? (int) $_GET['p'] : 1;

$template = s2_get_template('service.php');

ob_start();

?>
<div class="search-results">
	<form method="get" action="<?php echo S2_URL_PREFIX ? S2_PATH.S2_URL_PREFIX : S2_PATH.'/search'; ?>">
		<div class="button">
			<input type="submit" name="search" value="<?php echo $lang_s2_search['Search button']; ?>" />
		</div>
		<div class="wrap">
			<input id="s2_search_input_ext" type="text" name="q" value="<?php echo s2_htmlencode($s2_search_query); ?>" />
		</div>
	</form>
<?php

if ($s2_search_query !== '')
{
	require $ext_info['path'].'/stemmer.class.php';
	require $ext_info['path'].'/worker.class.php';
	require $ext_info['path'].'/finder.class.php';
	require $ext_info['path'].'/fetcher.class.php';

	$fetcher = new s2_search_fetcher();
	$finder = new s2_search_finder(S2_CACHE_DIR);

	list($weights, $toc) = $finder->find($s2_search_query);

if (defined('DEBUG')) $start_time = microtime(true);

	$page = array();

	$item_num = count($weights);

	if ($item_num)
	{
		if (substr(S2_LANGUAGE, 0, 7) == 'Russian')
			// Well... Not pretty much. But it's nice to see phrases in human language.
			// Feel free to suggest the code for other languages.
			$result_num_str = sprintf(s2_search_rus_plural($item_num, 'Нашлось %d страниц.', 'Нашлась %d страница.', 'Нашлось %d страницы.'), $item_num);
		else
			$result_num_str = sprintf($lang_s2_search['Found'], $item_num);
		echo '<p>'.$result_num_str.'</p>';

		$items_per_page = S2_MAX_ITEMS ? S2_MAX_ITEMS : 10.0;
		$total_pages = ceil(1.0 * $item_num / $items_per_page);
		if ($cur_page < 1 || $cur_page > $total_pages)
			$cur_page = 1;

		$i = 0;
		$output = array();
		foreach ($weights as $chapter => $weight)
		{
			$i++;
			if ($i <= ($cur_page - 1) * $items_per_page)
				continue;
			if ($i > $cur_page * $items_per_page)
				break;

			$output[$chapter]['title'] = '<a class="title" href="'.s2_link($toc[$chapter]['url']).'">'.s2_htmlencode($toc[$chapter]['title']).'</a>';
			$output[$chapter]['descr'] = trim($toc[$chapter]['descr']);
			$output[$chapter]['info'] = '<small><a class="url" href="'.s2_link($toc[$chapter]['url']).'">'.s2_search_display_url(s2_abs_link($toc[$chapter]['url'])).'</a>'.($toc[$chapter]['time'] ? ' &mdash; '.s2_date($toc[$chapter]['time']) : '').'</small>';
		}

if (defined('DEBUG')) echo 'Страница: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

		$snippets = $finder->snippets(array_keys($output), $fetcher);

if (defined('DEBUG')) echo 'Сниппеты: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

		foreach ($output as $id => &$chapter_info)
		{
			if (($snippets[$id]['rel'] > 0.6))
				$chapter_info['descr'] = $snippets[$id]['snippet'];
			elseif(!$chapter_info['descr'])
				$chapter_info['descr'] = $snippets[$id]['start_text'];

			echo '<p>'.implode('<br />', $chapter_info).'<p>';
		}

		$link_nav = array();
		echo s2_paging($cur_page, $total_pages, s2_link('/search', array('q='.str_replace('%', '%%', urlencode($s2_search_query)), 'p=%d')), $link_nav);
		foreach ($link_nav as $rel => $href)
			$page['link_navigation'][$rel] = $href;
	}
	else
		echo '<p>'.$lang_s2_search['Not found'].'</p>';

}

?>
</div>
<?php

$page['text'] = ob_get_clean();
$page['title'] = $lang_s2_search['Search'];

$s2_search_sql = array(
	'SELECT'	=> 'title',
	'FROM'		=> 'articles',
	'WHERE'		=> 'parent_id = '.S2_ROOT_ID,
);
($hook = s2_hook('s2_search_pre_crumbs_fetch_qr')) ? eval($hook) : null;
$s2_search_result = $s2_db->query_build($s2_search_sql) or error(__FILE__, __LINE__);
list($s2_search_main) = $s2_db->fetch_row($s2_search_result);

$page['path'] = sprintf($lang_s2_search['Crumbs'], $s2_search_main, s2_link('/'), $lang_s2_search['Search']);
