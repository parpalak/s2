<?php
/**
 * Admin panel
 *
 * Main page for the admin panel
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

define(S2_ROOT, '../');
require S2_ROOT.'_include/common.php';
require S2_ROOT.'_lang/'.S2_LANGUAGE.'/admin.php';
require 'site_lib.php';
require 'login.php';
require 'comments.php';

($hook = s2_hook('ai_start')) ? eval($hook) : null;

s2_no_cache();
header('Content-Type: text/html; charset=utf-8');

s2_cleanup_expired();

$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';

($hook = s2_hook('ai_pre_try_login')) ? eval($hook) : null;

if ($session_id == '')
{
	// New session

	if (!isset($_POST['login']))
	{
		// A simple login page loading
		echo s2_get_login_form();
		die();
	}

	// We have POST data. Process it! Handle errors if they are.
	if (($redirect_url = s2_try_login()) !== false)
	{
		// User logged in successfully. Let him go where he wants.
		header('Location: '.$redirect_url);
	}

	// We've already told the user everything we want to.
	die();
}

// Existed session
$login = s2_get_login($session_id);

if ($login === false)
{
	// We didn't find a session of a logged user
	// Most likely the user has refreshed the login page
	setcookie($s2_cookie_name, '');

	// We tell him nothing
	echo s2_get_login_form();
	die();
}

s2_update_challenge_time($session_id);

// Preparing the template for the preview tab
$return = ($hook = s2_hook('ai_pre_get_template')) ? eval($hook) : null;
$template = $return ? $return : s2_get_template('site.php');

ob_start();
include S2_ROOT.'_styles/'.S2_STYLE.'/'.S2_STYLE.'.php';
$template = str_replace('<!-- s2_styles -->', ob_get_clean(), $template);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang_admin['Admin panel'], S2_SITE_NAME ? ' - '.S2_SITE_NAME : ''; ?></title>
<meta http-equiv="Pragma" content="no-cache" />
<link rel="stylesheet" type="text/css" href="css/tabsheets.css" />
<link rel="stylesheet" type="text/css" href="css/style.css" />
<!--[if IE 8]><link rel="stylesheet" type="text/css" href="css/ie8.css" /><![endif]-->
<script type="text/javascript" src="../_lang/<?php echo S2_LANGUAGE; ?>/ui.js"></script>
<?php ($hook = s2_hook('ai_pre_js_include')) ? eval($hook) : null; ?>
<script type="text/javascript" src="js/ajax.js"></script>
<script type="text/javascript" src="js/md5.js"></script>
<script type="text/javascript" src="js/tabsheets.js"></script>
<script type="text/javascript" src="js/tablesort.js"></script>
<script type="text/javascript" src="js/admin.js"></script>
<script type="text/javascript">
var sUrl = '<?php echo S2_PATH; ?>/_admin/site_ajax.php?';
var cur_date = new Date();
var time_shift = Date.parse("<?php echo date('d M Y H:i:s'); ?>") - cur_date.getTime();
var template = '<?php echo str_replace(array("\n", "\r", '\'', '</script>'), array(' ', '', '\\\'', '</scr\' + \'ipt>') , $template); ?>';
<?php ($hook = s2_hook('ai_after_js_init')) ? eval($hook) : null; ?>
</script>
<?php ($hook = s2_hook('ai_head_end')) ? eval($hook) : null; ?>
</head>

<body>
	<div id="logout">
		<div id="loading"></div>
		<?php printf($lang_admin['Welcome'], $login)."\n"; ?><br />
		<a href="#" title="<?php echo $lang_admin['Logout info']; ?>" onclick="return Logout();"><?php echo $lang_admin['Logout']; ?></a>
	</div>
<?php ($hook = s2_hook('ai_pre_site')) ? eval($hook) : null; ?>
	<dl class="tabsheets">
		<dt id="list_tab"><?php echo $lang_admin['Site']; ?></dt>
		<dd>
			<div class="reducer" id="tree_div">
				<div id="keytable" class="r-float">
					<div class="tagswitcher"><img class="closed" src="i/1.gif" alt="<?php echo $lang_admin['Show tags']; ?>" onclick="return SwitchTags(this);"></div>
					<div id="tag_values"><?php echo $lang_admin['Choose tag']; ?></div>
					<div id="tag_names"><ul id="tag_list"></ul></div>
				</div>
<?php

	$padding = 2.5;
	($hook = s2_hook('ai_pre_tree_col')) ? eval($hook) : null;

?>
				<div class="l-float no-border" style="padding-bottom: <?php echo $padding; ?>em;">
					<div class="toolbar">
						<img class="expand" src="i/1.gif" onclick="OpenAll(); return false;" alt="<?php echo $lang_admin['Expand']; ?>" />
						<img class="collapse" src="i/1.gif" onclick="CloseAll(); return false;" alt="<?php echo $lang_admin['Collapse']; ?>" />
						<img class="refresh" src="i/1.gif" onclick="RefreshTree(); return false;" alt="<?php echo $lang_admin['Refresh']; ?>" />
						<input class="inactive" type="text" width="30" name="search" id="search_field" />
					</div>
					<?php s2_context_buttons(); ?>
					<div id="tree" class="treetree"></div>
				</div>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_pre_edit')) ? eval($hook) : null; ?>
		<dt id="edit_tab"><?php echo $lang_admin['Editor']; ?></dt>
		<dd>
			<div class="reducer" id="form_div"><?php s2_preload_editor(); ?></div>
		</dd>
<?php ($hook = s2_hook('ai_pre_review')) ? eval($hook) : null; ?>
		<dt id="view_tab"><?php echo $lang_admin['Preview']; ?></dt>
		<dd>
			<div class="reducer no-scroll" style="padding: 0;">
				<iframe src="<?php echo S2_PATH; ?>/_admin/site_ajax.php?action=load_preview_frame" frameborder="0" id="preview_frame" name="preview_frame"></iframe>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_pre_pictures')) ? eval($hook) : null; ?>
		<dt id="pict_tab"><?php echo $lang_admin['Pictures']; ?></dt>
		<dd>
			<div class="reducer no-scroll">
				<iframe src="" frameborder="0" id="pict_frame" name="pict_frame"></iframe>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_pre_comments')) ? eval($hook) : null; ?>
		<dt id="comm_tab"><?php echo $lang_common['Comments']; ?></dt>
		<dd>
			<div class="reducer" id="comm_div"><?php echo s2_for_premoderation(); ?></div>
		</dd>
<?php ($hook = s2_hook('ai_pre_tags')) ? eval($hook) : null; ?>
		<dt id="tag_tab"><?php echo $lang_admin['Tags']; ?></dt>
		<dd>
			<div class="reducer" id="tag_div"></div>
		</dd>
<?php ($hook = s2_hook('ai_pre_admin')) ? eval($hook) : null; ?>
		<dt id="admin_tab"><?php echo $lang_admin['Administrate']; ?></dt>
		<dd>
			<div class="reducer" id="admin_div" style="padding: 0;">
			<dl class="tabsheets">
<?php ($hook = s2_hook('ai_pre_stat')) ? eval($hook) : null; ?>
				<dt id="admin-stat_tab"><?php echo $lang_admin['Stat']; ?></dt>
				<dd>
					<div class="reducer" id="stat_div"></div>
				</dd>
<?php ($hook = s2_hook('ai_pre_options')) ? eval($hook) : null; ?>
				<dt id="admin-opt_tab"><?php echo $lang_admin['Options']; ?></dt>
				<dd>
					<div class="reducer" id="opt_div"></div>
				</dd>
<?php ($hook = s2_hook('ai_pre_users')) ? eval($hook) : null; ?>
				<dt id="admin-user_tab"><?php echo $lang_admin['Users']; ?></dt>
				<dd>
					<div class="reducer">
						<p align="center">
							<input type="text" name="userlogin" id="userlogin" size="30" value="" />
							<input class="bitbtn add_user" type="button" onclick="return AddUser(document.getElementById('userlogin').value);" value="<?php echo $lang_admin['Add user']; ?>" />
						</p>
						<hr />
						<div id="user_div"></div>
					</div>
				</dd>
<?php ($hook = s2_hook('ai_pre_extensions')) ? eval($hook) : null; ?>
				<dt id="admin-ext_tab"><?php echo $lang_admin['Extensions']; ?></dt>
				<dd>
					<div class="reducer" id="ext_div"></div>
				</dd>
<?php ($hook = s2_hook('ai_after_extensions')) ? eval($hook) : null; ?>
			</dl>
			</div>
		</dd>
<?php ($hook = s2_hook('ai_last_tab')) ? eval($hook) : null; ?>
	</dl>
	<script type="text/javascript">Make_Tabsheet();</script>
<?php ($hook = s2_hook('ai_after_tabs')) ? eval($hook) : null; ?>
</body>
</html>