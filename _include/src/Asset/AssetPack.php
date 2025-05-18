<?php /** @noinspection HtmlWrongAttributeValue */
/** @noinspection HtmlUnknownTarget */
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Asset;

class AssetPack
{
    public const OPTION_PRELOAD = 'preload';
    public const OPTION_DEFER   = 'defer';
    public const OPTION_ASYNC   = 'async';
    public const OPTION_MERGE   = 'merge';

    private array $meta = [];
    private array $css = [];
    private array $headJs = [];
    private array $headInlineJs = [];
    private array $js = [];
    private array $inlineJs = [];
    private array $preload = [];
    private ?string $favIcon = null;
    private string $localDir;

    public function __construct(string $localDir)
    {
        $this->localDir = rtrim($localDir, '/');
    }

    public function addCss(string $filename, array $options = []): self
    {
        $o = array_flip($options);

        $merge = isset($o[self::OPTION_MERGE]);
        unset($o[self::OPTION_MERGE]);
        if (\count($o) > 0) {
            throw new \DomainException(\sprintf('Found unknown options [%s] for style "%s".', implode(', ', array_keys($o)), $filename));
        }

        $this->css[] = ['src' => $filename, 'merge' => $merge];

        return $this;
    }

    public function addJs(string $filename, array $options = []): self
    {
        $o = array_flip($options);

        $isPreload = isset($o[self::OPTION_PRELOAD]);
        $isAsync   = isset($o[self::OPTION_ASYNC]);
        $isDefer   = isset($o[self::OPTION_DEFER]);
        $merge     = isset($o[self::OPTION_MERGE]);
        if ($isAsync && $isDefer) {
            throw new \DomainException(\sprintf('Async and defer options cannot be used together for script "%s".', $filename));
        }

        unset($o[self::OPTION_MERGE], $o[self::OPTION_ASYNC], $o[self::OPTION_DEFER], $o[self::OPTION_PRELOAD]);
        if (\count($o) > 0) {
            throw new \DomainException(\sprintf('Found unknown options [%s] for script "%s".', implode(', ', array_keys($o)), $filename));
        }

        if ($isPreload) {
            $this->preload[] = ['src' => $filename, 'as' => 'script'];
        }
        $this->js[] = ['src' => $filename, 'is_async' => $isAsync, 'is_defer' => $isDefer, 'merge' => $merge];

        return $this;
    }


    public function addInlineJs(string $code): self
    {
        $this->inlineJs[] = $code;

        return $this;
    }

    public function addMeta(string $code): self
    {
        $this->meta[] = $code;

        return $this;
    }

    public function addHeadJs(string $filename, array $options = []): self
    {
        $o = array_flip($options);
        if (isset($o[self::OPTION_PRELOAD])) {
            throw new \DomainException(\sprintf(
                'JS script "%s" in head cannot be preloaded.',
                $filename
            ));
        }

        if (isset($o[self::OPTION_MERGE])) {
            throw new \DomainException(\sprintf(
                'JS script "%s" in head cannot be merged. Try to use addJs() method instead.',
                $filename
            ));
        }

        $isAsync = isset($o[self::OPTION_ASYNC]);
        $isDefer = isset($o[self::OPTION_DEFER]);
        if ($isAsync && $isDefer) {
            throw new \DomainException(\sprintf(
                'Async and defer options cannot be used together for script "%s".',
                $filename
            ));
        }

        unset($o[self::OPTION_ASYNC], $o[self::OPTION_DEFER]);
        if (\count($o) > 0) {
            throw new \DomainException(\sprintf(
                'Found unknown options [%s] for script "%s".',
                implode(', ', array_keys($o)),
                $filename
            ));
        }

        $this->headJs[] = ['src' => $filename, 'is_async' => $isAsync, 'is_defer' => $isDefer];

        return $this;
    }

    public function addHeadInlineJs(string $code): self
    {
        $this->headInlineJs[] = $code;

        return $this;
    }

    public function setFavIcon(?string $favicon): self
    {
        $this->favIcon = $favicon;

        return $this;
    }

