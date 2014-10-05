<?php
/**
 * General list of sections and articles
 * Used for tag and favorite pages.
 *
 * @var string $description
 * @var string $sections
 * @var string $articles
 */

if (!empty($description))
	echo $description, '<hr class="description-separator" />', "\n";

echo $articles;

if (!empty($sections))
{
	global $lang_common;
	if ($lang_common['Subsections']);
		echo '<h2 class="subsections">' . $lang_common['Subsections'] . '</h2>' . "\n";

	echo $sections;
}
