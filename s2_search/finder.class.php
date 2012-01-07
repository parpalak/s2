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

abstract class s2_search_worker
{
	const index_name = 's2_search_index.php';

	protected $fulltext_index = array();
	protected $excluded_words = array();
	protected $keyword_1_index = array();
	protected $keyword_base_index = array();
	protected $keyword_n_index = array();
	protected $table_of_contents = array();

	protected $dir;

	function __construct($dir)
	{
		$this->dir = $dir;
		$this->read_index();
	}

	protected function read_index ()
	{
		if (count($this->fulltext_index))
			return false;
if (defined('DEBUG'))
	$start_time = microtime(true);

		if (!is_file($this->dir.self::index_name))
		{
			if (defined('DEBUG'))
				echo 'Can\'t find index file. Try to rebuild search index.';
			return false;
		}

		$data = file_get_contents($this->dir.self::index_name);
if (defined('DEBUG'))
	echo 'Чтение файла индекса: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

		$end = strpos($data, "\n");
		$my_data = substr($data, 8, $end);
		$data = substr($data, $end + 1);
		$this->fulltext_index = unserialize($my_data);

		$end = strpos($data, "\n");
		$my_data = substr($data, 8, $end);
		$data = substr($data, $end + 1);
		$this->excluded_words = unserialize($my_data);

		$end = strpos($data, "\n");
		$my_data = substr($data, 8, $end);
		$data = substr($data, $end + 1);
		$this->keyword_1_index = unserialize($my_data);

		$end = strpos($data, "\n");
		$my_data = substr($data, 8, $end);
		$data = substr($data, $end + 1);
		$this->keyword_base_index = unserialize($my_data);

		$end = strpos($data, "\n");
		$my_data = substr($data, 8, $end);
		$data = substr($data, $end + 1);
		$this->keyword_n_index = unserialize($my_data);

		$end = strpos($data, "\n");
		$my_data = substr($data, 8, $end);
		$data = substr($data, $end + 1);
		$this->table_of_contents = unserialize($my_data);

if (defined('DEBUG'))
	echo 'Чтение индекса: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';
	}

	protected function save_index ()
	{
		file_put_contents($this->dir.self::index_name, '<?php //'.'a:'.count($this->fulltext_index).':{');
		$buffer = '';
		$length = 0;
		foreach ($this->fulltext_index as $word => $data)
		{
			$chunk = serialize($word).serialize($data);
			$length += strlen($chunk);
			$buffer .= $chunk;
			if ($length > 100000)
			{
				file_put_contents($this->dir.self::index_name, $buffer, FILE_APPEND);
				$buffer = '';
				$length = 0;
			}
		}
		file_put_contents($this->dir.self::index_name, $buffer.'}'."\n", FILE_APPEND);
		$this->fulltext_index = null;

		file_put_contents($this->dir.self::index_name, '      //'.serialize($this->excluded_words)."\n", FILE_APPEND);
		$this->excluded_words = null;

		file_put_contents($this->dir.self::index_name, '      //'.serialize($this->keyword_1_index)."\n", FILE_APPEND);
		$this->keyword_1_index = null;

		file_put_contents($this->dir.self::index_name, '      //'.serialize($this->keyword_base_index)."\n", FILE_APPEND);
		$this->keyword_base_index = null;

		file_put_contents($this->dir.self::index_name, '      //'.serialize($this->keyword_n_index)."\n", FILE_APPEND);
		$this->keyword_n_index = null;

		file_put_contents($this->dir.self::index_name, '      //'.serialize($this->table_of_contents)."\n", FILE_APPEND);
	}
}

class s2_search_indexer extends s2_search_worker
{
	const process_state = 's2_search_state.txt';
	const buffer_name = 's2_search_buffer.txt';
	const buffer_pointer = 's2_search_pointer.txt';

	const KEYWORD_WEIGHT = 30;
	const TITLE_WEIGHT = 20;

	protected $fetcher;
	protected $chapter_lengths = array();

	function __construct($dir, s2_search_fetcher $fetcher)
	{
		parent::__construct($dir);
		$this->fetcher = $fetcher;
	}

