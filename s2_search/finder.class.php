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

	const index_name = 's2_search_index.php';
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
		self::$fulltext_index[$word][$chapter] = (isset(self::$fulltext_index[$word][$chapter]) ? '|' : '').$position; 
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
		$threshold = count(self::$table_of_contents) * 0.3;
		if ($threshold < 20)
			$threshold = 20;

		foreach (self::$fulltext_index as $word => $stat)
		{
			// Drop fulltext frequent or empty items
			if (count($stat) > $threshold || empty($word))
			{
				unset (self::$fulltext_index[$word]);
				self::$excluded_words[$word] = 1;
				continue;
			}

			self::$fulltext_index[$word] = $stat;
		}
	}

	protected static function save_index ()
	{
		$data = serialize(array(
				self::$fulltext_index,
				self::$excluded_words,
				self::$keyword_1_index,
				self::$keyword_base_index,
				self::$keyword_n_index,
				self::$table_of_contents,
			));
		file_put_contents(S2_CACHE_DIR.self::index_name, '<?php //'.$data);
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
			self::buffer_chapter($article['id'], $article['title'], $article['pagetext'], $article['meta_keys']);
			self::$table_of_contents[$article['id']] = array(
				'title'		=> $article['title'],
				'descr'		=> $article['meta_desc'],
				'time'		=> $article['create_time'],
				'url'		=> $url.urlencode($article['url']).($article['is_children'] ? '/' : ''),
			);

			$article['pagetext'] = '';

			self::walk_site($article['id'], $url.urlencode($article['url']).'/');
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
			self::walk_site(0, '');

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
				file_put_contents(S2_CACHE_DIR.self::buffer_name, '');
				file_put_contents(S2_CACHE_DIR.self::buffer_pointer, '');
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

		$data = ($hook = s2_hook('s2_search_get_chapter_start')) ? eval($hook) : null;
		if ($data)
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
				'url'		=> $parent_path.'/'.urlencode($article['url']).($article['url'] && $article['is_children'] ? '/' : ''),
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

		if (!is_file(S2_CACHE_DIR.self::index_name))
		{
			if (defined('DEBUG'))
				echo 'Can\'t find index file. Try to rebuild search index.';
			return false;
		}

		list(
			self::$fulltext_index,
			self::$excluded_words,
			self::$keyword_1_index,
			self::$keyword_base_index,
			self::$keyword_n_index,
			self::$table_of_contents,
		) = unserialize(file_get_contents(S2_CACHE_DIR.self::index_name, NULL, NULL, 8));

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
					$entries = explode('|', $entries);
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
					self::$keys[$chapter]['*n_'.$word] = self::neighbour_weight(self::compare_arrays($positions, $prev_positions[$chapter])) * $word_weight;

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

	protected static function display_url ($s)
	{
		$a = explode('/', $s);
		foreach ($a as $k => $v)
			$a[$k] = urldecode($v);

		return implode('/', $a);
	}

	protected static function get_snippets ($output)
	{
		global $s2_db;

		$ids = array_keys($output);
		$articles = array();

		$result = ($hook = s2_hook('s2_search_get_snippets_start')) ? eval($hook) : null;
		if ($result)
			return $output;

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
			($hook = s2_hook('s2_search_get_chapter_pre_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			while ($article = $s2_db->fetch_assoc($result))
				$articles[$article['id']] = $article['pagetext'];
		}

		foreach ($articles as $id => $string)
		{
			// Stems of the words found in the $id chapter
			$stems = $full_words = array();
			foreach (array_keys(self::$keys[$id]) as $word)
				// Excluding neighbours weight
				if (0 !== strpos($word, '*n_'))
					$full_words[$stems[] = s2_search_stemmer::stem_word($word)] = $word;

			// Text cleanup
			$string = str_replace("\r", '', $string);
			$string = str_replace('<br>', "<br>\r", $string);
			$string = str_replace('<br />', "<br />\r", $string);
			$string = str_replace('</p>', "</p>\r", $string);
			$string = str_replace('</code>', "</code>\r", $string);
			$string = str_replace('</ol>', "</ol>\r", $string);
			$string = str_replace('</ul>', "</ul>\r", $string);
			$string = str_replace('</blockquote>', "</blockquote>\r", $string);
			$string = strip_tags($string);
			$string = str_replace('ё', 'е', $string);
			$lines = preg_split('#((?<=[\.?!:;])[ \n\t]+|\r)#s', $string);
			$reserved_line = $lines[0].(isset($lines[1]) ? ' '.$lines[1] : '');

			// Remove the sentences without stems
			$found_words = $found_stems_lines = $lines_weight = array();
			for ($i = count($lines); $i-- ;)
			{
				// Check every sentence for the query words
				preg_match_all('#(?<=[^a-zа-я]|^)('.implode('|', $stems).')[a-zа-я]*#sui', $lines[$i], $matches);
				foreach ($matches[0] as $k => $word)
				{
					$stem = utf8_strtolower($matches[1][$k]);
					$word = utf8_strtolower($word);
					$stemmed_word = s2_search_stemmer::stem_word($word);
					if ($stem != $word && $stem != $stemmed_word && $stemmed_word != $full_words[$stem])
					{
						unset($matches[0][$k]);
						unset($matches[1][$k]);
					}
					else
						$matches[1][$k] = $stem;
				}

				if (!count($matches[0]))
				{
					unset($lines[$i]);
					continue;
				}

				$stem_weight = array();

				foreach ($matches[0] as $word)
					$found_words[$i][] = $word;

				foreach ($matches[1] as $stem)
				{
					$found_stems_lines[$i][$stem] = 1;
					if (isset($stem_weight[$stem]))
						$stem_weight[$stem] ++;
					else
						$stem_weight[$stem] = 1;
				}
				$lines_weight[$i] = array_sum($stem_weight);
			}

			// Finding the best matches for the snippet
			arsort($lines_weight);

			// Small array rearrangement
			$lines_with_weight = array();
			foreach ($lines_weight as $line_num => $weight)
				$lines_with_weight[$weight][] = $line_num;

			$i = 0;
			$snippet = $found_stems = array();
			foreach ($lines_with_weight as $weight => $line_num_array)
			{
				while (count($line_num_array))
				{
					$i++;
					// We take only 3 sentences with non-zero weight
					if ($i > 3 || !$weight)
						break 2;

					// Choose the best line with the weight given
					$result_weight = array();
					$max = 0;
					$max_index = -1;
					foreach ($line_num_array as $line_index => $line_num)
					{
						$future_found_stems = $found_stems;
						foreach ($found_stems_lines[$line_num] as $stem => $weight)
							$future_found_stems[$stem] = 1;

						if ($max < count($future_found_stems))
						{
							$max = count($future_found_stems);
							$max_index = $line_index;
						}
					}

					$line_num = $line_num_array[$max_index];
					unset($line_num_array[$max_index]);

					foreach ($found_stems_lines[$line_num] as $stem => $weight)
						$found_stems[$stem] = 1;

					// Highlighting
					$replace = array();
					foreach ($found_words[$line_num] as $word)
						$replace[$word] = '<i>'.$word.'</i>';

					$snippet[$line_num] = strtr($lines[$line_num], $replace);

					// If we have found all stems, we do not need any more sentence
					if ($max == count($stems))
						break 2;
				}
			}

			// Sort sentences in the snippet according to the text order
			$snippet_str = '';
			ksort($snippet);
			$previous_line_num = -1;
			foreach ($snippet as $line_num => $line)
			{
				if ($previous_line_num == -1)
					$snippet_str = $line;
				else
					$snippet_str .= ($previous_line_num + 1 == $line_num ? ' ' : '... ').$line;
				$previous_line_num = $line_num;
			}
			$snippet_str = str_replace('.... ', '... ', $snippet_str);


			if ((count($found_stems) * 1.0 / count($stems) > 0.6))
				$output[$id]['descr'] = $snippet_str;
			elseif(!$output[$id]['descr'])
				$output[$id]['descr'] = $reserved_line;
		}

		return $output;
	}

	protected static function s2_search_rus_plural ($number, $many, $one, $two)
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

	public static function find ($search_string, $cur_page)
	{
		global $lang_s2_search;

		self::read_index();

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
		$results = array();
		foreach (self::$keys as $chapter => $stat)
			$results[$chapter] = array_sum($stat);

		// Order by weight
		arsort($results);

if (defined('DEBUG'))
{
	echo '<pre>';
	print_r(self::$keys);
	echo '</pre>';
	echo 'Финальная обработка: ', - $start_time + ($start_time = microtime(true)), '<br>';
}
		$page = array();

		$item_num = count($results);
		if ($item_num)
		{
			if (substr(S2_LANGUAGE, 0, 7) == 'Russian')
				// Well... Not pretty much. But it's nice to see phrases in human language.
				// Feel free to suggest the code for other languages.
				$result_num_str = sprintf(self::s2_search_rus_plural($item_num, 'Нашлось %d страниц.', 'Нашлась %d страница.', 'Нашлось %d страницы.'), $item_num);
			else
				$result_num_str = sprintf($lang_s2_search['Found'], $item_num);
			echo '<p>'.$result_num_str.'</p>';

			$items_per_page = S2_MAX_ITEMS ? S2_MAX_ITEMS : 10.0;
			$total_pages = ceil(1.0 * $item_num / $items_per_page);
			if ($cur_page < 1 || $cur_page > $total_pages)
				$cur_page = 1;

			$i = 0;
			$output = array();
			foreach ($results as $chapter => $weight)
			{
				$i++;
				if ($i <= ($cur_page - 1) * $items_per_page)
					continue;
				if ($i > $cur_page * $items_per_page)
					break;

				$output[$chapter]['title'] = '<a class="title" href="'.S2_PATH.S2_URL_PREFIX.self::$table_of_contents[$chapter]['url'].'">'.self::$table_of_contents[$chapter]['title'].'</a>';
				$output[$chapter]['descr'] = trim(self::$table_of_contents[$chapter]['descr']);
				$output[$chapter]['info'] = '<small><a class="url" href="'.S2_PATH.S2_URL_PREFIX.self::$table_of_contents[$chapter]['url'].'">'.self::display_url(S2_BASE_URL.S2_URL_PREFIX.self::$table_of_contents[$chapter]['url']).'</a>'.(self::$table_of_contents[$chapter]['time'] ? ' &mdash; '.s2_date(self::$table_of_contents[$chapter]['time']) : '').'</small>';
			}

if (defined('DEBUG'))
	echo 'Страница: ', - $start_time + ($start_time = microtime(true)), '<br>';
			$output = self::get_snippets($output);
if (defined('DEBUG'))
	echo 'Сниппеты: ', - $start_time + ($start_time = microtime(true)), '<br>';

			foreach ($output as $chapter_info)
				echo '<p>'.implode('<br />', $chapter_info).'<p>';

			$link_nav = array();
			echo s2_paging($cur_page, $total_pages, S2_PATH.S2_URL_PREFIX.'/search'.(S2_URL_PREFIX ? '&amp;' : '?').'q='.str_replace('%', '%%', urlencode($search_string)).'&p=%d', $link_nav);
			foreach ($link_nav as $rel => $href)
				$page['link_navigation'][$rel] = $href;
		}
		else
			echo '<p>'.$lang_s2_search['Not found'].'</p>';

		return $page;
	}

	public static function find_autosearch ($search_string)
	{
		self::read_index();

		$output = array();
		foreach (self::$table_of_contents as $chapter => $chapter_info)
		{
			if (strpos(utf8_strtolower($chapter_info['title']), utf8_strtolower($search_string)) !== false)
			{
				$output[] = '<a href="'.self::$table_of_contents[$chapter]['url'].'">'.
					preg_replace('#('.preg_quote($search_string, '#').')#ui', '<em>\\1</em>', self::$table_of_contents[$chapter]['title']).'</a>';
			}
		}

		echo implode('', $output);
	}
}
