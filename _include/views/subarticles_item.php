<?php
/**
 * An item of <!-- s2_subarticles --> content
 *
 * @var string $parent_link
 * @var string $parent_title
 * @var string $link
 * @var string $title
 * @var string $date
 * @var string $excerpt
 */

$postfix = '';
$class = array('subsection');
if (!empty($favorite))
{
	if ($favorite != 2)
        $postfix = '<a href="'.s2_link('/'.S2_FAVORITE_URL.'/').'" class="favorite-star" title="'.Lang::get('Favorite').'">★</a>';
	else
        $postfix = '<span class="favorite-star" title="'.Lang::get('Favorite').'">★</span>';
	$class[] = 'favorite-item';
}

?>
				<h3 class="<?php echo implode(' ', $class)?>">
                    <a href="<?php echo s2_htmlencode($link); ?>"><?php echo s2_htmlencode($title); ?></a>
<?php if ($postfix) { ?>
					<?php echo $postfix; ?>

<?php } ?>
				</h3>
				<div class="subsection time"><?php echo $date; ?></div>
				<p class="subsection"><?php echo $excerpt; ?></p>

