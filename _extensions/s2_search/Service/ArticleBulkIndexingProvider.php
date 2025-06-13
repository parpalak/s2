<?php
/**
 * Provides data for building the search index
 *
 * @copyright 2010-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

namespace s2_extensions\s2_search\Service;

use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Rose\Entity\Indexable;

readonly class ArticleBulkIndexingProvider implements BulkIndexingProviderInterface
{
    public function __construct(private DbLayer $dbLayer)
    {
    }

    /**
     * @throws DbLayerException
     */
    private function crawl($parent_id, $url): \Generator
    {
        $childrenNumSubquery = $this->dbLayer
            ->select('COUNT(*)')
            ->from('articles AS a2')
            ->where('a2.parent_id = a.id')
            ->andWhere('a2.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('title, id, create_time, url, (' . $childrenNumSubquery . ') as has_children, parent_id, meta_keys, meta_desc, pagetext')
            ->from('articles AS a')
            ->where('parent_id = :parent_id')
            ->setParameter('parent_id', $parent_id)
            ->andWhere('published = 1')
            ->execute()
        ;

        while ($article = $result->fetchAssoc()) {
            $dateTime = null;
            if ($article['create_time'] > 0) {
                $dateTime = (new \DateTime('@' . $article['create_time']))->setTimezone((new \DateTime())->getTimezone());
            }

            $indexable = new Indexable((string)$article['id'], $article['title'], $article['pagetext'] ?? '');
            $indexable
                ->setKeywords($article['meta_keys'])
                ->setDescription($article['meta_desc'])
                ->setDate($dateTime)
                ->setUrl($url . urlencode($article['url']) . ($article['has_children'] ? '/' : ''))
            ;

            yield $indexable;

            $article['pagetext'] = '';

            yield from $this->crawl($article['id'], $url . urlencode($article['url']) . '/');
        }

    }

    /**
     * @throws DbLayerException
     */
    public function getIndexables(): \Generator
    {
        yield from $this->crawl(ArticleProvider::ROOT_ID, '');
    }
}
