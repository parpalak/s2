<?php
/**
 * @var string $baseUrl
 * @var string $selfLink
 * @var string $author
 * @var int $maxContentTime
 * @var string $items
 * @var \S2\Cms\Controller\Rss\FeedDto $feedInfo
 */

echo '<?xml version="1.0" encoding="utf-8"?>'."\n".
    '<?xml-stylesheet href="'. $baseUrl .'/_styles/rss.xslt' .'" type="text/xsl"?>'."\n";

?>
	<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
		<channel>
			<title><?php echo s2_htmlencode($feedInfo->title); ?></title>
			<link><?php echo $feedInfo->link; ?></link>
			<description><?php echo s2_htmlencode($feedInfo->description); ?></description>
			<generator>S2</generator>
			<ttl>10</ttl>
			<atom:link href="<?php echo $selfLink; ?>" rel="self" type="application/rss+xml" />
			<lastBuildDate><?php echo gmdate('D, d M Y H:i:s', $maxContentTime).' GMT'; ?></lastBuildDate>
<?php echo $items; ?>
		</channel>
	</rss>
<?php
