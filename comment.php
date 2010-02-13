<?php
/**
 * Receives POST data and saves user comments.
 *
 * @copyright (C) 2009-2010 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

define('S2_ROOT', './');
require S2_ROOT.'include/common.php';
require S2_ROOT.'include/comments.php';
require S2_ROOT.'lang/'.S2_LANGUAGE.'/comments.php';

($hook = s2_hook('cmnt_start')) ? eval($hook) : null;

if (isset($_GET['go']))
{
	// Outputs "comment saved" message (used if the premoderation mode is enabled)
	header('Content-Type: text/html; charset=utf-8');

?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php echo $lang_comments['Comment sent']; ?></title>
</head>
<body style="margin: 40px; font: 85%/130% verdana, arial, sans-serif; color: #333;">
	<h1><?php echo $lang_comments['Comment sent']; ?></h1>
	<?php printf($lang_comments['Comment sent info'], $_GET['go'], S2_BASE_URL.'/'); ?>
</body>
<?php

	die;
}

if (isset($_GET['unsubscribe']))
{
	header('Content-Type: text/html; charset=utf-8');

	if (isset($_GET['id']) && isset($_GET['mail']))
	{
		$query = array(
			'SELECT'	=> 'id, nick, email, ip, time',
			'FROM'		=> 'art_comments',
			'WHERE'		=> 'article_id = '.intval($_GET['id']).' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
		);
		($hook = s2_hook('cmnt_unsubscribe_pre_get_receivers_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($receiver = $s2_db->fetch_assoc($result))
		{
			if ($_GET['unsubscribe'] == substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16))
			{
				$query = array(
					'UPDATE'	=> 'art_comments',
					'SET'		=> 'subscribed = 0',
					'WHERE'		=> 'article_id = '.intval($_GET['id']).' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
				);
				($hook = s2_hook('cmnt_unsubscribe_pre_upd_qr')) ? eval($hook) : null;
				$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php echo $lang_comments['Unsubscribed OK']; ?></title>
</head>
<body style="margin: 40px; font: 85%/130% verdana, arial, sans-serif; color: #333;">
	<h1><?php echo $lang_comments['Unsubscribed OK']; ?></h1>
	<?php echo $lang_comments['Unsubscribed OK info']; ?>
</body>
<?php

				die;
			}
		}
	}

?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php echo $lang_comments['Unsubscribed failed info']; ?></title>
</head>
<body style="margin: 40px; font: 85%/130% verdana, arial, sans-serif; color: #333;">
	<h1><?php echo $lang_comments['Unsubscribed failed']; ?></h1>
	<?php echo $lang_comments['Unsubscribed failed info']; ?>
</body>
<?php

	die;
}

if (!S2_ENABLED_COMMENTS)
	s2_comment_error(array($lang_comment_errors['disabled']));

if (!defined('S2_MAX_COMMENT_BYTES'))
	define('S2_MAX_COMMENT_BYTES', 65535);

$_POST[s2_field_name('show_email')] = $show_email = (int) isset($_POST[s2_field_name('show_email')]);
$_POST[s2_field_name('subscribed')] = $subscribed = (int) isset($_POST[s2_field_name('subscribed')]);

($hook = s2_hook('cmnt_pre_post_check')) ? eval($hook) : null;

// Make sure we have all POST data
foreach ($s2_comment_fields as $field)
	if (!isset($_POST[$field]))
		s2_comment_error(array($lang_comment_errors['post_error']));


//
// Starting input validation
//

$errors = array();

$text = trim($_POST[s2_field_name('text')]);

if (empty($text))
	$errors[] = $lang_comment_errors['missing_text'];

if (strlen($text) > S2_MAX_COMMENT_BYTES)
	$errors[] = sprintf($lang_comment_errors['long_text'], S2_MAX_COMMENT_BYTES);

$email = $_POST[s2_field_name('email')];

if (!is_valid_email($email))
	$errors[] = $lang_comment_errors['email'];

$name = trim($_POST[s2_field_name('name')]);

if (empty($name))
	$errors[] = $lang_comment_errors['missing_nick'];

if (utf8_strlen($name) > 50)
	$errors[] = $lang_comment_errors['long_nick'];

if (!s2_check_comment_question($_POST[s2_field_name('key')], $_POST[s2_field_name('quest')]))
	$errors[] = $lang_comment_errors['question'];

$id = (int) $_POST[s2_field_name('id')];

($hook = s2_hook('cmnt_after_post_check')) ? eval($hook) : null;

// What are we going to comment?
$query = array(
	'SELECT'	=> 'title, parent_id, url',
	'FROM'		=> 'articles',
	'WHERE'		=> 'id = '.$id.' AND published = 1 AND commented = 1'
);
($hook = s2_hook('cmnt_pre_get_page_info_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

if (!$row = $s2_db->fetch_assoc($result))
	$errors[] = $lang_comment_errors['no_item'];

$path = s2_path_from_id($row['parent_id'], true);
($hook = s2_hook('cmnt_pre_path_check')) ? eval($hook) : null;
if ($path === false)
	$errors[] = $lang_comment_errors['no_item'];

if (!empty($errors))
	s2_comment_error($errors);

$link = S2_BASE_URL.$path.'/'.urlencode($row['url']);

//
// Everything is ok, save and send the comment
//

// Save the comment
$query = array(
	'INSERT'	=> 'article_id, time, ip, nick, email, show_email, subscribed, sent, shown, good, text',
	'INTO'		=> 'art_comments',
	'VALUES'	=> $id.', '.time().', \''.$s2_db->escape($_SERVER['REMOTE_ADDR']).'\', \''.$s2_db->escape($name).'\', \''.$s2_db->escape($email).'\', '.$show_email.', '.$subscribed.', '.(1 - S2_PREMODERATION).', '.(1 - S2_PREMODERATION).', 0, \''.$s2_db->escape($text).'\''
);
($hook = s2_hook('cmnt_pre_save_comment_qr')) ? eval($hook) : null;
$s2_db->query_build($query) or error(__FILE__, __LINE__);

$message = s2_bbcode_to_mail($text);

// Sending the comment to subscribers
if (!S2_PREMODERATION)
{
	$query = array(
		'SELECT'	=> 'id, nick, email, ip, time',
		'FROM'		=> 'art_comments',
		'WHERE'		=> 'article_id = '.$id.' and subscribed = 1 and email <> \''.$s2_db->escape($email).'\''
	);
	($hook = s2_hook('cmnt_pre_get_subscribers_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$receivers = array();
	while ($receiver = $s2_db->fetch_assoc($result))
		$receivers[$receiver['email']] = $receiver;

	foreach ($receivers as $receiver)
	{
		$unsubscribe_link = S2_BASE_URL.'/comment.php?mail='.urlencode($receiver['email']).'&id='.$id.'&unsubscribe='.substr(md5($receiver['id'].$receiver['ip'].$receiver['nick'].$receiver['email'].$receiver['time']), 0, 16);
		s2_mail_comment($receiver['nick'], $receiver['email'], $message, $row['title'], $link, $name, $unsubscribe_link);
	}
}

// Sending the comment to moderators
$query = array(
	'SELECT'	=> 'login, email',
	'FROM'		=> 'users',
	'WHERE'		=> 'hide_comments = 1 and email <> \'\''
);
($hook = s2_hook('cmnt_pre_get_moderators_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
while ($mrow = $s2_db->fetch_assoc($result))
	s2_mail_comment($mrow['login'], $mrow['email'], $message, $row['title'], $link, $name, '---');

if (!S2_PREMODERATION)
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
