<?php
/**
 * Receives POST data and saves user comments.
 *
 * @copyright (C) 2009-2024 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

use Psr\Log\LoggerInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplateProvider;

define('S2_ROOT', './');
require S2_ROOT.'_include/common.php';
require S2_ROOT.'_include/comments.php';

($hook = s2_hook('cmnt_start')) ? eval($hook) : null;

header('X-Powered-By: S2/'.S2_VERSION);

/** @var HtmlTemplateProvider $templateProvider */
$templateProvider      = $app->container->get(HtmlTemplateProvider::class);
$template = $templateProvider->getTemplate('service.php');

if (isset($_GET['go']))
{
	// Outputs "comment saved" message (used if the premoderation mode is enabled)
    $template
        ->putInPlaceholder('head_title', '✅' . Lang::get('Comment sent', 'comments'))
        ->putInPlaceholder('title', '<span class="icon-success">✔</span>' . Lang::get('Comment sent', 'comments'))
        ->putInPlaceholder('text', sprintf(Lang::get('Comment sent info', 'comments'), s2_htmlencode($_GET['go']), s2_link('/')))
    ;

    $template->toHttpResponse()->send();

	die();
}

if (isset($_GET['unsubscribe']))
{
	header('Content-Type: text/html; charset=utf-8');

	if (isset($_GET['id'], $_GET['mail'])) {
        /** @var DbLayer $s2_db */
        $s2_db = $app->container->get(DbLayer::class);

		[$id, $class] = explode('.', $_GET['id']);
		$id = (int) $id;
		$class = (string) $class;

		$query = [
			'SELECT'	=> 'id, nick, email, ip, time',
			'FROM'		=> 'art_comments',
			'WHERE'		=> 'article_id = '.$id.' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
        ];
		($hook = s2_hook('cmnt_unsubscribe_pre_get_receivers_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$found = false;
		while ($receiver = $s2_db->fetchAssoc($result)) {
            if ($_GET['unsubscribe'] === base_convert(substr(md5($receiver['id'] . $receiver['ip'] . $receiver['nick'] . $receiver['email'] . $receiver['time']), 0, 16), 16, 36)) {
                $found = true;
            }
        }

		if ($found) {
			$query = [
				'UPDATE'	=> 'art_comments',
				'SET'		=> 'subscribed = 0',
				'WHERE'		=> 'article_id = '.$id.' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
            ];
			($hook = s2_hook('cmnt_unsubscribe_pre_upd_qr')) ? eval($hook) : null;
			$s2_db->buildAndQuery($query);

            $template
                ->putInPlaceholder('head_title', Lang::get('Unsubscribed OK', 'comments'))
                ->putInPlaceholder('title', Lang::get('Unsubscribed OK', 'comments'))
                ->putInPlaceholder('text', Lang::get('Unsubscribed OK info', 'comments'))
            ;

            $template->toHttpResponse()->send();

			die();
		}
	}

    $template
        ->putInPlaceholder('head_title', Lang::get('Unsubscribed failed', 'comments'))
        ->putInPlaceholder('title', Lang::get('Unsubscribed failed', 'comments'))
        ->putInPlaceholder('text', Lang::get('Unsubscribed failed info', 'comments'))
    ;

    $template->toHttpResponse()->send();

	die();
}

if (!defined('S2_MAX_COMMENT_BYTES')) {
    define('S2_MAX_COMMENT_BYTES', 65535);
}

$_POST['show_email'] = $show_email = (int) isset($_POST['show_email']);
$_POST['subscribed'] = $subscribed = (int) isset($_POST['subscribed']);

($hook = s2_hook('cmnt_pre_post_check')) ? eval($hook) : null;

//
// Starting input validation
//

$errors = [];

if (!S2_ENABLED_COMMENTS) {
    $errors[] = Lang::get('disabled', 'comments');
}

function s2_ext_var($field)
{
	return !empty($_POST[$field]) ? trim((string) $_POST[$field]) : '';
}

$text = s2_ext_var('text');
if ($text === '') {
    $errors[] = Lang::get('missing_text', 'comments');
}
if (strlen($text) > S2_MAX_COMMENT_BYTES) {
    $errors[] = sprintf(Lang::get('long_text', 'comments'), S2_MAX_COMMENT_BYTES);
} elseif (s2_link_count($text) > 0) {
    $errors[] = Lang::get('links_in_text', 'comments');
}

$email = s2_ext_var('email');
if (!s2_is_valid_email($email))
	$errors[] = Lang::get('email', 'comments');

$name = s2_ext_var('name');
if (empty($name))
	$errors[] = Lang::get('missing_nick', 'comments');
if (mb_strlen($name) > 50)
	$errors[] = Lang::get('long_nick', 'comments');

if (count($errors) === 0 && !s2_check_comment_question($_POST['key'] ?? '', $_POST['question'] ?? ''))
	$errors[] = Lang::get('question', 'comments');

$idArray = explode('.', s2_ext_var('id'));
if (count($idArray) !== 2) {
    $errors[] = Lang::get('no_item', 'comments');
    $id = $class = null;
} else {
    [$id, $class] = $idArray;
    $id = (int) $id;
}

($hook = s2_hook('cmnt_after_post_check')) ? eval($hook) : null;

if (isset($_POST['preview']))
{
	// Handling "Preview" button

	($hook = s2_hook('cmnt_preview_pre_comment_merge')) ? eval($hook) : null;

	$viewer = $app->container->get(\S2\Cms\Template\Viewer::class);
	$text_preview = '<p>' . Lang::get('Comment preview info', 'comments') . '</p>' . "\n" .
		$viewer->render('comment', array(
			'text'       => $text,
			'nick'       => $name,
			'time'       => time(),
			'email'      => $email,
			'show_email' => $show_email,
		));

    $template
        ->putInPlaceholder('head_title'   , Lang::get('Comment preview', 'comments'))
        ->putInPlaceholder('title'        , Lang::get('Comment preview', 'comments'))
        ->putInPlaceholder('text'         , $text_preview)
        ->putInPlaceholder('id'           , $id)
        ->putInPlaceholder('class'        , $class)
        ->putInPlaceholder('commented'    , true)
        ->putInPlaceholder('comment_form' , compact('name', 'email', 'show_email', 'subscribed', 'text'))
	;

    $template->toHttpResponse()->send();

	die();
}

// What are we going to comment?
$path = false;

if (empty($errors)) {
    /** @var DbLayer $s2_db */
    $s2_db = $app->container->get(DbLayer::class);

    $query = array(
        'SELECT' => 'title, parent_id, url',
        'FROM'   => 'articles',
        'WHERE'  => 'id = ' . $id . ' AND published = 1 AND commented = 1'
    );
    ($hook = s2_hook('cmnt_pre_get_page_info_qr')) ? eval($hook) : null;
    $result = $s2_db->buildAndQuery($query);

    if (!$row = $s2_db->fetchAssoc($result)) {
        $errors[] = Lang::get('no_item', 'comments');
    } else {
        $path = Model::path_from_id($row['parent_id'], true);
        ($hook = s2_hook('cmnt_pre_path_check')) ? eval($hook) : null;
        if ($path === false) {
            $errors[] = Lang::get('no_item', 'comments');
        }
    }
}

if (!empty($errors))
{
	$error_text = '<p>'.Lang::get('Error message', 'comments').'</p><ul>';
	foreach ($errors as $error)
		$error_text .=  '<li>'.$error.'</li>';
	$error_text .=  '</ul>';

    $template
		->putInPlaceholder('head_title'   , '❌' . Lang::get('Error'))
		->putInPlaceholder('title'        , '<span class="icon-error">✖</span>' . Lang::get('Error'))
		->putInPlaceholder('text'         , $error_text . ($id !== null ? '<p>' . Lang::get('Fix error', 'comments') . '</p>' : ''))
		->putInPlaceholder('id'           , $id)
		->putInPlaceholder('class'        , $class)
		->putInPlaceholder('commented'    , $id !== null)
		->putInPlaceholder('comment_form' , compact('name', 'email', 'show_email', 'subscribed', 'text'))
	;

    $template->toHttpResponse()->send();

    /** @var LoggerInterface $logger */
    $logger = $app->container->get(LoggerInterface::class);
    $logger->notice('Comment was not saved due to errors.', [
        'errors' => $errors,
        'id'     => s2_ext_var('id'),
    ]);

	die();
}

$link = s2_abs_link($path.'/'.urlencode($row['url']));

//
// Everything is ok, save and send the comment
//

/** @var DbLayer $s2_db */
$s2_db = $app->container->get(DbLayer::class);

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
	$result = $s2_db->buildAndQuery($query);

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
$s2_db->buildAndQuery($query);

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
	$result = $s2_db->buildAndQuery($query);

	$receivers = array();
	while ($receiver = $s2_db->fetchAssoc($result))
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
$result = $s2_db->buildAndQuery($query);
while ($mrow = $s2_db->fetchAssoc($result))
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
	$result = $s2_db->buildAndQuery($query);
	$hash = $s2_db->result($result);

	($hook = s2_hook('cmnt_pre_redirect')) ? eval($hook) : null;

	header('Location: '.s2_link($path.'/'.urlencode($row['url'])).'#'.$hash);
}
else
	header('Location: '.S2_PATH.'/comment.php?go='.urlencode($link));

$s2_db->close();
