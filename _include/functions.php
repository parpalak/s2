<?php
/**
 * Loads common functions used throughout the site.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use S2\Cms\Pdo\DbLayerException;

//
// Encodes the contents of $str so that they are safe to output on an (X)HTML page
//
function s2_htmlencode($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Display a simple error message
function error()
{
    if (!headers_sent()) {
        // if no HTTP response code is set we send 503
        header($_SERVER['SERVER_PROTOCOL'] . ' 503 Service Temporarily Unavailable');
        header('Content-Type: text/html; charset=utf-8');
    }

    /*
        Parse input parameters. Possible function signatures:
        error('Error message.');
        error(__FILE__, __LINE__);
        error('Error message.', __FILE__, __LINE__);
    */
    $num_args = func_num_args();
    if ($num_args == 3) {
        $message = func_get_arg(0);
        $file    = func_get_arg(1);
        $line    = func_get_arg(2);
    } else if ($num_args == 2) {
        $file = func_get_arg(0);
        $line = func_get_arg(1);
    } else if ($num_args == 1)
        $message = func_get_arg(0);

    // Set a default title and gzip setting if the script failed before constants could be defined
    if (!defined('S2_SITE_NAME')) {
        define('S2_SITE_NAME', 'S2');
        define('S2_COMPRESS', 0);
    }

    $title = 'An error was encountered';

    // Empty all output buffers and stop buffering
    while (@ob_end_clean()) ;

    // "Restart" output buffering if we are using ob_gzhandler (since the gzip header is already sent)
    if (S2_COMPRESS && extension_loaded('zlib') && !empty($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false))
        ob_start('ob_gzhandler');

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="Generator" content="S2">
        <title>Error - <?php echo s2_htmlencode(S2_SITE_NAME); ?></title>
        <style>
            body {
                margin: 40px;
                font: 16px/1.5 Helvetica, Arial, sans-serif;
                color: #333;
            }

            pre {
                font-size: 16px;
                font-family: Consolas, monospace;
            }

            pre.code {
                overflow: auto;
                background: #003;
                color: #9e9;
                padding: 1em;
            }
        </style>
    </head>
    <body>
    <h1><?php echo $title; ?></h1>
    <hr/>
    <?php

    if (isset($message) && !($message instanceof Exception)) {
        echo '<p>' . $message . '</p>' . "\n";
    }

    if ($num_args > 1 || isset($message) && $message instanceof Exception) {
        if (defined('S2_DEBUG')) {
            if (isset($message) && $message instanceof Exception) {
                if ($message instanceof DbLayerException) {
                    // Special report for DB
                    echo '<p>Database reported: <b>' . s2_htmlencode($message->getMessage()) . ($message->getCode() ? ' (Errno: ' . $message->getCode() . ')' : '') . '</b>.</p>' . "\n";

                    if ($message->getQuery() !== '') {
                        echo '<p>Failed query: </p>' . "\n";
                        echo '<pre class="code">' . s2_htmlencode($message->getQuery()) . '</pre>' . "\n";
                    }
                } else {
                    echo '<p>', s2_htmlencode(get_class($message)), '</p><p>', s2_htmlencode($message->getMessage()), '</p>', "\n";
                }

                // Output trace
                echo '<h3>Call trace</h3>';
                $i = 0;
                foreach ($message->getTrace() as $trace) {
                    $i++;
                    echo '<p>' . $i . '. File <b>' . $trace['file'] . ':' . $trace['line'] . "</b></p>";
                    echo '<pre class="code">';
                    echo (isset($trace['class']) ? $trace['class'] . $trace['type'] : '') . $trace['function'] . '(';

                    $args = array();
                    foreach ($trace['args'] ?? [] as $arg) {
                        $args[] = var_export($arg, true);
                    }

                    echo implode(', ', $args) . ');' . "\n";
                    echo '</pre>';
                }
            } else if (isset($file, $line)) {
                echo '<p><em>The error occurred on line ' . $line . ' in ' . $file . '</em></p>' . "\n";
            }
        } else {
            echo '<p><strong>Note:</strong> For detailed error information (necessary for troubleshooting), enable "DEBUG mode". To enable "DEBUG mode", open up the file config.php in a text editor, add a line that looks like "define(\'S2_DEBUG\', 1);" (without the quotation marks), and re-upload the file. Once you\'ve solved the problem, it is recommended that "DEBUG mode" be turned off again (just remove the line from the file and re-upload it).</p>' . "\n";
        }
    }

    ?>
    </body>
    </html>
    <?php

    exit;
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
