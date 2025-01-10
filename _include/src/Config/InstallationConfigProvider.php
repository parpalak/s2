<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Config;

use Psr\Cache\InvalidArgumentException;

class InstallationConfigProvider extends DynamicConfigProvider
{
    public function __construct()
    {
    }

    /**
     * @var callable
     */
    private $callback;

    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $paramName): mixed
    {
        return ($this->callback)($paramName);
    }
}
