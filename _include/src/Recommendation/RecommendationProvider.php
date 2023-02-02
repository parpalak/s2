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
use S2\Rose\Entity\ExternalId;
use S2\Rose\Entity\TocEntryWithMetadata;
use S2\Rose\Storage\Database\PdoStorage;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RecommendationProvider
{
    public const TAG_RECOMMENDATIONS = 'recommendations';
    private PdoStorage $pdoStorage;
    private LayoutMatcher $layoutMatcher;
    private CacheInterface $cache;

    public function __construct(PdoStorage $pdoStorage, LayoutMatcher $layoutMatcher, CacheInterface $cache)
    {
        $this->pdoStorage    = $pdoStorage;
        $this->layoutMatcher = $layoutMatcher;
        $this->cache         = $cache;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getRecommendations(string $page, ExternalId $externalId): array
    {
        $recommendations = $this->cache->get('recommendations_' . $externalId->toString(), fn(ItemInterface $item) => $this->pdoStorage->getSimilar($externalId));

        return array_merge($this->processRecommendations($page, $recommendations), [$recommendations]);
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
}
