<?php
/**
 * @var string $link
 * @var int $time
 * @var int $modify_time
 */

?>
    <url>
        <loc><?php echo s2_htmlencode($link); ?></loc>
        <lastmod><?php echo gmdate('c', $modify_time ?: $time); ?></lastmod>
    </url>
