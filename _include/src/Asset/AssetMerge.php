<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
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
    public const  TYPE_CSS              = 'css';
    public const  TYPE_JS               = 'js';
    private const META_KEY_FAILED_FILES = 'failed_files';
    private const META_KEY_CONTENT_HASH = 'hash';

    private array $filesToMerge = [];
    private array $failedExternalFiles = [];
    private string $mergedHash = '';
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
    public function getMergedPaths(): array
    {
        if ($this->needToDump() || !$this->readMetadata()) {
            $this->dumpContent();
        }

        $result   = $this->failedExternalFiles;
        $result[] = \sprintf('%s%s?v=%s', $this->publicCachePath, $this->getFilename(), $this->mergedHash);
        return $result;
    }

    private function minifyFiles(Minify $minifier): string
    {
        $this->failedExternalFiles = [];

        foreach ($this->filesToMerge as $fileToMerge) {
            $parsedUrl = parse_url($fileToMerge);
            if (isset($parsedUrl['host'])) {
                // file is external
                try {
                    $response = $this->httpClient->fetch($fileToMerge);
                    if (!$response->isSuccessful()) {
                        throw new \RuntimeException('Failed to fetch ' . $fileToMerge);
                    }
                    if ($response->content !== null) {
                        $minifier->add($response->content);
                    }
                } catch (\Exception $e) {
                    // Store failed file and continue with next one
                    $this->failedExternalFiles[] = $fileToMerge;
                }
            } else {
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
        $this->filesystem()->remove($this->getDumpTempFilename());
        $this->mergedHash = md5($content);
        $this->filesystem()->dumpFile($this->getMetadataFilename(), '<?php return ' . var_export([
                self::META_KEY_CONTENT_HASH => $this->mergedHash,
                self::META_KEY_FAILED_FILES => $this->failedExternalFiles,
            ], true) . ';');
        if (\function_exists('opcache_invalidate')) {
            opcache_invalidate($this->getMetadataFilename(), true);
        }
    }

    private function needToDump(): bool
    {
        if (!file_exists($this->getDumpFilename())) {
            return true;
        }

        if ($this->devEnv) {
            // TODO add tracking of modified images that are embedded in CSS
            $dumpModifiedAt = filemtime($this->getDumpFilename());
            foreach ($this->filesToMerge as $fileToMerge) {
                $parsedUrl = parse_url($fileToMerge);
                if (isset($parsedUrl['host'])) {
                    // file is external
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

    private function getMetadataFilename(): string
    {
        return \sprintf('%s%s.meta.php', $this->publicCacheDir, $this->getFilename());
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

    private function readMetadata(): bool
    {
        $metadataFilename = $this->getMetadataFilename();
        if (!file_exists($metadataFilename)) {
            return false;
        }

        $result = @include $metadataFilename;
        if (!\is_array($result)) {
            return false;
        }
        if (!isset($result[self::META_KEY_CONTENT_HASH])) {
            return false;
        }
        if (!isset($result[self::META_KEY_FAILED_FILES])) {
            return false;
        }
        $this->failedExternalFiles = $result[self::META_KEY_FAILED_FILES];
        $this->mergedHash          = $result[self::META_KEY_CONTENT_HASH];

        return true;
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
