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

    public function getVersion(): array
    {
        $result = $this->select('version()')->execute();

        return [
            'name'    => 'PostgreSQL',
            'version' => $result->result(),
        ];
    }

    /**
     * @throws DbLayerException
     */
    public function tableExists(string $tableName): bool
    {
        $result = $this->query('SELECT 1 FROM pg_class WHERE relname = :name', [
            'name' => $this->prefix . $tableName
        ]);
        return \count($result->fetchAll()) > 0;
    }

    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $tableName, string $fieldName): bool
    {
        $result = $this->query('SELECT 1 FROM pg_class c INNER JOIN pg_attribute a ON a.attrelid = c.oid WHERE c.relname = :table_name AND a.attname = :field_name', [
            'table_name' => $this->prefix . $tableName,
            'field_name' => $fieldName,
        ]);
        return \count($result->fetchAll()) > 0;
    }

    /**
     * @throws DbLayerException
     */
    public function indexExists(string $tableName, string $indexName): bool
    {
        $result = $this->query('SELECT 1 FROM pg_index i INNER JOIN pg_class c1 ON c1.oid = i.indrelid INNER JOIN pg_class c2 ON c2.oid = i.indexrelid WHERE c1.relname = :table_name AND c2.relname = :index_name', [
            'table_name' => $this->prefix . $tableName,
            'index_name' => $this->prefix . $tableName . '_' . $indexName,
        ]);
        return \count($result->fetchAll()) > 0;
    }

    /**
     * @throws DbLayerException
     */
    public function createTable(string $table_name, array $schema): void
    {
        if ($this->tableExists($table_name)) {
            return;
        }

        $query = 'CREATE TABLE ' . $this->prefix . $table_name . " (\n";

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
                $this->addIndex($table_name, $index_name, $index_fields, false);
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
                    $foreign_key['on_update'] ?? null
                );
            }
        }
    }

    /**
     * @throws DbLayerException
     */
    public function addField(
        string                $tableName,
        string                $fieldName,
        string                $fieldType,
        bool                  $allowNull,
        string|int|float|null $defaultValue = null,
        ?string               $afterField = null
    ): void {
        if ($this->fieldExists($tableName, $fieldName)) {
            return;
        }

        $fieldType = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $fieldType);

        $sql = 'ALTER TABLE ' . $this->prefix . $tableName . ' ADD ' . $fieldName . ' ' . $fieldType;
        if (!$allowNull) {
            $sql .= ' NOT NULL';
        }

        if ($defaultValue !== null) {
            if (!\is_int($defaultValue) && !\is_float($defaultValue)) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $defaultValue = $this->pdo->quote($defaultValue);
            }

            $sql .= ' DEFAULT ' . $defaultValue;
        }
        $this->query($sql);
    }

    /**
     * @throws DbLayerException
     */
    public function alterField(string $tableName, string $fieldName, string $fieldType, bool $allowNull, $defaultValue = null, ?string $afterField = null): void
    {
        if (!$this->fieldExists($tableName, $fieldName)) {
            return;
        }

        $fieldType = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $fieldType);

        $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ALTER COLUMN ' . $fieldName . ' TYPE ' . $fieldType);

        if ($defaultValue !== null) {
            if (!\is_int($defaultValue) && !\is_float($defaultValue)) {
                $defaultValue = $this->pdo->quote($defaultValue);
            }
            $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ALTER COLUMN ' . $fieldName . ' SET DEFAULT ' . $defaultValue);
        } else {
            $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ALTER COLUMN ' . $fieldName . ' DROP DEFAULT');
        }

        if (!$allowNull) {
            $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ALTER COLUMN ' . $fieldName . ' SET NOT NULL');
        } else {
            $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ALTER COLUMN ' . $fieldName . ' DROP NOT NULL');
        }
    }


    /**
     * @throws DbLayerException
     */
    public function addIndex(string $tableName, string $indexName, array $indexFields, bool $unique = false): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        $tableNameWithPrefix = $this->prefix . $tableName;
        $this->query('CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $tableNameWithPrefix . '_' . $indexName . ' ON ' . $tableNameWithPrefix . '(' . implode(',', $indexFields) . ')');
    }

    public function dropIndex(string $tableName, string $indexName): void
    {
        if (!$this->indexExists($tableName, $indexName)) {
            return;
        }

        $this->query('DROP INDEX ' . $this->prefix . $tableName . '_' . $indexName);
    }

    public function foreignKeyExists(string $tableName, string $fkName): bool
    {
        $tableNameWithPrefix = $this->prefix . $tableName;

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
    public function dropForeignKey(string $tableName, string $fkName): void
    {
        if (!$this->foreignKeyExists($tableName, $fkName)) {
            return;
        }

        $tableNameWithPrefix = $this->prefix . $tableName;

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
