<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Config;

use S2\Cms\Pdo\DbLayerException;

final readonly class IntProxy
{
    public function __construct(
        private DynamicConfigProvider $provider,
        private string                $paramName,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function get(): int
    {
        return (int)$this->provider->get($this->paramName);
    }
}
