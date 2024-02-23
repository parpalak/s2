<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework\Exception;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException  extends \RuntimeException implements NotFoundExceptionInterface
{

}
