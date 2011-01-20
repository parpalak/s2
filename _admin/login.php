<?php
/**
 * Login routines
 *
 * Maintain logins, logouts, checking permissions in the admin panel
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

// Session timeout
define('S2_EXPIRE_LOGIN_TIMEOUT', (S2_LOGIN_TIMEOUT > 1 ? S2_LOGIN_TIMEOUT : 1) * 60);

// Challenge timeout - 24 hours
define('S2_EXPIRE_CHALLENGE_TIMEOUT', 24 * 60 * 60);

//
// Creates hew challenge and puts it into DB
//
function s2_get_challenge ()
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

//
// Removes outdated challenges and sessions from DB
//
function s2_cleanup_expired ()
{
	global $s2_db;

	$time = time() - S2_EXPIRE_CHALLENGE_TIMEOUT;

	$query = array (
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'time < '.$time
	);
	($hook = s2_hook('fn_cleanup_expired_pre_remove_challenge_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$time = time() - S2_EXPIRE_LOGIN_TIMEOUT;

	$query = array (
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'time < '.$time.' AND login IS NOT NULL'
	);
	($hook = s2_hook('fn_cleanup_expired_pre_remove_session_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_get_login ($challenge)
{
	global $s2_db;

	$query = array(
		'SELECT'	=> 'login',
		'FROM'		=> 'users_online',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\' AND login IS NOT NULL'
	);
	($hook = s2_hook('fn_get_login_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	return $s2_db->num_rows($result) ? $s2_db->result($result) : false;
}

function s2_update_challenge_time ($challenge)
{
	global $s2_db;

	$query = array(
		'UPDATE'	=> 'users_online',
		'SET'		=> 'time = '.time(),
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_update_challenge_time_pre_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

function s2_test_user_rights ($challenge, $permissions)
{
	global $s2_db, $lang_admin;

	// If the challenge exists and isn't expired
	$query = array(
		'SELECT'	=> 'login, time',
		'FROM'		=> 'users_online',
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_test_user_rights_pre_get_time_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($s2_db->num_rows($result) != 1)
	{
		// Most likely the challenge was removed when another user tried to login
		header('X-S2-Status: Lost');
		die($lang_admin['Lost session']);
	}

	list($login, $time) = $s2_db->fetch_row($result);

	if (time() > $time + S2_EXPIRE_LOGIN_TIMEOUT)
	{
		header('X-S2-Status: Expired');
		die($lang_admin['Expired session']);
	}

	// Ok, we keep it fresh
	s2_update_challenge_time($challenge);

	// Testing permissions
	$query = array(
		'SELECT'	=> 'login',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$s2_db->escape($login).'\' AND ('.implode(' = 1 OR ', $permissions).' = 1)'
	);
	($hook = s2_hook('fn_test_user_rights_pre_check_perm_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$s2_db->num_rows($result))
	{
		header('X-S2-Status: Forbidden');
		die($lang_admin['No permission']);
	}

	return $login;
}

//=======================[Logging user in]======================================

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

	return $s2_db->num_rows($result) > 0 ? $s2_db->result($result) : false;
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

	return $s2_db->num_rows($result) ? $s2_db->result($result) : false;
}

function s2_login_success ($login, $challenge)
{
	global $s2_db, $s2_cookie_name;

	// We allow to login only from one browser
	// So we have to delete other challenges
	$query = array (
		'DELETE'	=> 'users_online',
		'WHERE'		=> 'login = \''.$s2_db->escape($login).'\' AND NOT challenge = \''.$s2_db->escape($challenge).'\''
	);

	($hook = s2_hook('fn_login_success_pre_delete_challenge_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$time = time();

	// Link the challenge to the user
	$query = array(
		'UPDATE'	=> 'users_online',
		'SET'		=> 'login = \''.$s2_db->escape($login).'\', time = '.$time,
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);

	($hook = s2_hook('fn_login_success_pre_update_challenge_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	setcookie($s2_cookie_name, $challenge);
}

function s2_try_login()
{
	global $s2_db, $lang_admin;

	$login = $_POST['login'];
	$challenge = isset($_POST['challenge']) ? $_POST['challenge'] : '';
	$key = isset($_POST['key']) ? $_POST['key'] : '';

	$redir = isset($_POST['redir']) ? $_POST['redir'] : '';

	($hook = s2_hook('fn_try_login_start')) ? eval($hook) : null;

	if (!$salt = s2_get_salt($challenge))
	{
		echo s2_get_login_form($lang_admin['Old login page']);
		return false;
	}

	do
	{
		// Getting user password
		$pass = s2_get_password_hash($login);
		if ($pass === false)
			break;

		($hook = s2_hook('fn_try_login_pre_password_check')) ? eval($hook) : null;

		// Verifying password
		if ($key != md5($pass.';-)'.$salt))
			break;

		// Everything is Ok.
		s2_login_success($login, $challenge);
		return $redir;
	} while (0);

	echo s2_get_login_form($lang_admin['Error login page']);

	return false;
}

function s2_logout ($challenge)
{
	global $s2_db, $s2_cookie_name;

	$time = time();
	($hook = s2_hook('fn_logout_start')) ? eval($hook) : null;

	$query = array(
		'UPDATE'	=> 'users_online',
		'SET'		=> 'login = NULL, time = '.$time,
		'WHERE'		=> 'challenge = \''.$s2_db->escape($challenge).'\''
	);
	($hook = s2_hook('fn_logout_pre_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	setcookie($s2_cookie_name, '');
}

//
// Returns login page
//
function s2_get_login_form ($message = '')
{
	global $lang_admin;
	list($challenge, $salt) = s2_get_challenge();

	s2_no_cache();
	ob_start();

	($hook = s2_hook('fn_get_login_form_pre_output')) ? eval($hook) : null;

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="Pragma" content="no-cache" />
<title>Login - S2</title>
<style type="text/css">
body {
	color: #000;
	backgroud: #fff;
	}
.table {
	border: 0;
	border-collapse: collapse;
	width: 100%;
	height: 100%;
	}
	.table tr td {
		padding: 0;
		}
	.table table tr td {
		background: #eef;
		padding: 1px;
		text-align: center;
		}
</style>
<script type="text/javascript" src="js/md5.js"></script>
<script type="text/javascript">
function SendForm ()
{
	document.loginform.redir.value = document.location;
	document.loginform.key.value = hex_md5(hex_md5(document.loginform.pass.value + 'Life is not so easy :-)') + ';-)<?php echo $salt ?>');
	document.loginform.pass.value = '';
	document.loginform.submit;
	return true;
}
</script>
</head>
<body onload="document.loginform.login.focus();">
	<table class="table">
		<tr><td align="center">
			<noscript><p><?php echo $lang_admin['Noscript']; ?></p></noscript>
			<b style="color: red;"><?php echo $message; ?></b>
			<form name="loginform" method="post" action="" onsubmit="SendForm();">
			<table>
				<tr>
					<td><?php echo $lang_admin['Login']; ?></td>
					<td>
						<input type="text" name="login" size="30" maxlength="255" />
					</td>
				</tr>
				<tr>
					<td><?php echo $lang_admin['Password']; ?></td>
					<td>
						<script type="text/javascript">document.write('<input type="password" name="pass" size="30" maxlength="255" />');</script>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<input type="submit" name="button" value="<?php echo $lang_admin['Log in'];; ?>" />
						<input type="hidden" name="key" value="" />
						<input type="hidden" name="redir" value="" />
						<input type="hidden" name="challenge" value="<?php echo $challenge ?>" />
					</td>
				</tr>
			</table>
			</form>
		</td></tr>
	</table>
</body>
</html>
<?php

	$form_page = ob_get_contents();
	ob_end_clean();

	($hook = s2_hook('fn_get_login_form_end')) ? eval($hook) : null;

	return $form_page;
}