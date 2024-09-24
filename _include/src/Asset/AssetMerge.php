<?php
/**
 * @copyright 2023-2024 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Asset;

use MatthiasMullie\Minify;
use Symfony\Component\Filesystem\Filesystem;

class AssetMerge implements AssetMergeInterface
{
    public const TYPE_CSS = 'css';
    public const TYPE_JS  = 'js';

    private array $filesToMerge = [];
    private ?Filesystem $filesystem = null;

    public function __construct(
        private readonly string $publicCacheDir,
        private readonly string $publicCachePath,
        private readonly string $cacheFilenamePrefix,
        private readonly string $type,
        private readonly bool   $devEnv
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function concat(string $fileName): void
    {
        $this->filesToMerge[] = $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function getMergedPath(): string
    {
        if ($this->needToDump()) {
            $this->dumpContent();
        }

        return sprintf('%s%s?v=%s', $this->publicCachePath, $this->getFilename(), $this->getCacheHash());
    }

    private function dumpContent(): void
    {
        if ($this->type === self::TYPE_CSS) {
            $minifier = new Minify\CSS();
            $minifier->setMaxImportSize(4);
            foreach ($this->filesToMerge as $fileToMerge) {
                $minifier->add($fileToMerge);
            }
            // Taking realpath here since there are some bugs in dependency for relative paths
            $content = $minifier->minify($this->getDumpFilename(true));
        } elseif ($this->type === self::TYPE_JS) {
            $minifier = new Minify\JS();
            foreach ($this->filesToMerge as $fileToMerge) {
                $minifier->add($fileToMerge);
            }
            // Taking realpath here since there are some bugs in dependency for relative paths
            $content = $minifier->minify($this->getDumpFilename(true));
        } else {
            $content = $this->getConcatenatedContent();
        }

        $this->filesystem()->dumpFile($this->getDumpFilename(), $content);
        if (\function_exists('gzencode')) {
            $this->filesystem()->dumpFile($this->getDumpFilename() . '.gz', gzencode($content, 6));
        }
        $this->filesystem()->dumpFile($this->getHashFilename(), '<?php return "' . md5($content) . '";');
    }

    private function needToDump(): bool
    {
        if (!file_exists($this->getDumpFilename())) {
            return true;
        }

        if ($this->devEnv) {
            // TODO add images embedding in CSS
            $dumpModifiedAt = filemtime($this->getDumpFilename());
            foreach ($this->filesToMerge as $fileToMerge) {
                if (filemtime($fileToMerge) > $dumpModifiedAt) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getDumpFilename(bool $realPath = false): string
    {
        return sprintf('%s%s', $realPath ? realpath($this->publicCacheDir) . '/' : $this->publicCacheDir, $this->getFilename());
    }

    private function getHashFilename(): string
    {
        return sprintf('%s%s.hash.php', $this->publicCacheDir, $this->getFilename());
    }

    private function getFilename(): string
    {
        return sprintf('%s.%x.%s', $this->cacheFilenamePrefix, crc32(serialize($this->filesToMerge)), $this->type);
    }

    private function fileSystem(): Filesystem
    {
        return $this->filesystem ?? $this->filesystem = new Filesystem();
    }

    private function getCacheHash(): string
    {
        $hashFilename = $this->getHashFilename();
        if (!file_exists($hashFilename)) {
            return '';
        }

        $result = @include $hashFilename;

        return \is_string($result) ? $result : '';
    }

    private function getConcatenatedContent(): string
    {
        $content = '';
        foreach ($this->filesToMerge as $fileToMerge) {
            if ($content !== '') {
                $content .= "\n";
            }
            $content .= file_get_contents($fileToMerge);
        }

        return $content;
    }
}
