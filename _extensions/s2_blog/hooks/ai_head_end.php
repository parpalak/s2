<?php
/**
 * Hook ai_head_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

echo '<link rel="stylesheet" type="text/css" href="'.S2_PATH.'/_extensions/s2_blog'.'/admin.css" />'."\n";
