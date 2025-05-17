<?php
/**
 * Proper environment setup.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

mb_internal_encoding('UTF-8');

// If the cache directory is not specified, we use the default setting
if (!defined('S2_CACHE_DIR')) {
    define('S2_CACHE_DIR', (static function () {
        $appEnv   = getenv('APP_ENV');
        $cacheDir = dirname(__DIR__) . '/_cache/';
        if (is_string($appEnv) && $appEnv !== '') {
            return $cacheDir . $appEnv . '/';
        }

        return $cacheDir;
    })());
}

/**
 * Removes known problematic, invisible, or non-printable UTF-8 characters
 * from global input arrays ($_GET, $_POST, $_COOKIE, $_REQUEST).
 *
 * These characters are often used for:
 * - Obfuscating text (spam, malicious input)
 * - Breaking visual formatting (zero-width characters)
 * - Introducing compatibility issues (e.g., BOM)
 * - Causing unexpected behavior in string processing, rendering, or sorting
 *
 * This is especially important in a CMS context to maintain clean, safe,
 * and predictable content entered by users.
 *
 * Use this early in the request lifecycle, before processing or validation.
 */
(static function (): void {
    $utf8BadChars = [
        "\0",         // NULL byte - string terminator, used in exploits
        "\xc2\xad",   // Soft hyphen (SHY) - invisible, can affect search/sorting
        "\xcc\xb7",   // Combining short solidus overlay - can visually alter characters
        "\xcc\xb8",   // Combining cedilla - can be used for obfuscation`
        "\xe1\x85\x9F", "\xe1\x85\xA0", "\xe3\x85\xa4", // Hangul compatibility characters,
        "\xe2\x80\x80", // EN QUAD - wide space, can break layout
        "\xe2\x80\x81", // EM QUAD - wide space`
        "\xe2\x80\x82", // EN SPACE - invisible formatting space
        "\xe2\x80\x83", // EM SPACE - invisible formatting space
        "\xe2\x80\x84", // THREE-PER-EM SPACE
        "\xe2\x80\x85", // FOUR-PER-EM SPACE
        "\xe2\x80\x86", // SIX-PER-EM SPACE
        "\xe2\x80\x87", // FIGURE SPACE - used in numbers, can break layout
        "\xe2\x80\x88", // PUNCTUATION SPACE
        "\xe2\x80\x89", // THIN SPACE - very narrow space
        "\xe2\x80\x8a", // HAIR SPACE - even narrower space
        "\xe2\x80\x8b", // ZERO WIDTH SPACE - invisible, used for obfuscation
        "\xe2\x80\x8c", // ZERO WIDTH NON-JOINER - invisible, breaks ligatures
        "\xe2\x80\x8d", // ZERO WIDTH JOINER - invisible, joins characters
        "\xe2\x80\x8e", // LEFT-TO-RIGHT MARK - affects text direction
        "\xe2\x80\x8f", // RIGHT-TO-LEFT MARK - affects text direction
        "\xe2\x80\xaa", // LEFT-TO-RIGHT EMBEDDING
        "\xe2\x80\xab", // RIGHT-TO-LEFT EMBEDDING
        "\xe2\x80\xac", // POP DIRECTIONAL FORMATTING
        "\xe2\x80\xad", // LEFT-TO-RIGHT OVERRIDE
        "\xe2\x80\xae", // RIGHT-TO-LEFT OVERRIDE
        "\xe2\x80\xaf", // NARROW NO-BREAK SPACE - not always visible
        "\xe2\x81\x9f", // MEDIUM MATHEMATICAL SPACE
        "\xe2\x81\xa0", // WORD JOINER - zero-width, no line break allowed
        "\xe3\x80\x80", // IDEOGRAPHIC SPACE - used in CJK text, wide space
        "\xef\xbb\xbf", // BYTE ORDER MARK (BOM) - can cause parsing issues
        "\xef\xbe\xa0", // HALFWIDTH HANGUL FILLER - legacy Korean filler
        "\xef\xbf\xb9", // Unassigned - reserved in Unicode
        "\xef\xbf\xba", // Unassigned - reserved in Unicode
        "\xef\xbf\xbb"  // Unassigned - reserved in Unicode
    ];

    function _s2_remove_bad_characters(mixed &$data, array $utf8BadChars): void
    {
        if (is_array($data)) {
            foreach (array_keys($data) as $key) {
                _s2_remove_bad_characters($data[$key], $utf8BadChars);
            }
        } else {
            $data = str_replace($utf8BadChars, '', $data);
        }
    }

    _s2_remove_bad_characters($_GET, $utf8BadChars);
    _s2_remove_bad_characters($_POST, $utf8BadChars);
    _s2_remove_bad_characters($_COOKIE, $utf8BadChars);
    _s2_remove_bad_characters($_REQUEST, $utf8BadChars);
})();
