<?php
/**
 * An item of <!-- s2_last_articles --> content
 *
 * @var callable $trans
 * @var string $parent_link
 * @var string $parent_title
 * @var string $favorite_link
 * @var string $link
 * @var string $title
 * @var string $date
 * @var string $text
 */

$postfix = '';
$class = array('preview');
if (!empty($favorite))
{
    $postfix = '<a href="'.$favorite_link.'" class="favorite-star" title="'.$trans('Favorite').'">★</a>';
	$class[] = 'favorite-item';
}

?>
<h2 class="<?php echo implode(' ', $class)?>">
	<small>
		<a class="preview_section" href="<?php echo s2_htmlencode($parent_link); ?>"><?php echo s2_htmlencode($parent_title); ?></a>
		&rarr;
	</small>
	<a href="<?php echo s2_htmlencode($link); ?>"><?php echo s2_htmlencode($title); ?></a>
    <?php echo $postfix; ?>
</h2>
<div class="preview time"><?php echo $date; ?></div>
<div class="preview cite"><?php echo $text; ?></div>
