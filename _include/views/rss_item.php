<?php
/**
 * @var string $title
 * @var string $text
 * @var string $link
 * @var string $author
 * @var int $time
 */

?>
			<item>
				<title><?php echo s2_htmlencode(s2_htmlencode($title)); ?></title>
				<link><?php echo s2_htmlencode($link); ?></link>
				<description><?php echo s2_htmlencode($text); ?></description>
<?php

if (!empty($author))
{

?>
				<author><?php echo s2_htmlencode($author); ?></author>
<?php

}

?>
				<guid isPermaLink="true"><?php echo s2_htmlencode($link); ?></guid>
				<pubDate><?php echo gmdate('D, d M Y H:i:s', $time).' GMT'; ?></pubDate>
				<comments><?php echo s2_htmlencode($link).'#comment'; ?></comments>
			</item>
