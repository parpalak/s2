<?php
/**
 * Comment forms and functions.
 *
 * @copyright (C) 2009-2013 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

($hook = s2_hook('cmts_start')) ? eval($hook) : null;


function s2_check_comment_question ($key, $answer)
{
	return ((int) ($key[10].$key[12]) + (int) ($key[20]) == (int) trim($answer));
}

//
// Sends comments to subscribed users
//
function s2_mail_comment ($name, $email, $text, $title, $url, $auth_name, $unsubscribe_link)
{
	global $lang_comments;

	$message = $lang_comments['Email pattern'];
	$message = str_replace(array('<name>', '<author>', '<title>', '<url>', '<text>', '<unsubscribe>'),
		array($name, $auth_name, $title, $url, $text, $unsubscribe_link), $message);

	// Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
	$message = str_replace(array("\n", "\0"), array("\r\n", ''), $message);

	$subject = sprintf($lang_comments['Email subject'], $url);
	$subject = "=?UTF-8?B?".base64_encode($subject)."?=";

	$sender_email = S2_WEBMASTER_EMAIL ? S2_WEBMASTER_EMAIL : 'example@example.com';
	$from = S2_WEBMASTER ? "=?UTF-8?B?".base64_encode(S2_WEBMASTER)."?=".' <'.$sender_email.'>' : $sender_email;
	$headers = 'From: '.$from."\r\n".
		'Date: '.gmdate('r')."\r\n".
		'MIME-Version: 1.0'."\r\n".
		'Content-transfer-encoding: 8bit'."\r\n".
		'Content-type: text/plain; charset=utf-8'."\r\n".
		'X-Mailer: S2 Mailer'."\r\n".
		'Reply-To: '.$from;

	// Change the linebreaks used in the headers according to OS
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'MAC')
		$headers = str_replace("\r\n", "\r", $headers);
	else if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN')
		$headers = str_replace("\r\n", "\n", $headers);

	mail($email, $subject, $message, $headers);
}

//
// Sends comments to subscribed users
//
function s2_mail_moderator ($name, $email, $text, $title, $url, $auth_name, $auth_email)
{
	global $lang_comments;

	$message = $lang_comments['Email moderator pattern'];
	$message = str_replace(array('<name>', '<author>', '<title>', '<url>', '<text>'),
		array($name, $auth_name, $title, $url, $text), $message);

	// Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
	$message = str_replace(array("\n", "\0"), array("\r\n", ''), $message);

	$subject = sprintf($lang_comments['Email subject'], $url);
	$subject = "=?UTF-8?B?".base64_encode($subject)."?=";

	$sender_email = S2_WEBMASTER_EMAIL ? S2_WEBMASTER_EMAIL : 'example@example.com';
	$sender = S2_WEBMASTER ? "=?UTF-8?B?".base64_encode(S2_WEBMASTER)."?=".' <'.$sender_email.'>' : $sender_email;
	$from = trim($auth_name) ? "=?UTF-8?B?".base64_encode($auth_name)."?=".' <'.$auth_email.'>' : $auth_email;
	$headers = 'From: '.$from."\r\n".
		'Sender: '.$sender."\r\n".
		'Date: '.gmdate('r')."\r\n".
		'MIME-Version: 1.0'."\r\n".
		'Content-transfer-encoding: 8bit'."\r\n".
		'Content-type: text/plain; charset=utf-8'."\r\n".
		'X-Mailer: S2 Mailer'."\r\n".
		'Reply-To: '.$from;

	// Change the linebreaks used in the headers according to OS
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'MAC')
		$headers = str_replace("\r\n", "\r", $headers);
	else if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN')
		$headers = str_replace("\r\n", "\n", $headers);

	mail($email, $subject, $message, $headers);
}

//
// Parses BB-codes in comments
//
function s2_bbcode_to_html ($s)
{
	$s = str_replace("''", '"', $s);
	$s = str_replace("\r", '', $s);

	$s = preg_replace('#\[I\](.*?)\[/I\]#isS', '<em>\1</em>', $s);
	$s = preg_replace('#\[B\](.*?)\[/B\]#isS', '<strong>\1</strong>', $s);

	while (preg_match ('/\[Q\s*=\s*([^\]]*)\].*?\[\/Q\]/isS', $s))
		$s = preg_replace('/\s*\[Q\s*=\s*([^\]]*)\]\s*(.*?)\s*\[\/Q\]\s*/isS', '<blockquote><strong>\\1</strong> '.Lang::get('Wrote').'<br/><br/><em>\\2</em></blockquote>', $s);

	while (preg_match ('/\[Q\].*?\[\/Q\]/isS', $s))
		$s = preg_replace('/\s*\[Q\]\s*(.*?)\s*\[\/Q\]\s*/isS', '<blockquote>\\1</blockquote>', $s);

	$s = preg_replace('#(https?://\S{2,}?)(?=[\s\),\'\><\]]|&lt;|&gt;|[\.;:](?:\s|$)|$)#ue', '\'<noindex><a href="\\1" rel="nofollow">\'.((utf8_strlen(\'\\1\') > 55) ? utf8_substr(\'\\1\', 0 , 42).\' … \'.utf8_substr(\'\\1\', -10) : \'\\1\').\'</a></noindex>\'', $s);
	$s = str_replace("\n", '<br />', $s);
	return $s;
}

