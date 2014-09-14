<?php
/**
 * Describes the data provider for building the search index
 *
 * @copyright (C) 2010-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;


interface GenericFetcher
{
	// Walks through all pages and gets info about them
	// This method should call the following method
	// for each page available for search:
	//
	// Indexer::buffer_chapter($id, $title, $text,
	//   $meta_keywords, $meta_description, $time, $url);
	public function process (Indexer $indexer);

	// Returns info about a page ID
	public function chapter ($id);

	// Returns page text for a given array of IDs
	public function texts ($ids);
}
