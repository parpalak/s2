#!/usr/bin/env php
<?php

$options  = getopt('', ['dir:']);
$mailDir  = $options['dir'] ?? sys_get_temp_dir() . '/mail';
$fileName = getFileName($mailDir);
$filePath = $mailDir . '/' . $fileName;

if (!is_dir($mailDir) && !mkdir($mailDir, 0777, true) && !is_dir($mailDir)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $mailDir));
}

file_put_contents($filePath, file_get_contents('php://stdin'));

function getFileName(string $dir): string
{
    $i = iterator_count(new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS));
    while (file_exists($i . '.txt')) {
        $i++;
    }

    return $i . '.txt';
}
