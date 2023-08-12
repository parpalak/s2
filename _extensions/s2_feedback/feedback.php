<?php
/**
 * Receives POST data and process user messages.
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_feedback
 */

use S2\Cms\Pdo\DbLayer;

define('S2_ROOT', '../../');
require 'functions.php';
require S2_ROOT.'_include/common.php';

Lang::load('s2_feedback', function ()
{
	if (file_exists('lang/'.S2_LANGUAGE.'.php'))
		return require 'lang/'.S2_LANGUAGE.'.php';
	else
		return require 'lang/English.php';
});

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['go']))
{
	// Outputs "message sent" message

	$controller = new Page_Service(array(
		'head_title' => Lang::get('Message sent', 's2_feedback'),
		'title'      => Lang::get('Message sent', 's2_feedback'),
		'text'       => Lang::get('Message sent text', 's2_feedback'),
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
	$errors[] = Lang::get('missing_name', 's2_feedback');

$contact = isset($_POST['contact']) ? trim((string) $_POST['contact']) : '';
if (!$contact)
	$errors[] = Lang::get('missing_contact', 's2_feedback');

$text = isset($_POST['message']) ? trim((string) $_POST['message']) : '';
if (!$text)
	$errors[] = Lang::get('missing_text', 's2_feedback');

$subject = isset($_POST['subject']) ? trim((string) $_POST['subject']) : '';

if (empty($errors) && !s2_feedback_check_question(isset($_POST['key']) ? (string) $_POST['key'] : '', isset($_POST['question']) ? (string) $_POST['question'] : ''))
	$errors[] = Lang::get('question', 's2_feedback');

if (!empty($errors))
{
	$error_text = '<p>'.Lang::get('Error message', 's2_feedback').'</p><ul><li>'.implode('</li><li>', $errors).'</li></ul>';

	$error_text = $error_text.s2_feedback_form('', $name, $contact, $subject, $text);

	$controller = new Page_Service(array(
		'head_title' => Lang::get('Error'),
		'title'      => Lang::get('Error'),
		'text'       => $error_text,
	));

	$controller->render();

	die();
}

//
// Everything is ok, save and send the comment
//

$message = sprintf(Lang::get('Mail template', 's2_feedback'), $name, $contact, $subject, $text);

/** @var DbLayer $s2_db */
$s2_db = \Container::get(DbLayer::class);

// Sending the comment to administrators
$query = array(
	'SELECT'	=> 'login, email',
	'FROM'		=> 'users',
	'WHERE'		=> 'edit_users = 1 and email <> \'\''
);
$result = $s2_db->buildAndQuery($query);
while ($mrow = $s2_db->fetchAssoc($result))
	s2_feedback_send($mrow['email'], Lang::get('Mail subject', 's2_feedback'), $message);

header('Location: ?go=1');

$s2_db->close();
