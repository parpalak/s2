<?php
/**
 * Hook ai_head_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_highlight
 */

 if (!defined('S2_ROOT')) {
     die;
}

echo '<link rel="stylesheet" type="text/css" href="'.S2_PATH.'/_extensions/s2_highlight/codemirror.css" />'."\n";
echo '<link rel="stylesheet" type="text/css" href="'.S2_PATH.'/_extensions/s2_highlight/codemirror/foldgutter.css" />'."\n";
echo '<link rel="stylesheet" type="text/css" href="'.S2_PATH.'/_extensions/s2_highlight/codemirror/dialog.css" />'."\n";
echo '<link rel="stylesheet" type="text/css" href="'.S2_PATH.'/_extensions/s2_highlight/codemirror/matchesonscrollbar.css" />'."\n";
