<?php
/**
 * Hook ai_js_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (!is_writable(S2_ROOT.'/_extensions/s2_counter'.'/data/'))
{
	Lang::load('s2_counter', function () use ($ext_info)
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_counter'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_counter'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_counter'.'/lang/English.php';
	});

?>
PopupMessages.show('<?php printf(Lang::get('Data folder not writable', 's2_counter'), S2_ROOT.'/_extensions/s2_counter'.'/data/'); ?>');
<?php
}
