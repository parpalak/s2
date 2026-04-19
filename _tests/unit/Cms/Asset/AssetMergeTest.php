<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace unit\Cms\Asset;

use Codeception\Test\Unit;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use S2\Cms\Asset\AssetMerge;
use S2\Cms\HttpClient\HttpClient;
use S2\Cms\HttpClient\HttpClientException;
use S2\Cms\HttpClient\HttpResponse;

class AssetMergeTest extends Unit
{
    private string $cacheDir;

    protected function _before(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/s2_asset_merge_test_' . \bin2hex(\random_bytes(4)) . '/';
        \mkdir($this->cacheDir, 0777, true);
    }

    protected function _after(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function testExternalAssetFetchErrorIsLogged(): void
    {
        $logger = new RecordingLogger();
        $merge  = new AssetMerge(
            new FailingHttpClient(),
            $logger,
            $this->cacheDir,
            '/_cache/',
            'test_scripts',
            AssetMerge::TYPE_JS,
            false
        );

        $externalUrl = 'https://cdn.example.com/script.js';
        $merge->concat($externalUrl);

        $paths = $merge->getMergedPaths();

        $this->assertSame($externalUrl, $paths[0]);
        $this->assertCount(1, $logger->records);
        $this->assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        $this->assertSame('Failed to fetch external asset.', $logger->records[0]['message']);
        $this->assertSame($externalUrl, $logger->records[0]['context']['url']);
        $this->assertInstanceOf(HttpClientException::class, $logger->records[0]['context']['exception']);
        $this->assertStringContainsString('SSL certificate problem', $logger->records[0]['context']['exception']->getMessage());
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (\is_dir($path)) {
                $this->removeDir($path);
            } else {
                \unlink($path);
            }
        }
        \rmdir($dir);
    }
}

readonly class FailingHttpClient extends HttpClient
{
    public function fetch(string $url): HttpResponse
    {
        throw new HttpClientException('SSL certificate problem: unable to get local issuer certificate');
    }
}

class RecordingLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}
