<?php
/**
 * Hook ai_pre_edit
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
require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';
?>
		<dt id="blog_tab"><?php echo Lang::get('Blog', 's2_blog'); ?></dt>
		<dd class="inactive">
			<div class="reducer" id="blog_wrapper">
				<form name="blogform">
					<table width="100%" class="fields">
						<tr>
							<td class="label"><?php echo Lang::get('Start time', 's2_blog'); ?></td>
							<td><input style="width: 10em;" type="text" name="posts[start_time]" size="20" value="" /></td>
							<td class="label"><?php echo Lang::get('Search label', 's2_blog'); ?></td>
							<td><input type="text" name="posts[text]" size="40" value="" /></td>
                            <td></td>
						</tr>
						<tr>
							<td class="label"><?php echo Lang::get('End time', 's2_blog'); ?></td>
							<td><input style="width: 10em;" type="text" name="posts[end_time]" size="20" value="<?php echo date(Lang::get('Date pattern', 's2_blog')); ?>" /></td>
							<td class="label"><?php echo Lang::get('Tag label', 's2_blog'); ?></td>
							<td><input type="text" name="posts[key]" size="40" value="" /></td>
							<td></td>
						</tr>
						<tr>
							<td class="label"><?php echo Lang::get('Author', 's2_blog'); ?></td>
							<td><input style="width: 10em;" type="text" name="posts[author]" size="20" value="" /></td>
							<td style="padding-left: 0.5em;"><label><input type="checkbox" name="posts[hidden]" value="1" checked="checked" /><?php echo Lang::get('Only hidden', 's2_blog'); ?></label></td>
                            <td><button class="find_posts long-button" name="button" type="submit" onclick="return LoadPosts();"><?php echo Lang::get('Show posts', 's2_blog'); ?></button></td>
                            <td align="right"><button class="add_post long-button" name="button" type="button" onclick="return CreateBlankRecord();"><?php echo Lang::get('Create new', 's2_blog'); ?></button></td>
						</tr>
					</table>
				</form>
				<div id="blog_div"><?php s2_blog_output_post_list(array('hidden' => 1)); ?></div>
			</div>
		</dd>
<?php
