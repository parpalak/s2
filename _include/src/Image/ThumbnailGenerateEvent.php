<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Image;

use Psr\EventDispatcher\StoppableEventInterface;

class ThumbnailGenerateEvent implements StoppableEventInterface
{
    private ?string $result = null;

    public function __construct(
        public string $src,
        public string $originalWidth,
        public string $originalHeight,
        public int    $maxWidth,
        public int    $maxHeight
    ) {
    }

    public function setResult(string $result): void
    {
        if ($this->result !== null) {
            throw new \LogicException('Result has already been set');
        }
        $this->result = $result;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function isPropagationStopped(): bool
    {
        return $this->result !== null;
    }
}
