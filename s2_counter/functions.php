<?php
/**
 * Functions of the counter extension
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */
 
if (!defined('S2_COUNTER_TOTAL_HITS_FNAME'))
	define('S2_COUNTER_TOTAL_HITS_FNAME', '/data/total_hits.txt');

if (!defined('S2_COUNTER_TODAY_INFO_FNAME'))
	define('S2_COUNTER_TODAY_INFO_FNAME', '/data/today_info.txt');

if (!defined('S2_COUNTER_ARCH_INFO_FNAME'))
	define('S2_COUNTER_ARCH_INFO_FNAME', '/data/arch_info.txt');

if (!defined('S2_COUNTER_TODAY_DATA_FNAME'))
	define('S2_COUNTER_TODAY_DATA_FNAME', '/data/today_data.txt');

function s2_counter_is_bot ()
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

	foreach ($sebot as $se1)
		if (stristr($_SERVER['HTTP_USER_AGENT'], $se1))
			return true;

	return false;
}

function s2_counter_get_total_hits ()
{
	global $ext_info;

	$f = fopen($ext_info['path'].S2_COUNTER_TOTAL_HITS_FNAME, 'a+');
	flock($f, LOCK_EX);

	$hits = intval(fread($f, 100)) + 1;

	ftruncate($f, 0);
	fwrite($f, $hits);
	fflush($f);

	flock($f, LOCK_UN);
	fclose($f);

	return $hits;
}

function s2_counter_save_curr_info ($total_hits, $today_hits, $today_hosts)
{
	global $ext_info;

	$f = fopen($ext_info['path'].S2_COUNTER_TODAY_INFO_FNAME, 'a+');
	flock($f, LOCK_EX);

	ftruncate($f, 0);
	@fputs($f, $total_hits."\n".$today_hits."\n".$today_hosts);
	fflush($f);

	flock($f, LOCK_UN);
	fclose($f);
}

function s2_counter_process ()
{
	global $ext_info;

	if (s2_counter_is_bot())
		return;

	if (!is_file($ext_info['path'].S2_COUNTER_TODAY_DATA_FNAME) && !is_writable(dirname($ext_info['path'].S2_COUNTER_TODAY_DATA_FNAME)))
		return;

	$f_day_info = fopen($ext_info['path'].S2_COUNTER_TODAY_DATA_FNAME, 'a+');
	flock($f_day_info, LOCK_EX);

	$ips = unserialize(file_get_contents($ext_info['path'].S2_COUNTER_TODAY_DATA_FNAME));

	if (date('j', filemtime($ext_info['path'].S2_COUNTER_TODAY_DATA_FNAME)) == date('j'))
	{
		// We have already some hits today

		// Let's correct the data saved before
		if (isset($ips[$_SERVER['REMOTE_ADDR']]))
			$ips[$_SERVER['REMOTE_ADDR']]++;
		else
			$ips[$_SERVER['REMOTE_ADDR']] = 1;

		$today_hosts = count($ips);
		$today_hits = array_sum($ips);
	}
	else
	{
		// It's a new day!

		// Logging yesterday info
		$f = fopen($ext_info['path'].S2_COUNTER_ARCH_INFO_FNAME, 'a+');
		fputs($f, date('Y-m-d', time() - 86400).'^'.array_sum($ips).'^'.count($ips)."\n");
		fclose($f);

		// Erase yesterday info
		unset($ips);
		$ips[$_SERVER['REMOTE_ADDR']] = 1;

		$today_hits = $today_hosts = 1;
	}

	// Let's write the modified data
	ftruncate($f_day_info, 0);
	fwrite($f_day_info, serialize($ips));
	fflush($f_day_info);
	fflush($f_day_info);

	flock($f_day_info,LOCK_UN);
	fclose($f_day_info);

	$total_hits = s2_counter_get_total_hits();
	s2_counter_save_curr_info($total_hits, $today_hits, $today_hosts);
}

define('S2_COUNTER_FUNCTIONS_LOADED', 1);