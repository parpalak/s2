<?php
/**
 * Loads static configuration for the application, providing a normalized array
 * that can be consumed by the bootstrap and container.
 *
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Config;

final class StaticConfigLoader
{
    public const DEFAULT_IMAGE_DIR          = '_pictures';
    public const DEFAULT_ALLOWED_EXTENSIONS = 'gif bmp jpg jpeg png ico svg mp3 wav ogg flac mp4 avi flv mpg mpeg mkv zip 7z rar doc docx ppt pptx odt odp ods xlsx xls pdf txt rtf csv';
    public const DEFAULT_COOKIE_NAME        = 's2_cookie_6094033457';

    public function load(string $filename): array
    {
        if (!\file_exists($filename)) {
            $config = $this->createDefaultConfig();
            $this->overrideWithGlobalConstants($config);
            $this->applyCompatibilityConstants($config, false);
            return $config;
        }

        [$config, $legacyConfig] = $this->includeConfig($filename);

        if (\is_array($config)) {
            $normalized = $this->normalizeArrayConfig($config);
            $this->overrideWithGlobalConstants($normalized);
            $this->applyCompatibilityConstants($normalized, false);
            return $normalized;
        }

        $normalized = $this->normalizeArrayConfig($legacyConfig);
        $this->overrideWithGlobalConstants($normalized);
        $this->applyCompatibilityConstants($normalized, true);
        return $normalized;
    }

    private function createDefaultConfig(): array
    {
        return $this->normalizeArrayConfig([]);
    }

    private function normalizeArrayConfig(array $config): array
    {
        $database  = $config['database'] ?? [];
        $http      = $config['http'] ?? [];
        $options   = $config['options'] ?? [];
        $files     = $config['files'] ?? [];
        $cookies   = $config['cookies'] ?? [];
        $redirects = $config['redirects'] ?? [];

        $normalizeDir = static function (?string $dir): ?string {
            if ($dir === null || $dir === '') {
                return $dir === '' ? '' : null;
            }

            return rtrim($dir, '/') . '/';
        };

        return [
            'database' => [
                'type'      => self::nullableString($database['type'] ?? null),
                'host'      => self::nullableString($database['host'] ?? null),
                'name'      => self::nullableString($database['name'] ?? null),
                'user'      => self::nullableString($database['user'] ?? null),
                'password'  => self::nullableString($database['password'] ?? null),
                'prefix'    => self::nullableString($database['prefix'] ?? null),
                'p_connect' => self::toBool($database['p_connect'] ?? false),
            ],
            'http' => [
                'base_url'   => self::nullableString($http['base_url'] ?? null),
                'base_path'  => self::nullableString($http['base_path'] ?? null, ''),
                'url_prefix' => self::nullableString($http['url_prefix'] ?? null, ''),
            ],
            'options' => [
                'force_admin_https' => self::toBool($options['force_admin_https'] ?? false),
                'canonical_url'     => self::nullableString($options['canonical_url'] ?? null),
                'disable_cache'     => self::toBool($options['disable_cache'] ?? false),
                'debug'             => self::toBool($options['debug'] ?? false),
                'debug_view'        => self::toBool($options['debug_view'] ?? false),
                'show_queries'      => self::toBool($options['show_queries'] ?? false),
            ],
            'files' => [
                'cache_dir'          => $normalizeDir(self::nullableString($files['cache_dir'] ?? null)),
                'image_dir'          => self::nullableString($files['image_dir'] ?? null, self::DEFAULT_IMAGE_DIR),
                'allowed_extensions' => self::nullableString($files['allowed_extensions'] ?? null, self::DEFAULT_ALLOWED_EXTENSIONS),
                'log_dir'            => $normalizeDir(self::nullableString($files['log_dir'] ?? null)),
            ],
            'cookies' => [
                'name' => self::nullableString($cookies['name'] ?? null, self::DEFAULT_COOKIE_NAME),
            ],
            'redirects' => \is_array($redirects) ? $redirects : [],
        ];
    }

    private function overrideWithGlobalConstants(array &$config): void
    {
        if (\defined('S2_CACHE_DIR')) {
            $config['files']['cache_dir'] = rtrim((string)S2_CACHE_DIR, '/') . '/';
        }

        if (\defined('S2_LOG_DIR')) {
            $config['files']['log_dir'] = rtrim((string)S2_LOG_DIR, '/') . '/';
        }

        if (\defined('S2_PATH')) {
            $config['http']['base_path'] = (string)S2_PATH;
        }

        if (\defined('S2_BASE_URL')) {
            $config['http']['base_url'] = (string)S2_BASE_URL;
        }

        if (\defined('S2_URL_PREFIX')) {
            $config['http']['url_prefix'] = (string)S2_URL_PREFIX;
        }

        if (\defined('S2_CANONICAL_URL')) {
            $config['options']['canonical_url'] = (string)S2_CANONICAL_URL;
        }

        if (\defined('S2_FORCE_ADMIN_HTTPS')) {
            $config['options']['force_admin_https'] = true;
        }

        if (\defined('S2_DISABLE_CACHE')) {
            $config['options']['disable_cache'] = true;
        }

        if (\defined('S2_DEBUG')) {
            $config['options']['debug'] = true;
        }

        if (\defined('S2_DEBUG_VIEW')) {
            $config['options']['debug_view'] = true;
        }

        if (\defined('S2_SHOW_QUERIES')) {
            $config['options']['show_queries'] = true;
        }
    }

    private function applyCompatibilityConstants(array $config, bool $legacyFormatUsed): void
    {
        if ($legacyFormatUsed) {
            return;
        }

        if (isset($config['files']['cache_dir']) && $config['files']['cache_dir'] !== null && !\defined('S2_CACHE_DIR')) {
            \define('S2_CACHE_DIR', $config['files']['cache_dir']);
        }

        if (isset($config['files']['log_dir']) && $config['files']['log_dir'] !== null && !\defined('S2_LOG_DIR')) {
            \define('S2_LOG_DIR', $config['files']['log_dir']);
        }
    }

    /**
     * Includes the config file once and returns both the raw include result
     * and the legacy-style data inferred from globals/constants.
     *
     * @return array{0:mixed,1:array}
     */
    private function includeConfig(string $filename): array
    {
        return (static function (string $filename): array {
            $db_type     = null;
            $db_host     = null;
            $db_name     = null;
            $db_username = null;
            $db_password = null;
            $db_prefix   = null;
            $p_connect   = false;

            $s2_cookie_name = null;
            $s2_redirect    = [];

            $config = include $filename;

            return [
                $config,
                [
                    'database' => [
                        'type'      => $db_type,
                        'host'      => $db_host,
                        'name'      => $db_name,
                        'user'      => $db_username,
                        'password'  => $db_password,
                        'prefix'    => $db_prefix,
                        'p_connect' => $p_connect,
                    ],
                    'http' => [
                        'base_url'   => \defined('S2_BASE_URL') ? (string)S2_BASE_URL : null,
                        'base_path'  => \defined('S2_PATH') ? (string)S2_PATH : '',
                        'url_prefix' => \defined('S2_URL_PREFIX') ? (string)S2_URL_PREFIX : '',
                    ],
                    'options' => [
                        'force_admin_https' => \defined('S2_FORCE_ADMIN_HTTPS'),
                        'canonical_url'     => \defined('S2_CANONICAL_URL') ? (string)S2_CANONICAL_URL : null,
                        'disable_cache'     => \defined('S2_DISABLE_CACHE'),
                        'debug'             => \defined('S2_DEBUG'),
                        'debug_view'        => \defined('S2_DEBUG_VIEW'),
                        'show_queries'      => \defined('S2_SHOW_QUERIES'),
                    ],
                    'files' => [
                        'cache_dir'          => \defined('S2_CACHE_DIR') ? (string)S2_CACHE_DIR : null,
                        'image_dir'          => \defined('S2_IMG_DIR') ? (string)S2_IMG_DIR : self::DEFAULT_IMAGE_DIR,
                        'allowed_extensions' => \defined('S2_ALLOWED_EXTENSIONS') ? (string)S2_ALLOWED_EXTENSIONS : self::DEFAULT_ALLOWED_EXTENSIONS,
                        'log_dir'            => \defined('S2_LOG_DIR') ? (string)S2_LOG_DIR : null,
                    ],
                    'cookies' => [
                        'name' => $s2_cookie_name ?? self::DEFAULT_COOKIE_NAME,
                    ],
                    'redirects' => \is_array($s2_redirect) ? $s2_redirect : [],
                ],
            ];
        })($filename);
    }

    private static function nullableString(mixed $value, ?string $default = null): ?string
    {
        if ($value === null) {
            return $default;
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            return (string)$value;
        }

        return $default;
    }

    private static function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
