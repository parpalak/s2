<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

interface SelectCompilerInterface
{
    /**
     * @throws DbLayerException
     */
    public function getSql(SelectBuilder $builder): string;
}
