<?php
/**
 * Displays image with hits/hosts info
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Expires: Mon, 26 Jul 1997 00:00:00 GMT');
header('Chace-Control: no-chace, must-revalidate');
header('Pragma: no-chace');

if (!is_file('data/today_info.txt') || false === ($data = file_get_contents('data/today_info.txt')))
	die;

$data = explode("\n", $data);

if (count($data) < 3)
	die;

$image = imagecreatefrompng('pattern.png');
$black = imagecolorallocate($image, 0, 0, 0);

imagestring($image, 1, 86 - 5*strlen(trim($data[0])),  2, trim($data[0]), $black);
imagestring($image, 1, 86 - 5*strlen(trim($data[1])), 12, trim($data[1]), $black);
imagestring($image, 1, 86 - 5*strlen(trim($data[2])), 22, trim($data[2]), $black);

header('Content-type: image/png');
imagepng($image);
imagedestroy($image);