	// Cleaning up an HTML string
	protected static function htmlstr_to_str ($contents)
	{
		$contents = strip_tags($contents);

		$contents = str_replace('&nbsp;', ' ' , $contents);
		$contents = preg_replace('#&[^;]{1,20};#', '', $contents);
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

	protected function add_keyword_to_index ($id, $word, $weight)
	{
		if ($word === '')
			return;

		$word = str_replace('ё', 'е', $word);

		if (strpos($word, ' ') !== false)
			$this->keyword_n_index[$word][$id] = $weight;
		elseif (substr($word, -2) == '__' && substr($word, 0, 2) == '__')
			$this->keyword_base_index[s2_search_stemmer::stem_word(substr($word, 2, -2))][$id] = $weight;
		else
			$this->keyword_1_index[$word][$id] = $weight;
	}

	protected function add_word_to_fulltext ($id, $position, $word)
	{
		$word = s2_search_stemmer::stem_word($word);
		$this->fulltext_index[$word][$id] = (isset($this->fulltext_index[$word][$id]) ? $this->fulltext_index[$word][$id].'|' : '').base_convert($position, 10, 36);
	}

	protected function add_to_index ($chapter, $title, $contents, $keywords)
	{
		$id = $this->table_of_contents[$chapter]['id'];

		// Processing title
		foreach (self::str_to_array($title) as $word)
			$this->add_keyword_to_index($id, trim($word), self::TITLE_WEIGHT);

		// Processing keywords
		foreach (explode(',', $keywords) as $item)
			$this->add_keyword_to_index($id, trim($item), self::KEYWORD_WEIGHT);

		// Fulltext index
		$words = self::str_to_array($title.' '.str_replace(', ', ' ', $keywords).' '.$contents);

		$i = 0;
		foreach ($words as $word)
		{
			if ($word == '-')
				continue;

			$i++;

			if (isset($this->excluded_words[$word]))
				continue;

			/// Build reverse index

			// Remove ё from the fulltext index
			if (false !== strpos($word, 'ё'))
			{
				$new_word = str_replace('ё', 'е', $word);
				$this->add_word_to_fulltext($id, $i, $new_word);
			}
			else
				$this->add_word_to_fulltext($id, $i, $word);

			// If the word contains hyphen, add a variant without it
			if (strlen($word) > 1 && false !== strpos($word, '-'))
			{
				$new_word = str_replace('-', '', $word);
				if ($new_word != '')
					$this->add_word_to_fulltext($id, $i, $new_word);
			}
		}
	}

	public function buffer_chapter ($chapter, $title, $contents, $keywords, $description, $time, $url)
	{
		$str = $chapter.' '.serialize(array(
			self::htmlstr_to_str($title),
			self::htmlstr_to_str($contents),
			$keywords
			))."\n";
		file_put_contents($this->dir.self::buffer_name, $str, FILE_APPEND);

		$this->table_of_contents[$chapter] = array(
			'title'		=> $title,
			'descr'		=> $description,
			'time'		=> $time,
			'url'		=> $url,
		);

		$this->chapter_lengths[$chapter] = strlen($contents);
	}

	protected function build_ids ()
	{
		arsort($this->chapter_lengths);
		$id = 0;
		foreach($this->chapter_lengths as $chapter => $length)
			$this->table_of_contents[$chapter]['id'] = ++$id;
	}

	protected function cleanup_index ()
	{
		$threshold = count($this->table_of_contents) * 0.3;
		if ($threshold < 20)
			$threshold = 20;

		$link = &$this->fulltext_index; // for memory optimization
		foreach ($this->fulltext_index as $word => $stat)
		{
			// Drop fulltext frequent or empty items
			if (count($stat) > $threshold || empty($word))
			{
				unset ($this->fulltext_index[$word]);
				$this->excluded_words[$word] = 1;
			}
		}
	}

	public function index ()
	{
		if (!is_file($this->dir.self::process_state) || !($state = file_get_contents($this->dir.self::process_state)))
			$state = 'start';

		if ($state == 'start')
		{
			file_put_contents($this->dir.self::buffer_name, '');
			file_put_contents($this->dir.self::buffer_pointer, '0');

			$this->fetcher->process($this);
			$this->build_ids();

			file_put_contents($this->dir.self::process_state, 'step');
			$this->save_index();
			clearstatcache();

			return 'go_20';
		}
		elseif ($state == 'step')
		{
			$start = microtime(1);

			$file_pointer = file_get_contents($this->dir.self::buffer_pointer);

			$f = fopen($this->dir.self::buffer_name, 'rb');
			fseek($f, $file_pointer);

			do
			{
				$data = fgets($f);

				if (!$data)
				{
					fclose($f);
					$this->cleanup_index();
					$this->save_index();
					file_put_contents($this->dir.self::buffer_name, '');
					file_put_contents($this->dir.self::buffer_pointer, '');
					file_put_contents($this->dir.self::process_state, '');
					return 'stop';
				}

				$file_pointer += strlen($data);
				list($chapter, $data) = explode(' ', $data, 2);
				$data = unserialize($data);
				$this->add_to_index($chapter, $data[0], $data[1], $data[2]);
			} while ($start + 4.0 > microtime(1));

			fclose($f);
			file_put_contents($this->dir.self::buffer_pointer, $file_pointer);
			$this->save_index();

			return 'go_'.(20 + (int)(80.0*$file_pointer/filesize($this->dir.self::buffer_name)));
		}

		file_put_contents($this->dir.self::process_state, '');

		return 'unknown state';
	}

	protected function remove_from_index ($chapter)
	{
		$id = $this->table_of_contents[$chapter]['id'];

		foreach ($this->fulltext_index as $word => &$data)
			if (isset($data[$id]))
				unset($data[$id]);

		foreach ($this->keyword_1_index as $word => &$data)
			if (isset($data[$id]))
				unset($data[$id]);

		foreach ($this->keyword_base_index as $word => &$data)
			if (isset($data[$id]))
				unset($data[$id]);

		foreach ($this->keyword_n_index as $word => &$data)
			if (isset($data[$id]))
				unset($data[$id]);
	}

	public function refresh ($chapter)
	{
		if (isset($this->table_of_contents[$chapter]))
		{
			$chapter_id = $this->table_of_contents[$chapter]['id'];
			$this->remove_from_index($chapter);
			unset($this->table_of_contents[$chapter]);
		}

		$data = $this->fetcher->chapter($chapter);

		if (!empty($data))
		{
			if (!isset($chapter_id))
			{
				$chapter_id = 0;
				foreach ($this->table_of_contents as &$entry)
					if ($chapter_id < $entry['id'])
						$chapter_id = $entry['id'];
				$chapter_id++;
			}

			$this->table_of_contents[$chapter] = $data[3];
			$this->table_of_contents[$chapter]['id'] = $chapter_id;

			$this->add_to_index($chapter, self::htmlstr_to_str($data[0]), self::htmlstr_to_str($data[1]), $data[2]);
		}

		$this->save_index();
	}

}

class s2_search_finder extends s2_search_worker
{
	protected $keys;
	protected $chapters = array();

