<?php
/**
 * An item of <!-- s2_last_articles --> content
 *
 * @var string $parent_link
 * @var string $parent_title
 * @var string $link
 * @var string $title
 * @var string $date
 * @var string $text
 */

$prefix = '';
$class = array('preview');
if (!empty($favorite))
{
	$prefix = '<a href="'.s2_link('/'.S2_FAVORITE_URL.'/').'" class="favorite-star" title="'.Lang::get('Favorite').'">*</a>';
	$class[] = 'favorite-item';
}

?>
<h2 class="<?php echo implode(' ', $class)?>"><?php echo $prefix; ?>
	<small>
		<a class="preview_section" href="<?php echo s2_htmlencode($parent_link); ?>"><?php echo s2_htmlencode($parent_title); ?></a>
		&rarr;
	</small>
	<a href="<?php echo s2_htmlencode($link); ?>"><?php echo s2_htmlencode($title); ?></a>
</h2>
<div class="preview time"><?php echo $date; ?></div>
<div class="preview cite"><?php echo $text; ?></div>
