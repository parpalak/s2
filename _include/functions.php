<?php
/**
 * Loads common functions used throughout the site.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayerException;

//
// Link processing
//
/**
 * @deprecated Use UrlBuilder instead
 */
function s2_link($path = '', $params = []): string
{
    /** @var UrlBuilder $urlBuilder */
    $urlBuilder = Container::get(UrlBuilder::class);
    return $urlBuilder->link($path, $params);
}


// Creates paging navigation (1  2  3 ... total_pages - 1  total_pages)
// $url must have the following form http://example.com/page?num=%d
function s2_paging($page, $total_pages, $url, &$link_nav)
{
    $links = '';
    for ($i = 1; $i <= $total_pages; $i++)
        $links .= ($i == $page ? ' <span class="current digit">' . $i . '</span>' : ' <a class="digit" href="' . sprintf($url, $i) . '">' . $i . '</a>');

    $link_nav = array();

    if ($page <= 1 || $page > $total_pages)
        $prev_link = '<span class="arrow left">&larr;</span>';
    else {
        $prev_url         = sprintf($url, $page - 1);
        $link_nav['prev'] = $prev_url;
        $prev_link        = '<a class="arrow left" href="' . $prev_url . '">&larr;</a>';
    }

    if ($page == $total_pages)
        $next_link = ' <span class="arrow right">&rarr;</span>';
    else {
        $next_url         = sprintf($url, $page + 1);
        $link_nav['next'] = $next_url;
        $next_link        = ' <a class="arrow right" href="' . $next_url . '">&rarr;</a>';
    }

    return '<p class="paging">' . $prev_link . $links . $next_link . '</p>';
}


//
// Encodes the contents of $str so that they are safe to output on an (X)HTML page
//
function s2_htmlencode($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

//
// JS-protected mailto: link
//
function s2_js_mailto($name, $email)
{
    $parts = explode('@', $email);

    if (count($parts) != 2)
        return $name;

    return '<script type="text/javascript">var mailto="' . $parts[0] . '"+"%40"+"' . $parts[1] . '";' .
        'document.write(\'<a href="mailto:\'+mailto+\'">' . str_replace('\'', '\\\'', $name) . '</a>\');</script>' .
        '<noscript>' . $name . ', <small>[' . $parts[0] . ' at ' . $parts[1] . ']</small></noscript>';
}

// Attempts to fetch the provided URL using any available means
function s2_get_remote_file($url, $timeout = 10, $head_only = false, $max_redirects = 10, $ignore_errors = false)
{
    $result          = null;
    $parsed_url      = parse_url($url);
    $allow_url_fopen = strtolower(@ini_get('allow_url_fopen'));

    // Quite unlikely that this will be allowed on a shared host, but it can't hurt
    if (function_exists('ini_set'))
        @ini_set('default_socket_timeout', $timeout);

    // If we have cURL, we might as well use it
    if (function_exists('curl_init')) {
        // Setup the transfer
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, $head_only);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'S2');
        if ($parsed_url['scheme'] == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        // Grab the page
        $content       = @curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }
        curl_close($ch);

        // Process 301/302 redirect
        if ($content !== false && ($response_code == '301' || $response_code == '302') && $max_redirects > 0) {
            $headers = explode("\r\n", trim($content));
            foreach ($headers as $header)
                if (substr($header, 0, 10) == 'Location: ') {
                    $response = s2_get_remote_file(substr($header, 10), $timeout, $head_only, $max_redirects - 1);
                    if ($response !== null)
                        $response['headers'] = array_merge($headers, $response['headers']);
                    return $response;
                }
        }

        // Ignore everything except a 200 response code
        if ($content !== false && ($response_code == '200' || $ignore_errors)) {
            if ($head_only)
                $result['headers'] = explode("\r\n", str_replace("\r\n\r\n", "\r\n", trim($content)));
            else {
                preg_match('#HTTP/1.[01] \\d\\d\\d #', $content, $match, PREG_OFFSET_CAPTURE);
                $last_content  = substr($content, $match[0][1]);
                $content_start = strpos($last_content, "\r\n\r\n");
                if ($content_start !== false) {
                    $result['headers'] = explode("\r\n", str_replace("\r\n\r\n", "\r\n", substr($content, 0, $match[0][1] + $content_start)));
                    $result['content'] = substr($last_content, $content_start + 4);
                }
            }
        }
    } // fsockopen() is the second best thing
    else if (function_exists('fsockopen')) {
        $remote = @fsockopen(($parsed_url['scheme'] == 'https' ? 'ssl://' : '') . $parsed_url['host'], !empty($parsed_url['port']) ? intval($parsed_url['port']) : ($parsed_url['scheme'] == 'https' ? 443 : 80), $errno, $errstr, $timeout);
        if ($remote) {
            // Send a standard HTTP 1.0 request for the page
            fwrite($remote, ($head_only ? 'HEAD' : 'GET') . ' ' . (!empty($parsed_url['path']) ? $parsed_url['path'] : '/') . (!empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '') . ' HTTP/1.0' . "\r\n");
            fwrite($remote, 'Host: ' . $parsed_url['host'] . "\r\n");
            fwrite($remote, 'User-Agent: S2' . "\r\n");
            fwrite($remote, 'Connection: Close' . "\r\n\r\n");

            stream_set_timeout($remote, $timeout);
            $stream_meta = stream_get_meta_data($remote);

            // Fetch the response 1024 bytes at a time and watch out for a timeout
            $content = false;
            while (!feof($remote) && !$stream_meta['timed_out']) {
                $content     .= fgets($remote, 1024);
                $stream_meta = stream_get_meta_data($remote);
            }

            fclose($remote);

            // Process 301/302 redirect
            if ($content !== false && $max_redirects > 0 && preg_match('#^HTTP/1.[01] 30[12]#', $content)) {
                $headers = explode("\r\n", trim($content));
                foreach ($headers as $header)
                    if (substr($header, 0, 10) == 'Location: ') {
                        $response = s2_get_remote_file(substr($header, 10), $timeout, $head_only, $max_redirects - 1);
                        if ($response !== null)
                            $response['headers'] = array_merge($headers, $response['headers']);
                        return $response;
                    }
            }

            // Ignore everything except a 200 response code
            if ($content !== false && ($ignore_errors || preg_match('#^HTTP/1.[01] 200 OK#', $content))) {
                if ($head_only)
                    $result['headers'] = explode("\r\n", trim($content));
                else {
                    $content_start = strpos($content, "\r\n\r\n");
                    if ($content_start !== false) {
                        $result['headers'] = explode("\r\n", substr($content, 0, $content_start));
                        $result['content'] = substr($content, $content_start + 4);
                    }
                }
            }
        }
    } // Last case scenario, we use file_get_contents provided allow_url_fopen is enabled (any non 200 response results in a failure)
    else if (in_array($allow_url_fopen, array('on', 'true', '1'))) {
        // Setup a stream context
        $stream_context = stream_context_create(
            array(
                'http' => array(
                    'method'        => $head_only ? 'HEAD' : 'GET',
                    'user_agent'    => 'S2',
                    'max_redirects' => $max_redirects + 1,
                    'timeout'       => $timeout
                )
            )
        );

        $content = @file_get_contents($url, false, $stream_context);

        // Did we get anything?
        if ($content !== false) {
            // Gotta love the fact that $http_response_header just appears in the global scope (*cough* hack! *cough*)
            $result['headers'] = $http_response_header;
            if (!$head_only)
                $result['content'] = $content;
        }
    }

    return $result;
}

