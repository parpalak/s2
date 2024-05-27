<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace unit\Cms\Layout;

use Codeception\Test\Unit;
use S2\Cms\Layout\LayoutMatcher;

/**
 * @group layout-matcher
 */
class LayoutMatcherTest extends Unit
{
    public static function distributeItemsProvider(): array
    {
        return [
            'case 1' => [
                'mapItemToGroups' => [
                    [0, 1, 2],
                    [0, 2],
                    [1, 2],
                    [2],
                    [2],
                ],
                'mapGroupToItems' => [
                    0 => [0, 1],
                    1 => [0, 2],
                    2 => [0, 1, 2, 3, 4],
                ],
                'blocksInGroup'   => [
                    0 => 1,
                    1 => 2,
                    2 => 2,
                ],
                'expectedResult'  => [
                    0 => [1],
                    1 => [0, 2],
                    2 => [3, 4],
                ],
            ],
            'case 2' => [
                'mapItemToGroups' => [
                    [0, 1],
                    [0],
                    [0, 2],
                    [1, 2],
                    [1, 2],
                ],
                'mapGroupToItems' => [
                    0 => [0, 1, 2],
                    1 => [0, 3, 4],
                    2 => [2, 3, 4],
                ],
                'blocksInGroup'   => [
                    0 => 2,
                    1 => 2,
                    2 => 1,
                ],
                'expectedResult'  => [
                    0 => [0, 1],
                    1 => [3, 4],
                    2 => [2],
                ],
            ],
            'case 3' => [
                'mapItemToGroups' => [
                    [3, 4],
                    [0, 3, 4],
                    [1, 2, 3, 4],
                    [0, 3, 4],
                    [3, 4],
                    [3, 4],
                    [3, 4],
                    [3, 4],
                    [1, 2, 3, 4],
                ],
                'mapGroupToItems' => [
                    0 => [1, 3],
                    1 => [2, 8],
                    2 => [2, 8],
                    3 => [0, 1, 2, 3, 4, 5, 6, 7, 8],
                    4 => [0, 1, 2, 3, 4, 5, 6, 7, 8],
                ],
                'blocksInGroup'   => [
                    0 => 1,
                    1 => 2,
                    2 => 1,
                    3 => 1,
                    4 => 4,
                ],
                'expectedResult'  => null,
            ],
            'case 4' => [
                'mapItemToGroups' => [
                    [3, 4],
                    [0, 3, 4],
                    [1, 2, 3, 4],
                    [0, 1, 3, 4],
                    [3, 4],
                    [3, 4],
                    [3, 4],
                    [3, 4],
                    [1, 2, 3, 4],
                ],
                'mapGroupToItems' => [
                    [1, 3],
                    [2, 3, 8],
                    [2, 8],
                    [0, 1, 2, 3, 4, 5, 6, 7, 8],
                    [0, 1, 2, 3, 4, 5, 6, 7, 8],
                ],
                'blocksInGroup'   => [
                    1,
                    2,
                    1,
                    1,
                    4,
                ],
                'expectedResult'  => [
                    [1],
                    [2, 3],
                    [8],
                    [0],
                    [4, 5, 6, 7],
                ],
            ],
            'case 5' => [
                'mapItemToGroups' => [
                    [1, 2],
                    [0, 1],
                    [2],
                    [0],
                ],
                'mapGroupToItems' => [
                    [1, 3],
                    [0, 1],
                    [0, 2],
                ],
                'blocksInGroup'   => [
                    1,
                    2,
                    1,
                ],
                'expectedResult'  => [
                    [3],
                    [0, 1],
                    [2],
                ],
            ],
            'case 6' => [
                'mapItemToGroups' => [
                    [0, 2],
                    [0, 1],
                    [0],
                ],
                'mapGroupToItems' => [
                    [0, 1, 2],
                    [1, 2],
                    [0],
                ],
                'blocksInGroup'   => [
                    1,
                    1,
                    1,
                ],
                'expectedResult'  => [
                    [2],
                    [1],
                    [0],
                ],
            ],
            'empty' => [
                'mapItemToGroups' => [
                ],
                'mapGroupToItems' => [
                ],
                'blocksInGroup'   => [
                ],
                'expectedResult'  => [
                ],
            ],
        ];
    }


    /**
     * @dataProvider distributeItemsProvider
     */
    public function testDistributeItems(array $mapGroupToItems, array $mapItemToGroups, array $blocksInGroup, ?array $expectedResult): void
    {
        $result = LayoutMatcher::distributeItemsOverBlocks($mapItemToGroups, $blocksInGroup);
        $this->assertEquals($expectedResult, $result);
    }
}
