<?php
/** @var string $id */
/** @var string $action */
/** @var string[] $syntaxHelpItems */
/** @var callable $trans */

isset($name) || ($name = '');
isset($email) || ($email = '');
isset($show_email) || ($show_email = false);
isset($subscribed) || ($subscribed = false);
isset($text) || ($text = '');

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
<h2 class="comment form" id="add-comment"><?php echo $trans('Post a comment'); ?></h2>
<form method="post" name="post_comment" action="<?php echo $action?>">
	<p class="input name">
		<label><?php echo $trans('Your name'); ?><br />
            <input type="text" name="name" value="<?php echo s2_htmlencode($name); ?>" maxlength="50" size="40" /></label>
	</p>
	<p class="input email">
		<label><?php echo $trans('Your email'); ?><br />
            <input type="text" name="email" value="<?php echo s2_htmlencode($email); ?>" maxlength="50" size="40" /></label><br />
		<label for="show_email" title="<?php echo $trans('Show email label title'); ?>"><input type="checkbox" id="show_email" name="show_email" <?php if ($show_email) echo 'checked="checked" '; ?>/><?php echo $trans('Show email label'); ?></label><br />
		<label for="subscribed" title="<?php echo $trans('Subscribe label title'); ?>"><input type="checkbox" id="subscribed" name="subscribed" <?php if ($subscribed) echo 'checked="checked" '; ?>/><?php echo $trans('Subscribe label'); ?></label>
	</p>
	<p class="input text">
		<label><?php echo $trans('Your comment'); ?><br />
            <textarea cols="50" rows="10" name="text"><?php echo s2_htmlencode($text); ?></textarea></label>
		<br />
		<small class="comment-syntax"><?php foreach ($syntaxHelpItems as $item) { echo $item . "\n"; } ?></small>
	</p>
	<p id="qsp">
		<label><?php printf($trans('Comment question'), '&#x003'.$a.';&#x003'.$b.';&#x002b;&#x003'.$c.';'); ?><br />
    		<input class="comm_input" type="text" name="question" maxlength="50" size="40" id="quest" /></label>
	</p>
	<input type="hidden" name="id" value="<?php echo s2_htmlencode($id); ?>" />
	<input type="hidden" name="key" value="<?php echo $key; ?>" />
	<p class="input buttons">
		<input type="submit" name="submit" value="<?php echo $trans('Submit'); ?>" />
		<input type="submit" name="preview" value="<?php echo $trans('Preview'); ?>" />
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