//
// wordwrap() with utf-8 support
//
function utf8_wordwrap($string, $width = 75, $break = "\n") 
{
	$a = explode("\n", $string);
	foreach ($a as $k => $str)
	{
		$str = preg_split('#[\s\r]+#', $str);
		$len = 0;
		$return = '';
		foreach ($str as $val)
		{
			$val .= ' ';
			$tmp = utf8_strlen($val);
			$len += $tmp;
			if ($len >= $width)
			{
				$return .= $break . $val;
				$len = $tmp;
			}
			else
				$return .= $val;
		}
		$a[$k] = $return;
	}
	return implode("\n", $a);
}

//
// Parses BB-codes in comments and makes quotes mail-styled (used '>')
//
function s2_bbcode_to_mail ($s)
{
	$s = str_replace("\r", '', $s);
	$s = str_replace(array('&quot;', '&laquo;', '&raquo;'), '"', $s);
	$s = preg_replace('/\[I\s*?\](.*?)\[\/I\s*?\]/isu', "_\\1_", $s);
	$s = preg_replace('/\[B\s*?\](.*?)\[\/B\s*?\]/isu', "*\\1*", $s);

	// Do not ask me how the rest of the function works.
	// It just works :)

	while (preg_match ('/\[Q\s*?=?\s*?([^\]]*)\s*?\].*?\[\/Q.*?\]/is', $s))
		$s = preg_replace('/\s*\[Q\s*?=?\s*?([^\]]*)\s*?\]\s*(.*?)\s*\[\/Q.*?\]\s*/is', "<q/>\\2</q>", $s);

	$strings = $levels = array();

	$curr = 0;
	$level = 0;

	while (1)
	{
		$up = strpos($s, '<q/>', $curr);
		$down = strpos($s, '</q>', $curr);
		if ($up === false)
		{
			if ($down ===false)
				break;
			$dl = -1;
			$c = $down;
		}
		elseif ($down === false || $up < $down)
		{
			$dl = 1;
			$c = $up;
		}
		else
		{
			$dl = -1;
			$c = $down;
		}
		$strings[] = substr($s, $curr, $c - $curr);
		$curr = $c + 4;
		$levels[] = $level;
		$level += $dl;
	}

	$strings[] = substr($s, $curr);
	$levels[] = 0;

	$out = array();
	foreach ($strings as $i => $string)
	{
		if (trim($string) == '')
			continue;
		$delimeter = "\n".str_repeat('> ', $levels[$i]);
		$out[] = $delimeter.utf8_wordwrap(str_replace("\n", $delimeter, $string), 70 - 2*$levels[$i], $delimeter);
	}

	$s = implode ("\n", $out);

	return trim($s);
}

define('S2_COMMENTS_FUNCTIONS_LOADED', 1);
