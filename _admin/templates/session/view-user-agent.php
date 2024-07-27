<?php

/** @var string $value */

$browser_aliases = ['MSIE' => 'Internet Explorer'];

$detectedUserAgent = $value;
foreach (['Opera', 'Firefox', 'Chrome', 'Safari', 'MSIE', 'Mozilla'] as $browser) {
    if (str_contains($value, $browser)) {
        $detectedUserAgent = '<span title="' . $value . '">' . ($browser_aliases[$browser] ?? $browser) . '</span>';
        break;
    }
}

echo $detectedUserAgent;
