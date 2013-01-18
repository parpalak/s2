<?php
/**
 * Login routines
 *
 * Maintain logins, logouts, checking permissions in the admin panel
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

// Session timeout
define('S2_EXPIRE_LOGIN_TIMEOUT', (S2_LOGIN_TIMEOUT > 1 ? S2_LOGIN_TIMEOUT : 1) * 60);

// Challenge timeout - 24 hours
define('S2_EXPIRE_CHALLENGE_TIMEOUT', 24 * 60 * 60);

// Cookie lifetime. Not less than 2 weeks.
define('S2_COOKIE_EXPIRE', 14*86400 > S2_EXPIRE_LOGIN_TIMEOUT ? 14*86400 : S2_EXPIRE_LOGIN_TIMEOUT);

//
// Custom cookie sender
//
function s2_setcookie($name, $value, $expire = 0)
{
	if (version_compare(PHP_VERSION, '5.2.0', '>='))
		setcookie($name, $value, $expire, S2_PATH.'/_admin/', null, defined('S2_FORCE_ADMIN_HTTPS'), true);
	else
		setcookie($name, $value, $expire, S2_PATH.'/_admin/; HttpOnly', null, defined('S2_FORCE_ADMIN_HTTPS'));
}

//
// Challenge management
//

// Creates hew challenge and puts it into DB
function s2_get_new_challenge ()
{
	global $s2_db;

	$time = time();

	// A unique stuff :)
	$challenge = md5(rand().'Let us write something... :-)'.$time);
	$salt = md5(rand().'And something else'.$time);

	$query = array(
		'INSERT'	=> 'challenge, salt, time',
		'INTO'		=> 'users_online',
		'VALUES'	=> '\''.$challenge.'\', \''.$salt.'\', '.$time
	);
	($hook = s2_hook('fn_get_challenge_pre_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	return array($challenge, $salt);
}

function s2_update_challenge ($challenge)
{
	global $s2_db;

	$query = array(
		'UPDATE'	=> 'users_online',
		'SET'		=> 'time = '.time().', ua = \''.$s2_db->escape($_SERVER['HTTP_USER_AGENT']).'\', ip = \''.$s2_db->escape($_SERVER['REMOTE_ADDR']).'\'',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_update_challenge_pre_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_delete_challenge ($challenge)
{
	global $s2_db;

	$query = array(
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_delete_challenge_pre_del_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_close_other_sessions ($challenge)
{
	global $s2_db, $s2_user;

	$query = array (
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'login = \''.$s2_db->escape($s2_user['login']).'\' AND NOT challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_close_other_sessions_pre_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

// Removes outdated challenges and sessions from DB
function s2_cleanup_expired_sessions ()
{
	global $s2_db;

	$time = time() - S2_EXPIRE_CHALLENGE_TIMEOUT;

	$query = array (
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'time < '.$time.' AND login IS NULL'
	);
	($hook = s2_hook('fn_cleanup_expired_pre_remove_challenge_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$time = time() - S2_COOKIE_EXPIRE;

	$query = array (
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'time < '.$time.' AND login IS NOT NULL'
	);
	($hook = s2_hook('fn_cleanup_expired_pre_remove_session_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

//
// Authentication
//

function s2_get_login ($challenge)
{
	global $s2_db;

	$query = array(
		'SELECT'	=> 'login, time',
		'FROM'		=> 'users_online',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\' AND ip = \''.$s2_db->escape($_SERVER['REMOTE_ADDR']).'\' AND login IS NOT NULL'
	);
	($hook = s2_hook('fn_get_login_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_row($result))
		list($login, $time) = $row;
	else
		return false;

	if (time() > $time + S2_EXPIRE_LOGIN_TIMEOUT)
	{
		s2_delete_challenge($challenge);
		return false;
	}

	return $login;
}

function s2_get_user_info ($login)
{
	global $s2_db;

	// Fetching user info
	$query = array(
		'SELECT'	=> '*',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$s2_db->escape($login).'\''
	);
	($hook = s2_hook('fn_get_user_info_pre_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	return $s2_db->fetch_assoc($result);
}

function s2_authenticate_user ($challenge)
{
	global $s2_db, $lang_admin, $s2_cookie_name;

	// If the challenge exists and isn't expired
	$query = array(
		'SELECT'	=> 'login, time, ip',
		'FROM'		=> 'users_online',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_authenticate_user_pre_get_time_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$status = '';
	if ($row = $s2_db->fetch_row($result))
		list($login, $time, $ip) = $row;
	else
		$status = 'Lost';

	$now = time();

	if (!$status && $now > $time + S2_EXPIRE_LOGIN_TIMEOUT)
		$status = 'Expired';

	if (!$status && ($ip != $_SERVER['REMOTE_ADDR']))
		$status = 'Wrong_IP';

	if ($status)
	{
		s2_delete_challenge($challenge);

		header('X-S2-Status: '.$status);
		s2_setcookie($s2_cookie_name, '');
		echo $lang_admin[$status.' session'];

		s2_get_ajax_login_form();
		die();
	}

	// Ok, we keep it fresh every 5 seconds.
	if ($now > $time + 5)
		s2_update_challenge($challenge);

	return s2_get_user_info($login);
}

function s2_test_user_rights ($is_permissions)
{
	global $lang_admin;

	if (!$is_permissions)
	{
		header('X-S2-Status: Forbidden');
		die($lang_admin['No permission']);
	}
}

//
// Logging user in
//

function s2_get_salt ($s)
{
	global $s2_db;

	$query = array(
		'SELECT'	=> 'salt',
		'FROM'		=> 'users_online',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($s).'\''
	);
	($hook = s2_hook('fn_verify_challenge_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	return ($return = $s2_db->result($result)) ? $return : false;
}

function s2_get_password_hash ($login)
{
	global $s2_db;

	$query = array(
		'SELECT'	=> 'password',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$s2_db->escape($login).'\''
	);
	($hook = s2_hook('fn_get_password_hash_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	return ($return = $s2_db->result($result)) ? $return : false;
}

function s2_login_success ($login, $challenge)
{
	global $s2_db, $s2_cookie_name;

	$time = time();

	// Link the challenge to the user
	$query = array(
		'UPDATE'	=> 'users_online',
		'SET'		=> 'login = \''.$s2_db->escape($login).'\', time = '.$time.', ua = \''.$s2_db->escape($_SERVER['HTTP_USER_AGENT']).'\', ip = \''.$s2_db->escape($_SERVER['REMOTE_ADDR']).'\'',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);

	($hook = s2_hook('fn_login_success_pre_update_challenge_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	s2_setcookie($s2_cookie_name, $challenge, ($time + S2_COOKIE_EXPIRE), S2_PATH.'/_admin/; HttpOnly', null, defined('S2_FORCE_ADMIN_HTTPS'));
}

function s2_ajax_login($login, $challenge, $key)
{
	global $lang_admin;

	($hook = s2_hook('fn_ajax_login_start')) ? eval($hook) : null;

	if (!$salt = s2_get_salt($challenge))
	{
		list($challenge, $salt) = s2_get_new_challenge();
		return 'OLD_SALT_'.$salt.'_'.$challenge;
	}

	if ($login == '')
		return $lang_admin['Error login page'];

	// Getting user password
	$pass = s2_get_password_hash($login);
	if ($pass === false)
		return $lang_admin['Error login page'];

	($hook = s2_hook('fn_ajax_login_pre_password_check')) ? eval($hook) : null;

	// Verifying password
	if ($key != md5($pass.';-)'.$salt))
		return $lang_admin['Error login page'];

	// Everything is Ok.
	s2_login_success($login, $challenge);

	return 'OK';
}

function s2_logout ($challenge)
{
	global $s2_cookie_name;

	s2_delete_challenge($challenge);
	s2_setcookie($s2_cookie_name, '');
}

//
// HTML builders
//

function s2_get_sessions ($login)
{
	global $s2_db, $lang_admin;

	$query = array(
		'SELECT'	=> 'ip, ua, time, challenge',
		'FROM'		=> 'users_online',
		'WHERE'		=> 'login = \''.$s2_db->escape($login).'\' AND time >= '.(time() - S2_EXPIRE_LOGIN_TIMEOUT),
		'ORDER BY'	=> 'time ASC'
	);
	($hook = s2_hook('fn_get_other_sessions_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$known_browsers = array('Opera', 'Firefox', 'Chrome', 'Safari', 'MSIE', 'Mozilla');
	$browser_aliases = array('MSIE' => 'Internet Explorer');

	$sessions = array();
	while ($session = $s2_db->fetch_assoc($result))
	{
		$detected_ua = '';
		foreach ($known_browsers as $browser)
		{
			if (strpos($session['ua'], $browser) !== false)
			{
				$browser_name = isset($browser_aliases[$browser]) ? $browser_aliases[$browser] : $browser;
				$detected_ua = '<span title="'.$session['ua'].'">'.$browser_name.'</span>';
				break;
			}
		}

		if (!$detected_ua)
			$detected_ua = $session['ua'];

		$cur_line = s2_date_time($session['time']).'&nbsp; '.$session['ip'].'&nbsp; '.$detected_ua;

		$sessions[] = $cur_line;
	}

	if (count($sessions) <= 1)
		return '';

	return 'PopupMessages.show(\''.sprintf($lang_admin['Other sessions'], implode('<br />', $sessions)).'\', [{name: \''.$lang_admin['Close other sessions'].'\', action: function () { CloseOtherSessions(); }, once: true}]);'."\n";
}

// Returns ajax login form
function s2_get_ajax_login_form ()
{
	global $lang_admin;

	list($challenge, $salt) = s2_get_new_challenge();

?>
	<form name="loginform" method="post" action="" data-salt="<?php echo $salt ?>" onsubmit="SendAjaxLoginForm(); return false; ">
		<input type="password" name="pass" size="30" maxlength="255" />
		<input type="submit" name="button" value="<?php echo $lang_admin['Log in'];; ?>" />
		<input type="hidden" name="login" value="" />
		<input type="hidden" name="key" value="" />
		<input type="hidden" name="challenge" value="<?php echo $challenge ?>" />
	</form>
	<div id="ajax_login_message"></div>
<?php

}

// Returns login page
function s2_get_login_form ($message = '')
{
	global $lang_admin;

	list($challenge, $salt) = s2_get_new_challenge();

	s2_no_cache();
	ob_start();

	($hook = s2_hook('fn_get_login_form_pre_output')) ? eval($hook) : null;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo $lang_admin['Admin panel'], S2_SITE_NAME ? ' - '.S2_SITE_NAME : ''; ?></title>
<link rel="stylesheet" href="css/style.css">
<!--[if lt IE 9]>
<script src="js/jquery-1.9.0.min.js"></script>
<![endif]-->
<!--[if gte IE 9]><!-->
<script src="js/jquery.js"></script>
<!--><![endif]-->
<script src="js/ajax.js"></script>
<script>
var sUrl = '<?php echo S2_PATH; ?>/_admin/site_ajax.php?', shake = null;

function SendForm ()
{
	var $form = $(document.forms['loginform']);

	function shift (time)
	{
		$form.css({left: parseInt(-150.0 * Math.exp(-time * 0.006) * Math.sin(0.026179938 * time)) + 'px'});
	}

	clearInterval(shake);
	shift(0);

	SendLoginData($form[0], function ()
	{
		document.location.reload();
	}, function (sText)
	{
		$('#message').html(sText);

		var time = new Date().getTime();
		shake = setInterval(function ()
		{
			var now = (new Date().getTime()) - time;
			if (now > 835)
			{
				shift(0);
				clearInterval(shake);
				shake = null;
			}
			else
				shift(now);
		}, 30);
	});
}

function LoginInit ()
{
	var eLogin = document.forms['loginform'].elements['login'],
		ePass = document.forms['loginform'].elements['pass'];

	eLogin.focus();
	$(ePass).removeAttr('disabled');

	var login = '', password = '';

	eLogin.onkeyup =
	ePass.onkeyup = function (e)
	{
		if (shake)
			return;

		if (login != $(eLogin).val() || password != $(ePass).val())
		{
			$('#message').empty();
			login = $(eLogin).val();
			password = $(ePass).val();
		}
	};
}
</script>
</head>
<body id="login_wrap" onload="LoginInit();">
	<noscript><p><?php echo $lang_admin['Noscript']; ?></p></noscript>
	<form name="loginform" class="loginform" method="post" action="" data-salt="<?php echo $salt ?>" onsubmit="SendForm(); return false; ">
		<p>
		<label>
			<span><?php echo $lang_admin['Login']; ?></span>
			<input type="text" name="login" size="30" maxlength="255">
		</label>
		<label>
			<span><?php echo $lang_admin['Password']; ?></span>
			<input type="password" name="pass" size="30" maxlength="255" disabled="disabled">
		</label>
		</p>
		<p>
			<input type="submit" name="button" value="<?php echo $lang_admin['Log in'];; ?>" />
			<input type="hidden" name="key" value="" />
			<input type="hidden" name="challenge" value="<?php echo $challenge ?>" />
		</p>
	</form>
	<p id="message" class="message"></p>
</body>
</html>
<?php

	$form_page = ob_get_contents();
	ob_end_clean();

	($hook = s2_hook('fn_get_login_form_end')) ? eval($hook) : null;

	return $form_page;
}