// Removes any "bad" characters (characters which mess with the display of a page, are invisible, etc) from user input
function s2_remove_bad_characters(): void
{
    $bad_utf8_chars = array("\0", "\xc2\xad", "\xcc\xb7", "\xcc\xb8", "\xe1\x85\x9F", "\xe1\x85\xA0", "\xe2\x80\x80", "\xe2\x80\x81", "\xe2\x80\x82", "\xe2\x80\x83", "\xe2\x80\x84", "\xe2\x80\x85", "\xe2\x80\x86", "\xe2\x80\x87", "\xe2\x80\x88", "\xe2\x80\x89", "\xe2\x80\x8a", "\xe2\x80\x8b", "\xe2\x80\x8e", "\xe2\x80\x8f", "\xe2\x80\xaa", "\xe2\x80\xab", "\xe2\x80\xac", "\xe2\x80\xad", "\xe2\x80\xae", "\xe2\x80\xaf", "\xe2\x81\x9f", "\xe3\x80\x80", "\xe3\x85\xa4", "\xef\xbb\xbf", "\xef\xbe\xa0", "\xef\xbf\xb9", "\xef\xbf\xba", "\xef\xbf\xbb", "\xE2\x80\x8D");

    function _s2_remove_bad_characters(mixed &$data, array $bad_utf8_chars): void {
        if (is_array($data)) {
            foreach (array_keys($data) as $key) {
                _s2_remove_bad_characters($data[$key], $bad_utf8_chars);
            }
        }
        else {
            $data = str_replace($bad_utf8_chars, '', $data);
        }
    }

    _s2_remove_bad_characters($_GET, $bad_utf8_chars);
    _s2_remove_bad_characters($_POST, $bad_utf8_chars);
    _s2_remove_bad_characters($_COOKIE, $bad_utf8_chars);
    _s2_remove_bad_characters($_REQUEST, $bad_utf8_chars);
}


