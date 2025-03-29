<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Layout;

class Block
{
    private int $titleSize = 1;
    private ?int $titleLargeLengthLimit = null;

    private bool $hasImage = false;
    private float $imageMinRatio = 0.05;
    private ?float $imageMaxRatio = null;
    private ?int $imageMinWidth = null;
    private ?int $imageMaxWidth = null;
    private string $imageClass = '';

    private bool $hasText = false;
    private int $textMinLength = 0;
    private ?int $textMaxLength = null;
    private float $extraLengthForWideImg = 0;

    private ?string $hash = null;

    public function __construct()
    {
    }

    public static function thumbnail(): self
    {
        $instance = new static();
        // http://localhost:8081/?/blog/2012/01/21/churoffmetics - 400
        // http://localhost:8081/?/blog/2011/11/13/free_will 1.8
        return $instance->img(0, 1.2, 90, 400)->imgClass('thumb');
    }

    public static function img1column(): self
    {
        return (new static())->img(0, 0.83, 300);
    }

    public static function img1columnTall(): self
    {
        return (new static())->img(0.8, 1.5, 300);
    }

    public static function imgRight(): self
    {
        return (new static())->img(1, 4, 180)->imgClass('right');
    }

    public static function imgRight2(): self
    {
        return (new static())->img(1, 6, 80)->imgClass('right2');
    }

    public function text(int $minLength = 0, ?int $maxLength = null, float $extraLengthForWideImg = 0): self
    {
        if ($this->hasText) {
            throw new \LogicException('Text config is already specified.');
        }
        $this->textMinLength         = $minLength;
        $this->textMaxLength         = $maxLength;
        $this->extraLengthForWideImg = $extraLengthForWideImg;
        $this->hasText               = true;

        return $this;
    }

    public function img(float $minRatio = 0.05, ?float $maxRatio = null, ?int $minWidth = null, ?int $maxWidth = null): self
    {
        if ($this->hasImage) {
            throw new \LogicException('Image config is already specified.');
        }

        if ($maxRatio !== null && $minRatio > $maxRatio) {
            throw new \InvalidArgumentException(\sprintf('Invalid constraints: $minRatio (%s)> $maxRatio (%s).', $minRatio, $maxRatio));
        }

        if ($minWidth !== null && $maxWidth !== null && $minWidth > $maxWidth) {
            throw new \InvalidArgumentException(\sprintf('Invalid constraints: $minWidth (%s) > $maxWidth (%s).', $minWidth, $maxWidth));
        }

        $this->imageMinRatio = $minRatio;
        $this->imageMaxRatio = $maxRatio;
        $this->imageMinWidth = $minWidth;
        $this->imageMaxWidth = $maxWidth;
        $this->hasImage      = true;

        return $this;
    }

    public function imgClass(string $class = ''): self
    {
        $this->imageClass = $class;

        return $this;
    }

    public function bigTitle(?int $lengthLimit = null): self
    {
        $this->titleSize             = 2;
        $this->titleLargeLengthLimit = $lengthLimit;

        return $this;
    }

    public function hasImage(): bool
    {
        return $this->hasImage;
    }

    public function getImageClass(): string
    {
        return $this->imageClass;
    }

    public function getImageMinRatio(): float
    {
        return $this->imageMinRatio;
    }

    public function getImageMaxRatio(): ?float
    {
        return $this->imageMaxRatio;
    }

    public function getImageMinWidth(): ?int
    {
        return $this->imageMinWidth;
    }

    public function getImageMaxWidth(): ?int
    {
        return $this->imageMaxWidth;
    }

    public function hasText(): bool
    {
        return $this->hasText;
    }

    public function getTextMinLength(): int
    {
        return $this->textMinLength;
    }

    public function getTextMaxLength(): ?int
    {
        return $this->textMaxLength;
    }

    public function getExtraLengthForWideImg(): float
    {
        return $this->extraLengthForWideImg;
    }

    public function getTitleSize(string $title): int
    {
        if ($this->titleSize > 1 && $this->titleLargeLengthLimit !== null && mb_strlen($title) > $this->titleLargeLengthLimit) {
            // Title is too long to be large.
            return 1;
        }

        return $this->titleSize;
    }

    public function getHash(): string
    {
        try {
            return $this->hash ?? $this->hash = json_encode(get_object_vars($this), JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \LogicException('Impossible json_encode error');
        }
    }

    public function sortByImageHeight(): bool
    {
        return $this->hasImage /*&& \in_array($this->imageClass, ['', 'thumb'], true)*/;
    }
}
