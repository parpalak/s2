<?php
/**
 * Hook ai_after_js_init
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_wysiwyg
 */

 if (!defined('S2_ROOT')) {
     die;
}

?>
var s2_wysiwyg_pict_url = '<?php echo S2_PATH; ?>/_admin/pictman.php';
<?php
