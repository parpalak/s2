<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

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
        callable|object            $factory,
        callable                   $decorator,
        private readonly Container $container
    ) {
        $this->factory   = $factory;
        $this->decorator = $decorator;
    }

    public function __invoke(): mixed
    {
        return ($this->decorator)($this->container, $this->factory);
    }
}
