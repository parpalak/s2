<?php
/**
 * Functions of the counter extension
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */


if (!defined('S2_ROOT'))
    die;

if (!defined('S2_COUNTER_TOTAL_HITS_FNAME'))
    define('S2_COUNTER_TOTAL_HITS_FNAME', '/data/total_hits.txt');

if (!defined('S2_COUNTER_TODAY_INFO_FNAME'))
    define('S2_COUNTER_TODAY_INFO_FNAME', '/data/today_info.txt');

if (!defined('S2_COUNTER_ARCH_INFO_FNAME'))
    define('S2_COUNTER_ARCH_INFO_FNAME', '/data/arch_info.txt');

if (!defined('S2_COUNTER_TODAY_DATA_FNAME'))
    define('S2_COUNTER_TODAY_DATA_FNAME', '/data/today_data.txt');

function s2_counter_is_bot()
{
    $sebot = array(
        'bot',
        'Yahoo!',
        'Mediapartners-Google',
        'Spider',
        'Yandex',
        'StackRambler',
        'ia_archiver',
        'appie',
        'ZyBorg',
        'WebAlta',
        'ichiro',
        'TurtleScanner',
        'LinkWalker',
        'Snoopy',
        'libwww',
        'Aport',
        'Crawler',
        'Spyder',
        'findlinks',
        'Parser',
        'Mail.Ru',
        'rulinki.ru',
    );

    if (!isset($_SERVER['HTTP_USER_AGENT']))
        return false;

    foreach ($sebot as $se1)
        if (stristr($_SERVER['HTTP_USER_AGENT'], $se1))
            return true;

    return false;
}

function s2_counter_append_file($filename, $str)
{
    $f = fopen($filename, 'a+');
    flock($f, LOCK_EX);

    fwrite($f, $str);
    fflush($f);
    fflush($f);

    flock($f, LOCK_UN);
    fclose($f);
}

function s2_counter_refresh_file($filename, $str)
{
    $f = fopen($filename, 'a+');
    flock($f, LOCK_EX);

    ftruncate($f, 0);
    fwrite($f, $str);
    fflush($f);
    fflush($f);

    flock($f, LOCK_UN);
    fclose($f);
}

function s2_counter_get_total_hits($dir)
{
    $f = fopen($dir . S2_COUNTER_TOTAL_HITS_FNAME, 'a+');
    flock($f, LOCK_EX);

    $hits = intval(fread($f, 100)) + 1;

    ftruncate($f, 0);
    fwrite($f, $hits);
    fflush($f);

    flock($f, LOCK_UN);
    fclose($f);

    return $hits;
}

function s2_counter_process($dir)
{
    if (s2_counter_is_bot())
        return;

    if (!is_file($dir . S2_COUNTER_TODAY_DATA_FNAME) && !is_writable(dirname($dir . S2_COUNTER_TODAY_DATA_FNAME)))
        return;

    $f_day_info = fopen($dir . S2_COUNTER_TODAY_DATA_FNAME, 'a+');
    flock($f_day_info, LOCK_EX);

    $ips = unserialize(file_get_contents($dir . S2_COUNTER_TODAY_DATA_FNAME));

    clearstatcache();
    if (!is_file($dir . S2_COUNTER_TODAY_DATA_FNAME) || date('j', filemtime($dir . S2_COUNTER_TODAY_DATA_FNAME)) == date('j')) {
        // We have already some hits today

        // Let's correct the data saved before
        if (isset($ips[$_SERVER['REMOTE_ADDR']]))
            $ips[$_SERVER['REMOTE_ADDR']]++;
        else
            $ips[$_SERVER['REMOTE_ADDR']] = 1;

        $today_hosts = count($ips);
        $today_hits  = array_sum($ips);
    } else {
        // It's a new day!

        // Logging yesterday info
        s2_counter_append_file($dir . S2_COUNTER_ARCH_INFO_FNAME, date('Y-m-d', time() - 86400) . '^' . (is_array($ips) && count($ips) ? array_sum($ips) : 0) . '^' . count($ips) . "\n");

        // Erase yesterday info
        unset($ips);
        $ips[$_SERVER['REMOTE_ADDR']] = 1;

        $today_hits = $today_hosts = 1;
    }

    ftruncate($f_day_info, 0);
    fwrite($f_day_info, serialize($ips));
    fflush($f_day_info);
    fflush($f_day_info);

    flock($f_day_info, LOCK_UN);
    fclose($f_day_info);

    $total_hits = s2_counter_get_total_hits($dir);
    s2_counter_refresh_file($dir . S2_COUNTER_TODAY_INFO_FNAME, $total_hits . "\n" . $today_hits . "\n" . $today_hosts);
}

