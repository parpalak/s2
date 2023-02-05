<?php
/**
 * Ajax request processing for autosearch
 *
 * @copyright (C) 2011-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */


use S2\Rose\Storage\Database\PdoStorage;

header('Content-Type: text/html; charset=utf-8');

define('S2_ROOT', '../../');
require S2_ROOT . '_include/common.php';

header('X-Powered-By: S2/' . S2_VERSION);

$s2_search_query = $_GET['q'] ?? '';

if ($s2_search_query !== '') {
    /** @var PdoStorage $pdoStorage */
    $pdoStorage = Container::get(PdoStorage::class);
    $toc        = $pdoStorage->getTocByTitlePrefix($s2_search_query);

    foreach ($toc as $tocEntryWithExtId) {
        echo '<a href="' . s2_link($tocEntryWithExtId->getTocEntry()->getUrl()) . '">' .
            preg_replace('#(' . preg_quote($s2_search_query, '#') . ')#ui', '<em>\\1</em>', s2_htmlencode($tocEntryWithExtId->getTocEntry()->getTitle())) . '</a>';
    }
}
