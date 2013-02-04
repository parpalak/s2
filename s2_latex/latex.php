<?php
/**
 * Cache for latex.codecogs.com
 *
 * @copyright (C) 2011-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */


define('S2_ROOT', '../../');
define('S2_NO_DB', 1);
require S2_ROOT.'_include/common.php';

header('X-Powered-By: S2/'.S2_VERSION);

$formula = isset($_GET['latex']) ? trim((string) $_GET['latex']) : '';
$format = (isset($_GET['type']) && $_GET['type'] == 'svg' ) ? 'svg' : 'gif';

$hash = md5($formula);
$filename = 'cache/'.$hash.'.'.$format;
if (is_file($filename))
{
	$mtime = filemtime($filename);
 	if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $mtime <= strtotime(substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 5)))
	{
		header('HTTP/1.1 304 Not Modified');
		die;
	}
	$file = file_get_contents($filename);
}
else
{
	$result = s2_get_remote_file('http://latex.codecogs.com/'.$format.'.latex?'.rawurlencode($formula), 10);
	if (!$result)
		die('latex.codecogs.com is unavailable.');
	$file = $result['content'];
	if ($format == 'svg')
		$file = str_replace('<script type="text/ecmascript" xlink:href="http://codecogs.izyba.com/svg.js"/>', '', $file);

	file_put_contents($filename, $file);
	$mtime = time();
	clearstatcache();
}

header('Cache-Control: public,max-age=3600');
header('Expires: '.gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
header($format == 'svg' ? 'Content-type: image/svg+xml' : 'Content-type: image/gif');
header('Content-Length: '.strlen($file));

die($file);
