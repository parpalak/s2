<?php
/**
 * Content of <!-- s2_title --> placeholder
 *
 * @var string $title
 * @var string $favorite
 */

$postfix = '';
$class = array();
if (!empty($favorite))
{
    $postfix = '<a href="'.s2_link('/'.S2_FAVORITE_URL.'/').'" class="favorite-star" title="'.Lang::get('Favorite').'">★</a>';
	$class[] = 'favorite-item';
}

?>
<h1<?php if (!empty($class)) echo ' class="'.implode(' ', $class).'"'; ?>><?php echo $title; ?><?php echo $postfix; ?></h1>
