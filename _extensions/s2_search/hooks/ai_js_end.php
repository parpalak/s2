<?php
/**
 * Hook ai_js_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

$size = 0;
try {
	\Container::get(\S2\Rose\Storage\Database\PdoStorage::class)->getIndexStat();
	$size = \Container::get(\S2\Rose\Storage\Database\PdoStorage::class)->getTocSize(null);
} catch (\Exception $e) {
}
if ($size === 0)
{
	Lang::load('s2_search', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_search'.'/lang/English.php';
	});

?>
PopupMessages.show('<?php echo Lang::get('Indexing required', 's2_search'); ?>', [
	{
		name: '<?php echo Lang::get('Index now', 's2_search'); ?>',
		action: s2_search.build_index,
		once: true
	}
]);
<?php
}
