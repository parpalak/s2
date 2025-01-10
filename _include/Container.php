<?php
/**
 * Simple DI container to be used in legacy code.
 *
 * @copyright  2023-2025 Roman Parpalak
 * @license    https://opensource.org/license/mit MIT
 * @package    S2
 *
 * @deprecated Use DI and S2\Cms\Framework\Container instead
 */

class Container
{
    private static ?\S2\Cms\Framework\Container $container = null;


    public static function setContainer(\S2\Cms\Framework\Container $container): void
    {
        self::$container = $container;
    }

    public static function get(string $className): object
    {
        return self::$container->get($className);
    }

    public static function isSet(): bool
    {
        return self::$container !== null;
    }

    public static function getIfInstantiated(string $className): ?object
    {
        return self::$container?->getIfInstantiated($className);
    }
}
