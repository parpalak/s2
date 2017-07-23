<?php
/**
 * Fulltext and keyword search
 *
 * @copyright (C) 2010-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;

//define('DEBUG', 1);
//define('MORE_DEBUG', 1);

class Finder extends Worker
{
	protected $keys;
	protected $chapters = array();

	function __construct ($dir)
	{
		parent::__construct($dir);
		$this->read_index();

		foreach ($this->table_of_contents as $chapter => &$info)
			$this->chapters[$info['id']] = $chapter;
	}

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
		return max(23 - $distance, 13);
	}

	protected static function word_repeat_weight ($word_count)
	{
		return min(0.5*($word_count - 1) + 1, 4);
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
				$words_for_search[] = Stemmer::stem_word($words_for_search[$i]);

			$words_for_search = array_unique($words_for_search);

			$curr_positions = array();

			foreach ($words_for_search as $search_word)
			{
				if (!isset($this->fulltext_index[$search_word]))
					continue;
				foreach ($this->fulltext_index[$search_word] as $id => $entries)
				{
					$chapter = $this->chapters[$id];

					// Remember chapters and positions
					if (is_int($entries))
						$curr_positions[$chapter][] = $entries;
					else
					{
						$entries = explode('|', $entries);
						foreach ($entries as $position)
							$curr_positions[$chapter][] = base_convert($position, 36, 10);
					}

					$word_count = self::word_repeat_weight(count($entries));
					if (!isset ($this->keys[$chapter][$word]))
						$this->keys[$chapter][$word] = $word_count * $word_weight;
					else
						$this->keys[$chapter][$word] += $word_count * $word_weight;
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

		$word = Stemmer::stem_word($word);

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

	public function snippets (array $ids, GenericFetcher $fetcher)
	{
		$snippets = array();
		Stemmer::stem_caching(1);

		$articles = $fetcher->texts($ids);

		// Text cleanup
		$replace_what = array("\r", 'ё', '&nbsp;', '&mdash;', '&ndash;', '&laquo;', '&raquo;');
		$replace_to = array('', 'е', ' ', '—', '–', '«', '»',);
		foreach (array('<br>', '<br />', '</h1>', '</h2>', '</h3>', '</h4>', '</p>', '</pre>', '</blockquote>', '</li>') as $tag)
		{
			$replace_what[] = $tag;
			$replace_to[] = $tag."\r";
		}
		$articles = str_replace($replace_what, $replace_to, $articles);
		foreach ($articles as &$string)
			$string = strip_tags($string);
		unset($string);

		// Preparing for breaking into lines
		$articles = preg_replace('#(?<=[\.?!;])[ \n\t]+#sS', "\r", $articles);

		foreach ($articles as $id => &$string)
		{
			// Stems of the words found in the $id chapter
			$stems = $full_words = array();
			foreach (array_keys($this->keys[$id]) as $word)
				// Excluding neighbours weight
				if (0 !== strpos($word, '*n_') && !isset($this->excluded_words[$word]))
					$full_words[$stems[] = Stemmer::stem_word($word)] = $word;

			// Breaking the text into lines
			$lines = explode("\r", $string);
			$reserved_line = $lines[0].(isset($lines[1]) ? ' '.$lines[1] : '');

			if (empty($full_words))
			{
				$snippets[$id]['snippet'] = '';
				$snippets[$id]['rel'] = 0;
				$snippets[$id]['start_text'] = $reserved_line;
				continue;
			}

			// Check the text for the query words
			// Modifier S works poorly on cyrillic :(
			preg_match_all('#(?<=[^a-zа-я]|^)('.implode('|', $stems).')[a-zа-я]*#sui', $string, $matches, PREG_OFFSET_CAPTURE);

			$line_num = 0;
			$line_end = strlen($lines[$line_num]);

			$found_words = $found_stems_lines = $lines_weight = array();
			foreach ($matches[0] as $i => $word_info)
			{
				$word = utf8_strtolower($word_info[0]);
				$stem = utf8_strtolower($matches[1][$i][0]);
				$stemmed_word = Stemmer::stem_word($word);

				// Ignore entry if the word stem differs from needed ones
				if ($stem != $word && $stem != $stemmed_word && $stemmed_word != $full_words[$stem])
					continue;

				$offset = $word_info[1];

				while ($line_end < $offset && isset($lines[$line_num + 1]))
				{
					$line_num++;
					$line_end += 1 + strlen($lines[$line_num]);
				}

				$found_words[$line_num][] = $word_info[0];
				$found_stems_lines[$line_num][$stem] = 1;
				if (isset($lines_weight[$line_num]))
					$lines_weight[$line_num]++;
				else
					$lines_weight[$line_num] = 1;
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
					$max = 0;
					$max_index = -1;
					foreach ($line_num_array as $line_index => $line_num)
					{
						$future_found_stems = $found_stems;
						foreach ($found_stems_lines[$line_num] as $stem => $weight2)
							$future_found_stems[$stem] = 1;

						if ($max < count($future_found_stems))
						{
							$max = count($future_found_stems);
							$max_index = $line_index;
						}
					}

					$line_num = $line_num_array[$max_index];
					unset($line_num_array[$max_index]);

					foreach ($found_stems_lines[$line_num] as $stem => $weight2)
						$found_stems[$stem] = 1;

					// Highlighting
					$snippet[$line_num] = preg_replace(
						'#\b(' . implode('|', $found_words[$line_num]) . ')\b#su',
						'<i>$1</i>',
						$lines[$line_num]
					);

					// If we have found all stems, we do not need any more sentence
					if ($max == count($stems))
						break 2;
				}
			}

			// Sort sentences in the snippet according to the text order
			$snippet_str = '';
			ksort($snippet);
			$previous_line_num = -1;
			foreach ($snippet as $line_num => &$line)
			{
				// Cleaning up unbalanced quotation makrs
				$line = preg_replace('#«(.*?)»#Ss', '&laquo;\\1&raquo;', $line);
				$line = str_replace(array('&quot', '«', '»'), array('"', ''), $line);
				if (substr_count($line, '"') % 2)
					$line = str_replace('"', '', $line);

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

if (defined('DEBUG')) $start_time = microtime(true);

		$this->keys = array();

		$raw_words = self::filter_input($search_string);
		$cleaned_search_string = implode(' ', $raw_words);

if (defined('DEBUG')) echo 'Чистка строки: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

		if (count($raw_words) > 1)
			$this->find_spaced_keywords($cleaned_search_string);

if (defined('DEBUG')) echo 'Ключевые слова с пробелом: ', - $start_time + ($start_time = microtime(true)), '<br>';

		foreach ($raw_words as $word)
			$this->find_simple_keywords($word);

if (defined('DEBUG')) echo 'Одиночные ключевые слова: ', - $start_time + ($start_time = microtime(true)), '<br>';

		$this->find_fulltext($raw_words);

if (defined('DEBUG')) echo 'Полнотекстовый поиск: ', - $start_time + ($start_time = microtime(true)), '<br>';

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
	print_r($weights);
	echo '</pre>';
	echo 'Финальная обработка: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';
}

		return array($weights, $toc);
	}
}

