<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

use S2\Cms\Queue\QueueConsumer;

if (PHP_SAPI !== 'cli') {
    return;
}

require __DIR__ . '/_include/common.php';

/** @var QueueConsumer $consumer */
$consumer = $app->container->get(QueueConsumer::class);
$startedAt = microtime(true);
while ($consumer->runQueue() && microtime(true) - $startedAt < 50);
