<?php
/**
 * Content of <!-- s2_title --> placeholder
 *
 * @var callable $trans
 * @var string $title
 * @var string $favorite
 * @var string $favorite_link
 */

$postfix = '';
$class = array();
if (!empty($favorite))
{
    $postfix = ' <a href="'.$favorite_link.'" class="favorite-star" title="'.$trans('Favorite').'">★</a>';
	$class[] = 'favorite-item';
}

?>
<h1<?php if (!empty($class)) echo ' class="'.implode(' ', $class).'"'; ?>><?php echo $title; ?><?php echo $postfix; ?></h1>
