<?php
/**
 * Receives POST data and saves user comments.
 *
 * @copyright (C) 2009-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

define('S2_ROOT', './');
require S2_ROOT.'_include/common.php';
require S2_ROOT.'_include/comments.php';
require S2_ROOT.'_lang/'.S2_LANGUAGE.'/comments.php';

($hook = s2_hook('cmnt_start')) ? eval($hook) : null;

header('X-Powered-By: S2/'.S2_VERSION);

if (isset($_GET['go']))
{
	// Outputs "comment saved" message (used if the premoderation mode is enabled)
	$controller = new Page_Service(array(
		'head_title' => $lang_comments['Comment sent'],
		'title'      => '<h1>'.$lang_comments['Comment sent'].'</h1>',
		'text'       => sprintf($lang_comments['Comment sent info'], s2_htmlencode($_GET['go']), s2_link('/')),
	));

	$controller->render();

	die();
}

if (isset($_GET['unsubscribe']))
{
	header('Content-Type: text/html; charset=utf-8');

	if (isset($_GET['id']) && isset($_GET['mail']))
	{
		list($id, $class) = explode('.', $_GET['id']);
		$id = (int) $id;
		$class = (string) $class;

		$query = array(
			'SELECT'	=> 'id, nick, email, ip, time',
			'FROM'		=> 'art_comments',
			'WHERE'		=> 'article_id = '.$id.' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
		);
		($hook = s2_hook('cmnt_unsubscribe_pre_get_receivers_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$found = false;
		while ($receiver = $s2_db->fetch_assoc($result))
			if ($_GET['unsubscribe'] === base_convert(substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16), 16, 36))
				$found = true;

		if ($found)
		{
			$query = array(
				'UPDATE'	=> 'art_comments',
				'SET'		=> 'subscribed = 0',
				'WHERE'		=> 'article_id = '.$id.' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
			);
			($hook = s2_hook('cmnt_unsubscribe_pre_upd_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);

			$controller = new Page_Service(array(
				'head_title' => $lang_comments['Unsubscribed OK'],
				'title'      => $lang_comments['Unsubscribed OK info'],
				'text'       => $error_text . '<p>' . $lang_comments['Fix error'] . '</p>' . s2_comment_form($id.'.'.$class, $name, $email, $show_email, $subscribed, $text),
			));

			$controller->render();

			die();
		}
	}

	$controller = new Page_Service(array(
		'head_title' => $lang_comments['Unsubscribed failed'],
		'title'      => $lang_comments['Unsubscribed failed'],
		'text'       => $lang_comments['Unsubscribed failed info'],
	));

	$controller->render();

	die();
}

if (!defined('S2_MAX_COMMENT_BYTES'))
	define('S2_MAX_COMMENT_BYTES', 65535);

$_POST[s2_field_name('show_email')] = $show_email = (int) isset($_POST[s2_field_name('show_email')]);
$_POST[s2_field_name('subscribed')] = $subscribed = (int) isset($_POST[s2_field_name('subscribed')]);

($hook = s2_hook('cmnt_pre_post_check')) ? eval($hook) : null;

//
// Starting input validation
//

$errors = array();

if (!S2_ENABLED_COMMENTS)
	$errors[] = $lang_comment_errors['disabled'];

function get_ext_var($field)
{
	$field = s2_field_name($field);
	return !empty($_POST[$field]) ? trim((string) $_POST[$field]) : '';
}

$text = get_ext_var('text');
if ($text === '')
	$errors[] = $lang_comment_errors['missing_text'];
if (strlen($text) > S2_MAX_COMMENT_BYTES)
	$errors[] = sprintf($lang_comment_errors['long_text'], S2_MAX_COMMENT_BYTES);

$email = get_ext_var('email');
if (!s2_is_valid_email($email))
	$errors[] = $lang_comment_errors['email'];

$name = get_ext_var('name');
if (empty($name))
	$errors[] = $lang_comment_errors['missing_nick'];
if (utf8_strlen($name) > 50)
	$errors[] = $lang_comment_errors['long_nick'];

if (!s2_check_comment_question($_POST[s2_field_name('key')], $_POST[s2_field_name('quest')]))
	$errors[] = $lang_comment_errors['question'];

list($id, $class) = explode('.', get_ext_var('id'));
$id = (int) $id;
$class = (string) $class;

($hook = s2_hook('cmnt_after_post_check')) ? eval($hook) : null;

if (isset($_POST['preview']))
{
	// Handling "Preview" button

	($hook = s2_hook('cmnt_preview_pre_comment_merge')) ? eval($hook) : null;

	$viewer = new Viewer();
	$text_preview = '<p>' . $lang_comments['Comment preview info'] . '</p>' . "\n" .
		$viewer->render('comment', array(
			'text'       => $text,
			'nick'       => $name,
			'time'       => time(),
			'email'      => $email,
			'show_email' => $show_email,
		)) .
		"\t\t\t\t" . '<h2>' . $lang_common['Post a comment'] . '</h2>' . "\n" .
		s2_comment_form($id.'.'.$class, $name, $email, $show_email, $subscribed, $text);

	$controller = new Page_Service(array(
		'head_title' => $lang_comments['Comment preview'],
		'title'      => $lang_comments['Comment preview'],
		'text'       => $text_preview,
	));

	$controller->render();

	die();
}

// What are we going to comment?
$path = false;

$query = array(
	'SELECT'	=> 'title, parent_id, url',
	'FROM'		=> 'articles',
	'WHERE'		=> 'id = '.$id.' AND published = 1 AND commented = 1'
);
($hook = s2_hook('cmnt_pre_get_page_info_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

if (!$row = $s2_db->fetch_assoc($result))
	$errors[] = $lang_comment_errors['no_item'];
else
{
	$path = Model::path_from_id($row['parent_id'], true);
	($hook = s2_hook('cmnt_pre_path_check')) ? eval($hook) : null;
	if ($path === false)
		$errors[] = $lang_comment_errors['no_item'];
}

if (!empty($errors))
{
	$error_text = '<p>'.$lang_comment_errors['Error message'].'</p><ul>';
	foreach ($errors as $error)
		$error_text .=  '<li>'.$error.'</li>';
	$error_text .=  '</ul>';

	$controller = new Page_Service(array(
		'head_title' => $lang_common['Error'],
		'title'      => $lang_common['Error'],
		'text'       => $error_text . '<p>' . $lang_comments['Fix error'] . '</p>' . s2_comment_form($id.'.'.$class, $name, $email, $show_email, $subscribed, $text),
	));

	$controller->render();

	die();
}

$link = s2_abs_link($path.'/'.urlencode($row['url']));

//
// Everything is ok, save and send the comment
//

// Detect if there is a user logged in
$is_logged_in = false;
if (isset($_COOKIE[$s2_cookie_name.'_c']))
{
	$query = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'users_online AS o',
				'ON'			=> 'o.login = u.login'
			),
		),
		'WHERE'		=> 'u.email = \''.$s2_db->escape($email).'\' AND o.comment_cookie = \''.$s2_db->escape($_COOKIE[$s2_cookie_name.'_c']).'\''
	);
	($hook = s2_hook('cmnt_pre_get_logged_in_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$is_logged_in = $s2_db->result($result);
}

$is_moderate = $is_logged_in ? 0 : S2_PREMODERATION;
// Save the comment
$query = array(
	'INSERT'	=> 'article_id, time, ip, nick, email, show_email, subscribed, sent, shown, good, text',
	'INTO'		=> 'art_comments',
	'VALUES'	=> $id.', '.time().', \''.$s2_db->escape($_SERVER['REMOTE_ADDR']).'\', \''.$s2_db->escape($name).'\', \''.$s2_db->escape($email).'\', '.$show_email.', '.$subscribed.', '.(1 - $is_moderate).', '.(1 - $is_moderate).', 0, \''.$s2_db->escape($text).'\''
);
($hook = s2_hook('cmnt_pre_save_comment_qr')) ? eval($hook) : null;
$s2_db->query_build($query) or error(__FILE__, __LINE__);

$message = s2_bbcode_to_mail($text);

// Sending the comment to subscribers
if (!$is_moderate)
{
	$query = array(
		'SELECT'	=> 'id, nick, email, ip, time',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'article_id = '.$id.' AND subscribed = 1 AND shown = 1 AND email <> \''.$s2_db->escape($email).'\''
	);
	($hook = s2_hook('cmnt_pre_get_subscribers_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$receivers = array();
	while ($receiver = $s2_db->fetch_assoc($result))
		$receivers[$receiver['email']] = $receiver;

	foreach ($receivers as $receiver)
	{
		$unsubscribe_link = S2_BASE_URL.'/comment.php?mail='.urlencode($receiver['email']).'&id='.$id.'.'.$class.'&unsubscribe='.base_convert(substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16), 16, 36);
		($hook = s2_hook('cmnt_pre_send_mail')) ? eval($hook) : null;
		s2_mail_comment($receiver['nick'], $receiver['email'], $message, $row['title'], $link, $name, $unsubscribe_link);
	}
}

// Sending the comment to moderators
$query = array(
	'SELECT'	=> 'login, email',
	'FROM'		=> 'users',
	'WHERE'		=> 'hide_comments = 1 AND email <> \'\''
);
if ($is_logged_in)
	$query['WHERE'] .= ' AND email <> \''.$s2_db->escape($email).'\'';
($hook = s2_hook('cmnt_pre_get_moderators_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
while ($mrow = $s2_db->fetch_assoc($result))
	s2_mail_moderator($mrow['login'], $mrow['email'], $message, $row['title'], $link, $name, $email);

setcookie('comment_form_sent', $id.'.'.$class);

if (!$is_moderate)
{
	// Redirect to the last comment
	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'article_id = '.$id.' AND shown = 1'
	);
	($hook = s2_hook('cmnt_pre_get_comment_count_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$hash = $s2_db->result($result);

	($hook = s2_hook('cmnt_pre_redirect')) ? eval($hook) : null;

	header('Location: '.$link.'#'.$hash);
}
else
	header('Location: '.S2_BASE_URL.'/comment.php?go='.urlencode($link));

$s2_db->close();
