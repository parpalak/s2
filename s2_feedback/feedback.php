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

	$controller = new Page_Service(array(
		'head_title' => $lang_s2_feedback['Message sent'],
		'title'      => $lang_s2_feedback['Message sent'],
		'text'       => $lang_s2_feedback['Message sent text'],
	));

	$controller->render();

	die();
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
	$error_text = '<p>'.$lang_s2_feedback['Error message'].'</p><ul><li>'.implode('</li><li>', $errors).'</li></ul>';

	$error_text = $error_text.s2_feedback_form('', $name, $contact, $subject, $text);

	$controller = new Page_Service(array(
		'head_title' => $lang_common['Error'],
		'title'      => $lang_common['Error'],
		'text'       => $error_text,
	));

	$controller->render();

	die();
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
