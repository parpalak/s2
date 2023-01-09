<?php
/**
 * Hook fn_save_tag_pre_id_check
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

$s2_blog_important = isset($_POST['tag']['s2_blog_important']) ? (int) $_POST['tag']['s2_blog_important'] : 0;
