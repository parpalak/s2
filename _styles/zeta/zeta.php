<?php

if (!defined('S2_ROOT')) die;

ob_start();

?>
<link rel="shortcut icon" type="image/vnd.microsoft.icon" href="<?php echo S2_PATH.'/_styles/'.S2_STYLE ?>/favicon.ico" />
<?php

$link = ob_get_clean();

// Feel free to add your own styles and scripts
// Pathes here are relative to the template (this file).
return array(
	'css' => array(
		'site.css',
	),
	'css_inline' => array(
		$link,
	),
	'js' => array(
		'script.js',
	),
	'js_inline' => array(
//		'<script>alert(\'test\');</script>',
	),
);