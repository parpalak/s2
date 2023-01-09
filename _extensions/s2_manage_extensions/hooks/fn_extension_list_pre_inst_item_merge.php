<?php
/**
 * Hook fn_extension_list_pre_inst_item_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_manage_extensions
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_manage_extensions', function () use ($ext_info)
{
	if (file_exists(S2_ROOT.'/_extensions/s2_manage_extensions'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_manage_extensions'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_manage_extensions'.'/lang/English.php';
});
$buttons = array_merge(array('refresh_hooks' => '<button class="bitbtn" style="background-image: url('.S2_PATH.'/_extensions/s2_manage_extensions'.'/r.png);" onclick="GETAsyncRequest(sUrl + \'action=s2_manage_extensions_refresh_hooks&id='.s2_htmlencode(addslashes($id)).'\'); return false;">'.Lang::get('Refresh hooks', 's2_manage_extensions').'</button>'), $buttons);
