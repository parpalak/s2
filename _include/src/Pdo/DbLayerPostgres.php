<?php
/**
 * PostgreSQL database layer class.
 *
 * @copyright 2009-2024 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license   http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

use S2\Cms\Pdo\QueryBuilder\InsertBuilder;
use S2\Cms\Pdo\QueryBuilder\InsertCommonCompiler;
use S2\Cms\Pdo\QueryBuilder\UpsertBuilder;
use S2\Cms\Pdo\QueryBuilder\UpsertMysqlCompiler;
use S2\Cms\Pdo\QueryBuilder\UpsertPgsqlCompiler;

class DbLayerPostgres extends DbLayer
{
    protected const DATATYPE_TRANSFORMATIONS = [
        '/^(TINY|SMALL)INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i' => 'SMALLINT',
        '/^(MEDIUM)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i'    => 'INTEGER',
        '/^BIGINT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i'          => 'BIGINT',
        '/^(TINY|MEDIUM|LONG)?TEXT$/i'                           => 'TEXT',
        '/^DOUBLE( )?(\\([0-9,]+\\))?( )?(UNSIGNED)?$/i'         => 'DOUBLE PRECISION',
        '/^FLOAT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i'           => 'REAL'
    ];

    public function build(array $query): string
    {
        if (isset($query['UPSERT'])) {
            /**
             * INSERT INTO table_name (column1, column2, ...)
             * VALUES (value1, value2, ...)
             * ON CONFLICT (conflict_target) DO UPDATE
             * SET column1 = EXCLUDED.column1, column2 = EXCLUDED.column2, ...;
             * */
            $sql = 'INSERT INTO ' . (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . $query['INTO'];

            if (!empty($query['UPSERT'])) {
                $sql .= ' (' . $query['UPSERT'] . ')';
            }

            $uniqueFields = explode(',', $query['UNIQUE']);
            $uniqueFields = array_map('trim', $uniqueFields);
            $uniqueFields = array_flip($uniqueFields);

            $set = '';
            foreach (explode(',', $query['UPSERT']) as $field) {
                if (isset($uniqueFields[$field])) {
                    continue;
                }
                $field = trim($field);
                $set   .= $field . ' = EXCLUDED.' . $field . ',';
            }
            $set = rtrim($set, ',');

            $sql .= ' VALUES(' . $query['VALUES'] . ') ON CONFLICT (' . $query['UNIQUE'] . ') DO UPDATE SET ' . $set;

            return $sql;
        }

        return parent::build($query);
    }

    public function escape($str): string
    {
        return \is_array($str) ? '' : substr($this->pdo->quote($str), 1, -1);
    }

    public function getVersion(): array
    {
        $sql    = 'SELECT version()';
        $result = $this->query($sql);
        [$ver] = $this->fetchRow($result);

        return [
            'name'    => 'PostgreSQL',
            'version' => $ver
        ];
    }

    /**
     * @throws DbLayerException
     */
    public function tableExists(string $tableName, bool $noPrefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM pg_class WHERE relname = \'' . ($noPrefix ? '' : $this->prefix) . $this->escape($tableName) . '\'');
        return (bool)$this->fetchRow($result);
    }

    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $tableName, string $fieldName, bool $noPrefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM pg_class c INNER JOIN pg_attribute a ON a.attrelid = c.oid WHERE c.relname = \'' . ($noPrefix ? '' : $this->prefix) . $this->escape($tableName) . '\' AND a.attname = \'' . $this->escape($fieldName) . '\'');

        return (bool)$this->fetchRow($result);
    }

    /**
     * @throws DbLayerException
     */
    public function indexExists(string $table_name, string $index_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM pg_index i INNER JOIN pg_class c1 ON c1.oid = i.indrelid INNER JOIN pg_class c2 ON c2.oid = i.indexrelid WHERE c1.relname = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\' AND c2.relname = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_' . $this->escape($index_name) . '\'');
        return (bool)$this->fetchRow($result);
    }

    /**
     * @throws DbLayerException
     */
    public function createTable(string $table_name, array $schema, bool $no_prefix = false): void
    {
        if ($this->tableExists($table_name, $no_prefix)) {
            return;
        }

        $query = 'CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . " (\n";

        // Go through every schema element and add it to the query
        foreach ($schema['FIELDS'] as $field_name => $field_data) {
            $field_data['datatype'] = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $field_data['datatype']);

            $query .= $field_name . ' ' . $field_data['datatype'];

            // The SERIAL datatype is a special case where we don't need to say not null
            if (!$field_data['allow_null'] && $field_data['datatype'] != 'SERIAL') {
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
                $this->addIndex($table_name, $index_name, $index_fields, false, $no_prefix);
            }
        }

        // Add foreign keys
        if (isset($schema['FOREIGN KEYS'])) {
            foreach ($schema['FOREIGN KEYS'] as $key_name => $foreign_key) {
                $this->addForeignKey(
                    $table_name,
                    $key_name,
                    $foreign_key['columns'],
                    $foreign_key['reference_table'],
                    $foreign_key['reference_columns'],
                    $foreign_key['on_delete'] ?? null,
                    $foreign_key['on_update'] ?? null,
                    $no_prefix
                );
            }
        }
    }

    /**
     * @throws DbLayerException
     */
    public function addField(string $tableName, string $fieldName, string $fieldType, bool $allowNull, $defaultValue = null, ?string $afterField = null, bool $noPrefix = false): void
    {
        if ($this->fieldExists($tableName, $fieldName, $noPrefix)) {
            return;
        }

        $fieldType = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $fieldType);

        $sql = 'ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ADD ' . $fieldName . ' ' . $fieldType;
        if (!$allowNull) {
            $sql .= ' NOT NULL';
        }

        if ($defaultValue !== null) {
            if (!\is_int($defaultValue) && !\is_float($defaultValue)) {
                $defaultValue = '\'' . $this->escape($defaultValue) . '\'';
            }

            $sql .= ' DEFAULT ' . $defaultValue;
        }
        $this->query($sql);
    }

    /**
     * @throws DbLayerException
     */
    public function alterField(string $tableName, string $fieldName, string $fieldType, bool $allowNull, $defaultValue = null, ?string $afterField = null, bool $noPrefix = false): void
    {
        if (!$this->fieldExists($tableName, $fieldName, $noPrefix)) {
            return;
        }

        $fieldType = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $fieldType);

        $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ALTER COLUMN ' . $fieldName . ' TYPE ' . $fieldType);

        if ($defaultValue !== null) {
            if (!\is_int($defaultValue) && !\is_float($defaultValue)) {
                $defaultValue = '\'' . $this->escape($defaultValue) . '\'';
            }
            $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ALTER COLUMN ' . $fieldName . ' SET DEFAULT ' . $defaultValue);
        } else {
            $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ALTER COLUMN ' . $fieldName . ' DROP DEFAULT');
        }

        if (!$allowNull) {
            $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ALTER COLUMN ' . $fieldName . ' SET NOT NULL');
        } else {
            $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ALTER COLUMN ' . $fieldName . ' DROP NOT NULL');
        }
    }


    /**
     * @throws DbLayerException
     */
    public function addIndex(string $tableName, string $indexName, array $indexFields, bool $unique = false, bool $noPrefix = false): void
    {
        if ($this->indexExists($tableName, $indexName, $noPrefix)) {
            return;
        }

        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;
        $this->query('CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $tableNameWithPrefix . '_' . $indexName . ' ON ' . $tableNameWithPrefix . '(' . implode(',', $indexFields) . ')');
    }

    public function dropIndex(string $tableName, string $indexName, bool $noPrefix = false): void
    {
        if (!$this->indexExists($tableName, $indexName, $noPrefix)) {
            return;
        }

        $this->query('DROP INDEX ' . ($noPrefix ? '' : $this->prefix) . $tableName . '_' . $indexName);
    }

    public function foreignKeyExists(string $tableName, string $fkName, bool $noPrefix = false): bool
    {
        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;

        // Query to check if the foreign key exists
        $sql = 'SELECT 1
                FROM pg_constraint c
                JOIN pg_class t ON t.oid = c.conrelid
                JOIN pg_namespace n ON n.oid = t.relnamespace
                WHERE c.conname = :foreign_key_name
                AND t.relname = :table_name
                AND c.contype = \'f\'';

        $result = $this->query($sql, [
            'table_name'       => $tableNameWithPrefix,
            'foreign_key_name' => $tableNameWithPrefix . '_' . $fkName
        ]);

        return (bool)$this->result($result);
    }

    /**
     * @throws DbLayerException
     */
    public function dropForeignKey(string $tableName, string $fkName, bool $noPrefix = false): void
    {
        if (!$this->foreignKeyExists($tableName, $fkName, $noPrefix)) {
            return;
        }

        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;

        $query = 'ALTER TABLE ' . $tableNameWithPrefix . ' DROP CONSTRAINT ' . $tableNameWithPrefix . '_' . $fkName;

        $this->query($query);
    }

    public function insert(string $table): InsertBuilder
    {
        return (new InsertBuilder(new InsertCommonCompiler($this->prefix), $this))->insert($table);
    }

    public function upsert(string $table): UpsertBuilder
    {
        return (new UpsertBuilder(new UpsertPgsqlCompiler($this->prefix), $this))->upsert($table);
    }
}
