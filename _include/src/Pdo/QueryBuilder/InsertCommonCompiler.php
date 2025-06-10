<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

readonly class InsertCommonCompiler implements InsertCompilerInterface
{

    /**
     * @param string $prefix
     */
    public function __construct(private string $prefix)
    {
    }

    public function getSql(InsertBuilder $builder): string
    {
        $columnExpressions = $builder->getColumnExpressions();
        $sql = \sprintf(
            "INSERT INTO %s%s (%s) VALUES (%s)",
            $this->prefix,
            $builder->getTable(),
            implode(', ', array_keys($columnExpressions)),
            implode(', ', array_values($columnExpressions))
        );

        return $sql;
    }
}
