<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace s2_extensions\s2_search\Layout;

class BlockGroup
{
    private Block $block;
    private array $positions;
    private ?int $cachedCount = null;

    public function __construct(array $positions, Block $block)
    {
        $this->block     = $block;
        $this->positions = $positions;
    }

    public function getBlock(): Block
    {
        return $this->block;
    }

    public function getPositions(): array
    {
        return $this->positions;
    }

    public function count(): int
    {
        return $this->cachedCount ?? $this->cachedCount = \count($this->positions);
    }
}
