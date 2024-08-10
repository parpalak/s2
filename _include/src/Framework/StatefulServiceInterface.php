<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

/**
 * Special interface for the services which internal state is dependent on the request
 * and must be cleared between requests.
 */
interface StatefulServiceInterface
{
    public function clearState(): void;
}
