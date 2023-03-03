<?php

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
    public const CACHE_HOOK_NAMES_FILENAME = S2_CACHE_DIR . 'cache_hook_names.php';

    /**
     * Delete every .php file in the cache directory
     *
     * @return void
     */
    public static function clear(): void
    {
        $file_list = ['cache_config.php', 'cache_hook_names.php'];

        $return = ($hook = s2_hook('fn_clear_cache_start')) ? eval($hook) : null;
        if ($return !== null) {
            return;
        }

        foreach ($file_list as $entry) {
            @unlink(S2_CACHE_DIR . $entry);
        }
    }

    /**
     * Generate the config cache
     */
    public static function generate_config(bool $load = false): void
    {
        /** @var \DBLayer_Abstract $s2_db */
        $s2_db = \Container::get('db');

        $return = ($hook = s2_hook('fn_generate_config_cache_start')) ? eval($hook) : null;
        if ($return !== null) {
            return;
        }

        // Get the config from the DB
        $query = array(
            'SELECT' => 'c.*',
            'FROM'   => 'config AS c'
        );

        ($hook = s2_hook('fn_generate_config_cache_qr_get_config')) ? eval($hook) : null;
        $result = $s2_db->query_build($query);

        $output = '';
        while ($row = $s2_db->fetch_row($result)) {
            $output .= 'define(\'' . $row[0] . '\', \'' . str_replace(array('\\', '\''), array('\\\\', '\\\''), $row[1]) . '\');' . "\n";
            if ($load) {
                define($row[0], $row[1]);
            }
        }

        if ($load) {
            define('S2_CONFIG_LOADED', 1);
        }

        if (defined('S2_DISABLE_CACHE')) {
            return;
        }

        // Output config as PHP code
        try {
            s2_overwrite_file_skip_locked(S2_CACHE_DIR . 'cache_config.php', '<?php' . "\n\n" . 'define(\'S2_CONFIG_LOADED\', 1);' . "\n\n" . $output . "\n");
        } catch (\RuntimeException $e) {
            error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \'' . S2_CACHE_DIR . '\'.', __FILE__, __LINE__);
        }
    }

    /**
     * Generate the hooks cache
     */
    public static function generate_hooks(): array
    {
        /** @var \DBLayer_Abstract $s2_db */
        $s2_db = \Container::get('db');

        if (!isset($s2_db)) {
            return []; // Install
        }
        // Get the hotfix hooks from the DB
//        $query = array(
//            'SELECT'   => 'eh.id, eh.code, eh.extension_id, eh.priority, e.dependencies',
//            'FROM'     => 'extension_hooks AS eh',
//            'JOINS'    => array(
//                array(
//                    'INNER JOIN' => 'extensions AS e',
//                    'ON'         => 'e.id=eh.extension_id'
//                )
//            ),
//            'WHERE'    => 'e.disabled=0 AND e.name LIKE \'hotfix_%\'',
//            'ORDER BY' => 'eh.priority, eh.installed'
//        );
//
//        $result = $s2_db->query_build($query);
//
//        while ($cur_hook = $s2_db->fetch_assoc($result)) {
//            $code = $cur_hook['code'];
//            TODO cache somewhere the hotfix code
//        }

        // Get extensions from the DB
        $query = array(
            'SELECT' => 'e.id',
            'FROM'   => 'extensions AS e',
            'WHERE'  => 'e.disabled=0',
        );

        $result = $s2_db->query_build($query);

        $map = [];
        while ($extension = $s2_db->fetch_assoc($result)) {
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

    /**
     * Generate the updates cache PHP script
     * @return array|mixed
     */
    public static function generate_updates()
    {
        /** @var \DBLayer_Abstract $s2_db */
        $s2_db = \Container::get('db');

        $return = ($hook = s2_hook('fn_generate_updates_cache_start')) ? eval($hook) : null;
        if ($return !== null) {
            return $return;
        }
        /*
            // Get a list of installed hotfix extensions
            $query = array(
                'SELECT'	=> 'e.id',
                'FROM'		=> 'extensions AS e',
                'WHERE'		=> 'e.id LIKE \'hotfix_%\''
            );

            ($hook = s2_hook('fn_generate_updates_cache_qr_get_hotfixes')) ? eval($hook) : null;
            $result = $s2_db->query_build($query);

            $hotfixes = array();
            while ($hotfix = $s2_db->fetch_assoc($result))
                $hotfixes[] = urlencode($hotfix['id']);

            $result = s2_get_remote_file('http://s2cms.ru/update/?type=xml&version='.urlencode(S2_VERSION).'&hotfixes='.implode(',', $hotfixes), 8);
        */
        // Contact the S2 updates service
        $result = s2_get_remote_file('http://s2cms.ru/update/index.php?version=' . urlencode(S2_VERSION), 8);

        // Make sure we got everything we need
        if ($result !== null && strpos($result['content'], '</s2_updates>') !== false) {
            if (!defined('S2_XML_FUNCTIONS_LOADED'))
                require S2_ROOT . '_include/xml.php';

            $update_info = s2_xml_to_array(trim($result['content']));

            $output['version'] = $update_info['s2_updates']['lastversion'];
            $output['cached']  = time();
            $output['fail']    = false;
        } else {
            $output = array('cached' => time(), 'fail' => true);
        }

        ($hook = s2_hook('fn_generate_updates_cache_write')) ? eval($hook) : null;

        if (!defined('S2_DISABLE_CACHE')) {
            // Output update status as PHP code
            try {
                s2_overwrite_file_skip_locked(S2_CACHE_DIR . 'cache_updates.php', '<?php' . "\n\n" . 'return ' . var_export($output, true) . ';' . "\n");
            } catch (\RuntimeException $e) {
                error('Unable to write updates cache file to cache directory. Please make sure PHP has write access to the directory \'' . S2_CACHE_DIR . '\'.', __FILE__, __LINE__);
            }
        }

        return $output;
    }
}
