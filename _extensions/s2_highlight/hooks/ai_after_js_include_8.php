<?php
/**
 * Hook ai_after_js_include
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_highlight
 */

 if (!defined('S2_ROOT')) {
     die;
}

?>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/codemirror.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/selection-pointer.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/xml.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/javascript.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/css.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/htmlmixed.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/clike.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/php.min.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/foldcode.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/foldgutter.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/xml-fold.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/annotatescrollbar.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/dialog.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/jump-to-line.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/matchesonscrollbar.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/search.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/codemirror/searchcursor.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_highlight'; ?>/init.js"></script>
<?php
