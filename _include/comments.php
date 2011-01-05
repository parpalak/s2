<?php
/**
 * Comment forms and functions.
 *
 * @copyright (C) 2009-2010 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


$s2_comment_fields = array(
	'name' => 'Name',
	'email' => 'Email',
	'key' => 'DigKey',
	'show_email' => 'ShowEmail',
	'subscribed' => 'Subscribed',
	'text' => 'Message',
	'quest' => 'Question',
	'id' => 'RecordID',
);

($hook = s2_hook('cmts_start')) ? eval($hook) : null;

function s2_field_name ($index)
{
	global $s2_comment_fields, $lang_comments;

	if (isset($s2_comment_fields[$index]))
		return $s2_comment_fields[$index];

	echo $lang_comments['Error post comment'];
	return '';
}

function s2_comment_form ($id, $name = '', $email = '', $showmail = false, $subscribed = false, $text = false)
{
	global $lang_common;

	$action = S2_BASE_URL.'/comment.php';

	$key = md5(time() + 'A very secret string ;-)');
	$a = rand(1, 8);
	$b = rand(0, 9);
	$c = rand(1, 9);
	$key[10] = $a;
	$key[12] = $b;
	$key[20] = $c;

	$bbb = rand(0, 1);
	$add1 = rand (0, 100000);
	$add2 = rand (0, 100);

	$s = $a*10 + $b;
	$s += $bbb ? $add1 : -$add1;

	($hook = s2_hook('fn_comment_form_pre_output')) ? eval($hook) : null;

	ob_start();

?>
				<form method="post" action="<?php echo $action?>">
					<p class="input name">
						<?php echo $lang_common['Your name']; ?><br />
						<input type="text" name="<?php echo s2_field_name('name');?>" value="<?php echo s2_htmlencode($name); ?>" maxlength="50" size="40" />
					</p>
					<p class="input email">
						<?php echo $lang_common['Your email']; ?><br />
						<input type="text" name="<?php echo s2_field_name('email')?>" value="<?php echo s2_htmlencode($email); ?>" maxlength="50" size="40" /><br />
						<label for="showmail" title="<?php echo $lang_common['Show email label title']; ?>"><input type="checkbox" id="showmail" name="<?php echo s2_field_name('show_email')?>" <?php if ($showmail) echo 'checked="checked" '; ?>/><?php echo $lang_common['Show email label']; ?></label><br />
						<label for="subscr" title="<?php echo $lang_common['Subscript label title']; ?>"><input type="checkbox" id="subscr" name="<?php echo s2_field_name('subscribed')?>" <?php if ($subscribed) echo 'checked="checked" '; ?>/><?php echo $lang_common['Subscript label']; ?></label>
					</p>
					<p class="input text">
						<?php echo $lang_common['Your comment']; ?><br />
						<textarea cols="50" rows="10" name="<?php echo s2_field_name('text'); ?>"><?php echo s2_htmlencode($text); ?></textarea>
						<br />
						<small class="comment-syntax"><?php echo $lang_common['Comment syntax info']; ?></small>
					</p>
					<p id="qsp">
						<?php printf($lang_common['Comment question'], '&#x003'.$a.';&#x003'.$b.';&#x002b;&#x003'.$c.';'); ?><br />
						<input class="comm_input" type="text" name="<?php echo s2_field_name('quest'); ?>" maxlength="50" size="40" id="quest" />
					</p>
					<input type="hidden" name="<?php echo s2_field_name('id')?>" value="<?php echo intval($id); ?>" />
					<input type="hidden" name="<?php echo s2_field_name('key')?>" value="<?php echo $key; ?>" />
					<p class="input buttons">
						<input type="submit" name="submit" value="<?php echo $lang_common['Submit']; ?>" />
						<input type="submit" name="preview" value="<?php echo $lang_common['Preview']; ?>" />
					</p>
				</form>
				<script type="text/javascript">
<!--
var a=<?php echo ($s - $add2)?>;
a=a<?php echo ($bbb ? '-' : '+'), $add1?>;
document.getElementById("quest").value=parseInt(a)+<?php echo ($c + $add2)?>;
document.getElementById("qsp").style.display="none";
// -->
				</script>
<?php

	return ob_get_clean();
}

function s2_comment_error ($errors)
{
	global $lang_common, $lang_comments, $lang_comment_errors;

	header('Content-Type: text/html; charset=utf-8');

	ob_start();

?>
	<p><?php echo $lang_comment_errors['Error message']; ?></p>
	<ul>
<?php
	foreach ($errors as $error)
		echo "\t\t".'<li>'.$error.'</li>'."\n";
?>
	</ul>
<?php

	if (!empty($_POST[s2_field_name('text')]))
	{
		echo "\t".'<p>'.$lang_comments['Save comment'].'</p>'."\n";

?>
	<textarea style="width: 100%;" cols="80" rows="10"><?php echo s2_htmlencode($_POST[s2_field_name('text')]) ?></textarea>
<?php

	}

	if (S2_ENABLED_COMMENTS)
		echo "\t".'<p>'.$lang_comments['Go back'].'</p>'."\n";

	$text = ob_get_clean();

	$template = s2_get_service_template();
	$replace = array(
		'<!-- s2_head_title -->'	=> $lang_common['Error'],
		'<!-- s2_title -->'			=> '<h1>'.$lang_common['Error'].'</h1>',
		'<!-- s2_text -->'			=> $text,
		'<!-- s2_debug -->'			=> defined('S2_SHOW_QUERIES') ? s2_get_saved_queries() : '',
	);

	($hook = s2_hook('cmnt_pre_sent_comment_output')) ? eval($hook) : null;

	foreach ($replace as $what => $to)
		$template = str_replace($what, $to, $template);

	die($template);
}

function s2_check_comment_question ($key, $answer)
{
	return ((int) ($key[10].$key[12]) + (int) ($key[20]) == (int) trim($answer));
}

//
// Sends comments to subscribed users
//
function s2_mail_comment ($name, $email, $text, $title, $url, $aut_name, $unsubscribe_link)
{
	global $lang_comments;

	$message = $lang_comments['Email pattern'];
	$message = str_replace('<name>', $name, $message);
	$message = str_replace('<author>', $aut_name, $message);
	$message = str_replace('<title>', $title, $message);
	$message = str_replace('<url>', $url, $message);
	$message = str_replace('<text>', $text, $message);
	$message = str_replace('<unsubscribe>', $unsubscribe_link, $message);

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
// Parses BB-codes in comments
//
function s2_bbcode_to_html ($s)
{
	global $lang_common;

	$s = str_replace("''", '"', $s);
	$s = str_replace("\r", '', $s);

	$s = preg_replace('#\[I\](.*?)\[/I\]#isu', '<em>\1</em>', $s);
	$s = preg_replace('#\[B\](.*?)\[/B\]#isu', '<strong>\1</strong>', $s);

	while (preg_match ('/\[Q\s*?=\s*?([^\]]*)\s*?\].*?\[\/Q.*?\]/uis', $s))
		$s = preg_replace('/\s*\[Q\s*?=\s*?([^\]]*)\s*?\]\s*(.*?)\s*\[\/Q.*?\]\s*/uis', '<blockquote><strong>\\1</strong> '.$lang_common['Wrote'].'<br/><br/><em>\\2</em></blockquote>', $s);

	while (preg_match ('/\[Q\s*?\].*?\[\/Q\s*?\]/uis', $s))
		$s = preg_replace('/\s*\[Q\s*?\]\s*(.*?)\s*\[\/Q\s*?\]\s*/uis', '<blockquote>\\1</blockquote>', $s);

	$s = preg_replace('#(https?://\S{2,}?)(?=[\s:\),\'\><\]]|[\.;](?:\s|$)|$)#ue', '\'<noindex><a href="\\1" rel="nofollow">\'.((utf8_strlen(\'\\1\') > 55) ? utf8_substr(\'\\1\', 0 , 42).\' … \'.utf8_substr(\'\\1\', -10) : \'\\1\').\'</a></noindex>\'', $s);
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