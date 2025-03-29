<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Layout;

use s2_extensions\s2_search\Rose\CustomExtractor;

class ContentItem
{
    private string $title;
    private string $url;
    private ?\DateTime $createdAt;
    private array $snippets = [];
    private array $images = [];
    /**
     * @var array|MatchingContext[]
     */
    private array $matchedBlocks = [];

    public function __construct(string $title, string $url, ?\DateTime $createdAt)
    {
        $this->title     = $title;
        $this->url       = $url;
        $this->createdAt = $createdAt;
    }

    public function attachTextSnippet(string $snippet): void
    {
        if ($snippet !== '') {
            $this->snippets[] = $snippet;
        }
    }

    public function addImage(string $src, string $width, string $height): void
    {
        if (!is_numeric($width)) {
            throw new \InvalidArgumentException('Width must be a number');
        }
        if (!is_numeric($height)) {
            throw new \InvalidArgumentException('Height must be a number');
        }

        if (str_starts_with($src, CustomExtractor::YOUTUBE_PROTOCOL)) { // TODO organize hardcoded check
            $width  = '640';
            $height = '360';
        }
        $this->images[] = ['src' => $src, 'w' => $width, 'h' => $height, 'r' => $height / $width];
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function match(Block $block): bool
    {
        if (!isset($this->matchedBlocks[$block->getHash()])) {
            $this->matchedBlocks[$block->getHash()] = $this->getMatchContext($block);
        }

        return $this->matchedBlocks[$block->getHash()]->hasMatch();
    }

    public function getMatchedImage(Block $block): ?array
    {
        if (!isset($this->matchedBlocks[$block->getHash()])) {
            throw new \InvalidArgumentException('This block has not been checked for matching. Call match() method first.');
        }

        return $this->matchedBlocks[$block->getHash()]->getImage();
    }

    public function getMatchedSnippet(Block $block): string
    {
        if (!isset($this->matchedBlocks[$block->getHash()])) {
            throw new \InvalidArgumentException('This block has not been checked for matching. Call match() method first.');
        }

        return $this->matchedBlocks[$block->getHash()]->getSnippet();

    }

    /**
     * Checks if this item can be displayed in the block specified.
     *
     * @param Block $block Configuration of tht block specified
     *
     * @return MatchingContext Additional information required for displaying (what image to display and so on).
     */
    private function getMatchContext(Block $block): MatchingContext
    {
        $trueMatchContext = new MatchingContext(true);
        $freeSpaceHeight  = 0;

        if ($block->hasImage()) {
            $foundImage = null;
            $minRatio   = $block->getImageMinRatio();
            $maxRatio   = $block->getImageMaxRatio();
            $minWidth   = $block->getImageMinWidth();
            $maxWidth   = $block->getImageMaxWidth();
            foreach ($this->images as $image) {
                if ($minWidth > $image['w'] || ($maxWidth !== null && $maxWidth < $image['w'])) {
                    continue;
                }
                if ($minRatio >= $image['r'] || ($maxRatio !== null && $maxRatio < $image['r'])) {
                    continue;
                }
                $foundImage = $image;
                break;
            }
            if ($foundImage === null) {
                return new MatchingContext(false);
            }

            $freeSpaceHeight = $maxRatio - $foundImage['r'];
            $trueMatchContext->addImage($foundImage, $block->getImageClass());
        }

        if ($block->hasText()) {
            if (\count($this->snippets) === 0) {
                return new MatchingContext(false);
            }

            $textMinLength = $block->getTextMinLength();
            $textMaxLength = $block->getTextMaxLength();

            if ($textMaxLength !== null) {
                // For wide images
                $textMaxLength += $block->getExtraLengthForWideImg() * $freeSpaceHeight;
                // For very long titles
                $textMaxLength -= max(0, mb_strlen($this->title) - 35);
                $text          = '';
                $totalLength   = 0;
                foreach ($this->snippets as $snippet) {
                    $length      = mb_strlen(strip_tags($snippet));
                    $deltaLength = ($text !== '' ? 1 : 0) + $length;
                    if ($totalLength > $textMaxLength || $totalLength + $deltaLength > $textMaxLength) {
                        break;
                    }
                    $text        .= ($text !== '' ? ' ' : '') . $snippet;
                    $totalLength += $deltaLength;
                }
                $textNotGreaterMaxLimit = $text;
            } else {
                $textNotGreaterMaxLimit = implode(' ', $this->snippets);
            }
            if (mb_strlen($textNotGreaterMaxLimit) < $textMinLength) {
                return new MatchingContext(false);
            }
            $trueMatchContext->addSnippet($textNotGreaterMaxLimit);
        }

        return $trueMatchContext;
    }

    public function hasImage(): bool
    {
        return \count($this->images) > 0;
    }
}
