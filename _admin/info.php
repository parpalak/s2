<?php
/**
 * Displays information about S2 and environment in the admin panel
 *
 * @copyright (C) 2007-2011 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

function s2_count_articles ($id)
{
	global $s2_db;

	$n = 0;

	$query = array(
		'SELECT'	=> 'id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'published = 1 AND parent_id = '.$id
	);
	($hook = s2_hook('fn_count_articles_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	while ($row = $s2_db->fetch_row($result))
		$n += s2_count_articles($row[0]);

	($hook = s2_hook('fn_count_articles_end')) ? eval($hook) : null;
	return $n ? $n : 1;
}

function s2_get_counters ()
{
	global $s2_db, $lang_admin;

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
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$comments_num = $s2_db->result($result);

	$counters = array(
		sprintf($lang_admin['Articles'], $articles_num),
		sprintf($lang_admin['Comments'], $comments_num)
	);

	($hook = s2_hook('fn_get_counters_end')) ? eval($hook) : null;
	return implode('<br />', $counters);
}

function s2_stat_info()
{
	global $s2_db, $db_name, $db_type, $db_version, $db_prefix, $lang_admin;

	$output = '';

	// Get the server load averages (if possible)
	if (function_exists('sys_getloadavg') && is_array(sys_getloadavg()))
	{
		$load_averages = sys_getloadavg();
		array_walk($load_averages, create_function('&$v', '$v = round($v, 3);'));
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
	if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
	{
		$db_version = 'MySQL '.$db_version;

		// Calculate total db size/row count
		$result = $s2_db->query('SHOW TABLE STATUS FROM `'.$db_name.'` LIKE \''.$db_prefix.'%\'') or error(__FILE__, __LINE__);

		$total_records = $total_size = 0;
		while ($status = $s2_db->fetch_assoc($result))
		{
			$total_records += $status['Rows'];
			$total_size += $status['Data_length'] + $status['Index_length'];
		}

		$total_size = s2_frendly_filesize($total_size);
	}

	// Check for the existance of various PHP opcode caches/optimizers
	if (function_exists('mmcache'))
		$php_accelerator = '<a href="http://turck-mmcache.sourceforge.net/">Turck MMCache</a>';
	else if (isset($_PHPA))
		$php_accelerator = '<a href="http://www.php-accelerator.co.uk/">ionCube PHP Accelerator</a>';
	else if (ini_get('apc.enabled'))
		$php_accelerator ='<a href="http://www.php.net/apc/">Alternative PHP Cache (APC)</a>';
	else if (ini_get('zend_optimizer.optimization_level'))
		$php_accelerator = '<a href="http://www.zend.com/products/zend_optimizer/">Zend Optimizer</a>';
	else if (ini_get('eaccelerator.enable'))
		$php_accelerator = '<a href="http://eaccelerator.net/">eAccelerator</a>';
	else if (ini_get('xcache.cacher'))
		$php_accelerator = '<a href="http://trac.lighttpd.net/xcache/">XCache</a>';
	else
		$php_accelerator = $lang_admin['N/A'];

	$version = array(
		'<a href="http://s2cms.ru/" target="_blank">S2 '.S2_VERSION.' &uarr;</a>',
		'© 2007–2011 Roman Parpalak',
	);

	$environment = array(
		sprintf($lang_admin['OS'], PHP_OS),
		'<a href="site_ajax.php?action=phpinfo" title="'.$lang_admin['PHP info'].'" target="_blank">PHP: '.PHP_VERSION.' &uarr;</a>',
		sprintf($lang_admin['Accelerator'], $php_accelerator),
	);

	$database = array(
		implode(' ', $s2_db->get_version()),
		!empty($total_records) ? sprintf($lang_admin['Rows'], $total_records) : '',
		!empty($total_size) ? sprintf($lang_admin['Size'], $total_size) : '',
	);

	($hook = s2_hook('fn_stat_info_pre_output_merge')) ? eval($hook) : null;

	$output .= '<div class="input"><span class="subhead">'.$lang_admin['Already published'].'</span>'.s2_get_counters().'</div>';
	$output .= '<div class="input"><span class="subhead">'.$lang_admin['Server load'].'</span>'.$server_load.'</div>';
	$output .= '<div class="input"><span class="subhead">'.$lang_admin['S2 version'].'</span>'.implode('<br />', $version).'</div>';
	$output .= '<div class="input"><span class="subhead">'.$lang_admin['Environment'].'</span>'.implode('<br />', $environment).'</div>';
	$output .= '<div class="input"><span class="subhead">'.$lang_admin['Database'].'</span>'.implode('<br />', $database).'</div>';

	$output = '<fieldset><legend>'.$lang_admin['Stat'].'</legend>'.$output.'</fieldset>';

	($hook = s2_hook('fn_stat_info_end')) ? eval($hook) : null;

	return $output;
}