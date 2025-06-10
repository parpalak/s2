<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;
use S2\Cms\Pdo\DbLayerException;

interface UpdateCompilerInterface
{
    /**
     * @throws DbLayerException
     */
    public function getSql(UpdateBuilder $builder): string;
}
