<?php
/**
 * @var string $id
 */

isset($name) || ($name = '');
isset($email) || ($email = '');
isset($show_email) || ($show_email = false);
isset($subscribed) || ($subscribed = false);
isset($text) || ($text = '');

$action = S2_BASE_URL.'/comment.php';

$key = md5(time() . 'A very secret string ;-)');
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

?>
<h2 class="comment form"><?php echo Lang::get('Post a comment'); ?></h2>
<form method="post" name="post_comment" action="<?php echo $action?>">
	<p class="input name">
		<?php echo Lang::get('Your name'); ?><br />
		<input type="text" name="name" value="<?php echo s2_htmlencode($name); ?>" maxlength="50" size="40" />
	</p>
	<p class="input email">
		<?php echo Lang::get('Your email'); ?><br />
		<input type="text" name="email" value="<?php echo s2_htmlencode($email); ?>" maxlength="50" size="40" /><br />
		<label for="show_email" title="<?php echo Lang::get('Show email label title'); ?>"><input type="checkbox" id="show_email" name="show_email" <?php if ($show_email) echo 'checked="checked" '; ?>/><?php echo Lang::get('Show email label'); ?></label><br />
		<label for="subscribed" title="<?php echo Lang::get('Subscribe label title'); ?>"><input type="checkbox" id="subscribed" name="subscribed" <?php if ($subscribed) echo 'checked="checked" '; ?>/><?php echo Lang::get('Subscribe label'); ?></label>
	</p>
	<p class="input text">
		<?php echo Lang::get('Your comment'); ?><br />
		<textarea cols="50" rows="10" name="text"><?php echo s2_htmlencode($text); ?></textarea>
		<br />
		<small class="comment-syntax"><?php ($hook = s2_hook('v_comment_form_pre_syntax_info')) ? eval($hook) : null; echo Lang::get('Comment syntax info'); ?></small>
	</p>
	<p id="qsp">
		<?php printf(Lang::get('Comment question'), '&#x003'.$a.';&#x003'.$b.';&#x002b;&#x003'.$c.';'); ?><br />
		<input class="comm_input" type="text" name="question" maxlength="50" size="40" id="quest" />
	</p>
	<input type="hidden" name="id" value="<?php echo s2_htmlencode($id); ?>" />
	<input type="hidden" name="key" value="<?php echo $key; ?>" />
	<p class="input buttons">
		<input type="submit" name="submit" value="<?php echo Lang::get('Submit'); ?>" />
		<input type="submit" name="preview" value="<?php echo Lang::get('Preview'); ?>" />
	</p>
</form>
<script type="text/javascript">
(function ()
{
	var a=<?php echo ($s - $add2); ?>;
	a=a<?php echo ($bbb ? '-' : '+'), $add1; ?>;
	document.getElementById("quest").value=parseInt(a)+<?php echo ($c + $add2); ?>;
	document.getElementById("qsp").style.display="none";
}());
</script>
