<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Picture;

use S2\AdminYard\Helper\RandomHelper;
use Symfony\Component\HttpFoundation\Response;

class PictureReserveManager
{
    private const RESERVE_TTL_SECONDS = 900;
    private const RESERVE_CACHE_SUBDIR = 'picture_reserve';

    private string $imageDir;
    private string $cacheDir;

    public function __construct(
        private readonly PictureFileNameHelper $fileNameHelper,
        string $imageDir,
        string $cacheDir,
    ) {
        $this->imageDir = rtrim($imageDir, '/');
        $this->cacheDir = rtrim($cacheDir, '/');
    }

    /**
     * @throws \JsonException
     */
    public function reserveFileName(string $path, string $suggestedName): array
    {
        $normalized = $this->fileNameHelper->normalizeFileName($suggestedName);
        if ($normalized === '') {
            throw new \RuntimeException('Empty file name.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->fileNameHelper->assertAllowedExtension($normalized);
        $this->ensureDirExists($this->imageDir . $path);
        $reserveDir = $this->getReservePathDir($path);
        $this->ensureDirExists($reserveDir);
        if (!is_writable($reserveDir)) {
            throw new \RuntimeException('Reserve directory is not writable.', Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $this->cleanupExpiredReserves($path);

        $token     = RandomHelper::getRandomHexString32();
        $candidate = $normalized;
        $attempts  = 0;

        while (is_file($this->imageDir . $path . '/' . $candidate)) {
            $candidate = $this->fileNameHelper->incrementCopySuffix($candidate);
        }

        while ($attempts++ < 100) {
            $reserveFile = $this->getReserveFilePath($path, $candidate);
            if ($this->tryCreateReserve($reserveFile, $token, $path, $candidate)) {
                return [
                    'name'  => $candidate,
                    'token' => $token,
                ];
            }

            $candidate = $this->fileNameHelper->incrementCopySuffix($candidate);
        }

        throw new \RuntimeException('Unable to reserve file name.', Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function validateReserveToken(string $path, string $filename, string $token): bool
    {
        $reserveFile = $this->getReserveFilePath($path, $filename);
        if (!is_file($reserveFile)) {
            return false;
        }

        $payload = @file_get_contents($reserveFile);
        if ($payload === false) {
            return false;
        }

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        if (!\is_array($data)) {
            return false;
        }

        $expiresAt = (int)($data['expires_at'] ?? 0);
        if ($expiresAt < time()) {
            @unlink($reserveFile);
            return false;
        }

        return hash_equals((string)($data['token'] ?? ''), $token)
            && (string)($data['path'] ?? '') === $path
            && (string)($data['name'] ?? '') === $filename;
    }

    public function clearReserve(string $path, string $filename): void
    {
        $reserveFile = $this->getReserveFilePath($path, $filename);
        if (is_file($reserveFile)) {
            @unlink($reserveFile);
        }
    }

    public function cleanupExpiredReserves(string $path): void
    {
        $reserveDir = $this->getReservePathDir($path);
        if (!is_dir($reserveDir)) {
            return;
        }

        $iterator = new \DirectoryIterator($reserveDir);
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $filename = $item->getFilename();
            if (!str_ends_with($filename, '.json')) {
                continue;
            }
            $reserveFile = $item->getPathname();
            if ($this->isReserveExpired($reserveFile)) {
                @unlink($reserveFile);
            }
        }
    }

    private function getReserveFilePath(string $path, string $filename): string
    {
        $reserveRoot = $this->cacheDir . '/' . self::RESERVE_CACHE_SUBDIR;
        $safePath    = ltrim($path, '/');

        return $reserveRoot . ($safePath !== '' ? '/' . $safePath : '') . '/' . $filename . '.json';
    }

    private function getReservePathDir(string $path): string
    {
        $reserveRoot = $this->cacheDir . '/' . self::RESERVE_CACHE_SUBDIR;
        $safePath    = ltrim($path, '/');

        return $reserveRoot . ($safePath !== '' ? '/' . $safePath : '');
    }

    private function tryCreateReserve(string $reserveFile, string $token, string $path, string $name): bool
    {
        if (is_file($reserveFile)) {
            if (!$this->isReserveExpired($reserveFile)) {
                return false;
            }
            @unlink($reserveFile);
        } else {
            $dir = \dirname($reserveFile);
            $this->ensureDirExists($dir);
        }

        $fh = @fopen($reserveFile, 'xb');
        if ($fh === false) {
            if ($this->isReserveExpired($reserveFile)) {
                @unlink($reserveFile);
            }
            return false;
        }

        $payload = json_encode([
            'token'      => $token,
            'path'       => $path,
            'name'       => $name,
            'expires_at' => time() + self::RESERVE_TTL_SECONDS,
        ], JSON_THROW_ON_ERROR);

        fwrite($fh, $payload);
        fclose($fh);

        return true;
    }

    private function isReserveExpired(string $reserveFile): bool
    {
        $payload = @file_get_contents($reserveFile);
        if ($payload === false) {
            return true;
        }

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return true;
        }
        if (!\is_array($data)) {
            return true;
        }

        $expiresAt = (int)($data['expires_at'] ?? 0);

        return $expiresAt < time();
    }

    private function ensureDirExists(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        $warning = null;
        set_error_handler(static function ($errno, $errstr) use (&$warning) {
            $warning = $errstr;
        });
        $created = mkdir($dir, 0777, true);
        restore_error_handler();

        if (!$created && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $dir) . ($warning ? ' (' . $warning . ')' : ''));
        }

        chmod($dir, 0777);
    }
}
