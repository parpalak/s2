<?php
/**
 * Helper functions for the feedback extension
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_feedback
 */

function s2_feedback_check_question ($key, $answer)
{
	return (int) ($key[10].$key[12]) + (int) ($key[20]) == (int) trim($answer);
}

function s2_feedback_form ($action, $name = '', $contact = '', $subject = '', $message = '')
{
	global $lang_s2_feedback;

	$key = md5(time() + 'some stuff :)');
	$digita = rand(1, 8);
	$digitb = rand(0, 9);
	$digitc = rand(1, 9);
	$key[10] = $digita;
	$key[12] = $digitb;
	$key[20] = $digitc;

	$operation = rand(0, 1);
	$num1 = rand (0, 100000);
	$num2 = rand (0, 100);

	$js = $digita*10 + $digitb;
	$js += $operation ? $num1 : -$num1;

	ob_start();

?>
<form method="post" action="<?php echo $action; ?>">
	<p class="input name">
		<?php echo $lang_s2_feedback['Name']; ?><br />
		<input type="text" name="name" maxlength="100" size="40" value="<?php echo s2_htmlencode($name); ?>" />
	</p>
	<p class="input">
		<?php echo $lang_s2_feedback['Contact']; ?><br />
		<input type="text" name="contact" maxlength="50" size="40" value="<?php echo s2_htmlencode($contact); ?>" />
	</p>
	<p class="input">
		<?php echo $lang_s2_feedback['Subject']; ?><br />
		<input type="text" name="subject" maxlength="250" size="40" value="<?php echo s2_htmlencode($subject); ?>" />
	</p>
	<p class="input text">
		<?php echo $lang_s2_feedback['Text']; ?><br />
		<textarea cols="50" rows="10" name="message"><?php echo s2_htmlencode($message); ?></textarea>
	</p>
	<p id="qsp">
		<?php printf(Lang::get('Comment question'), '&#x003'.$digita.';&#x003'.$digitb.';&#x002b;&#x003'.$digitc.';'); ?><br />
		<input class="comm_input" type="text" name="question" maxlength="50" size="40" id="quest" />
	</p>
	<input type="hidden" name="key" value="<?php echo $key; ?>" />
	<p class="input buttons">
		<input type="submit" name="submit" value="<?php echo Lang::get('Submit'); ?>" />
	</p>
</form>
<script type="text/javascript">
(function ()
{
	var a=<?php echo ($js - $num2); ?>;
	a=a<?php echo ($operation ? '-' : '+'), $num1; ?>;
	document.getElementById("quest").value=parseInt(a)+<?php echo ($digitc + $num2); ?>;
	document.getElementById("qsp").style.display="none";
}());
</script>
<?php

	return ob_get_clean();
}

function s2_feedback_send ($email, $subject, $message)
{
	// Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
	$message = str_replace(array("\r", "\n", "\0"), array('', "\r\n", ''), $message);
	$subject = "=?UTF-8?B?".base64_encode($subject)."?=";

	$sender_email = S2_WEBMASTER_EMAIL ? S2_WEBMASTER_EMAIL : 'example@example.com';
	$from = S2_WEBMASTER ? "=?UTF-8?B?".base64_encode(S2_WEBMASTER)."?=".' <'.$sender_email.'>' : $sender_email;
	$headers = 'From: '.$from."\r\n".
		'Return-Path: '.$from."\r\n".
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
