<?php
/**
 * @var string $link
 * @var int $time
 * @var int $modify_time
 */

?>
    <url>
        <loc><?php echo s2_htmlencode($link); ?></loc>
        <lastmod><?php echo gmdate('D, d M Y H:i:s', $modify_time ?: $time).' GMT'; ?></lastmod>
    </url>
