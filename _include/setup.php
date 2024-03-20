<?php
/**
 * Autoloader and proper environment setup.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */


if (!defined('S2_ROOT')) {
    die;
}

mb_internal_encoding('UTF-8');

// If the cache directory is not specified, we use the default setting
if (!defined('S2_CACHE_DIR')) {
    define('S2_CACHE_DIR', (static function () {
        $appEnv = getenv('APP_ENV');
        if (is_string($appEnv) && $appEnv !== '') {
            return S2_ROOT . '_cache/' . $appEnv .'/';
        }

        return S2_ROOT . '_cache/';
    })());
}

spl_autoload_register(static function ($class) {
    $class = ltrim($class, '\\');
    $dir   = '';
    if (!strpos($class, '\\')) {
        return false;
    }

    $ns_array = explode('\\', $class);
    $class    = array_pop($ns_array);
    if (count($ns_array) === 2 && $ns_array[0] === 's2_extensions' && $class !== 'Extension') {
        $ns_array = ['_extensions', $ns_array[1], '_include'];
    } else {
        return false;
    }
    $dir  = S2_ROOT . implode(DIRECTORY_SEPARATOR, $ns_array) . DIRECTORY_SEPARATOR;
    $file = $dir . str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';

    require $file;
});

// Strip out "bad" UTF-8 characters
s2_remove_bad_characters();
