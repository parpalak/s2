<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

readonly class SelectCommonCompiler implements SelectCompilerInterface
{
    public function __construct(private string $prefix)
    {
    }

    /**
     * @throws DbLayerException
     */
    public function getSql(SelectBuilder $builder): string
    {
        $sql = \sprintf(
            "SELECT %s FROM %s%s",
            implode(', ', $builder->getSelect()),
            $this->prefix,
            $builder->getFrom()
        );

        $joins = $builder->getJoins();
        foreach ($joins as $join) {
            $sql .= \sprintf(' %s %s%s ON %s',
                match ($join['type']) {
                    SelectBuilder::JOIN_TYPE_INNER => 'INNER JOIN',
                    SelectBuilder::JOIN_TYPE_LEFT => 'LEFT JOIN',
                },
                $this->prefix,
                $join['table'],
                $join['condition']
            );
        }

        $whereConditions = $builder->getWhere();
        if (\count($whereConditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        $groupBy = $builder->getGroupBy();
        if (\count($groupBy) > 0) {
            $sql .= ' GROUP BY ' . implode(', ', $groupBy);
        }

        $having = $builder->getHaving();
        if (\count($having) > 0) {
            $sql .= ' HAVING ' . implode(' AND ', $having);
        }

        $orderBy = $builder->getOrderBy();
        if (\count($orderBy) > 0) {
            $sql .= ' ORDER BY ' . implode(', ', $orderBy);
        }

        $limit = $builder->getLimit();
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }

        $offset = $builder->getOffset();
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }
}
