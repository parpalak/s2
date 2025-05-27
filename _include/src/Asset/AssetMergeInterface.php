<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Asset;

interface AssetMergeInterface
{
    /**
     * Add a file to be merged
     */
    public function concat(string $fileName): void;

    /**
     * Get the list of merged files
     * @return string[]
     */
    public function getMergedPaths(): array;
}
