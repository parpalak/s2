<?php declare(strict_types=1);

use S2\Cms\Queue\QueueConsumer;

if (PHP_SAPI !== 'cli') {
    return;
}

define('S2_ROOT', './');
require S2_ROOT . '_include/common.php';

/** @var QueueConsumer $consumer */
$consumer = Container::get(QueueConsumer::class);
$startedAt = microtime(true);
while ($consumer->runQueue() && microtime(true) - $startedAt < 50);
