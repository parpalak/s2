<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
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

    /**
     * @return string HTML-escaped relative link
     */
    public function link(string $path = '', array $params = []): string
    {
        return $this->basePath . $this->getRelativeUrl($path, $params);
    }

    /**
     * @return string Raw relative link, suitable for headers
     */
    public function rawLink(string $path = '', array $params = []): string
    {
        return $this->basePath . $this->getRelativeUrl($path, $params, '&');
    }

    /**
     * @return string HTML-escaped full link with protocol and domain
     */
    public function absLink(string $path = '', array $params = []): string
    {
        return $this->baseUrl . $this->getRelativeUrl($path, $params);
    }

    /**
     * @return string Raw full link with protocol and domain, suitable for headers
     */
    public function rawAbsLink(string $path = '', array $params = []): string
    {
        return $this->baseUrl . $this->getRelativeUrl($path, $params, '&');
    }

    public function hasPrefix(): bool
    {
        return $this->urlPrefix !== '';
    }

    private function getRelativeUrl(string $path, array $params, string $amp = '&amp;'): string
    {
        return $this->urlPrefix . $path
            . (!empty($params)
                ? (str_contains($this->urlPrefix, '?') ? $amp : '?') . implode($amp, $params)
                : ''
            );
    }
}
