<?php
/**
 * Picture manager
 *
 * Maintain picture displaying and management
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

define('S2_ROOT', '../');
require S2_ROOT.'_include/common.php';

// Activate HTTP Strict Transport Security
// IIS sets HTTPS to 'off' for non-SSL requests
if (defined('S2_FORCE_ADMIN_HTTPS') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
	header('Strict-Transport-Security: max-age=500');
elseif (defined('S2_FORCE_ADMIN_HTTPS'))
{
	header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	die();
}

require S2_ROOT.'_lang/'.S2_LANGUAGE.'/admin.php';
require S2_ROOT.'_lang/'.S2_LANGUAGE.'/pictures.php';
require 'login.php';
require 'pict_lib.php';

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

s2_no_cache();

$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';

if ($session_id == '')
{
	echo $lang_admin['Lost session'];
	$s2_db->close();
	die();
}

// Existed session
$login = s2_get_login($session_id);

if ($login === false)
{
	echo $lang_admin['Lost session'];
	$s2_db->close();
	die();
}

s2_update_challenge($session_id);
$s2_user = s2_get_user_info($login);

$is_permission = $s2_user['view'];
($hook = s2_hook('apm_start')) ? eval($hook) : null;
s2_test_user_rights($is_permission);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang_pictures['Pictures'];?></title>
<meta http-equiv="Pragma" content="no-cache" />
<link rel="stylesheet" type="text/css" href="css/style.css" />
<link rel="stylesheet" type="text/css" href="css/pictures.css" />
<script type="text/javascript" src="js/ajax.js"></script>
<script type="text/javascript" src="js/pictman.js"></script>
<script type="text/javascript" src="../_lang/<?php echo S2_LANGUAGE; ?>/ui.js"></script>
<script type="text/javascript">
var sUrl = '<?php echo S2_PATH; ?>/_admin/pict_ajax.php?';
var sPicturePrefix = '<?php echo S2_PATH.'/'.S2_IMG_DIR; ?>';
var iMaxFileSize = <?php echo s2_return_bytes(ini_get('upload_max_filesize')); ?>;
var sFriendlyMaxFileSize = '<?php echo s2_frendly_filesize(s2_return_bytes(ini_get('upload_max_filesize'))); ?>';
</script>
</head>

<body>
	<div id="tree_div">
		<div class="treetree">
			<div id="fupload">
				<?php s2_upload_form(); ?>
			</div>
			<ul>
				<li class="ExpandOpen IsLast"><div></div><div><span data-path=""><?php echo $lang_pictures['Pictures'];?></span></div><ul><?php echo s2_walk_dir(''); ?></ul></li>
			</ul>
			<div id="finfo"></div>
		</div>
		<div id="file-wrap">
			<div id="brd">
				<div id="files"></div>
			</div>
		</div>
	</div>
</body>
</html>
<?php

$s2_db->close();
