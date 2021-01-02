<?php
/**
 * Receives POST data and process user messages.
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_feedback
 */

define('S2_ROOT', '../../');
require 'functions.php';
require S2_ROOT.'_include/common.php';
if (file_exists('lang/'.S2_LANGUAGE.'.php'))
	require 'lang/'.S2_LANGUAGE.'.php';
else
	require 'lang/English.php';

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['go']))
{
	// Outputs "message sent" message

	$template = s2_get_service_template();
	$replace = array(
		'<!-- s2_head_title -->'	=> $lang_s2_feedback['Message sent'],
		'<!-- s2_title -->'			=> '<h1>'.$lang_s2_feedback['Message sent'].'</h1>',
		'<!-- s2_text -->'			=> $lang_s2_feedback['Message sent text'],
		'<!-- s2_debug -->'			=> defined('S2_SHOW_QUERIES') ? s2_get_saved_queries() : '',
	);

	($hook = s2_hook('cmnt_go_pre_tpl_replace')) ? eval($hook) : null;

	foreach ($replace as $what => $to)
		$template = str_replace($what, $to, $template);

	$s2_db->close();

	die($template);
}

//
// Starting input validation
//

$errors = array();

$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
if (!$name)
	$errors[] = $lang_s2_feedback['missing_name'];

$contact = isset($_POST['contact']) ? trim((string) $_POST['contact']) : '';
if (!$contact)
	$errors[] = $lang_s2_feedback['missing_contact'];

$text = isset($_POST['message']) ? trim((string) $_POST['message']) : '';
if (!$text)
	$errors[] = $lang_s2_feedback['missing_text'];

$subject = isset($_POST['subject']) ? trim((string) $_POST['subject']) : '';

if (empty($errors) && !s2_feedback_check_question(isset($_POST['key']) ? (string) $_POST['key'] : '', isset($_POST['question']) ? (string) $_POST['question'] : ''))
	$errors[] = $lang_s2_feedback['question'];

if (!empty($errors))
{
	$template = s2_get_service_template();
	$replace['<!-- s2_head_title -->'] = $lang_common['Error'];
	$replace['<!-- s2_title -->'] = '<h1>'.$lang_common['Error'].'</h1>';

	$error_text = '<p>'.$lang_s2_feedback['Error message'].'</p><ul><li>'.implode('</li><li>', $errors).'</li></ul>';

	$replace['<!-- s2_text -->'] = $error_text.s2_feedback_form('', $name, $contact, $subject, $text);
	$replace['<!-- s2_debug -->'] = defined('S2_SHOW_QUERIES') ? s2_get_saved_queries() : '';

	($hook = s2_hook('cmnt_pre_error_tpl_replace')) ? eval($hook) : null;

	foreach ($replace as $what => $to)
		$template = str_replace($what, $to, $template);

	($hook = s2_hook('cmnt_pre_error_output')) ? eval($hook) : null;

	$s2_db->close();

	die($template);
}

//
// Everything is ok, save and send the comment
//

$message = sprintf($lang_s2_feedback['Mail template'], $name, $contact, $subject, $text); 

// Sending the comment to administrators
$query = array(
	'SELECT'	=> 'login, email',
	'FROM'		=> 'users',
	'WHERE'		=> 'edit_users = 1 and email <> \'\''
);
$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
while ($mrow = $s2_db->fetch_assoc($result))
	s2_feedback_send($mrow['email'], $lang_s2_feedback['Mail subject'], $message);

header('Location: ?go=1');

$s2_db->close();
