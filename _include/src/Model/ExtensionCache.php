<?php
/**
 * Cache functions for extensions.
 *
 * This file contains the functions used to generate the cache files used for extension system.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;

class ExtensionCache
{
    public function __construct(
        private readonly DbLayer $dbLayer,
        private readonly bool    $disableCache,
        private readonly string  $rootDir,
        private readonly string  $cacheDir,
    ) {
    }

    public const CACHE_ENABLED_EXTENSIONS_FILENAME = 'cache_enabled_extensions.php';

    /**
     * Delete every .php cache file in the cache directory
     */
    public function clear(): void
    {
        $file_list = [
            // Deprecated. Remove when all values are accessed through DynamicConfigProvider
            $this->cacheDir . 'cache_config.php',

            $this->getHookNamesCacheFilename(),
            $this->cacheDir . self::CACHE_ENABLED_EXTENSIONS_FILENAME,
            $this->getCachedRoutesFilename(),
        ];

        foreach ($file_list as $entry) {
            @unlink($entry);
        }
    }

    /**
     * Retrieves Extension class names if they exist for enabled extensions.
     */
    public function generateEnabledExtensionClassNames(): array
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id',
            'FROM'   => 'extensions',
            'WHERE'  => 'disabled=0',
        ]);

        $extensionClassNames = [];
        while ($extension = $this->dbLayer->fetchAssoc($result)) {
            $className = sprintf('\s2_extensions\%s\Extension', $extension['id']);
            if (class_exists($className)) {
                $extensionClassNames[] = $className;
            }
        }

        if ($this->disableCache) {
            return $extensionClassNames;
        }

        // Output extension class names as PHP code
        try {
            s2_overwrite_file_skip_locked(
                $this->cacheDir . self::CACHE_ENABLED_EXTENSIONS_FILENAME,
                "<?php\n\nreturn " . var_export($extensionClassNames, true) . ';'
            );
        } catch (\RuntimeException $e) {
            error(sprintf(
                'Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory "%s".',
                $this->cacheDir
            ), __FILE__, __LINE__);
        }

        return $extensionClassNames;
    }

    /**
     * Scans hook directories for enabled extensions and generates the map of hook file names.
     */
    public function generateHooks(): array
    {
        // Get extensions from the DB
        $query = [
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.disabled=0',
        ];

        $result = $this->dbLayer->buildAndQuery($query);

        $map = [];
        while ($extension = $this->dbLayer->fetchAssoc($result)) {
            $hooks = glob($this->rootDir . '_extensions/' . $extension['id'] . '/hooks/*.php');
            foreach ($hooks as $filename) {
                if (1 !== preg_match($regex = '#/([a-z_\-0-9]+?)(?:_(\d))?\.php$#S', $filename, $matches)) {
                    throw new \RuntimeException(sprintf('Found invalid characters in hook filename "%s". Allowed name must match %s.', $filename, $regex));
                }
                $priority = (int)($matches[2] ?? 5);
                $hookName = $matches[1];

                // Structure
                $map[$hookName][$priority][] = '_extensions/' . $extension['id'] . '/hooks' . $matches[0];
            }
        }

        array_walk($map, static function (&$mapItem) {
            // Sort by priority
            ksort($mapItem);
            // Remove grouping by priority
            $mapItem = array_merge(...$mapItem);
        });

        if ($this->disableCache) {
            return $map;
        }

        // Output hooks as PHP code
        try {
            s2_overwrite_file_skip_locked(
                $this->getHookNamesCacheFilename(),
                "<?php\n\nreturn " . var_export($map, true) . ';'
            );
        } catch (\RuntimeException $e) {
            error(sprintf(
                'Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory "%s".',
                $this->cacheDir
            ), __FILE__, __LINE__);
        }

        return $map;
    }

    /**
     * Retrieves hook names for enabled extensions from cache or by scanning hook directories.
     */
    public function getHookNames(): array
    {
        $hookNames = null;
        if (!$this->disableCache && file_exists($filename = $this->getHookNamesCacheFilename())) {
            $hookNames = include $filename;
        }
        if (!\is_array($hookNames)) {
            $hookNames = $this->generateHooks();
        }

        return $hookNames;
    }

    public function getCachedRoutesFilename(): string
    {
        return S2_CACHE_DIR . 'cache_routes.php';
    }

    private function getHookNamesCacheFilename(): string
    {
        return $this->cacheDir . 'cache_hook_names.php';
    }
}
