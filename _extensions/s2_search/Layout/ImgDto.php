<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Layout;

class ImgDto
{
    private string $src;
    private float $width;
    private float $height;
    private string $class;
    private array $srcSet = [];

    public function __construct(string $src, float $width, float $height, string $class)
    {
        if ($width < 1 || $height < 1) {
            throw new \DomainException(\sprintf('Invalid image dimensions: "%s" "%s".', $width, $height));
        }
        $this->src    = $src;
        $this->width  = $width;
        $this->height = $height;
        $this->class  = $class;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getRatio(): float
    {
        return $this->height / $this->width;
    }

    public function addSrc(string $src): self
    {
        $this->srcSet[] = $src;

        return $this;
    }

    public function getSrcSet(): array
    {
        return $this->srcSet;
    }
}
