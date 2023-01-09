<?php
/**
 * Hook pr_render_pre_get_content
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */

 if (!defined('S2_ROOT')) {
     die;
}

require S2_ROOT.'/_extensions/s2_latex'.'/functions.php';

$content['rss_title'] = s2_latex_make($content['rss_title']);
$content['rss_description'] = s2_latex_make($content['rss_description']);
