<?php

/** @var string $value */

$browserChecks = [
    'Edge' => ['Edg/', 'EdgA/', 'EdgiOS/', 'Edge/'],
    'Opera' => ['OPR/', 'Opera/'],
    'Vivaldi' => ['Vivaldi/'],
    'Brave' => ['Brave/'],
    'Yandex' => ['YaBrowser/'],
    'Samsung Internet' => ['SamsungBrowser/'],
    'UC Browser' => ['UCBrowser/'],
    'QQ Browser' => ['QQBrowser/'],
    'MIUI Browser' => ['MiuiBrowser/'],
    'Firefox' => ['Firefox/', 'FxiOS/'],
    'Internet Explorer' => ['MSIE ', 'Trident/'],
    'Chromium' => ['Chromium/'],
    'Chrome' => ['Chrome/', 'CriOS/'],
    'Safari' => ['Safari/'],
];

$detectedUserAgent = $value;
foreach ($browserChecks as $browser => $needles) {
    foreach ($needles as $needle) {
        if (stripos($value, $needle) !== false) {
            $detectedUserAgent = '<span title="' . $value . '">' . $browser . '</span>';
            break 2;
        }
    }
}

echo $detectedUserAgent;
