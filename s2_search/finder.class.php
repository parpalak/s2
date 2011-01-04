<?php
/**
 * Functions of the search extension
 *
 * @copyright (C) 2010-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


//define('DEBUG', 1);
//define('MORE_DEBUG', 1);

//error_reporting (E_ALL);

class s2_search_finder
{
	const KEYWORD_WEIGHT = 30;
	const TITLE_WEIGHT = 20;

	const index_name = 's2_search_index.arr';
	const process_state = 's2_search_state.txt';
	const buffer_name = 's2_search_buffer.txt';
	const buffer_pointer = 's2_search_pointer.txt';

	protected static $fulltext_index = array();
	protected static $excluded_words = array();
	protected static $keyword_1_index = array();
	protected static $keyword_base_index = array();
	protected static $keyword_n_index = array();
	protected static $table_of_contents = array();

	protected static $keys = array();

	protected static function filter_input ($contents)
	{
		$contents = strip_tags($contents);

		foreach (array('\\', '|', '/') as $str)
			while (strpos($contents, $str.$str) !== false)
				$contents = str_replace($str.$str, $str, $contents);

		$contents = str_replace(array('\\', '/', '|'), ' ', $contents);
		$contents = str_replace(array('«', '»', '“', '”', '‘', '’'), '"', $contents);
		//$contents = str_replace(array('---', '--', '–'), '—', $contents);
		$contents = str_replace(array('---', '--', '–', '−',), '—', $contents);
		$contents = preg_replace('#,\s+,#u', ',,', $contents);
		$contents = preg_replace('#[^\-а-яё0-9a-z\^\.,\(\)";?!…:—]+#iu', ' ', $contents);
		$contents = preg_replace('#\n+#', ' ', $contents);
		$contents = preg_replace('#\s+#u', ' ', $contents);
		$contents = utf8_strtolower($contents);

		$contents = preg_replace('#(,+)#u', '\\1 ', $contents);

		$contents = preg_replace('#[ ]+#', ' ', $contents);

		$words = explode(' ', $contents);
		foreach ($words as $k => $v)
		{
			// Separate chars from the letter combination
			if (strlen($v) > 1)
				foreach (array('—', '^', '(', ')', '"', ':', '?', '!') as $special_char)
					if (utf8_substr($v, 0, 1) == $special_char || utf8_substr($v, -1) == $special_char)
					{
						$words[$k] = str_replace($special_char, '', $v);
						$words[] = $special_char;
					}

			// Separate hyphen from the letter combination
			if (strlen($v) > 1 && (substr($v, 0, 1) == '-' || substr($v, -1) == '-'))
			{
				$words[$k] = str_replace('-', '', $v);
				$words[] = '-';
			}

			// Replace 'ё' inside words
			if (false !== strpos($v, 'ё') && $v != 'ё')
				$words[$k] = str_replace('ё', 'е', $v);

			// Remove ','
			if (preg_match('#^[^,]+,$#u', $v) || preg_match('#^,[^,]+$#u', $v))
			{
				$words[$k] = str_replace(',', '', $v);
				$words[] = ',';
			}
		}

		$words = array_filter($words, "strlen");

		// Fix keys order
		$words = array_values($words);

		return $words;
	}

	// Cleaning up an HTML string
	protected static function htmlstr_to_str ($contents)
	{
		$contents = strip_tags($contents);

		$contents = str_replace('&nbsp;', ' ' , $contents);
		$contents = preg_replace('#&[^;]+;#', '', $contents);
		$contents = utf8_strtolower($contents);
		$contents = preg_replace('#[^\-а-яё0-9a-z\^]+#u', ' ', $contents);

		return $contents;
	}

	protected static function str_to_array ($contents)
	{
		$words = explode(' ', $contents);
		$words = array_filter($words, 'strlen');

		return $words;
	}

	protected static function add_keyword_to_index ($chapter, $word, $weight)
	{
		if ($word === '')
			return;

		$word = str_replace('ё', 'е', $word);

		if (strpos($word, ' ') !== false)
			self::$keyword_n_index[$word][$chapter] = $weight;
		elseif (substr($word, -2) == '__' && substr($word, 0, 2) == '__')
			self::$keyword_base_index[s2_search_stemmer::stem_word(substr($word, 2, -2))][$chapter] = $weight;
		else
			self::$keyword_1_index[$word][$chapter] = $weight;
	}

	protected static function add_word_to_fulltext ($chapter, $position, $word)
	{
		$word = s2_search_stemmer::stem_word($word);
		self::$fulltext_index[$word][$chapter][] = $position; 
	}

	protected static function add_to_index ($chapter, $title, $contents, $keywords)
	{
		// Processing keywords
		foreach (explode(',', $keywords) as $item)
			self::add_keyword_to_index($chapter, trim($item), self::KEYWORD_WEIGHT);

		// Processing title
		foreach (self::str_to_array($title) as $word)
			self::add_keyword_to_index($chapter, trim($word), self::TITLE_WEIGHT);

		// Fulltext index
		$words = self::str_to_array($title.' '.str_replace(', ', ' ', $keywords).' '.$contents);

		$i = 0;
		foreach ($words as $word)
		{
			if ($word == '-')
				continue;

			$i++;

			if (isset(self::$excluded_words[$word]))
				continue;

			/// Build reverse index

			// Remove ё from the fulltext index
			if (false !== strpos($word, 'ё'))
			{
				$new_word = str_replace('ё', 'е', $word);
				self::add_word_to_fulltext($chapter, $i, $new_word);
			}
			else
				self::add_word_to_fulltext($chapter, $i, $word);

			// If the word contains hyphen, add a variant without it
			if (strlen($word) > 1 && false !== strpos($word, '-'))
			{
				$new_word = str_replace('-', '', $word);
				if ($new_word != '')
					self::add_word_to_fulltext($chapter, $i, $new_word);
			}
		}
	}

	protected static function buffer_chapter ($chapter, $title, $contents, $keywords)
	{
		$str = $chapter.' '.serialize(array(
			self::htmlstr_to_str($title),
			self::htmlstr_to_str($contents),
			$keywords
			))."\n";
		file_put_contents(S2_CACHE_DIR.self::buffer_name, $str, FILE_APPEND);
	}

	protected static function cleanup_index ()
	{
		ksort(self::$fulltext_index);

		$threshold = count(self::$table_of_contents) * 0.5;
		foreach (self::$fulltext_index as $word => $stat)
		{
			// Drop fulltext frequent or empty items
			if (count($stat) > $threshold || empty($word))
			{
				unset (self::$fulltext_index[$word]);
				self::$excluded_words[$word] = 1;
				continue;
			}

			arsort($stat);
			self::$fulltext_index[$word] = $stat;
		}
	}

	protected static function save_index ()
	{
		file_put_contents(
			S2_CACHE_DIR.self::index_name,
			serialize(array(
				self::$fulltext_index,
				self::$excluded_words,
				self::$keyword_1_index,
				self::$keyword_base_index,
				self::$keyword_n_index,
				self::$table_of_contents,
			))
		);    
		file_put_contents(
			S2_CACHE_DIR.self::index_name.'.php',
			"<?php\n\n".'return '.var_export(array(
				self::$fulltext_index,
				self::$excluded_words,
				self::$keyword_1_index,
				self::$keyword_base_index,
				self::$keyword_n_index,
				self::$table_of_contents,
			), true).';'
		);
	}
  
	protected static function walk_site ($parent_id, $url)
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
		($hook = s2_hook('s2_search_walk_site_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($article = $s2_db->fetch_assoc($result))
		{
			self::walk_site($article['id'], $url.$article['url'].'/');

			self::buffer_chapter($article['id'], $article['title'], $article['pagetext'], $article['meta_keys']);
			self::$table_of_contents[$article['id']] = array(
				'title'		=> $article['title'],
				'descr'		=> $article['meta_desc'],
				'time'		=> $article['create_time'],
				'url'		=> $url.$article['url'].($article['is_children'] ? '/' : ''),
			);
		}

		($hook = s2_hook('s2_search_walk_site_end')) ? eval($hook) : null;
	}

	public static function index ()
	{
		self::$fulltext_index = array();
		self::$excluded_words = array();
		self::$keyword_1_index = array();
		self::$keyword_base_index = array();
		self::$keyword_n_index = array();
		self::$table_of_contents = array();

		if (!is_file(S2_CACHE_DIR.self::process_state) || !($state = file_get_contents(S2_CACHE_DIR.self::process_state)))
			$state = 'start';

		if ($state == 'start')
		{
			file_put_contents(S2_CACHE_DIR.self::buffer_name, '');
			file_put_contents(S2_CACHE_DIR.self::buffer_pointer, '0');
			self::walk_site(0, S2_BASE_URL);

			($hook = s2_hook('s2_search_index_after_walk')) ? eval($hook) : null;

			file_put_contents(S2_CACHE_DIR.self::process_state, 'step');
			self::save_index();
			clearstatcache();

			die('go_0');
		}
		elseif ($state == 'step')
		{
			self::read_index();

			$file_pointer = file_get_contents(S2_CACHE_DIR.self::buffer_pointer);

			$f = fopen(S2_CACHE_DIR.self::buffer_name, 'rb');
			fseek($f, $file_pointer);
			$data = fgets($f);
			fclose($f);

			if (!$data)
			{
				self::cleanup_index();
				self::save_index();    
				file_put_contents(S2_CACHE_DIR.self::process_state, '');
				die('stop');
			}

			file_put_contents(S2_CACHE_DIR.self::buffer_pointer, $file_pointer + strlen($data));

			list($chapter, $data) = explode(' ', $data, 2);
			$data = unserialize($data);
			self::add_to_index($chapter, $data[0], $data[1], $data[2]);

			self::save_index();    

			die('go_'.intval(100.0*$file_pointer/filesize(S2_CACHE_DIR.self::buffer_name)));
		}

		($hook = s2_hook('s2_search_index_end')) ? eval($hook) : null;

		file_put_contents(S2_CACHE_DIR.self::process_state, '');
	}

	protected static function remove_chapter ($chapter)
	{
		foreach (self::$fulltext_index as $word => $data)
		{
			if (isset($data[$chapter]))
			{
				unset($data[$chapter]);
				self::$fulltext_index[$word] = $data;
			}
		}
	}

	protected static function get_chapter ($chapter)
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
			'WHERE'		=> 'id = '.$s2_db->escape($chapter).' AND published = 1',
		);
		($hook = s2_hook('s2_search_get_chapter_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$article = $s2_db->fetch_assoc($result);
		if (!$article)
			return false;

		$parent_path = s2_path_from_id($article['parent_id'], true);
		if ($parent_path === false)
			return false;

		return array(
			$article['title'],
			$article['pagetext'],
			$article['meta_keys'],
			array(
				'title'		=> $article['title'],
				'descr'		=> $article['meta_desc'],
				'time'		=> $article['create_time'],
				'url'		=> S2_BASE_URL.$parent_path.'/'.$article['url'].($article['is_children'] ? '/' : ''),
			)
		);

	}

	public static function refresh ($chapter)
	{
		self::$fulltext_index = array();
		self::$excluded_words = array();
		self::$keyword_1_index = array();
		self::$keyword_base_index = array();
		self::$keyword_n_index = array();
		self::$table_of_contents = array();

		self::read_index();
		self::remove_chapter($chapter);

		$data = ($hook = s2_hook('s2_search_refresh_get_chapter')) ? eval($hook) : null;
		if (!$data)
			$data = self::get_chapter($chapter);

		if ($data)
		{
			self::add_to_index($chapter, self::htmlstr_to_str($data[0]), self::htmlstr_to_str($data[1]), $data[2]);
			self::$table_of_contents[$chapter] = $data[3];
		}
		else
			unset(self::$table_of_contents[$chapter]);

		self::save_index();    
	}

	protected static function read_index ()
	{
		if (count(self::$fulltext_index))
			return false;
if (defined('DEBUG'))
	$start_time = microtime(true);

//		self::index();
//if (defined('DEBUG'))
//	echo 'Индексация: ', - $start_time + ($start_time = microtime(true)), '<br>';

		list(
			self::$fulltext_index,
			self::$excluded_words,
			self::$keyword_1_index,
			self::$keyword_base_index,
			self::$keyword_n_index,
			self::$table_of_contents,
		) = unserialize(file_get_contents(S2_CACHE_DIR.self::index_name));

		//list(self::$fulltext_index, self::$excluded_words, self::$keyword_1_index, self::$keyword_n_index, self::$table_of_contents) = include S2_CACHE_DIR.self::index_name.'.php';
if (defined('DEBUG'))
	echo 'Чтение индекса: ', - $start_time + ($start_time = microtime(true)), '<br>';

	}

	protected static function compare_arrays ($a1, $a2)
	{
		$result = 100000000;
		foreach ($a1 as $x)
			foreach ($a2 as $y)
				if (abs($x - $y) < $result)
					$result = abs($x - $y);

		return $result;
	}

	protected static function fulltext_weight ($word_num)
	{
		if ($word_num < 4)
			return 1;

		if ($word_num == 4)
			return 7;

		return 10;
	}

	protected static function neighbour_weight ($distance)
	{
		if ($distance == 1)
			return 11;

		if ($distance == 2)
			return 9;

		if ($distance == 3)
			return 7;

		if ($distance == 4)
			return 4;

		return 0;
	}

	protected static function find_fulltext ($words)
	{
		$word_weight = self::fulltext_weight(count($words));
		$prev_positions = array();
		foreach ($words as $word)
		{
			// Add stemmed words
			$words_for_search = array($word);

			for ($i = count($words_for_search); $i-- ;)
				$words_for_search[] = s2_search_stemmer::stem_word($words_for_search[$i]);

			$words_for_search = array_unique($words_for_search);

			$curr_positions = array();

			foreach ($words_for_search as $search_word)
			{
				if (!isset(self::$fulltext_index[$search_word]))
					continue;

				foreach (self::$fulltext_index[$search_word] as $chapter => $entries)
				{
					// Remember chapters and positions
					foreach ($entries as $position)
						$curr_positions[$chapter][] = $position;

					if (!isset (self::$keys[$chapter][$word]))
						self::$keys[$chapter][$word] = count($entries) * $word_weight;
					else
						self::$keys[$chapter][$word] += count($entries) * $word_weight;
				}
			}

			foreach ($curr_positions as $chapter => $positions)
				if (isset($prev_positions[$chapter]))
					self::$keys[$chapter]['neighbour_'.$word] = self::neighbour_weight(self::compare_arrays($positions, $prev_positions[$chapter])) * $word_weight;

			$prev_positions = $curr_positions;
		}
	}

	protected static function find_simple_keywords ($string, $use_weight)
	{
		if (isset(self::$keyword_1_index[$string]))
			foreach (self::$keyword_1_index[$string] as $chapter => $weight)
				self::$keys[$chapter][$string] = $weight;

		$string = s2_search_stemmer::stem_word($string);

		if (isset(self::$keyword_base_index[$string]))
			foreach (self::$keyword_base_index[$string] as $chapter => $weight)
				self::$keys[$chapter][$string] = $weight;
	}

	protected static function find_spaced_keywords ($string)
	{
		$string = ' '.$string.' ';
		foreach (self::$keyword_n_index as $keyword => $value)
		{
			if (strpos($string, ' '.$keyword.' ') !== false)
			{
				foreach ($value as $chapter => $weight)
					self::$keys[$chapter][$keyword] = $weight;
			}
		}
	}

	public static function find ($search_string)
	{
		global $lang_s2_search;

		s2_search_stemmer::stem_caching(1);
		self::read_index();
		s2_search_stemmer::stem_caching(0);

if (defined('DEBUG'))
	$start_time = microtime(true);

		self::$keys = array();

		$raw_words = self::filter_input($search_string);
		$cleaned_search_string = implode(' ', $raw_words);

if (defined('DEBUG'))
	echo 'Чистка строки: ', - $start_time + ($start_time = microtime(true)), '<br>';

		if (count($raw_words) > 1)
			self::find_spaced_keywords($cleaned_search_string);

if (defined('DEBUG'))
	echo 'Ключевые слова с пробелом: ', - $start_time + ($start_time = microtime(true)), '<br>';

		foreach ($raw_words as $word)
		{
			self::find_simple_keywords($word, count($raw_words) == 1);
		}
if (defined('DEBUG'))
	echo 'Одиночные ключевые слова: ', - $start_time + ($start_time = microtime(true)), '<br>';

		self::find_fulltext($raw_words);

if (defined('DEBUG'))
	echo 'Полнотекстовый поиск: ', - $start_time + ($start_time = microtime(true)), '<br>';

		// Determine relevance

		// Now keys are chapters and values are arrays "word => weight".
if (defined('DEBUG') && defined('MORE_DEBUG'))
{
	echo "<pre>";
	print_r(self::$keys);
	echo '</pre>';
}
		foreach (self::$keys as $chapter => $stat)
			self::$keys[$chapter] = array_sum($stat);

		// Order by weight
		arsort(self::$keys);

if (defined('DEBUG'))
{
	echo '<pre>';
	print_r(self::$keys);
	echo '</pre>';
	echo 'Финальная обработка: ', - $start_time + ($start_time = microtime(true)), '<br>';
}
		echo '<p>', ($item_num = count(self::$keys)) ? sprintf($lang_s2_search['Found'], $item_num) : $lang_s2_search['Not found'], '</p>';

		foreach(self::$keys as $chapter => $weight)
		{
			echo '<p><a class="title" href="'.self::$table_of_contents[$chapter]['url'].'">'.self::$table_of_contents[$chapter]['title'].'</a><br />'.
				self::$table_of_contents[$chapter]['descr'].'<br />'.
				'<small><a class="url" href="'.self::$table_of_contents[$chapter]['url'].'">'.self::$table_of_contents[$chapter]['url'].'</a>'.(self::$table_of_contents[$chapter]['time'] ? ' &mdash; '.s2_date(self::$table_of_contents[$chapter]['time']) : '').'</small></p>';
		}
	}
}
