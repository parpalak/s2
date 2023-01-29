<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Layout;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use S2\Cms\Image\ImgDto;

class LayoutMatcher
{
    /**
     * @var array|BlockGroup[][]
     */
    private array $templatesList = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addGroup(string $key, BlockGroup ...$blockGroups): void
    {
        if (isset($this->templatesList[$key])) {
            throw new \InvalidArgumentException(sprintf('Block group "%s" is already registered.', $key));
        }
        $this->templatesList[$key] = $blockGroups;
    }

    public function match(string $page, ContentItem ...$contentItems): array
    {
        $start           = microtime(true);
        $log             = [];
        $contentItemsNum = \count($contentItems);
        if ($contentItemsNum === 0) {
            $this->logger->warning(sprintf('Requested layout for empty recommendations for page "%s".', $page), [
                'time' => self::formatTime($start),
            ]);

            return [null, $log];
        }

        foreach ($this->templatesList as $templateName => $templateGroups) {
            $log[]      = self::formatTime($start) . " >>>>> '$templateName': start";
            $blockCount = array_sum(array_map(static fn(BlockGroup $bg) => $bg->count(), $templateGroups));

            if ($blockCount > $contentItemsNum) {
                $log[] = self::formatTime($start) . " '$templateName': no match due to count condition ($blockCount > $contentItemsNum)";
                continue;
            }

            $mapGroupToItems  = [];
            $usedContentItems = [];

            $blocksInPrevAndCurGroup = 0;
            foreach ($templateGroups as $idx => $templateGroup) {
                for ($i = 0; $i < $blockCount; $i++) {
                    $contentItem = $contentItems[$i];
                    if ($contentItem->match($templateGroup->getBlock())) {
                        $mapGroupToItems[$idx][] = $i;
                        $usedContentItems[$i]    = true;

                        $positions = implode('\', \'', $templateGroup->getPositions());
                        $log[]     = self::formatTime($start) . " item $i match ['$positions']";
                    }
                }
                $foundCount       = \count($mapGroupToItems[$idx] ?? []);
                $blocksInCurGroup = $templateGroup->count();
                if ($foundCount < $blocksInCurGroup) {
                    $log[] = sprintf(
                        "%s '%s': not enough match [%s] for group [%s] found (%s < %s)",
                        self::formatTime($start),
                        $templateName,
                        implode(', ', $mapGroupToItems[$idx] ?? []),
                        implode(', ', $templateGroup->getPositions()),
                        $foundCount,
                        $blocksInCurGroup
                    );
                    continue 2;
                }
                $blocksInPrevAndCurGroup += $blocksInCurGroup;
                if (($matchedCount = \count($usedContentItems)) < $blocksInPrevAndCurGroup) {
                    $log[] = self::formatTime($start) . " '$templateName': no match for several groups (0..$idx) $matchedCount < required $blocksInPrevAndCurGroup";
                    continue 2;
                }
            }

            /**
             * Here we know what items can be placed in groups ($mapGroupToItems). Example:
             * |        | i1 | i2 | i3 | i4 | i5 |
             * | g1     | *  | *  |    |    |    |
             * | g2, g3 | *  |    | *  |    |    |
             * | g4, g5 | *  | *  | *  | *  | *  |
             * Some kind of search algorithm is required to find a match: g1 -> i2, (g2, g3) -> (i1, i3), (g4, g5) -> (i4, i5).
             *
             * I try some partial search hoping it would be quite good.
             *
             * Main search will go from small to large groups.
             */
            $itemsNumInGroups = array_map(static fn(array $a) => \count($a), $mapGroupToItems);
            asort($itemsNumInGroups);

            /**
             * Also let's sort items from more specific (has little matched groups) to more universal.
             */
            $stat = [];
            foreach ($mapGroupToItems as $itemsMatchedGroup) {
                foreach ($itemsMatchedGroup as $itemIdx) {
                    $stat[$itemIdx] = ($stat[$itemIdx] ?? 0) + 1;
                }
            }
            foreach ($mapGroupToItems as &$itemsMatchedGroup) {
                usort($itemsMatchedGroup, static fn(int $a, $b) => $stat[$a] <=> $stat[$b]);
            }
            unset($itemsMatchedGroup);

            $result          = [];
            $originalMap     = $mapGroupToItems;
            $hasUnusedImages = false;

            $accumulatedAvailableItems = [];
            $accumulatedVacanciesNum   = 0;
            foreach ($itemsNumInGroups as $idx => $itemNumInGroup) {
                $idx2          = 0;
                $templateGroup = $templateGroups[$idx];

                /**
                 * Recheck size for partial group union after sorting. (Same as above but soring may change the situation.)
                 */
                $accumulatedVacanciesNum   += $templateGroup->count();
                $accumulatedAvailableItems += array_combine($mapGroupToItems[$idx], $mapGroupToItems[$idx]);
                if (\count($accumulatedAvailableItems) < $accumulatedVacanciesNum) {
                    $log[] = sprintf(
                        "%s '%s': no match for several groups (%s) < required (%d)",
                        self::formatTime($start),
                        $templateName,
                        implode(', ', $accumulatedAvailableItems),
                        $accumulatedVacanciesNum
                    );
                    continue 2;
                }

                $partialResult = [];
                $positions     = [];
                foreach ($templateGroup->getPositions() as $position) {
                    while (true) {
                        if (!isset($mapGroupToItems[$idx][$idx2])) {
                            $log[] = self::formatTime($start) . " '$templateName': <b>warning</b> [$idx][$idx2]";
                            $this->logger->warning(sprintf('Cannot assign content items to block on page "%s": incomplete algorithm.', $page), [
                                'originalMap'  => $originalMap,
                                'templateName' => $templateName,
                            ]);
                            continue 4;

                            // TODO Prove that there is no solution in this case. Maybe it's a bad algorithm
                            // throw new \LogicException('Invalid group to content items mapping');
                        }
                        $i = $mapGroupToItems[$idx][$idx2];
                        $idx2++;
                        if (isset($usedContentItems[$i])) {
                            unset($usedContentItems[$i]);
                            break;
                        }
                    }
                    $contentItem  = $contentItems[$i];
                    $positions[]  = $position;
                    $matchedImage = $contentItem->getMatchedImage($templateGroup->getBlock());
                    if ($matchedImage === null && $contentItem->hasImage()) {
                        $hasUnusedImages = true;
                    }
                    $partialResult[] = [
                        'title'       => $contentItem->getTitle(),
                        'headingSize' => $templateGroup->getBlock()->getTitleSize($contentItem->getTitle()),
                        'url'         => $contentItem->getUrl(),
                        'date'        => $contentItem->getCreatedAt(),
                        'image'       => $matchedImage,
                        'snippet'     => $contentItem->getMatchedSnippet($templateGroup->getBlock()),
                    ];
                }

                if ($templateGroup->getBlock()->sortByImageHeight() && \count($partialResult) > 1) {
                    // Sort full-column images by height desc
                    // http://localhost:8081/?/blog/2012/03/26/presenter
                    // http://localhost:8081/?/blog/2012/01/28/kollaideru_net
                    // http://localhost:8081/?/blog/2011/12/25/Psychologists
                    usort($partialResult, static function (array $r1, array $r2) {
                        $ratioComparison = $r1['image']['w'] * $r2['image']['h'] <=> $r2['image']['w'] * $r1['image']['h'];
                        if ($ratioComparison === 0) {
                            return mb_strlen($r2['snippet']) + mb_strlen($r2['title']) <=> mb_strlen($r1['snippet']) + mb_strlen($r1['title']);
                        }

                        return $ratioComparison;
                    });
                }

                if (!$templateGroup->getBlock()->hasImage() && \count($partialResult) > 1) {
                    // Sort full-column images by height desc
                    // http://localhost:8081/?/blog/2021/08/27/fake_pop3_server
                    usort($partialResult, static function (array $r1, array $r2) {
                        return mb_strlen($r2['snippet']) + mb_strlen($r2['title']) <=> mb_strlen($r1['snippet']) + mb_strlen($r1['title']);
                    });
                }

                // Assign positions after sorting
                foreach ($positions as $k => $position) {
                    $partialResult[$k]['position'] = $position;

                    $imgArray = $partialResult[$k]['image'];
                    if ($imgArray !== null) {
                        $partialResult[$k]['image'] = new ImgDto($imgArray['src'], (float)$imgArray['w'], (float)$imgArray['h'], $imgArray['class']);
                    }
                }
                $result[$idx] = $partialResult;
            }
            // Restore order of layout
            ksort($result);

            $result        = array_merge(...$result);
            $formattedTime = self::formatTime($start);

            if ($hasUnusedImages && \count(array_filter($result, static fn(array $resultItem) => $resultItem['image'] !== null)) >= 5) {
                // 5 images is enough, do not log warning
                $hasUnusedImages = false;
            }
            $log[] = $formattedTime . " match" . ($hasUnusedImages ? ' (not all images used!)' : '') . " &nbsp; $templateName";

            $this->logger->log($hasUnusedImages ? LogLevel::WARNING : LogLevel::INFO, sprintf('Recommendations for page "%s" completed.', $page), [
                'time'                                          => $formattedTime,
                'templateName'                                  => $templateName,
                'tplid ' . str_replace(' ', '_', $templateName) => $page,
                '$hasUnusedImages'                              => $hasUnusedImages,
            ]);

            return [$result, $log];
        }

        $this->logger->warning(sprintf('No recommendations found for page "%s".', $page), [
            'time' => self::formatTime($start),
        ]);

        return [null, $log];
    }

    private static function formatTime(float $start): string
    {
        return (number_format((microtime(true) - $start) * 1000, 2));
    }
}
