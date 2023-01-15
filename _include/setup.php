<?php
/**
 * Autoloader and proper environment setup.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

if (!defined('S2_ROOT')) {
    die;
}

require S2_ROOT . '_include/functions.php';
require S2_ROOT . '_include/utf8/utf8.php';

// If the cache directory is not specified, we use the default setting
if (!defined('S2_CACHE_DIR')) {
    define('S2_CACHE_DIR', S2_ROOT . '_cache/');
}

spl_autoload_register(static function ($class) {
    $class = ltrim($class, '\\');
    $dir   = '';
    if (strpos($class, '\\')) {
        $ns_array = explode('\\', $class);
        $class    = array_pop($ns_array);
        if (count($ns_array) === 2 && $ns_array[0] === 's2_extensions') {
            $ns_array = ['_extensions', $ns_array[1], '_include'];
        } else {
            return false;
        }
        $dir = S2_ROOT . implode(DIRECTORY_SEPARATOR, $ns_array) . DIRECTORY_SEPARATOR;
    }
    $file = $dir . str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';

    require $file;
});

if (defined('S2_DEBUG')) {
    $errorHandler = Debug::enable();
} else {
    $errorHandler = ErrorHandler::register();
}
/** @noinspection PhpParamsInspection */
$errorHandler->setDefaultLogger(Container::get(LoggerInterface::class));
HtmlErrorRenderer::setTemplate(S2_ROOT.'_include/views/error.php');

// Strip out "bad" UTF-8 characters
s2_remove_bad_characters();
