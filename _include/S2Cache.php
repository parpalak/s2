<?php

use S2\Cms\Pdo\DbLayer;

/**
 * Caching functions.
 *
 * This file contains all the functions used to generate the cache files used by the site.
 *
 * @noinspection PhpExpressionResultUnusedInspection
 *
 * @copyright (C) 2009-2023 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */
class S2Cache
{
    public const CACHE_HOOK_NAMES_FILENAME         = S2_CACHE_DIR . 'cache_hook_names.php';
    public const CACHE_ENABLED_EXTENSIONS_FILENAME = S2_CACHE_DIR . 'cache_enabled_extensions.php';
    public const CACHE_ROUTES_FILENAME             = S2_CACHE_DIR . 'cache_routes.php';

    /**
     * Delete every .php file in the cache directory
     *
     * @return void
     */
    public static function clear(): void
    {
        $file_list = [
            S2_CACHE_DIR . 'cache_config.php',
            self::CACHE_HOOK_NAMES_FILENAME,
            self::CACHE_ENABLED_EXTENSIONS_FILENAME,
            self::CACHE_ROUTES_FILENAME,
        ];

        $return = ($hook = s2_hook('fn_clear_cache_start')) ? eval($hook) : null;
        if ($return !== null) {
            return;
        }

        foreach ($file_list as $entry) {
            @unlink($entry);
        }
    }

    public static function generateEnabledExtensionClassNames(DbLayer $dbLayer): array
    {
        $result = $dbLayer->buildAndQuery([
            'SELECT' => 'id',
            'FROM'   => 'extensions',
            'WHERE'  => 'disabled=0',
        ]);

        $extensionClassNames = [];
        while ($extension = $dbLayer->fetchAssoc($result)) {
            $className = sprintf('\s2_extensions\%s\Extension', $extension['id']);
            if (class_exists($className)) {
                $extensionClassNames[] = $className;
            }
        }

        // Output hooks as PHP code
        try {
            s2_overwrite_file_skip_locked(self::CACHE_ENABLED_EXTENSIONS_FILENAME, "<?php\n\nreturn " . var_export($extensionClassNames, true) . ';');
        } catch (\RuntimeException $e) {
            error('Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory \'' . S2_CACHE_DIR . '\'.', __FILE__, __LINE__);
        }

        return $extensionClassNames;
    }

    /**
     * Generate the hooks cache
     */
    public static function generate_hooks(): array
    {
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        if (!isset($s2_db)) {
            return []; // Install
        }

        // Get extensions from the DB
        $query = array(
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.disabled=0',
        );

        $result = $s2_db->buildAndQuery($query);

        $map = [];
        while ($extension = $s2_db->fetchAssoc($result)) {
            $hooks = glob(S2_ROOT . '_extensions/' . $extension['id'] . '/hooks/*.php');
            foreach ($hooks as $filename) {
                if (1 !== preg_match($regex = '#/([a-z_\-0-9]+?)(?:_(\d))?\.php$#S', $filename, $matches)) {
                    throw new RuntimeException(sprintf('Found invalid characters in hook filename "%s". Allowed name must match %s.', $filename, $regex));
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

        if (defined('S2_DISABLE_CACHE')) {
            return $map;
        }

        $cacheHookNamesContent = "<?php\n\nreturn " . var_export($map, true) . ';';

        // Output hooks as PHP code
        try {
            s2_overwrite_file_skip_locked(self::CACHE_HOOK_NAMES_FILENAME, $cacheHookNamesContent);
        } catch (\RuntimeException $e) {
            error('Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory \'' . S2_CACHE_DIR . '\'.', __FILE__, __LINE__);
        }

        return $map;
    }
}
