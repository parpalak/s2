<?php declare(strict_types=1);
/**
 * @copyright 2023 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
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

    /**
     * This function tries to distribute items over blocks based on the mapping of which items can be placed in which
     * block groups. Example:
     *  |        | i1 | i2 | i3 | i4 | i5 |
     *  | b1     | *  | *  |    |    |    |
     *  | b2, b3 | *  |    | *  |    |    |
     *  | b4, b5 | *  | *  | *  | *  | *  |
     *  The result is a match: b1 -> i2, (b2, b3) -> (i1, i3), (b4, b5) -> (i4, i5).
     *
     * For this example input parameters will be:
     * $mapItemToGroups = [
     *     [0, 1, 2],
     *     [0, 2],
     *     [1, 2],
     *     [2],
     *     [2],
     * ];
     * $blocksInGroup = [
     *     1,
     *     2,
     *     2,
     * ];
     *
     * Result:
     * $result = [
     *     [1],
     *     [0, 2],
     *     [3, 4],
     * ];
     */
    public static function distributeItemsOverBlocks(array $mapItemToGroups, array $blocksInGroup): ?array
    {
        $result = [];

        // Initialize the result array with empty arrays for each group.
        foreach ($blocksInGroup as $group => $blockCount) {
            $result[$group] = [];
        }

        // Sort items by the number of groups they can be placed in (ascending order).
        uksort($mapItemToGroups, static function ($a, $b) use ($mapItemToGroups) {
            return \count($mapItemToGroups[$a]) <=> \count($mapItemToGroups[$b]);
        });

        // Recursive backtracking function to allocate items to groups.
        $allocate = static function ($item, &$result) use ($mapItemToGroups, $blocksInGroup, &$allocate) {
            if (!isset($mapItemToGroups[$item])) { // The end is reached: item index is out of range
                return true;
            }

            foreach ($mapItemToGroups[$item] as $groupIdx) {
                if (\count($result[$groupIdx]) < $blocksInGroup[$groupIdx]) {
                    $result[$groupIdx][] = $item;

                    if ($allocate($item + 1, $result)) {
                        return true;
                    }

                    // Backtrack
                    array_pop($result[$groupIdx]);
                }
            }

            return false;
        };

        if (!$allocate(0, $result)) {
            return null;
        }

        foreach ($result as &$items) {
            sort($items);
        }
        unset($items);
        return $result;
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

        $distributionTimes = [];

        foreach ($this->templatesList as $templateName => $templateGroups) {
            $templateName = (string)$templateName; // Integers in array keys are converted to int
            $log[]        = self::formatTime($start) . " >>>>> '$templateName': start";
            $blockCount   = array_sum(array_map(static fn(BlockGroup $bg) => $bg->count(), $templateGroups));

            if ($blockCount > $contentItemsNum) {
                $log[] = self::formatTime($start) . " '$templateName': no match due to count constraint ($blockCount > $contentItemsNum)";
                continue;
            }

            $mapGroupToItems = [];
            $mapItemToGroups = [];

            $blocksInPrevAndCurGroup = 0;
            $blocksInGroup           = [];
            foreach ($templateGroups as $idx => $templateGroup) {
                for ($i = 0; $i < $blockCount; $i++) {
                    $contentItem = $contentItems[$i];
                    if ($contentItem->match($templateGroup->getBlock())) {
                        $mapGroupToItems[$idx][] = $i;
                        $mapItemToGroups[$i][]   = $idx;

                        $log[] = sprintf(
                            "%s item %d match ['%s']",
                            self::formatTime($start),
                            $i,
                            implode('\', \'', $templateGroup->getPositions())
                        );
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
                $blocksInGroup[$idx]     = $blocksInCurGroup;
                $blocksInPrevAndCurGroup += $blocksInCurGroup;
                if (($matchedCount = \count($mapItemToGroups)) < $blocksInPrevAndCurGroup) {
                    $log[] = self::formatTime($start) . " '$templateName': no match for several groups (0..$idx) $matchedCount < required $blocksInPrevAndCurGroup";
                    continue 2;
                }
            }

            /**
             * Here we know what items can be placed in block groups ($mapGroupToItems). Example:
             * |        | i1 | i2 | i3 | i4 | i5 |
             * | b1     | *  | *  |    |    |    |
             * | b2, b3 | *  |    | *  |    |    |
             * | b4, b5 | *  | *  | *  | *  | *  |
             * Some kind of search algorithm is required to find a match: b1 -> i2, (b2, b3) -> (i1, i3), (b4, b5) -> (i4, i5).
             */
            $startDistribution   = microtime(true);
            $processedMap        = self::distributeItemsOverBlocks($mapItemToGroups, $blocksInGroup);
            $distributionTimes[] = 1000 * (microtime(true) - $startDistribution);
            if ($processedMap === null) {
                $log[] = self::formatTime($start) . " '$templateName': cannot distribute items over blocks";
                continue;
            }

            $hasUnusedImages = false;
            foreach ($processedMap as $groupIdx => $itemsToUse) {
                $templateGroup = $templateGroups[$groupIdx];

                if ($templateGroup->count() !== \count($itemsToUse)) {
                    $this->logger->error(sprintf('Invalid distribution: count mismatch for template "%s" group "%s".', $templateName, $groupIdx), $processedMap);
                    continue 2;
                }

                $partialResult = [];
                $positions     = [];
                foreach ($templateGroup->getPositions() as $idx2 => $position) {
                    $i = $itemsToUse[$idx2];

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
                $result[$groupIdx] = $partialResult;
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
                'distr_times'                                   => implode(' ', $distributionTimes),
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
