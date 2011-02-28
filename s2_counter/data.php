<?php
/**
 * Outputs the file content
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */


header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Expires: Mon, 26 Jul 1997 00:00:00 GMT');
header('Chace-Control: no-chace, must-revalidate');
header('Pragma: no-chace');

$filename = isset($_GET['file']) ? trim(preg_replace('#[^0-9a-z_\-\.]#', '', $_GET['file']), '.') : '';
if (!$filename || !is_file('data/'.$filename))
{
	// "Empty" file
	$now = time();
	for ($i = 366; --$i ;)
		echo date('Y-m-d', $now - 86400 * $i).'^0'."\n";
	die;
}
echo file_get_contents('data/'.$filename);