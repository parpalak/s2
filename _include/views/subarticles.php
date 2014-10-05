<?php
/**
 * Content of <!-- s2_subarticles --> placeholder
 *
 * @var string $sections
 * @var string $articles
 */


if (!empty($sections))
{
	global $lang_common;

	if ($lang_common['Subsections']);
		echo '<h2 class="subsections">' . $lang_common['Subsections'] . '</h2>' . "\n";

	echo $sections;
}

if (!empty($articles))
{
	global $lang_common;

	if ($lang_common['Read in this section'])
		echo '<h2 class="articles">' . $lang_common['Read in this section'] . '</h2>' . "\n";

	echo $articles;
}
