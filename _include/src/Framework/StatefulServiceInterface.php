<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

/**
 * Special interface for the services which internal state is dependent on the request
 * or other external factors and must be cleared between requests.
 */
interface StatefulServiceInterface
{
    /**
     * Resets internal state (e.g. in-memory cache).
     */
    public function clearState(): void;
}
