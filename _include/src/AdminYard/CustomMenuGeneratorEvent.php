<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard;

class CustomMenuGeneratorEvent
{
    /**
     * @var array<string, Signal[]>
     */
    private array $signals = [];

    public function __construct(public readonly array $enabledEntities)
    {
    }

    public function addSignal(string $entity, Signal $signal): void
    {
        $this->signals[$entity][] = $signal;
    }

    /**
     * @return array<string, Signal[]>
     */
    public function getSignals(): array
    {
        return $this->signals;
    }
}
