<?php
/**
 * @var string $rss_title
 * @var string $rss_description
 * @var string $rss_link
 * @var string $self_link
 * @var string $author
 * @var int $max_time
 * @var string $items
 */

echo '<?xml version="1.0" encoding="utf-8"?>'."\n".
	'<?xml-stylesheet href="http://www.w3.org/2000/08/w3c-synd/style.css" type="text/css"?>'."\n";

?>
	<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
		<channel>
			<title><?php echo s2_htmlencode($rss_title); ?></title>
			<link><?php echo $rss_link; ?></link>
			<description><?php echo s2_htmlencode($rss_description); ?></description>
			<generator>S2 <?php echo S2_VERSION; ?></generator>
			<ttl>10</ttl>
			<atom:link href="<?php echo s2_abs_link($self_link); ?>" rel="self" type="application/rss+xml" />
			<lastBuildDate><?php echo gmdate('D, d M Y H:i:s', $max_time).' GMT'; ?></lastBuildDate>
<?php echo $items; ?>
		</channel>
	</rss>
<?php
