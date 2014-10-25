<?php
/**
 * Content of <!-- s2_subarticles --> placeholder
 *
 * @var string $sections
 * @var string $articles
 */


if (!empty($sections))
{
	if (Lang::get('Subsections'));
		echo '<h2 class="subsections">' . Lang::get('Subsections') . '</h2>' . "\n";

	echo $sections;
}

if (!empty($articles))
{
	if (Lang::get('Read in this section'))
		echo '<h2 class="articles">' . Lang::get('Read in this section') . '</h2>' . "\n";

	echo $articles;
}
