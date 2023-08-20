<?php /** @noinspection PhpExpressionResultUnusedInspection */
/**
 * Displays information about S2 and environment in the admin panel
 *
 * @copyright (C) 2007-2013 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use S2\Cms\Pdo\DbLayer;

if (!defined('S2_ROOT'))
	die;

function s2_count_articles ($id)
{
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$n = 0;

	$query = array(
		'SELECT'	=> 'id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'published = 1 AND parent_id = '.$id
	);
	($hook = s2_hook('fn_count_articles_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);
	while ($row = $s2_db->fetchRow($result))
		$n += s2_count_articles($row[0]);

	($hook = s2_hook('fn_count_articles_end')) ? eval($hook) : null;
	return $n ? $n : 1;
}

function s2_get_counters ()
{
	global $lang_admin;
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$articles_num = s2_count_articles(1);

	$query = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'art_comments AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'articles AS a',
				'ON'			=> 'a.id = c.article_id'
			)
		),
		'WHERE'		=> 'c.shown = 1 AND a.published = 1'
	);
	($hook = s2_hook('fn_get_counters_pre_get_comm_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);
	$comments_num = $s2_db->result($result);

	$counters = array(
		sprintf($lang_admin['Articles'], $articles_num),
		sprintf($lang_admin['Comments'], $comments_num)
	);

	($hook = s2_hook('fn_get_counters_end')) ? eval($hook) : null;
	return implode('<br />', $counters);
}

function s2_stat_info ()
{
	global $db_name, $db_type, $db_version, $db_prefix, $lang_admin;
    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$output = '';

	// Get the server load averages (if possible)
	if (function_exists('sys_getloadavg') && is_array(sys_getloadavg()))
	{
		$load_averages = sys_getloadavg();
        $load_averages = array_map(function ($value) { return round($value, 3); }, $load_averages);
		$server_load = $load_averages[0].' '.$load_averages[1].' '.$load_averages[2];
	}
	else if (@is_readable('/proc/loadavg'))
	{
		$fh = @fopen('/proc/loadavg', 'r');
		$load_averages = @fread($fh, 64);
		@fclose($fh);

		$load_averages = empty($load_averages) ? array() : explode(' ', $load_averages);

		$server_load = isset($load_averages[2]) ? $load_averages[0].' '.$load_averages[1].' '.$load_averages[2] : 'Not available';
	}
	else if (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/i', @exec('uptime'), $load_averages))
		$server_load = $load_averages[1].' '.$load_averages[2].' '.$load_averages[3];
	else
		$server_load = $lang_admin['N/A'];

	// Collect some additional info about MySQL
	if ($db_type === 'mysql') {
		$db_version = 'MySQL '.$db_version;

		// Calculate total db size/row count
		$result = $s2_db->query('SHOW TABLE STATUS FROM `'.$db_name.'` WHERE NAME LIKE \''.$db_prefix.'%\' AND NAME NOT LIKE \''.$db_prefix.'s2_search_idx_%\'');

		$total_records = $total_size = 0;
		while ($status = $s2_db->fetchAssoc($result)) {
			$total_records += $status['Rows'];
			$total_size += $status['Data_length'] + $status['Index_length'];
		}

		$total_size = Lang::friendly_filesize($total_size);
	}

	$version = array(
		'<a href="http://s2cms.ru/" target="_blank">S2 '.S2_VERSION.' &uarr;</a>',
		'© 2007–2023 Roman Parpalak',
	);

	$environment = array(
		sprintf($lang_admin['OS'], PHP_OS),
		'<a href="site_ajax.php?action=phpinfo" title="'.$lang_admin['PHP info'].'" target="_blank">PHP: '.PHP_VERSION.' &uarr;</a>',
        sprintf($lang_admin['Server load'], $server_load),
	);

	$database = array(
		implode('<br>', $s2_db->getVersion()),
		!empty($total_records) ? sprintf($lang_admin['Rows'], $total_records) : '',
		!empty($total_size) ? sprintf($lang_admin['Size'], $total_size) : '',
	);

	($hook = s2_hook('fn_stat_info_pre_output_merge')) ? eval($hook) : null;

	$output .= '<div class="stat-item"><h3>'.$lang_admin['Already published'].'</h3>'.s2_get_counters().'</div>';
	$output .= '<div class="stat-item"><h3>'.$lang_admin['Environment'].'</h3>'.implode('<br />', $environment).'</div>';
	$output .= '<div class="stat-item"><h3>'.$lang_admin['Database'].'</h3>'.implode('<br />', $database).'</div>';
    $output .= '<div class="stat-item"><h3>'.$lang_admin['S2 version'].'</h3>'.implode('<br />', $version).'</div>';

    ($hook = s2_hook('fn_stat_info_pre_div_merge')) ? eval($hook) : null;

	$output = '<div class="stat-items">'.$output.'</div>';

	($hook = s2_hook('fn_stat_info_end')) ? eval($hook) : null;

	return $output;
}
