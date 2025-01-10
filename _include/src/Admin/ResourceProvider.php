<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

readonly class ResourceProvider
{
    public function __construct(private string $rootDir)
    {
    }

    /**
     * Languages available in current S2 installation
     */
    public function readLanguages(): array
    {
        $result = [];

        $directory = dir($this->rootDir . '_lang');
        while (($entry = $directory->read()) !== false) {
            if ($entry !== '.' && $entry !== '..' && is_dir($this->rootDir . '_lang/' . $entry) && file_exists($this->rootDir . '_lang/' . $entry . '/common.php')) {
                $result[] = $entry;
            }
        }

        $directory->close();

        return $result;
    }

    /**
     * Styles available in current S2 installation
     */
    public function readStyles(): array
    {
        $result = [];

        $directory = dir($this->rootDir . '_styles');
        while (($entry = $directory->read()) !== false) {
            if ($entry !== '.' && $entry !== '..' && is_dir($this->rootDir . '_styles/' . $entry) && file_exists($this->rootDir . '_styles/' . $entry . '/' . $entry . '.php')) {
                $result[] = $entry;
            }
        }

        $directory->close();

        return $result;
    }
}
