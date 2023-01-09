<?php
/**
 * Hook ai_after_js_include
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_wysiwyg
 */

 if (!defined('S2_ROOT')) {
     die;
}

?>
<script type="text/javascript">
	var s2_wysiwyg_type = <?php echo S2_WYSIWYG_TYPE; ?>, s2_wysiwyg_cut = <?php echo S2_ADMIN_CUT; ?>, s2_wysiwyg_lang = '<?php echo substr(S2_LANGUAGE, 0, 7) == 'Russian' ? 'ru' : 'en'; ?>';
</script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_wysiwyg'; ?>/tiny_mce/tiny_mce.js"></script>
<script type="text/javascript" src="<?php echo S2_PATH.'/_extensions/s2_wysiwyg'; ?>/init.js"></script>
<?php
