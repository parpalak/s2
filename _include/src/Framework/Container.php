<?php
/**
 * Simple DI container.
 *
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

use Psr\Container\ContainerInterface;
use S2\Cms\Framework\Exception\ParameterNotFoundException;
use S2\Cms\Framework\Exception\ServiceNotFoundException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];

    public function __construct(private array $parameters)
    {
    }

    public function set(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (!isset($this->bindings[$id])) {
            throw new ServiceNotFoundException(sprintf('Entity "%s" not found in container.', $id));
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $factory = $this->bindings[$id];

        return $this->instances[$id] = $factory($this);
    }

    public function getIfInstantiated(string $id): mixed
    {
        return $this->instances[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    public function getParameter(string $name): mixed
    {
        if (!\array_key_exists($name, $this->parameters)) {
            throw new ParameterNotFoundException(sprintf('Unknown parameter "%s" has been requested from container. Either define one or fix its name.', $name));
        }

        if (!isset($this->parameters[$name])) {
            throw new ParameterNotFoundException(sprintf('Parameter "%s" is not initialized in container.', $name));
        }

        return $this->parameters[$name];
    }
}
