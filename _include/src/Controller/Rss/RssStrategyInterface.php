<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller\Rss;

interface RssStrategyInterface
{
    public function getFeedInfo(): FeedDto;

    /**
     * @return FeedItemDto[]
     */
    public function getFeedItems(): array;
}
