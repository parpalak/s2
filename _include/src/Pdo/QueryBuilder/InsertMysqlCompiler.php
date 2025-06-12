<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

readonly class InsertMysqlCompiler extends InsertCommonCompiler
{
    protected function getConflictClause(InsertBuilder $builder): string
    {
        $uniqueColumns = $builder->getUniqueColumnsForConflictDoNothing();
        if (\count($uniqueColumns) > 0) {
            return ' IGNORE';
        }

        return '';
    }

    protected function substituteQueryParts(string $tableName, string $columnList, string $valuesList, string $onConflict): string
    {
        return "INSERT$onConflict INTO $tableName ($columnList) VALUES ($valuesList)";
    }
}
