<?php
/**
 * @var \S2\Cms\Controller\Rss\FeedItemDto $item
 */

?>
			<item>
				<title><?php echo s2_htmlencode(s2_htmlencode($item->title)); ?></title>
				<link><?php echo s2_htmlencode($item->link); ?></link>
				<description><?php echo s2_htmlencode($item->text); ?></description>
<?php if (!empty($item->author)) {?>
				<dc:creator><?php echo s2_htmlencode($item->author); ?></dc:creator>
<?php } ?>
				<guid isPermaLink="true"><?php echo s2_htmlencode($item->link); ?></guid>
				<pubDate><?php echo gmdate('D, d M Y H:i:s', $item->time) . ' GMT'; ?></pubDate>
				<comments><?php echo s2_htmlencode($item->link) . '#comment'; ?></comments>
			</item>
