<?php
/**
 * Hook idx_template_after_replace
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_typo
 */

 if (!defined('S2_ROOT')) {
     die;
}

require S2_ROOT.'/_extensions/s2_typo'.'/functions.php';
$template = s2_typo_make($template);
