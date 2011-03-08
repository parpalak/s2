<?php
/**
 * Ajax request processing for autosearch
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


header('Content-Type: text/html; charset=utf-8');

define('S2_ROOT', '../../');
require(S2_ROOT.'_include/common.php');

header('X-Powered-By: S2/'.S2_VERSION);

$s2_search_query = isset($_GET['q']) ? $_GET['q'] : '';

require 'stemmer.class.php';
require 'finder.class.php';

if ($s2_search_query !== '')
	s2_search_finder::find_autosearch($s2_search_query);
