<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

readonly class UpsertMysqlCompiler implements UpsertCompilerInterface
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
        $tableName         = $this->prefix . $builder->getTable();
        $columnList        = implode(', ', array_keys($columnExpressions));
        $valuesList        = implode(', ', array_values($columnExpressions));
        $uniqueColumns     = $builder->getUniqueColumns();
        $updateList        = implode(
            ', ',
            array_map(static function (string $columnName) {
                return "$columnName = VALUES($columnName)";
            }, array_diff(array_keys($columnExpressions), $uniqueColumns))
        );

        $sql = $this->substituteQueryParts($tableName, $columnList, $valuesList, $updateList);

        return $sql;
    }

    protected function substituteQueryParts(string $tableName, string $columnList, string $valuesList, string $updateList): string
    {
        /**
         * INSERT INTO table_name (column1, column2, ...)
         * VALUES (value1, value2, ...)
         * ON DUPLICATE KEY UPDATE column1 = VALUES(column1), column2 = VALUES(column2), ...;
         */
        return "INSERT INTO $tableName ($columnList) VALUES ($valuesList) ON DUPLICATE KEY UPDATE $updateList";
    }
}