	protected static function filter_input ($contents)
	{
		$contents = strip_tags($contents);

		foreach (array('\\', '|', '/') as $str)
			while (strpos($contents, $str.$str) !== false)
				$contents = str_replace($str.$str, $str, $contents);

		$contents = str_replace(array('\\', '/', '|'), ' ', $contents);
		$contents = str_replace(array('«', '»', '“', '”', '‘', '’'), '"', $contents);
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

		$words = array_filter($words, 'strlen');

		// Fix keys order
		$words = array_values($words);

		return $words;
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
		return max(13 - 2*$distance, 2);
	}

	protected function find_fulltext ($words)
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
				if (!isset($this->fulltext_index[$search_word]))
					continue;
				foreach ($this->fulltext_index[$search_word] as $id => $entries)
				{
					$chapter = $this->chapters[$id];
					$entries = explode('|', $entries);
					// Remember chapters and positions
					foreach ($entries as $position)
						$curr_positions[$chapter][] = base_convert($position, 36, 10);

					if (!isset ($this->keys[$chapter][$word]))
						$this->keys[$chapter][$word] = count($entries) * $word_weight;
					else
						$this->keys[$chapter][$word] += count($entries) * $word_weight;
				}
			}

			foreach ($curr_positions as $chapter => $positions)
				if (isset($prev_positions[$chapter]))
					$this->keys[$chapter]['*n_'.$word] = self::neighbour_weight(self::compare_arrays($positions, $prev_positions[$chapter])) * $word_weight;

