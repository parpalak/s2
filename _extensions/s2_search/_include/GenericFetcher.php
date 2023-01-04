<?php
/**
 * Describes the data provider for building the search index
 *
 * @copyright (C) 2010-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;

use S2\Rose\Entity\Indexable;

interface GenericFetcher
{
    /**
     * Walks through all pages and gets info about them
     *
     * @return \Generator|Indexable[]
     */
    public function process(): \Generator;

    /**
     * Returns info about a page ID
     */
    public function chapter(string $id): ?Indexable;

    /**
     * Returns page text for a given array of IDs
     *
     * @param array|string[] $ids
     * @return array
     */
    public function texts(array $ids): array;
}
