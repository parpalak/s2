<?php
/**
 * @var string $text
 * @var string $time
 * @var string $nick
 * @var string $email
 * @var bool $show_email
 * @var bool $good
 * @var int $i
 */

$nick = s2_htmlencode($nick);
$name = '<strong>'.($show_email ? s2_js_mailto($nick, $email) : $nick).'</strong>';
$link = !empty($i) ? '<a name="'.$i.'" href="#'.$i.'">#'.$i.'</a>. ' : '';

?>
<div class="reply_info<?php echo (!empty($good) ? ' good' : ''); ?>">
	<?php echo $link, sprintf(Lang::get('Comment info format'), s2_date_time($time), $name); ?>
</div>
<div class="reply<?php echo (!empty($good) ? ' good' : ''); ?>">
	<?php echo s2_bbcode_to_html(s2_htmlencode($text)); ?>
</div>
