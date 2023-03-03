<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Asset;

use MatthiasMullie\Minify;
use Symfony\Component\Filesystem\Filesystem;

class AssetMerge implements AssetMergeInterface
{
    public const FILTER_CSS = 'css';

    private string $publicCacheDir;
    private string $publicCachePath;
    private string $cacheFileName;
    private bool $devEnv;

    private array $filesToMerge = [];
    private ?Filesystem $filesystem = null;
    private string $filter;

    public function __construct(
        string $publicCacheDir,
        string $publicCachePath,
        string $cacheFileName,
        string $filter,
        bool   $devEnv
    ) {
        $this->publicCacheDir  = $publicCacheDir;
        $this->publicCachePath = $publicCachePath;
        $this->cacheFileName   = $cacheFileName;
        $this->devEnv          = $devEnv;
        $this->filter          = $filter;
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

        return $this->publicCachePath . $this->cacheFileName . '?v=' . $this->getCacheHash();
    }

    protected function dumpContent(): void
    {
        if ($this->filter === self::FILTER_CSS) {
            $minifier = new Minify\CSS();
            $minifier->setMaxImportSize(4);
            foreach ($this->filesToMerge as $fileToMerge) {
                $minifier->add($fileToMerge);
            }
            $content = $minifier->minify($this->getDumpFilename());
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
            $dumpModifiedAt = filemtime($this->getDumpFilename());
            foreach ($this->filesToMerge as $fileToMerge) {
                if (filemtime($fileToMerge) > $dumpModifiedAt) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getDumpFilename(): string
    {
        return $this->publicCacheDir . $this->cacheFileName;
    }

    private function getHashFilename(): string
    {
        return $this->publicCacheDir . $this->cacheFileName . '.hash.php';
    }

    private function fileSystem(): Filesystem
    {
        return $this->filesystem ?? $this->filesystem = new Filesystem();
    }

    private function getCacheHash(): string
    {
        $result = @include $this->getHashFilename();

        return \is_string($result) ? $result : '';
    }

    public function getConcatenatedContent(): string
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
