<?php
/**
 * Hook fn_output_tag_form_pre_url
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_blog', function ()
{
	if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
});
?>
		<label for="s2_blog_important_tag" title="<?php echo Lang::get('Important tag info', 's2_blog'); ?>">
			<input type="checkbox" id="s2_blog_important_tag" name="tag[s2_blog_important]" value="1"<?php if (!empty($tag['s2_blog_important'])) echo ' checked="checked"' ?> />
			<?php echo Lang::get('Important tag', 's2_blog'); ?>
		</label>
		<hr />
<?php
