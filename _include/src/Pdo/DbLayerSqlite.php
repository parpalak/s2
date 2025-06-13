<?php
/**
 * SQLite database layer class.
 *
 * @copyright 2011-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

use S2\Cms\Pdo\QueryBuilder\InsertBuilder;
use S2\Cms\Pdo\QueryBuilder\InsertCommonCompiler;
use S2\Cms\Pdo\QueryBuilder\UpsertBuilder;
use S2\Cms\Pdo\QueryBuilder\UpsertSqliteCompiler;

class DbLayerSqlite extends DbLayer
{
    protected const DATATYPE_TRANSFORMATIONS = [
        '/^SERIAL$/'                                                         => 'INTEGER',
        '/^(TINY|SMALL|MEDIUM|BIG)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i' => 'INTEGER',
        '/^(TINY|MEDIUM|LONG)?TEXT$/i'                                       => 'TEXT'
    ];

    public function getVersion(): array
    {
        $result  = $this->select('sqlite_version()')->execute();
        $version = $result->result();
        $result->freeResult();

        return [
            'name'    => 'SQLite',
            'version' => $version
        ];
    }

    /**
     * @throws DbLayerException
     */
    public function tableExists(string $tableName): bool
    {
        $result = $this->query('SELECT 1 FROM sqlite_master WHERE name = :name AND type = :type', [
            'name' => $this->prefix . $tableName,
            'type' => 'table'
        ]);
        $return = (bool)$this->result($result);
        $this->freeResult($result);

        return $return;
    }

    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $tableName, string $fieldName, bool $noPrefix = false): bool
    {
        $preparedTableName = ($noPrefix ? '' : $this->prefix) . $tableName;

        $result = $this->query('PRAGMA table_info(' . $preparedTableName . ')');

        $fieldExists = false;
        while ($row = $this->fetchAssoc($result)) {
            if ($row['name'] === $fieldName) {
                $fieldExists = true;
                break;
            }
        }

        $this->freeResult($result);

        return $fieldExists;
    }

    /**
     * @throws DbLayerException
     */
    public function indexExists(string $tableName, string $indexName, bool $noPrefix = false): bool
    {
        $prefix = $noPrefix ? '' : $this->prefix;

        $result = $this->query('PRAGMA index_list(' . $this->pdo->quote($prefix . $tableName) . ')');

        $exists    = false;
        $indexName = ($noPrefix ? '' : $this->prefix) . $tableName . '_' . $indexName;
        while ($row = $this->fetchAssoc($result)) {
            if ($row['name'] === $indexName) {
                $exists = true;
                break;
            }
        }

        $this->freeResult($result);

        return $exists;
    }

    /**
     * @throws DbLayerException
     */
    public function createTable(string $tableName, array $schema): void
    {
        if ($this->tableExists($tableName)) {
            return;
        }

        $query = 'CREATE TABLE ' . $this->prefix . $tableName . " (\n";

        // Go through every schema element and add it to the query
        foreach ($schema['FIELDS'] as $field_name => $field_data) {
            $field_data['datatype'] = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $field_data['datatype']);

            $query .= $field_name . ' ' . $field_data['datatype'];

            if (!$field_data['allow_null']) {
                $query .= ' NOT NULL';
            }

            if (isset($field_data['default'])) {
                $query .= ' DEFAULT ' . $field_data['default'];
            }

            $query .= ",\n";
        }

        // If we have a primary key, add it
        if (isset($schema['PRIMARY KEY'])) {
            $query .= 'PRIMARY KEY (' . implode(',', $schema['PRIMARY KEY']) . '),' . "\n";
        }

        // Add unique keys
        if (isset($schema['UNIQUE KEYS'])) {
            foreach ($schema['UNIQUE KEYS'] as $key_name => $key_fields) {
                $query .= 'UNIQUE (' . implode(',', $key_fields) . '),' . "\n";
            }
        }

        // We remove the last two characters (a newline and a comma) and add on the ending
        $query = substr($query, 0, -2) . "\n" . ')';

        $result = $this->query($query);
        $this->freeResult($result);

        // Add indexes
        if (isset($schema['INDEXES'])) {
            foreach ($schema['INDEXES'] as $index_name => $index_fields) {
                $this->addIndex($tableName, $index_name, $index_fields);
            }
        }

        // Add foreign keys
        if (isset($schema['FOREIGN KEYS'])) {
            foreach ($schema['FOREIGN KEYS'] as $key_name => $foreign_key) {
                $this->addForeignKey(
                    $tableName,
                    $key_name,
                    $foreign_key['columns'],
                    $foreign_key['reference_table'],
                    $foreign_key['reference_columns'],
                    $foreign_key['on_delete'] ?? null,
                    $foreign_key['on_update'] ?? null,
                );
            }
        }
    }

    /**
     * @throws DbLayerException
     */
    private function getTableInfo(string $tableName): SqliteCreateTableQuery
    {
        $result = $this->query('SELECT sql, type FROM sqlite_master WHERE tbl_name = :table_name ORDER BY type DESC', [
            'table_name' => $this->prefix . $tableName,
        ]);

        $sql     = '';
        $indexes = [];
        while ($curIndex = $this->fetchAssoc($result)) {
            if (!empty($curIndex['sql'])) {
                if ('table' === $curIndex['type']) {
                    $sql = $curIndex['sql'];
                } elseif ('index' === $curIndex['type']) {
                    $indexes[] = $curIndex['sql'];
                }
            }
        }
        $this->freeResult($result);

        return new SqliteCreateTableQuery($sql, $indexes);
    }

    /**
     * @throws DbLayerException
     */
    public function addField(string $tableName, string $fieldName, string $fieldType, bool $allowNull, $defaultValue = null, ?string $afterField = null): void
    {
        if ($this->fieldExists($tableName, $fieldName)) {
            return;
        }

        $tempTableName         = $tableName . '_t' . time();
        $preparedTempTableName = $this->prefix . $tempTableName;

        $createTable    = $this->getTableInfo($tableName);
        $fieldType      = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $fieldType);
        $newCreateTable = $createTable
            ->withNewField($fieldName, $fieldType, $allowNull, $defaultValue, $afterField)
            ->withTableName($preparedTempTableName)
        ;

        $this->changeTableStructure($newCreateTable, $createTable);
    }

    public function alterField(string $tableName, string $fieldName, string $fieldType, bool $allowNull, $defaultValue = null, ?string $afterField = null): void
    {
        $tempTableName         = $tableName . '_t' . time();
        $preparedTempTableName = $this->prefix . $tempTableName;

        $createTable    = $this->getTableInfo($tableName);
        $fieldType      = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $fieldType);
        $newCreateTable = $createTable
            ->withAlteredField($fieldName, $fieldType, $allowNull, $defaultValue, $afterField)
            ->withTableName($preparedTempTableName)
        ;

        $this->changeTableStructure($newCreateTable, $createTable);
    }

    public function addIndex(string $tableName, string $indexName, array $indexFields, bool $unique = false): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        $tableNameWithPrefix = $this->prefix . $tableName;
        $result              = $this->query(
            'CREATE ' . ($unique ? 'UNIQUE ' : '')
            . 'INDEX ' . $tableNameWithPrefix . '_' . $indexName
            . ' ON ' . $tableNameWithPrefix . '(' . implode(',', $indexFields) . ')'
        );
        $this->freeResult($result);
    }

    public function dropIndex(string $tableName, string $indexName): void
    {
        if (!$this->indexExists($tableName, $indexName)) {
            return;
        }

        $tableNameWithPrefix = $this->prefix . $tableName;
        $this->query('DROP INDEX ' . $tableNameWithPrefix . '_' . $indexName);
    }

    /**
     * @throws DbLayerException
     */
    public function foreignKeyExists(string $tableName, string $fkName): bool
    {
        $createTable = $this->getTableInfo($tableName);

        return isset($createTable->getForeignKeys()[$fkName]);
    }

    /**
     * @throws DbLayerException
     */
    public function addForeignKey(string $tableName, string $fkName, array $columns, string $referenceTable, array $referenceColumns, ?string $onDelete = null, ?string $onUpdate = null): void
    {
        if ($this->foreignKeyExists($tableName, $fkName)) {
            return;
        }

        $tempTableName         = $tableName . '_t' . time();
        $preparedTempTableName = $this->prefix . $tempTableName;

        $createTable    = $this->getTableInfo($tableName);
        $newCreateTable = $createTable
            ->withNewForeignKey($fkName, $columns, $this->prefix . $referenceTable, $referenceColumns, $onDelete, $onUpdate)
            ->withTableName($preparedTempTableName)
        ;

        $this->changeTableStructure($newCreateTable, $createTable);
    }

    /**
     * @throws DbLayerException
     */
    public function dropForeignKey(string $tableName, string $fkName): void
    {
        if (!$this->foreignKeyExists($tableName, $fkName)) {
            return;
        }

        // SQLite does not support dropping a specific foreign key directly
        // The table has to be recreated without the foreign key

        $tempTableName         = $tableName . '_t' . time();
        $preparedTempTableName = $this->prefix . $tempTableName;

        $createTable    = $this->getTableInfo($tableName);
        $newCreateTable = $createTable
            ->withoutForeignKey($fkName)
            ->withTableName($preparedTempTableName)
        ;

        $this->changeTableStructure($newCreateTable, $createTable);
    }

    /**
     * @throws DbLayerException
     */
    private function changeTableStructure(SqliteCreateTableQuery $tempWithNewStructure, SqliteCreateTableQuery $oldStructure): void
    {
        // Disable ON DELETE actions
        $this->query('PRAGMA foreign_keys = OFF;');

        // Create temp table with new structure
        $result = $this->query($tempWithNewStructure->__toString());
        $this->freeResult($result);

        // Copy data
        $joinedColumnNames = implode(', ', $oldStructure->getColumnNames());

        $result = $this->query('INSERT INTO ' . $tempWithNewStructure->getTableName() . '(' . $joinedColumnNames . ') SELECT ' . $joinedColumnNames . ' FROM ' . $oldStructure->getTableName());
        $this->freeResult($result);

        // Drop old table
        $result = $this->query('DROP TABLE ' . $oldStructure->getTableName());
        $this->freeResult($result);

        // Copy content back
        $sql    = 'ALTER TABLE ' . $tempWithNewStructure->getTableName() . ' RENAME TO ' . $oldStructure->getTableName();
        $result = $this->query($sql);
        $this->freeResult($result);

        // Recreate indexes
        foreach ($tempWithNewStructure->getIndexes() as $cur_index) {
            $result = $this->query($cur_index);
            $this->freeResult($result);
        }

        $this->query('PRAGMA foreign_keys = ON;');
    }

    public function insert(string $table): InsertBuilder
    {
        return (new InsertBuilder(new InsertCommonCompiler($this->prefix), $this))->insert($table);
    }

    public function upsert(string $table): UpsertBuilder
    {
        return (new UpsertBuilder(new UpsertSqliteCompiler($this->prefix), $this))->upsert($table);
    }
}
