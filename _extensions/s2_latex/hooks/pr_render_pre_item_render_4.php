<?php
/**
 * Hook pr_render_pre_item_render
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */

if (!defined('S2_ROOT')) {
    die;
}

$item['title'] = s2_latex_make($item['title']);
$item['text']  = s2_latex_make($item['text']);
