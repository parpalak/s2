<?php
/**
 * Quick search based on titles
 *
 * @copyright (C) 2010-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


class s2_search_title_finder extends s2_search_worker
{
	function __construct ($dir)
	{
		parent::__construct($dir);
		$this->read_index();
	}

	public function find ($search_string)
	{
		$output = array();
		foreach ($this->table_of_contents as $chapter => $chapter_info)
			if (strpos(utf8_strtolower($chapter_info['title']), utf8_strtolower($search_string)) !== false)
				$output[$chapter] = $chapter_info;

		return $output;
	}
}
