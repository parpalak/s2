<?php
/**
 * Functions of the counter extension
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_counter
 */

use S2\Cms\Controller\Rss\RssStrategyInterface;
use Symfony\Component\HttpFoundation\Request;

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

function s2_counter_process()
{
    if (s2_counter_is_bot())
        return;

    $dir = __DIR__;

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

function s2_counter_rss_count(Request $request, RssStrategyInterface $rssStrategy) {
    if (s2_counter_is_bot()) {
        return;
    }

    $dir = __DIR__;

    $userAgent = $request->headers->get('User-Agent', '');
    $clientIp  = $request->getClientIp() ?? $_SERVER['REMOTE_ADDR'];
    $fileName   = match (get_class($rssStrategy)) {
        \S2\Cms\Model\Article\ArticleRssStrategy::class => '/data/rss_main.txt',
        \s2_extensions\s2_blog\Model\BlogRssStrategy::class => '/data/rss_s2_blog.txt',
        default => '/data/rss_'.array_reverse(explode('\\', get_class($rssStrategy)))[0].'.txt',
    };

    $fullFileName = $dir . $fileName;
    if (!is_file($fullFileName) && !is_writable(dirname($fullFileName))) {
        return;
    }

    clearstatcache();
    if (!is_file($fullFileName) || date('j', filemtime($fullFileName)) === date('j')) {
        s2_counter_append_file($fullFileName, time() . '^' . $clientIp . '^' . $userAgent . "\n");
    } else {
        $fileDayInfo = fopen($fullFileName, 'a+');
        flock($fileDayInfo, LOCK_EX);

        $yesterdayLog = @file_get_contents($fullFileName);

        $totalReaders = s2_counter_get_total_readers($yesterdayLog);

        s2_counter_append_file($fullFileName . '.log', date('Y-m-d', time() - 86400) . '^' . $totalReaders . "\n");

        ftruncate($fileDayInfo, 0);
        fwrite($fileDayInfo, time() . '^' . $clientIp . '^' . $userAgent . "\n");
        fflush($fileDayInfo);
        fflush($fileDayInfo);

        flock($fileDayInfo, LOCK_UN);
        fclose($fileDayInfo);
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
