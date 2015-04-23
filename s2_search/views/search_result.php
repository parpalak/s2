<?php
/**
 * @var $title string
 * @var $url string
 * @var $descr string
 * @var $time string
 */

$a = explode('/', $url);
foreach ($a as $k => $v)
	$a[$k] = urldecode($v);

$link_escaped = implode('/', $a);

?>
<p>
	<a class="title" href="<?php echo s2_htmlencode($url); ?>"><?php echo s2_htmlencode($title); ?></a><br />
	<?php echo $descr; ?><br />
	<small class="stuff">
		<a class="url" href="<?php echo s2_htmlencode($url); ?>"><?php echo (s2_abs_link($link_escaped)); ?></a>
		<?php if (!empty($time)) echo ' &mdash; ', s2_date($time); ?>
	</small>
</p>
