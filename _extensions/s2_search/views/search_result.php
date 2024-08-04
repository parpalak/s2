<?php
/**
 * @var callable $thumbnailHtml
 * @var $title string
 * @var $url string
 * @var $link string
 * @var $descr string
 * @var $time string
 * @var $images \S2\Rose\Entity\Metadata\ImgCollection|\S2\Rose\Entity\Metadata\Img[]
 */

$a = explode('/', $url);
foreach ($a as $k => $v) {
    $a[$k] = urldecode($v);
}
$link_escaped = implode('/', $a);

?>
<div class="search-result-img-preview">
<?php

foreach ($images as $image) {
    $img = $thumbnailHtml($image->getSrc(), $image->getWidth(), $image->getHeight(), 300, 75);
    echo '<a class="preview-link" href="', $link , '">', $img, '</a>';
}
?>
</div>
<p class="search-result">
	<a class="title" href="<?php echo $link; ?>">
        <?php echo $title; ?>
    </a><br />
	<?php echo trim($descr) ? $descr . '<br />' : '';  ?>
	<small class="stuff">
<!--		<a class="url" href="--><?php //echo s2_link($url); ?><!--">--><?php //echo (s2_abs_link($link_escaped)); ?><!--</a>-->
		<?php if (!empty($time)) echo /*' &mdash; ',*/ s2_date($time); ?>
	</small>
</p>
