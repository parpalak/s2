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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use S2\Cms\Framework\Exception\ParameterNotFoundException;
use S2\Cms\Framework\Exception\ServiceNotFoundException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $idsByTag = [];

    public function __construct(private readonly array $parameters)
    {
    }

    public function set(string $id, callable|object $factory, array $tags = []): void
    {
        $this->bindings[$id] = $factory;
        if (!\is_callable($factory)) {
            $this->instances[$id] = $factory;
        }

        foreach ($tags as $tag) {
            $this->idsByTag[$tag][] = $id;
        }
    }

    public function decorate(string $id, callable $decorator): void
    {
        if (!isset($this->bindings[$id])) {
            // NOTE: If this case of primordial decoration of non-existent service is needed,
            // ServiceDecorator can be refactored to accept $factory later via a setter.
            throw new ServiceNotFoundException(sprintf('Entity "%s" not found in container.', $id));
        }

        $this->bindings[$id] = new ServiceDecorator($this->bindings[$id], $decorator, $this);
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

    public function getByTag(string $tag): array
    {
        try {
            return array_map(fn(string $id) => $this->get($id), $this->idsByTag[$tag] ?? []);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface  $e) {
            throw new \LogicException('Impossible exception occurred', 0, $e);
        }
    }

    public function getByTagIfInstantiated(string $tag): array
    {
        try {
            $services = array_map(fn(string $id) => $this->getIfInstantiated($id), $this->idsByTag[$tag] ?? []);

            return array_filter($services, static fn(mixed $service) => $service !== null);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface  $e) {
            throw new \LogicException('Impossible exception occurred', 0, $e);
        }
    }

    public function clear(string $id): void
    {
        if (!isset($this->bindings[$id])) {
            throw new ServiceNotFoundException(sprintf('Entity "%s" not found in container.', $id));
        }

        unset($this->instances[$id]);
    }

    public function clearByTag(string $tag): array
    {
        try {
            return array_map(fn(string $id) => $this->clear($id), $this->idsByTag[$tag] ?? []);
        } catch (NotFoundExceptionInterface $e) {
            throw new \LogicException('Impossible exception occurred', 0, $e);
        }
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
            throw new ParameterNotFoundException(sprintf('Parameter "%s" is initialized with null value in container.', $name));
        }

        return $this->parameters[$name];
    }
}
