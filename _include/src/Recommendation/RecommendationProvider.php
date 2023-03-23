<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Recommendation;

use Psr\Cache\InvalidArgumentException;
use S2\Cms\Layout\ContentItem;
use S2\Cms\Layout\LayoutMatcher;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Rose\Entity\ExternalId;
use S2\Rose\Entity\TocEntryWithMetadata;
use S2\Rose\Storage\Database\PdoStorage;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RecommendationProvider implements QueueHandlerInterface
{
    public const INVALIDATED_AT        = 'invalidatedAt';
    public const RECOMMENDATIONS_QUEUE = 'recommendations';
    public const CACHE_KEY_PREFIX      = 'recommendations_';

    private PdoStorage $pdoStorage;
    private LayoutMatcher $layoutMatcher;
    private CacheInterface $cache;
    private QueuePublisher $queuePublisher;

    public function __construct(
        PdoStorage     $pdoStorage,
        LayoutMatcher  $layoutMatcher,
        CacheInterface $cache,
        QueuePublisher $queuePublisher
    ) {
        $this->pdoStorage     = $pdoStorage;
        $this->layoutMatcher  = $layoutMatcher;
        $this->cache          = $cache;
        $this->queuePublisher = $queuePublisher;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getRecommendations(string $page, ExternalId $externalId): array
    {
        [$recommendations, $generatedAt] = ($this->cache->get(
            $this->getCacheKey($externalId),
            fn(ItemInterface $item) => $this->getValueForCache($externalId)
        ));

        $cacheInvalidatedAt = $this->cache->get(self::INVALIDATED_AT, fn(ItemInterface $item) => time());
        if ($cacheInvalidatedAt > $generatedAt + 1) {
            // +1 to protect from rebuilding
            $this->queuePublisher->publish($externalId->toString(), self::RECOMMENDATIONS_QUEUE);
        }

        return array_merge($this->processRecommendations($page, $recommendations), [$recommendations]);
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function handle(string $id, string $code, array $payload): bool
    {
        if ($code === self::RECOMMENDATIONS_QUEUE) {
            $externalId = ExternalId::fromString($id);
            $cacheKey   = $this->getCacheKey($externalId);

            $item = $this->cache->getItem($cacheKey);
            $item->set($this->getValueForCache($externalId));
            $this->cache->save($item);

            return true;
        }

        return false;
    }

    private function processRecommendations($page, array $recommendations): array
    {
        $contentItems = [];
        foreach ($recommendations as $recommendation) {
            $tocWithMetadata = $recommendation['tocWithMetadata'] ?? null;
            if (!$tocWithMetadata instanceof TocEntryWithMetadata) {
                throw new \LogicException('tocWithMetadata key must contain TocEntryWithMetadata');
            }
            $tocEntry    = $tocWithMetadata->getTocEntry();
            $contentItem = new ContentItem(
                $tocEntry->getTitle(),
                $tocEntry->getUrl(),
                $tocEntry->getDate()
            );

            $contentItem->attachTextSnippet($recommendation['snippet'] ?? '');
            $contentItem->attachTextSnippet($recommendation['snippet2'] ?? '');

            foreach ($tocWithMetadata->getImgCollection() as $image) {
                if ($image->hasNumericDimensions()) {
                    $contentItem->addImage($image->getSrc(), $image->getWidth(), $image->getHeight());
                }
            }

            $contentItems[] = $contentItem;
        }

        [$config, $log] = $this->layoutMatcher->match($page, ...$contentItems);

        return [$config, $log];
    }

    private function getCacheKey(ExternalId $externalId): string
    {
        return self::CACHE_KEY_PREFIX . $externalId->toString();
    }

    private function getValueForCache(ExternalId $externalId): array
    {
        return [
            $this->pdoStorage->getSimilar($externalId, null, 4, 9)
                ?: $this->pdoStorage->getSimilar($externalId, null, 2, 9),
            time()
        ];
    }
}
