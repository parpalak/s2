<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

readonly class UpsertSqliteCompiler implements UpsertCompilerInterface
{
    /**
     * @param string $prefix
     */
    public function __construct(private string $prefix)
    {
    }

    public function getSql(UpsertBuilder $builder): string
    {
        $columnExpressions = $builder->getColumnExpressions();
        $uniqueColumns     = $builder->getUniqueColumns();
        $tableName         = $this->prefix . $builder->getTable();

        $columnList        = implode(', ', array_keys($columnExpressions));
        $valuesList        = implode(', ', array_values($columnExpressions));
        $uniqueColumnsList = implode(', ', $uniqueColumns);
        $updateList        = implode(
            ', ',
            array_map(static function (string $columnName) {
                return "$columnName = excluded.$columnName";
            }, array_diff(array_keys($columnExpressions), $uniqueColumns))
        );

        $sql = $this->substituteQueryParts($tableName, $columnList, $valuesList, $uniqueColumnsList, $updateList);

        return $sql;
    }

    protected function substituteQueryParts(string $tableName, string $columnList, string $valuesList, string $uniqueColumnsList, string $updateList): string
    {
        /**
         * INSERT INTO table_name (column1, column2, ...)
         * VALUES (value1, value2, ...)
         * ON CONFLICT (conflict_target) DO UPDATE
         * SET column1 = excluded.column1, column2 = excluded.column2, ...;
         **/
        return "INSERT INTO $tableName ($columnList) VALUES ($valuesList) ON CONFLICT ($uniqueColumnsList) DO UPDATE SET $updateList";
    }
}
