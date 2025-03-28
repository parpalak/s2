<?php
/**
 * 1. Updates search index when a visible post has been changed.
 * 2. Provides blog posts data for bulk indexing.
 *
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Rose\Entity\Indexable;
use S2\Rose\Indexer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_search\Service\BulkIndexingProviderInterface;
use s2_extensions\s2_search\Service\RecommendationProvider;

readonly class PostIndexer implements QueueHandlerInterface, BulkIndexingProviderInterface
{
    public function __construct(
        private DbLayer                 $dbLayer,
        private BlogUrlBuilder          $blogUrlBuilder,
        private ?Indexer                $indexer,
        private ?CacheItemPoolInterface $cache,
        private QueuePublisher          $queuePublisher,
    ) {
    }

    /**
     * @throws DbLayerException
     * @throws InvalidArgumentException
     */
    public function handle(string $id, string $code, array $payload): bool
    {
        if ($code !== 's2_search_BlogPost') {
            return false;
        }

        if ($this->indexer === null) {
            return true;
        }

        $indexable = $this->getIndexable((int)$id);
        if ($indexable !== null) {
            $this->indexer->index($indexable);
            $this->queuePublisher->publish($indexable->getExternalId()->toString(), RecommendationProvider::RECOMMENDATIONS_QUEUE);
        } else {
            $this->indexer->removeById('s2_blog_' . $id, null);
        }

        $this->invalidateRecommendationsCache();

        return true;
    }

    /**
     * @throws DbLayerException
     * @throws \Exception
     */
    public function getIndexables(): \Generator
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id, title, text, create_time, url',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'published = 1'
        ]);

        while ($post = $this->dbLayer->fetchAssoc($result)) {
            $indexable = $this->getIndexableFromDbRow($post);
            yield $indexable;
        }
    }


    /**
     * @throws DbLayerException
     * @throws \Exception
     */
    private function getIndexable(int $id): ?Indexable
    {
        $query  = [
            'SELECT' => 'id, title, text, create_time, url',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'published = 1 AND id = :id',
        ];
        $result = $this->dbLayer->buildAndQuery($query, ['id' => $id]);
        $post   = $this->dbLayer->fetchAssoc($result);
        if (!$post) {
            return null;
        }

        return $this->getIndexableFromDbRow($post);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function invalidateRecommendationsCache(): void
    {
        $this->cache->deleteItem(RecommendationProvider::INVALIDATED_AT);
    }

    /**
     * @throws \Exception
     */
    private function getIndexableFromDbRow(array $post): Indexable
    {
        $dateTime = null;
        if ($post['create_time'] > 0) {
            $dateTime = (new \DateTime('@' . $post['create_time']))->setTimezone((new \DateTime())->getTimezone());
        }

        $indexable = new Indexable('s2_blog_' . $post['id'], $post['title'], $post['text']);
        $indexable
            ->setDate($dateTime)
            ->setUrl($this->blogUrlBuilder->postFromTimestampWithoutPrefix($post['create_time'], $post['url']))
        ;
        return $indexable;
    }
}
