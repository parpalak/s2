<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Asset;

interface AssetMergeInterface
{
    public function concat(string $fileName): void;

    public function getMergedPath(): string;
}
