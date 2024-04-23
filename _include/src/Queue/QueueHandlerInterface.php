<?php
/**
 * @copyright 2023-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Queue;

interface QueueHandlerInterface
{
    public function handle(string $id, string $code, array $payload): bool;
}
