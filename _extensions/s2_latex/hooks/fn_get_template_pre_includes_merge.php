<?php
/**
 * Hook fn_get_template_pre_includes_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */

 if (!defined('S2_ROOT')) {
     die;
}

$includes['css_inline'][] = '<script src="//i.upmath.me/latex.js"></script>';
