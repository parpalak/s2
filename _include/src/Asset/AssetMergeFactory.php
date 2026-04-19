<?php
/**
 * @copyright 2025-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Asset;

use Psr\Log\LoggerInterface;
use S2\Cms\HttpClient\HttpClient;

readonly class AssetMergeFactory
{
    public function __construct(
        private HttpClient $httpClient,
        private LoggerInterface $logger,
        private bool       $debug,
        private string     $publicCacheDir,
        private string     $publicCachePath,
        private bool       $disableCache,
    ) {
    }

    public function create(string $cacheFilenamePrefix, string $type): ?AssetMergeInterface
    {
        return $this->disableCache ? null : new AssetMerge(
            $this->httpClient,
            $this->logger,
            $this->publicCacheDir,
            $this->publicCachePath,
            $cacheFilenamePrefix,
            $type,
            $this->debug
        );
    }
}
