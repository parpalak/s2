<?php
/**
 * Proper environment setup.
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

// Strip out "bad" UTF-8 characters
s2_remove_bad_characters();