			$prev_positions = $curr_positions;
		}
	}

	protected function find_simple_keywords ($word)
	{
		if (isset($this->keyword_1_index[$word]))
			foreach ($this->keyword_1_index[$word] as $id => $weight)
				$this->keys[$this->chapters[$id]][$word] = $weight;

		$word = s2_search_stemmer::stem_word($word);

		if (isset($this->keyword_base_index[$word]))
			foreach ($this->keyword_base_index[$word] as $id => $weight)
				$this->keys[$this->chapters[$id]][$word] = $weight;
	}

	protected function find_spaced_keywords ($string)
	{
		$string = ' '.$string.' ';
		foreach ($this->keyword_n_index as $keyword => $value)
		{
			if (strpos($string, ' '.$keyword.' ') !== false)
			{
				foreach ($value as $id => $weight)
					$this->keys[$this->chapters[$id]][$keyword] = $weight;
			}
		}
	}

	public function snippets (array $ids, s2_search_fetcher $fetcher)
	{
		$snippets = array();

		$articles = $fetcher->texts($ids);

		foreach ($articles as $id => $string)
		{
			// Stems of the words found in the $id chapter
			$stems = $full_words = array();
			foreach (array_keys($this->keys[$id]) as $word)
				// Excluding neighbours weight
				if (0 !== strpos($word, '*n_') && !isset($this->excluded_words[$word]))
					$full_words[$stems[] = s2_search_stemmer::stem_word($word)] = $word;

			// Text cleanup
			$string = str_replace("\r", '', $string);
			$string = str_replace('&nbsp;', ' ', $string);
			$string = str_replace('&mdash;', '—', $string);
			$string = str_replace('&ndash;', '–', $string);
			$string = str_replace('&laquo;', '«', $string);
			$string = str_replace('&laquo;', '»', $string);
			$string = str_replace('<br>', "<br>\r", $string);
			$string = str_replace('<br />', "<br />\r", $string);
			$string = str_replace('</h2>', "</h2>\r", $string);
			$string = str_replace('</h3>', "</h3>\r", $string);
			$string = str_replace('</h4>', "</h4>\r", $string);
			$string = str_replace('</p>', "</p>\r", $string);
			$string = str_replace('</code>', "</code>\r", $string);
			$string = str_replace('</ol>', "</ol>\r", $string);
			$string = str_replace('</ul>', "</ul>\r", $string);
			$string = str_replace('</blockquote>', "</blockquote>\r", $string);
			$string = strip_tags($string);
			$string = str_replace('ё', 'е', $string);
			$lines = preg_split('#((?<=[\.?!:;])[ \n\t]+|\r)#s', $string);
			$reserved_line = $lines[0].(isset($lines[1]) ? ' '.$lines[1] : '');

			if (empty($full_words))
			{
				$snippets[$id]['snippet'] = '';
				$snippets[$id]['rel'] = 0;
				$snippets[$id]['start_text'] = $reserved_line;
				continue;
			}

			// Remove the sentences without stems
			$found_words = $found_stems_lines = $lines_weight = array();
			for ($i = count($lines); $i-- ;)
			{
				// Check every sentence for the query words
				// Modifier S works poorly on cyrillic :(
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

			$snippets[$id]['snippet'] = $snippet_str;
			$snippets[$id]['rel'] = count($found_stems) * 1.0 / count($stems);
			$snippets[$id]['start_text'] = $reserved_line;

		}

		return $snippets;
	}

	public function find ($search_string)
	{
if (defined('DEBUG'))
	$start_time = microtime(true);

		$this->keys = array();

		foreach ($this->table_of_contents as $chapter => &$info)
			$this->chapters[$info['id']] = $chapter;

		$raw_words = self::filter_input($search_string);
		$cleaned_search_string = implode(' ', $raw_words);

if (defined('DEBUG'))
	echo 'Чистка строки: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

		if (count($raw_words) > 1)
			$this->find_spaced_keywords($cleaned_search_string);

if (defined('DEBUG'))
	echo 'Ключевые слова с пробелом: ', - $start_time + ($start_time = microtime(true)), '<br>';

		foreach ($raw_words as $word)
			$this->find_simple_keywords($word);

if (defined('DEBUG'))
	echo 'Одиночные ключевые слова: ', - $start_time + ($start_time = microtime(true)), '<br>';

		$this->find_fulltext($raw_words);

if (defined('DEBUG'))
	echo 'Полнотекстовый поиск: ', - $start_time + ($start_time = microtime(true)), '<br>';

		// Determine relevance

if (defined('DEBUG') && defined('MORE_DEBUG'))
{
	echo "<pre>";
	print_r($this->keys);
	echo '</pre>';
}
		// Now keys are chapters and values are arrays "word => weight".
		$toc = $weights = array();
		foreach ($this->keys as $chapter => $stat)
		{
			$weights[$chapter] = array_sum($stat);
			$toc[$chapter] = $this->table_of_contents[$chapter];
		}

		// Order by weight
		arsort($weights);

if (defined('DEBUG'))
{
	echo '<pre>';
	print_r($this->keys);
	print_r($weights);
	echo '</pre>';
	echo 'Финальная обработка: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';
}
		return array($weights, $toc);
	}
}

class s2_search_title_finder extends s2_search_worker
{
	public function find ($search_string)
	{
		$output = array();
		foreach ($this->table_of_contents as $chapter => $chapter_info)
			if (strpos(utf8_strtolower($chapter_info['title']), utf8_strtolower($search_string)) !== false)
				$output[$chapter] = $chapter_info;

		return $output;
	}
}