    /**
     * Return styles (as long as meta tags and scripts) to be included in the head section.
     *
     * @param string                   $pathPrefix Path prefix to be prepended to local file names
     * @param AssetMergeInterface|null $assetMerge
     *
     * @return string
     */
    public function getStyles(string $pathPrefix, ?AssetMergeInterface $assetMerge): string
    {
        $result = array_values($this->meta);

        foreach ($this->preload as $preloadItem) {
            $preloadPath = self::getPrefixedPath($preloadItem['src'], $pathPrefix);
            $result[]    = \sprintf('<link rel="preload" href="%s" as="%s">', $preloadPath, $preloadItem['as']);
        }

        foreach ($this->css as $cssItem) {
            if ($assetMerge !== null && $cssItem['merge'] ?? false) {
                $assetMerge->concat((self::requireDirPrefix($cssItem['src']) ? $this->localDir . '/' : '') . $cssItem['src']);
            } else {
                $cssPath  = self::getPrefixedPath($cssItem['src'], $pathPrefix);
                $result[] = \sprintf('<link rel="stylesheet" href="%s">', $cssPath);
            }
        }

        if ($assetMerge !== null && ($mergedPath = $assetMerge->getMergedPath()) !== null) {
            $result[] = \sprintf('<link rel="stylesheet" href="%s" />', $mergedPath);
        }

        foreach ($this->headJs as $jsItem) {
            $result[] = \sprintf(
            /** @lang text */ '<script src="%s"%s%s></script>',
                self::getPrefixedPath($jsItem['src'], $pathPrefix),
                ($jsItem['is_defer'] ?? false) ? ' defer' : '',
                ($jsItem['is_async'] ?? false) ? ' async' : ''
            );
        }

        if ($this->favIcon !== null) {
            $result[] = '<link rel="shortcut icon" type="' . self::getFaviconMimeType($this->favIcon) . '" href="' . self::getPrefixedPath($this->favIcon, $pathPrefix) . '">';
        }

        $result = array_merge($result, $this->headInlineJs);

        return implode("\n", $result);
    }

    /**
     * Return scripts to be included in the body section.
     *
     * @param string                   $pathPrefix Path prefix to be prepended to local file names
     * @param AssetMergeInterface|null $assetMerge
     *
     * @return string
     */
    public function getScripts(string $pathPrefix, ?AssetMergeInterface $assetMerge): string
    {
        $result = [];
        foreach ($this->js as $jsItem) {
            if ($assetMerge !== null && $jsItem['merge'] ?? false) {
                $assetMerge->concat((self::requireDirPrefix($jsItem['src']) ? $this->localDir . '/' : '') . $jsItem['src']);
            } else {
                $result[] = \sprintf(
                /** @lang text */ '<script src="%s"%s%s></script>',
                    self::getPrefixedPath($jsItem['src'], $pathPrefix),
                    ($jsItem['is_defer'] ?? false) ? ' defer' : '',
                    ($jsItem['is_async'] ?? false) ? ' async' : ''
                );
            }
        }

        if ($assetMerge !== null && ($mergedPath = $assetMerge->getMergedPath()) !== null) {
            $result[] = \sprintf('<script src="%s" defer></script>', $mergedPath);
        }

        $result = array_merge($result, $this->inlineJs);

        return implode("\n", $result);
    }

    private static function getFaviconMimeType(?string $filename): string
    {
        switch (pathinfo($filename, PATHINFO_EXTENSION)) {
            case 'ico':
                return 'image/vnd.microsoft.icon';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            case 'jpg':
            case 'jpeg':
                return 'image/jpg';
            case 'svg':
            case 'svgz':
                return 'image/svg+xml';
        }

        throw new \InvalidArgumentException('This file type is not allowed for a favicon image');
    }

    private static function requireDirPrefix(string $path): bool
    {
        if ($path[0] === '/') {
            return false;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return false;
        }

        return true;
    }

    private static function getPrefixedPath($path, string $dirPrefix)
    {
        if (self::requireDirPrefix($path)) {
            $path = $dirPrefix . $path;
        }

        return $path;
    }
}
