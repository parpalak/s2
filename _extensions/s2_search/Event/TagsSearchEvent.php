<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Event;

class TagsSearchEvent
{
    private ?string $string = null;
    private array $shortStrings = [];

    public function __construct(
        public readonly array $where,
        public readonly array $words,
    ) {
    }

    public function addShortLine(string $shortLine): void
    {
        $this->shortStrings[] = $shortLine;
    }

    public function addLine(string $string): void
    {
        if ($this->string !== null) {
            throw new \LogicException('String is already set');
        }
        $this->string = $string;
    }

    public function getLine(): ?string
    {
        if ($this->string === null) {
            return null;
        }

        return $this->string . implode('', $this->shortStrings);
    }
}
