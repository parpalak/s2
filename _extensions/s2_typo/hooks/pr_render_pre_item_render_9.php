<?php
/**
 * Hook pr_render_pre_item_render
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_typo
 */

 if (!defined('S2_ROOT')) {
     die;
}

$item['title'] = s2_typo_make($item['title'], true);
$item['text'] = s2_typo_make($item['text']);
