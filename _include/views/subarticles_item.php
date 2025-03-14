<?php
/**
 * An item of <!-- s2_subarticles --> content
 *
 * @var callable $trans
 * @var string $parent_link
 * @var string $parent_title
 * @var string $favorite_link
 * @var string $link
 * @var string $title
 * @var string $date
 * @var string $excerpt
 */

$postfix = '';
$class = ['subsection'];
if (!empty($favorite)) {
	if ($favorite != 2) {
        $postfix = '<a href="' . s2_htmlencode($favorite_link) . '" class="favorite-star" title="' . $trans('Favorite') . '">★</a>';
    }
	else {
        $postfix = '<span class="favorite-star" title="' . $trans('Favorite') . '">★</span>';
    }
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

