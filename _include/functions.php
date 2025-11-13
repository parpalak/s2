<?php
/**
 * Loads common functions used throughout the site.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

/**
 * Encodes the contents of $str so that they are safe to output on an HTML page
 */
function s2_htmlencode($str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * @throws \RuntimeException
 */
function s2_overwrite_file_skip_locked(string $filename, string $content): void
{
    $fh = @fopen($filename, 'a+b');

    if ($fh === false) {
        // Try to remove the file if it's not writable
        @unlink($filename);
        $fh = @fopen($filename, 'a+b');
    }

    if ($fh === false) {
        throw new RuntimeException(sprintf('Cannot open file "%s" for write.', $filename));
    }

    if (flock($fh, LOCK_EX | LOCK_NB)) {
        ftruncate($fh, 0);
        fwrite($fh, $content);
        fflush($fh);
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/**
 * Check APP_NAME env variable for test purposes.
 *
 * @see https://gist.github.com/samdark/01279afbce4871bd02b556bbb7ca4790 for details of getenv() / $_ENV
 */
function s2_get_config_filename(): string
{
    $appEnv = getenv('APP_ENV');
    if (is_string($appEnv) && $appEnv !== '') {
        return sprintf('config.%s.php', $appEnv);
    }

    return 'config.php';
}

function s2_get_default_cache_dir(): string
{
    $appEnv   = getenv('APP_ENV');
    $cacheDir = dirname(__DIR__) . '/_cache/';
    if (is_string($appEnv) && $appEnv !== '') {
        return $cacheDir . $appEnv . '/';
    }

    return $cacheDir;
}
