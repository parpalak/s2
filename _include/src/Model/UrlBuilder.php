<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

readonly class UrlBuilder
{
    public function __construct(
        private string $basePath,
        private string $baseUrl,
        private string $urlPrefix
    ) {
    }

    public function link(string $path = '', array $params = []): string
    {
        return $this->basePath . $this->getRelativeUrl($path, $params);
    }

    public function absLink(string $path = '', array $params = []): string
    {
        return $this->baseUrl . $this->getRelativeUrl($path, $params);
    }

    private function getRelativeUrl(string $path, array $params): string
    {
        return $this->urlPrefix . $path . (!empty($params) ? ($this->urlPrefix ? '&amp;' : '?') . implode('&amp;', $params) : '');
    }
}
