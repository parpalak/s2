<?php
/**
 * Creates search index
 *
 * @copyright (C) 2010-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


class s2_search_indexer extends s2_search_worker
{
	const process_state = 's2_search_state.txt';
	const buffer_name = 's2_search_buffer.txt';
	const buffer_pointer = 's2_search_pointer.txt';

	const KEYWORD_WEIGHT = 30;
	const TITLE_WEIGHT = 20;

	protected $fetcher;
	protected $chapter_lengths = array();

	function __construct ($dir, s2_search_generic_fetcher $fetcher)
	{
		parent::__construct($dir);
		$this->fetcher = $fetcher;
	}

	// Cleaning up an HTML string
	protected static function htmlstr_to_str ($contents)
	{
		$contents = strip_tags($contents);

		$contents = str_replace(array('&nbsp;', "\xc2\xa0"), ' ' , $contents);
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

		if (isset($this->fulltext_index[$word][$id]))
		{
			$value = $this->fulltext_index[$word][$id];
			if (is_int($value))
				$this->fulltext_index[$word][$id] = base_convert($value, 10, 36).'|'.base_convert($position, 10, 36);
			else
				$this->fulltext_index[$word][$id] = $value.'|'.base_convert($position, 10, 36);
		}
		else
			$this->fulltext_index[$word][$id] = $position;
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
				$word = str_replace('ё', 'е', $word);

			$this->add_word_to_fulltext($id, $i, $word);

			// If the word contains hyphen, add a variant without it
			if (strlen($word) > 1 && false !== strpos($word, '-'))
				foreach (explode('-', $word) as $subword)
					if ($subword)
						$this->add_word_to_fulltext($id, $i, $subword);
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
		$threshold = count($this->table_of_contents) * 0.5;
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
			@unlink($this->dir.self::buffer_name);
			@unlink($this->dir.self::buffer_pointer);
			@unlink($this->dir.self::process_state);

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
			$this->read_index();

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
		$this->read_index();

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

		$this->cleanup_index();
		$this->save_index();
	}
}