//
// Validate an e-mail address
//
function s2_is_valid_email($email)
{
    if (strlen($email) > 80) {
        return false;
    }

    return preg_match('/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\']+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/', $email);
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

    // If a database connection was established (before this error) we close it
    /** @var ?\S2\Cms\Pdo\DbLayer $s2_db */
    $s2_db = Container::getIfInstantiated(\S2\Cms\Pdo\DbLayer::class);
    $s2_db?->close();

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


//
// Parses BB-codes in comments
//
function s2_bbcode_to_html($s, $wroteText)
{
    $s = str_replace("''", '"', $s);
    $s = str_replace("\r", '', $s);

    $s = preg_replace('#\[I\](.*?)\[/I\]#isS', '<em>\1</em>', $s);
    $s = preg_replace('#\[B\](.*?)\[/B\]#isS', '<strong>\1</strong>', $s);

    while (preg_match('/\[Q\s*=\s*([^\]]*)\].*?\[\/Q\]/isS', $s))
        $s = preg_replace('/\s*\[Q\s*=\s*([^\]]*)\]\s*(.*?)\s*\[\/Q\]\s*/isS', '<blockquote><strong>\\1</strong> ' . $wroteText . '<br/><br/><em>\\2</em></blockquote>', $s);

    while (preg_match('/\[Q\].*?\[\/Q\]/isS', $s))
        $s = preg_replace('/\s*\[Q\]\s*(.*?)\s*\[\/Q\]\s*/isS', '<blockquote>\\1</blockquote>', $s);

    $s = preg_replace_callback(
        '#(https?://\S{2,}?)(?=[\s),\'><\]]|&lt;|&gt;|[.;:](?:\s|$)|$)#u',
        function ($matches) {
            $href = $link = $matches[1];

            if (mb_strlen($matches[1]) > 55)
                $link = mb_substr($matches[1], 0, 42) . ' &hellip; ' . mb_substr($matches[1], -10);

            return '<noindex><a href="' . $href . '" rel="nofollow">' . $link . '</a></noindex>';
        },
        $s
    );
    $s = str_replace("\n", '<br />', $s);
    return $s;
}

//
// wordwrap() with utf-8 support
//
function s2_utf8_wordwrap($string, $width = 75, $break = "\n")
{
    $a = explode("\n", $string);
    foreach ($a as $k => $str) {
        $str    = preg_split('#[\s\r]+#', $str);
        $len    = 0;
        $return = '';
        foreach ($str as $val) {
            $val .= ' ';
            $tmp = mb_strlen($val);
            $len += $tmp;
            if ($len >= $width) {
                $return .= $break . $val;
                $len    = $tmp;
            } else
                $return .= $val;
        }
        $a[$k] = $return;
    }
    return implode("\n", $a);
}

//
// Parses BB-codes in comments and makes quotes mail-styled (used '>')
//
function s2_bbcode_to_mail($s)
{
    $s = str_replace("\r", '', $s);
    $s = str_replace(array('&quot;', '&laquo;', '&raquo;'), '"', $s);
    $s = preg_replace('/\[I\s*?\](.*?)\[\/I\s*?\]/isu', "_\\1_", $s);
    $s = preg_replace('/\[B\s*?\](.*?)\[\/B\s*?\]/isu', "*\\1*", $s);

    // Do not ask me how the rest of the function works.
    // It just works :)

    while (preg_match('/\[Q\s*?=?\s*?([^\]]*)\s*?\].*?\[\/Q.*?\]/is', $s))
        $s = preg_replace('/\s*\[Q\s*?=?\s*?([^\]]*)\s*?\]\s*(.*?)\s*\[\/Q.*?\]\s*/is', "<q/>\\2</q>", $s);

    $strings = $levels = array();

    $curr  = 0;
    $level = 0;

    while (1) {
        $up   = strpos($s, '<q/>', $curr);
        $down = strpos($s, '</q>', $curr);
        if ($up === false) {
            if ($down === false)
                break;
            $dl = -1;
            $c  = $down;
        } elseif ($down === false || $up < $down) {
            $dl = 1;
            $c  = $up;
        } else {
            $dl = -1;
            $c  = $down;
        }
        $strings[] = substr($s, $curr, $c - $curr);
        $curr      = $c + 4;
        $levels[]  = $level;
        $level     += $dl;
    }

    $strings[] = substr($s, $curr);
    $levels[]  = 0;

    $out = array();
    foreach ($strings as $i => $string) {
        if (trim($string) == '')
            continue;
        $delimiter = "\n" . str_repeat('> ', $levels[$i]);
        $out[]     = $delimiter . s2_utf8_wordwrap(str_replace("\n", $delimiter, $string), 70 - 2 * $levels[$i], $delimiter);
    }

    $s = implode("\n", $out);

    return trim($s);
}
