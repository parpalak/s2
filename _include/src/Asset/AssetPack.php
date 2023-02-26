<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Asset;

class AssetPack
{
    public const OPTION_PRELOAD = 'preload';
    public const OPTION_DEFER   = 'defer';
    public const OPTION_ASYNC   = 'async';
    public const OPTION_MERGE   = 'merge';

    private int $version = 0;
    private array $meta = [];
    private array $css = [];
    private array $headJs = [];
    private array $headInlineJs = [];
    private array $js = [];
    private array $inlineJs = [];
    private array $preload = [];
    private ?string $favIcon = null;

    public function addCss(string $filename, array $options = []): self
    {
        $o = array_flip($options);

        $merge = isset($o[self::OPTION_MERGE]);
        unset($o[self::OPTION_MERGE]);
        if (\count($o) > 0) {
            throw new \DomainException(sprintf('Found unknown options [%s] for style "%s".', implode(', ', array_keys($o)), $filename));
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
            throw new \DomainException(sprintf('Async and defer options cannot be used together for script "%s".', $filename));
        }

        unset($o[self::OPTION_MERGE], $o[self::OPTION_ASYNC], $o[self::OPTION_DEFER], $o[self::OPTION_PRELOAD]);
        if (\count($o) > 0) {
            throw new \DomainException(sprintf('Found unknown options [%s] for script "%s".', implode(', ', array_keys($o)), $filename));
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

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function addHeadJs(string $filename, array $options = []): self
    {
        $o = array_flip($options);
        if (isset($o[self::OPTION_PRELOAD])) {
            throw new \DomainException(sprintf('JS script "%s" in head cannot be preloaded.', $filename));
        }

        if (isset($o[self::OPTION_MERGE])) {
            throw new \DomainException(sprintf('JS script "%s" in head cannot be merged. Try to use addJs() method instead.', $filename));
        }

        $isAsync = isset($o[self::OPTION_ASYNC]);
        $isDefer = isset($o[self::OPTION_DEFER]);
        if ($isAsync && $isDefer) {
            throw new \DomainException(sprintf('Async and defer options cannot be used together for script "%s".', $filename));
        }

        unset($o[self::OPTION_ASYNC], $o[self::OPTION_DEFER]);
        if (\count($o) > 0) {
            throw new \DomainException(sprintf('Found unknown options [%s] for script "%s".', implode(', ', array_keys($o)), $filename));
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

    public function getStyles(string $localPrefix): string
    {
        $result = [];
        foreach ($this->meta as $item) {
            $result[] = $item;
        }

        foreach ($this->preload as $preloadItem) {
            $preloadPath = $preloadItem['src'];
            if (self::requireDirPrefix($preloadPath)) {
                $preloadPath = $localPrefix . $preloadPath;
            }
            $result[] = "<link rel=\"preload\" href=\"$preloadPath\" as=\"{$preloadItem['as']}\">";
        }

        foreach ($this->css as $cssItem) {
            $cssPath = $cssItem['src'];
            if (self::requireDirPrefix($cssPath)) {
                $cssPath = $localPrefix . $cssPath;
            }
            $result[] = "<link rel=\"stylesheet\" href=\"{$cssPath}\" />";
        }

        foreach ($this->headJs as $jsItem) {
            $result[] = sprintf(
                '<script src="%s"%s%s></script>',
                self::getPrefixedPath($jsItem['src'], $localPrefix) . '?v=' . $this->version,
                ($jsItem['is_defer'] ?? false) ? ' defer' : '',
                ($jsItem['is_async'] ?? false) ? ' async' : ''
            );
        }

        if ($this->favIcon !== null) {
            $result[] = '<link rel="shortcut icon" type="' . self::getFaviconMimeType($this->favIcon) . '" href="' . self::getPrefixedPath($this->favIcon, $localPrefix) . '">';
        }

        $result = array_merge($result, $this->headInlineJs);

        return implode("\n", $result);
    }

    public function getScripts(string $localPrefix): string
    {
        $result = [];
        foreach ($this->js as $jsItem) {
            $result[] = sprintf(
                '<script src="%s"%s%s></script>',
                self::getPrefixedPath($jsItem['src'], $localPrefix) . '?v=' . $this->version,
                ($jsItem['is_defer'] ?? false) ? ' defer' : '',
                ($jsItem['is_async'] ?? false) ? ' async' : ''
            );
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

    /** @noinspection SubStrUsedAsStrPosInspection */
    private static function requireDirPrefix(string $path): bool
    {
        if ($path[0] === '/') {
            return false;
        }

        if (substr($path, 0, 7) === 'http://') {
            return false;
        }

        if (substr($path, 0, 8) === 'https://') {
            return false;
        }

        return true;
    }

    private static function getPrefixedPath($jsPath, string $localPrefix)
    {
        if (self::requireDirPrefix($jsPath)) {
            $jsPath = $localPrefix . $jsPath;
        }

        return $jsPath;
    }
}
