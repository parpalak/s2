<?php
/**
 * Common parent for indexer and finder
 *
 * @copyright (C) 2010-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


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

	function __construct ($dir)
	{
		$this->dir = $dir;
	}

	protected function read_index ()
	{
		if (count($this->fulltext_index))
			return false;
if (defined('DEBUG')) $start_time = microtime(true);

		if (!is_file($this->dir.self::index_name))
		{
if (defined('DEBUG')) echo 'Can\'t find index file. Try to rebuild search index.';
			return false;
		}

		$data = file_get_contents($this->dir.self::index_name);

if (defined('DEBUG')) echo 'Чтение файла индекса: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';

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

if (defined('DEBUG')) echo 'Чтение индекса: ', - $start_time + ($start_time = microtime(true)), '  ', memory_get_usage(), '  ', memory_get_peak_usage(), '<br>';
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
