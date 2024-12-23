<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use S2\Cms\Model\UrlBuilder;

class BlogUrlBuilder
{
    private ?string $blogPath = null;
    private ?string $absBlogPath = null;
    private ?string $blogTagsPath = null;

    public function __construct(
        private readonly UrlBuilder $urlBuilder,
        private readonly string     $tagsUrl,
        private readonly string     $favoriteUrl,
        private readonly string     $blogUrl,
    ) {
    }

    public function main(): string
    {
        return $this->blogPath ?? $this->blogPath = $this->urlBuilder->link($this->encodedBlogUrl() . '/');
    }

    public function absMain(): string
    {
        return $this->absBlogPath ?? $this->absBlogPath = $this->urlBuilder->absLink($this->encodedBlogUrl() . '/');
    }

    public function favorite(): string
    {
        return $this->main() . rawurlencode($this->favoriteUrl) . '/';
    }

    public function tags(): string
    {
        return $this->blogTagsPath ?? $this->blogTagsPath = $this->main() . rawurlencode($this->tagsUrl) . '/';
    }

    public function tag(string $tagUrl): string
    {
        return $this->tags() . rawurlencode($tagUrl) . '/';
    }

    public function year(int $year): string
    {
        return $this->main() . $year . '/';
    }

    public function month(int $year, int $month): string
    {
        return $this->main() . $year . '/' . self::extendNumber($month) . '/';
    }

    public function monthFromTimestamp(int $timestamp): string
    {
        return $this->main() . date('Y/m/', $timestamp);
    }

    public function day(int $year, int $month, int $day): string
    {
        return $this->main() . $year . '/' . self::extendNumber($month) . '/' . self::extendNumber($day) . '/';
    }

    public function post(int $year, int $month, int $day, string $url): string
    {
        return $this->main() . $year . '/' . self::extendNumber($month) . '/' . self::extendNumber($day) . '/' . rawurlencode($url);
    }

    public function postFromTimestamp(int $createTime, string $url): string
    {
        return $this->main() . date('Y/m/d/', $createTime) . rawurlencode($url);
    }

    public function absPostFromTimestamp(int $createTime, string $url): string
    {
        return $this->absMain() . date('Y/m/d/', $createTime) . rawurlencode($url);
    }

    public function postFromTimestampWithoutPrefix(int $createTime, string $url): string
    {
        return $this->encodedBlogUrl() . date('/Y/m/d', $createTime) . '/' . rawurlencode($url);
    }

    public function blogIsOnTheSiteRoot(): bool
    {
        return $this->blogUrl === '';
    }

    private static function extendNumber(int $month): string
    {
        return str_pad((string)$month, 2, '0', STR_PAD_LEFT);
    }

    private function encodedBlogUrl(): string
    {
        return str_replace(rawurlencode('/'), '/', rawurlencode($this->blogUrl));
    }
}
