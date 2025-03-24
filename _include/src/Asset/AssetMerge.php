<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Asset;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use MatthiasMullie\Minify\Minify;
use S2\Cms\HttpClient\HttpClient;
use Symfony\Component\Filesystem\Filesystem;

class AssetMerge implements AssetMergeInterface
{
    public const TYPE_CSS = 'css';
    public const TYPE_JS  = 'js';

    private array $filesToMerge = [];
    private ?Filesystem $filesystem = null;

    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string     $publicCacheDir,
        private readonly string     $publicCachePath,
        private readonly string     $cacheFilenamePrefix,
        private readonly string     $type,
        private readonly bool       $devEnv
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

        return \sprintf('%s%s?v=%s', $this->publicCachePath, $this->getFilename(), $this->getCacheHash());
    }

    private function minifyFiles(Minify $minifier): string
    {
        foreach ($this->filesToMerge as $fileToMerge) {
            $parsedUrl = parse_url($fileToMerge);
            if (isset($parsedUrl['host'])) {
                // file is elsewhere
                $response    = $this->httpClient->fetch($fileToMerge);
                if (!$response->isSuccessful()) {
                    throw new \RuntimeException('Failed to fetch ' . $fileToMerge);
                }
                $fileToMerge = $response->content;
            }
            if ($fileToMerge !== null) {
                $minifier->add($fileToMerge);
            }
        }

        /**
         * Using a "fake" temp filename to dump.
         * 1. It is constructed using a realpath(). Otherwise, the minifier converts relative paths in CSS with errors.
         * 2. Minifier allows file corruptions on race condition. We do not trust the resulted file and ignore it.
         * The file will be dumped again later with an atomic operation.
         */
        return $minifier->minify($this->getDumpTempFilename());
    }

    private function dumpContent(): void
    {
        if ($this->type === self::TYPE_CSS) {
            $minifier = new CSS();
            $minifier->setMaxImportSize(4);
            $content = $this->minifyFiles($minifier);
        } elseif ($this->type === self::TYPE_JS) {
            $minifier = new JS();
            $content  = $this->minifyFiles($minifier);
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
                $parsedUrl = parse_url($fileToMerge);
                if (isset($parsedUrl['host'])) {
                    // file is elsewhere
                    continue;
                }
                if (filemtime($fileToMerge) > $dumpModifiedAt) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getDumpFilename(): string
    {
        return \sprintf('%s%s', $this->publicCacheDir, $this->getFilename());
    }

    private function getDumpTempFilename(): string
    {
        return \sprintf('%s%s', realpath($this->publicCacheDir) . '/', $this->getFilename('tmp'));
    }

    private function getHashFilename(): string
    {
        return \sprintf('%s%s.hash.php', $this->publicCacheDir, $this->getFilename());
    }

    private function getFilename(?string $postfix = null): string
    {
        return \sprintf(
            '%s.%x.%s',
            $this->cacheFilenamePrefix,
            crc32(serialize($this->filesToMerge)),
            ($postfix !== null ? $postfix . '.' : '') . $this->type
        );
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
