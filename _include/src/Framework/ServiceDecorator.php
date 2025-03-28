<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

use S2\Cms\Framework\Exception\DecoratedServiceNotFoundException;

class ServiceDecorator
{
    /**
     * @var callable|object
     */
    private $factory;

    /**
     * @var callable
     */
    private $decorator;

    public function __construct(
        callable|object|null       $factory,
        callable                   $decorator,
        private readonly Container $container
    ) {
        $this->factory   = $factory;
        $this->decorator = $decorator;
    }

    public function setFactory(callable|object $factory): void
    {
        $this->factory = $factory;
    }

    public function __invoke(): mixed
    {
        if ($this->factory === null) {
            throw new DecoratedServiceNotFoundException('Original factory is not set.');
        }
        return ($this->decorator)($this->container, $this->factory);
    }
}
