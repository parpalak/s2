<?php

require_once 'functions.php';

foreach ([
             __DIR__ . '/src',
             __DIR__ . '/Page',
             __DIR__ . '/../_extensions/s2_blog/_include',
         ] as $dir) {
    $directory = new RecursiveDirectoryIterator($dir);
    $fullTree  = new RecursiveIteratorIterator($directory);
    $phpFiles  = new RegexIterator($fullTree, '/.+((?<!Test)+\.php$)/i', RecursiveRegexIterator::GET_MATCH);

    foreach ($phpFiles as $key => $file) {
        opcache_compile_file($file[0]);
    }
}

require_once 'Container.php';
require_once 'Lang.php';
require_once 'Model.php';
require_once 'Placeholder.php';
require_once 'S2Cache.php';
require_once 'Viewer.php';
