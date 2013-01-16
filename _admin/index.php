<?php
/**
 * Admin panel
 *
 * Main page for the admin panel
 *
 * @copyright (C) 2007-2012 Roman Parpalak
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
require 'site_lib.php';
require 'login.php';
require 'comments.php';

($hook = s2_hook('ai_start')) ? eval($hook) : null;

s2_no_cache();
header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

s2_cleanup_expired_sessions();

$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';

($hook = s2_hook('ai_pre_try_login')) ? eval($hook) : null;

if ($session_id == '')
{
	// New session

	// A simple login page loading
	echo s2_get_login_form();

	$s2_db->close();

	die();
}

// Existed session
$login = s2_get_login($session_id);

if ($login === false)
{
	// We didn't find a session of a logged user
	// Most likely the user has refreshed the login page
	s2_setcookie($s2_cookie_name, '');

	// We tell him nothing
	echo s2_get_login_form();

	$s2_db->close();

	die();
}

s2_update_challenge($session_id);
$s2_user = s2_get_user_info($login);

// Preparing the template for the preview tab
$return = ($hook = s2_hook('ai_pre_get_template')) ? eval($hook) : null;
$template = $return ? $return : s2_get_template('site.php');

function s2_jsvarencode ($var)
{
	return str_replace(array('\\', '\'', '</script>', "\n", "\r"), array('\\\\', '\\\'', '</scr\' + \'ipt>', "\\\n", '') , $var);
}

ob_start();
include S2_ROOT.'_styles/'.S2_STYLE.'/'.S2_STYLE.'.php';
($hook = s2_hook('ai_pre_get_tpl_styles')) ? eval($hook) : null;
$template = str_replace('<!-- s2_styles -->', ob_get_clean(), $template);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang_admin['Admin panel'], S2_SITE_NAME ? ' - '.S2_SITE_NAME : ''; ?></title>
<meta http-equiv="Pragma" content="no-cache" />
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
<meta name="viewport" content="width=640, initial-scale=1">
<link rel="stylesheet" type="text/css" href="css/style.css" />
<!--[if IE 8]><link rel="stylesheet" type="text/css" href="css/ie8.css" /><![endif]-->
<!--[if IE 9]><link rel="stylesheet" type="text/css" href="css/ie9.css" /><![endif]-->
<style>body {background-color: <?php echo s2_jsvarencode(S2_ADMIN_COLOR); ?>;}</style>
<?php ($hook = s2_hook('ai_head_end')) ? eval($hook) : null; ?>
</head>

<body tabindex="0">
	<div id="logout">
		<div id="loading"></div>
		<?php printf($lang_admin['Welcome'], $login); ?><br />
		<a href="#" title="<?php echo $lang_admin['Logout info']; ?>" onclick="return Logout();"><?php echo $lang_admin['Logout']; ?></a>
	</div>
<?php ($hook = s2_hook('ai_pre_site')) ? eval($hook) : null; ?>
	<dl class="tabsheets">
		<dt id="list_tab"><?php echo $lang_admin['Site']; ?></dt>
		<dd class="inactive">
			<div class="reducer">
<?php

$padding = 2.5;
($hook = s2_hook('ai_pre_tree_col')) ? eval($hook) : null;

?>
				<div class="height_wrap" style="padding-bottom: <?php echo $padding; ?>em;">
					<div class="toolbar">
						<img class="expand" src="i/1.gif" onclick="OpenAll(); return false;" alt="<?php echo $lang_admin['Expand']; ?>" />
						<img class="separator" src="i/1.gif" alt="" />
						<img class="collapse" src="i/1.gif" onclick="CloseAll(); return false;" alt="<?php echo $lang_admin['Collapse']; ?>" />
						<img class="separator" src="i/1.gif" alt="" />
						<img class="refresh" src="i/1.gif" alt="<?php echo $lang_admin['Refresh']; ?>" />
						<input class="inactive" type="text" name="search" id="search_field" value="<?php echo $lang_admin['Search']?>" />
					</div>
					<?php s2_context_buttons(); ?>
					<div id="tree" class="treetree" tabindex="0"></div>
				</div>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_pre_edit')) ? eval($hook) : null; ?>
		<dt id="edit_tab"><?php echo $lang_admin['Editor']; ?></dt>
		<dd class="inactive">
			<div class="reducer" id="form_div"><?php echo $lang_admin['Empty editor info']; ?></div>
		</dd>
<?php ($hook = s2_hook('ai_pre_review')) ? eval($hook) : null; ?>
		<dt id="view_tab"><?php echo $lang_admin['Preview']; ?></dt>
		<dd class="inactive">
			<div class="reducer no-scroll" style="padding: 0;">
				<iframe src="" frameborder="0" id="preview_frame" name="preview_frame"></iframe>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_pre_pictures')) ? eval($hook) : null; ?>
		<dt id="pict_tab"><?php echo $lang_admin['Pictures']; ?></dt>
		<dd class="inactive">
			<div class="reducer no-scroll" style="padding: 0;">
				<iframe src="" frameborder="0" id="pict_frame" name="pict_frame"></iframe>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_pre_comments')) ? eval($hook) : null; ?>
		<dt id="comm_tab"><?php echo $lang_common['Comments']; ?></dt>
		<dd class="inactive">
			<div class="reducer" id="comm_div"><?php $premoderation = s2_for_premoderation(); echo $premoderation['content']; ?></div>
		</dd>
<?php ($hook = s2_hook('ai_pre_tags')) ? eval($hook) : null; ?>
		<dt id="tag_tab"><?php echo $lang_common['Tags']; ?></dt>
		<dd class="inactive">
			<div class="reducer" id="tag_div"></div>
		</dd>
<?php

($hook = s2_hook('ai_pre_admin')) ? eval($hook) : null;

if ($s2_user['view_hidden'] || $s2_user['edit_users'])
{

?>
		<dt id="admin_tab"><?php echo $lang_admin['Administrate']; ?></dt>
		<dd class="inactive">
			<div class="reducer" id="admin_div" style="padding: 0;">
			<dl class="tabsheets">
<?php ($hook = s2_hook('ai_pre_stat')) ? eval($hook) : null; ?>
				<dt id="admin-stat_tab"><?php echo $lang_admin['Stat']; ?></dt>
				<dd class="inactive">
					<div class="reducer" id="stat_div"></div>
				</dd>
<?php ($hook = s2_hook('ai_pre_options')) ? eval($hook) : null; ?>
				<dt id="admin-opt_tab"><?php echo $lang_admin['Options']; ?></dt>
				<dd class="inactive">
					<div class="reducer" id="opt_div"></div>
				</dd>
<?php ($hook = s2_hook('ai_pre_users')) ? eval($hook) : null; ?>
				<dt id="admin-user_tab"><?php echo $lang_admin['Users']; ?></dt>
				<dd class="inactive">
					<div class="reducer">
						<div id="user_div"></div>
<?php

	if ($s2_user['edit_users'])
	{

?>
						<fieldset>
							<legend><?php echo $lang_admin['Add user']; ?></legend>
							<p>
								<form name="adduserform" action="" onsubmit="AddUser(this); return false;">
									<input type="text" name="userlogin" size="30" value="" />
									<input class="bitbtn add_user" type="submit" value="<?php echo $lang_admin['Add user']; ?>" />
								</form>
							</p>
						</fieldset>
<?php

	}

?>
					</div>
				</dd>
<?php

	($hook = s2_hook('ai_pre_extensions')) ? eval($hook) : null;

	if ($s2_user['edit_users'])
	{

?>
				<dt id="admin-ext_tab"><?php echo $lang_admin['Extensions']; ?></dt>
				<dd class="inactive">
					<div class="reducer" id="ext_div"></div>
				</dd>
<?php

	}

	($hook = s2_hook('ai_after_extensions')) ? eval($hook) : null;

?>
			</dl>
			</div>
		</dd>
<?php

}

($hook = s2_hook('ai_last_tab')) ? eval($hook) : null;

?>
	</dl>
<?php ($hook = s2_hook('ai_after_tabs')) ? eval($hook) : null; ?>
<script type="text/javascript" src="js/ajax.js"></script>
<script type="text/javascript">
SetBackground('<?php echo s2_jsvarencode(S2_ADMIN_COLOR); ?>');
</script>
<script type="text/javascript" src="../_lang/<?php echo S2_LANGUAGE; ?>/ui.js"></script>
<?php ($hook = s2_hook('ai_pre_js_include')) ? eval($hook) : null; ?>
<!--[if lt IE 9]>
<script type="text/javascript" src="js/jquery-1.9.0.min.js"></script>
<![endif]-->
<!--[if gte IE 9]><!-->
<script type="text/javascript" src="js/jquery.js"></script>
<!--[endif]-->
<script src="http://code.jquery.com/jquery-migrate-1.0.0.js"></script>
<script type="text/javascript" src="js/jquery-tools.js"></script>
<script type="text/javascript" src="js/jquery.jstree.js"></script>
<script type="text/javascript" src="js/admin.js"></script>
<?php ($hook = s2_hook('ai_after_js_include')) ? eval($hook) : null; ?>
<script type="text/javascript">
var sUrl = '<?php echo S2_PATH; ?>/_admin/site_ajax.php?';
var cur_date = new Date();
var username = '<?php echo s2_jsvarencode($login); ?>';
var time_shift = Date.parse("<?php echo date('d M Y H:i:s'); ?>") - cur_date.getTime();
var template = '<?php echo s2_jsvarencode($template); ?>';
var new_page_pos = '<?php echo s2_jsvarencode(S2_ADMIN_NEW_POS); ?>';

<?php

s2_preload_editor();
if (isset($premoderation['script']))
	echo $premoderation['script'];
echo s2_get_sessions($login);

($hook = s2_hook('ai_after_js_init')) ? eval($hook) : null;

?>
makeTabsheet();
<?php

($hook = s2_hook('ai_js_end')) ? eval($hook) : null;

if ($s2_user['edit_users'] && S2_ADMIN_UPDATES)
{

?>
GETAsyncRequest(sUrl + 'action=check_updates', function (http, data)
{
	if (data.is_update)
		PopupMessages.show(data.message);
});
<?php

}

?>
</script>
</body>
</html>
<?php

$s2_db->close();
