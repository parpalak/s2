<?php
/**
 * Describes the data provider for building the search index
 *
 * @copyright 2010-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

namespace s2_extensions\s2_search\Service;

use S2\Rose\Entity\Indexable;

interface BulkIndexingProviderInterface
{
    /**
     * Walks through all pages and gets info about them
     *
     * @return \Generator|Indexable[]
     */
    public function getIndexables(): \Generator;
}
