<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework\Exception;

use Psr\Container\ContainerExceptionInterface;

class DecoratedServiceNotFoundException extends \RuntimeException implements ContainerExceptionInterface
{

}
