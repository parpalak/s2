<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Config;

use S2\Cms\Pdo\DbLayerException;

final readonly class StringProxy
{
    public function __construct(
        private DynamicConfigProvider $provider,
        private string                $paramName,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function get(): string
    {
        $value = $this->provider->get($this->paramName);
        if (\is_string($value)) {
            return $value;
        }

        throw new \LogicException(\sprintf('Dynamic config param "%s" must be a string.', $this->paramName));
    }

    public function __toString(): string
    {
        return $this->get();
    }
}
