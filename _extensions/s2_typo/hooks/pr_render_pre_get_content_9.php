<?php
/**
 * Hook pr_render_pre_get_content
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_typo
 */

 if (!defined('S2_ROOT')) {
     die;
}

$content['rss_title'] = \s2_extensions\s2_typo\Typograph::processRussianText($content['rss_title'], true);
$content['rss_description'] = \s2_extensions\s2_typo\Typograph::processRussianText($content['rss_description'], true);
