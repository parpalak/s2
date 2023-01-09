<?php
/**
 * Hook fn_load_tag_pre_qr
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

$query['SELECT'] .= ', s2_blog_important';
