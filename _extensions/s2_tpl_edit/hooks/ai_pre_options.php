<?php
/**
 * Hook ai_pre_options
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($s2_user['edit_users'])
{
	Lang::load('s2_tpl_edit', function () use ($ext_info)
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_tpl_edit'.'/functions.php';
?>
<style>#admin-tpl_tab:before { background-position: -32px -64px; }</style>
				<dt id="admin-tpl_tab"><?php echo Lang::get('Templates', 's2_tpl_edit'); ?></dt>
				<dd class="inactive">
					<div class="reducer" id="s2_tpl_edit_div"><?php echo s2_tpl_edit_form(); ?></div>
				</dd>
<?php
}