function s2_counter_get_aggregator(string $userAgent): ?array
{
    foreach ([
                 'feeder.co'                              => 1,
                 'NetNewsWire'                            => 1,
                 'Feedspot'                               => 1,
                 'http://www.google.com/feedfetcher.html' => 0,
             ] as $noStatsAggregator => $readersNum) {
        if (strpos($userAgent, $noStatsAggregator) !== false) {
            return [$noStatsAggregator, $readersNum];
        }
    }

    $knownAggregators = array(
        'YandexBlog'      => '#(YandexBlog).*?(\d+) (readers)#',
        'AideRSS'         => '#(AideRSS).*?(\d+) (subscribers)#',
        'NewsGatorOnline' => '#(NewsGatorOnline).*?(\d+) (subscribers)#',
        'PostRank'        => '#(PostRank).*?(\d+) (subscribers)#',
        'Feedbin'         => '#(Feedbin feed-id:\d+) - (\d+) (subscribers)#',
        'theoldreader'    => '#(theoldreader).* (\d+) (subscribers; feed-id=[^\)]*)#',
        'universal'       => '#(Feedly|Bloglovin|BazQux|inoreader|NewsBlur).* (\d+) (subscribers)#',
    );

    foreach ($knownAggregators as $aggregator => $pattern) {
        if (false !== strpos($userAgent, $aggregator)) {
            break;
        }
    }

    if (preg_match($pattern, $userAgent, $matches)) {
        return array($matches[1] . $matches[3], $matches[2]);
    }

    return null;
}

function s2_counter_rss_count($dir)
{
    if (s2_counter_is_bot()) {
        return;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $client_ip  = $_SERVER['REMOTE_ADDR'];
    $filename   = '/data/rss_main.txt';

    ($hook = s2_hook('fn_s2_count_rss_count_start')) ? eval($hook) : null;

    if (!is_file($dir . $filename) && !is_writable(dirname($dir . $filename))) {
        return;
    }

    clearstatcache();
    if (!is_file($dir . $filename) || date('j', filemtime($dir . $filename)) === date('j')) {
        s2_counter_append_file($dir . $filename, time() . '^' . $client_ip . '^' . $user_agent . "\n");
    } else {
        $f_day_info = fopen($dir . $filename, 'a+');
        flock($f_day_info, LOCK_EX);

        $yesterday_log = @file_get_contents($dir . $filename);

        $total_readers = s2_counter_get_total_readers($yesterday_log);

        s2_counter_append_file($dir . $filename . '.log', date('Y-m-d', time() - 86400) . '^' . $total_readers . "\n");

        ftruncate($f_day_info, 0);
        fwrite($f_day_info, time() . '^' . $client_ip . '^' . $user_agent . "\n");
        fflush($f_day_info);
        fflush($f_day_info);

        flock($f_day_info, LOCK_UN);
        fclose($f_day_info);
    }

}

function s2_counter_get_total_readers(string $logContents): int
{
    $rss_readers = $online_aggregators = [];
    foreach (explode("\n", substr($logContents, 0, -1)) as $line) {
        if ($line === '') {
            continue;
        }

        [, $ip, $ua] = explode('^', $line);

        $aggregator_info = s2_counter_get_aggregator($ua);
        if ($aggregator_info !== null) {
            $online_aggregators[$aggregator_info[0]] = $aggregator_info[1];
        } else {
            [$ip0, $ip1] = preg_split('#[.:]#', $ip );
            $rss_readers[$ip0 . $ua . $ip1] = 1;
        }
    }

    return \count($rss_readers) + array_sum($online_aggregators);
}

define('S2_COUNTER_FUNCTIONS_LOADED', 1);
