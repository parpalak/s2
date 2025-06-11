<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

readonly class UpdateCommonCompiler implements UpdateCompilerInterface
{
    public function __construct(private string $prefix)
    {
    }

    public function getSql(UpdateBuilder $builder): string
    {
        $set = [];
        foreach ($builder->getColumnExpressions() as $column => $expression) {
            $set[] = \sprintf('%s = %s', $column, $expression);
        }

        $sql = \sprintf(
            'UPDATE %s%s SET %s',
            $this->prefix,
            $builder->getTable(),
            \implode(', ', $set)
        );

        $whereConditions = $builder->getWhere();
        if (\count($whereConditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        return $sql;
    }
}
