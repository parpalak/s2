<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\HttpFoundation\Request;

class RedirectFromPublicEvent implements StoppableEventInterface
{
    private bool $isPropagationStopped = false;

    public function __construct(
        public readonly Request $request,
        public readonly string  $path,
    ) {
    }

    public function isPropagationStopped(): bool
    {
        return $this->isPropagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->isPropagationStopped = true;
    }
}
