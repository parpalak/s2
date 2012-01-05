<?php
/**
 * Provides data for building the search index
 *
 * @copyright (C) 2010-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

class s2_search_fetcher
{
	private function crawl ($finder, $parent_id, $url)
	{
		global $s2_db;

		$subquery = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 'articles AS a2',
			'WHERE'		=> 'a2.parent_id = a.id',
			'LIMIT'		=> '1'
		);
		$child_num_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'title, id, create_time, url, ('.$child_num_query.') as is_children, parent_id, meta_keys, meta_desc, pagetext',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'parent_id = '.$parent_id.' AND published = 1',
		);
		($hook = s2_hook('s2_search_fetcer_crawl_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($article = $s2_db->fetch_assoc($result))
		{
			$finder->buffer_chapter($article['id'], $article['title'], $article['pagetext'], $article['meta_keys'], $article['meta_desc'], $article['create_time'], $url.urlencode($article['url']).($article['is_children'] ? '/' : ''));

			$article['pagetext'] = '';

			$this->crawl($finder, $article['id'], $url.urlencode($article['url']).'/');
		}

		($hook = s2_hook('s2_search_fetcher_crawl_end')) ? eval($hook) : null;
	}

	public function process (s2_search_finder $finder)
	{
		$this->crawl($finder, 0, '');

		($hook = s2_hook('s2_search_fetcher_process_end')) ? eval($hook) : null;
	}

	public function chapter ($chapter)
	{
		global $s2_db;

		$data = ($hook = s2_hook('s2_search_fetcher_chapter_start')) ? eval($hook) : null;
		if (is_array($data))
			return $data;

		$subquery = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 'articles AS a2',
			'WHERE'		=> 'a2.parent_id = a.id',
			'LIMIT'		=> '1'
		);
		$child_num_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'title, id, create_time, url, ('.$child_num_query.') as is_children, parent_id, meta_keys, meta_desc, pagetext',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'id = \''.$s2_db->escape($chapter).'\' AND published = 1',
		);
		($hook = s2_hook('s2_search_fetcher_chapter_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$article = $s2_db->fetch_assoc($result);
		if (!$article)
			return array();

		$parent_path = s2_path_from_id($article['parent_id'], true);
		if ($parent_path === false)
			return array();

		return array(
			$article['title'],
			$article['pagetext'],
			$article['meta_keys'],
			array(
				'title'		=> $article['title'],
				'descr'		=> $article['meta_desc'],
				'time'		=> $article['create_time'],
				'url'		=> $parent_path.'/'.urlencode($article['url']).($article['url'] && $article['is_children'] ? '/' : ''),
			)
		);
	}

	public function texts ($ids)
	{
		global $s2_db;

		$articles = array();

		$result = ($hook = s2_hook('s2_search_fetcher_texts_start')) ? eval($hook) : null;
		if ($result)
			return $articles;

		foreach ($ids as $k => $v)
			$ids[$k] = (int) $v;

		if (count($ids))
		{
			// Obtaining articles text
			$query = array(
				'SELECT'	=> 'id, pagetext',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'id IN ('.implode(', ', $ids).') AND published = 1',
			);
			($hook = s2_hook('s2_search_fetcher_texts_pre_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			while ($article = $s2_db->fetch_assoc($result))
				$articles[$article['id']] = $article['pagetext'];
		}

		return $articles;
	}
}
