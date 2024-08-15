<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Rose\Entity\Indexable;
use S2\Rose\Indexer;

readonly class ArticleIndexer implements QueueHandlerInterface
{
    public function __construct(
        private DbLayer                $dbLayer,
        private ArticleProvider        $articleProvider,
        private Indexer                $indexer,
        private CacheItemPoolInterface $cache,
        private QueuePublisher         $queuePublisher,
    ) {
    }

    /**
     * @throws DbLayerException
     * @throws InvalidArgumentException
     */
    public function handle(string $id, string $code, array $payload): bool
    {
        if ($code !== 's2_search_Article') {
            return false;
        }

        $indexable = $this->getIndexable((int)$id);
        if ($indexable !== null) {
            $this->indexer->index($indexable);
            $this->queuePublisher->publish($indexable->getExternalId()->toString(), RecommendationProvider::RECOMMENDATIONS_QUEUE);
        } else {
            $this->indexer->removeById($id, null);
        }

        $this->invalidateRecommendationsCache();

        return true;
    }

    /**
     * @throws DbLayerException
     */
    private function getIndexable(int $id): ?Indexable
    {
        $childrenNumSubquery = $this->dbLayer->build([
            'SELECT' => 'COUNT(*)',
            'FROM'   => 'articles AS a2',
            'WHERE'  => 'a2.parent_id = a.id',
            'LIMIT'  => '1'
        ]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'title, id, create_time, url, (' . $childrenNumSubquery . ') as has_children, parent_id, meta_keys, meta_desc, pagetext',
            'FROM'   => 'articles AS a',
            'WHERE'  => 'id = :id AND published = 1',
        ], ['id' => $id]);

        $article = $this->dbLayer->fetchAssoc($result);
        if (!$article) {
            return null;
        }

        $parentPath = $this->articleProvider->pathFromId($article['parent_id'], true);
        if ($parentPath === false) {
            return null;
        }

        $dateTime  = null;
        if ($article['create_time'] > 0) {
            $dateTime = (new \DateTime('@' . $article['create_time']))->setTimezone((new \DateTime())->getTimezone());
        }

        $indexable = new Indexable((string)$article['id'], $article['title'], $article['pagetext'] ?? '');
        $indexable
            ->setKeywords($article['meta_keys'])
            ->setDescription($article['meta_desc'])
            ->setDate($dateTime)
            ->setUrl($parentPath . '/' . urlencode($article['url']) . ($article['url'] && $article['has_children'] ? '/' : ''))
        ;

        return $indexable;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function invalidateRecommendationsCache(): void
    {
        $this->cache->deleteItem(RecommendationProvider::INVALIDATED_AT);
    }
}
