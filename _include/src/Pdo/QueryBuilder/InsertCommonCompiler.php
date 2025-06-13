<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

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
        $tableName         = $this->prefix . $builder->getTable();
        $columnList        = implode(', ', array_keys($columnExpressions));
        $valuesList        = implode(', ', array_values($columnExpressions));
        $onConflict        = $this->getConflictClause($builder);

        $sql = $this->substituteQueryParts($tableName, $columnList, $valuesList, $onConflict);

        return $sql;
    }

    protected function getConflictClause(InsertBuilder $builder): string
    {
        $uniqueColumns = $builder->getUniqueColumnsForConflictDoNothing();
        if (\count($uniqueColumns) > 0) {
            return ' ON CONFLICT (' . implode(', ', $uniqueColumns) . ') DO NOTHING';
        }

        return '';
    }

    protected function substituteQueryParts(string $tableName, string $columnList, string $valuesList, string $onConflict): string
    {
        return "INSERT INTO $tableName ($columnList) VALUES ($valuesList)$onConflict";
    }
}
