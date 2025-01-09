<?php
/**
 * Content of <!-- s2_subarticles --> placeholder
 *
 * @var callable $trans
 * @var string   $sections
 * @var string   $articles
 */

if (!empty($sections)) {
    if ($trans('Subsections')) {
        echo '<h2 class="subsections">' . $trans('Subsections') . '</h2>' . "\n";
    }

    echo $sections;
}

if (!empty($articles)) {
    if ($trans('Read in this section')) {
        echo '<h2 class="articles">' . $trans('Read in this section') . '</h2>' . "\n";
    }

    echo $articles;
}
