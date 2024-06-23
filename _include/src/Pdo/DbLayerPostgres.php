<?php
/**
 * PostgreSQL database layer class.
 *
 * @copyright (C) 2009-2023 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

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

    public function startTransaction(): void
    {
        ++$this->transactionLevel;
        $this->pdo->beginTransaction();
    }

    public function endTransaction(): void
    {
        if ($this->transactionLevel > 0) {
            --$this->transactionLevel;
            $this->pdo->commit();
        }
    }

    public function query($sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute($params);

            return $stmt;
        } catch (\PDOException $e) {
            if ($this->transactionLevel > 0) {
                $this->pdo->rollBack();
                --$this->transactionLevel;
            }

            throw new DbLayerException(
                sprintf("%s. Failed query: %s. Error code: %s.", $e->getMessage(), $sql, $e->getCode()),
                $e->errorInfo[1],
                $sql,
                $e
            );
        }
    }

    public function escape($str): string
    {
        return \is_array($str) ? '' : substr($this->pdo->quote($str), 1, -1);
    }

    public function close(): bool
    {
        if ($this->transactionLevel > 0) {
            $this->pdo->commit();
        }

        return true;
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
    public function tableExists(string $table_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM pg_class WHERE relname = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\'');
        return (bool)$this->fetchRow($result);
    }


    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $table_name, string $field_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM pg_class c INNER JOIN pg_attribute a ON a.attrelid = c.oid WHERE c.relname = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\' AND a.attname = \'' . $this->escape($field_name) . '\'');
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
    }

    /**
     * @throws DbLayerException
     */
    public function addField(string $table_name, string $field_name, string $field_type, bool $allow_null, $default_value = null, ?string $after_field = null, bool $no_prefix = false): void
    {
        if ($this->fieldExists($table_name, $field_name, $no_prefix)) {
            return;
        }

        $field_type = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $field_type);

        $sql = 'ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' ADD ' . $field_name . ' ' . $field_type;
        if (!$allow_null) {
            $sql .= ' NOT NULL';
        }

        if ($default_value !== null) {
            if (!\is_int($default_value) && !\is_float($default_value)) {
                $default_value = '\'' . $this->escape($default_value) . '\'';
            }

            $sql .= ' DEFAULT ' . $default_value;
        }
        $this->query($sql);
    }

    /**
     * @throws DbLayerException
     */
    public function alterField(string $table_name, string $field_name, string $field_type, bool $allow_null, $default_value = null, ?string $after_field = null, bool $no_prefix = false): void
    {
        if (!$this->fieldExists($table_name, $field_name, $no_prefix)) {
            return;
        }

        $field_type = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $field_type);

        // TODO inspect and rewrite this code, maybe there is a direct way in modern Postgres versions.
        $this->addField($table_name, 'tmp_' . $field_name, $field_type, $allow_null, $default_value, $after_field, $no_prefix);
        $this->query('UPDATE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' SET tmp_' . $field_name . ' = ' . $field_name);
        $this->dropField($table_name, $field_name, $no_prefix);
        $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' RENAME COLUMN tmp_' . $field_name . ' TO ' . $field_name);

        // Set the default value
        if ($default_value === null) {
            $default_value = 'NULL';
        } elseif (!\is_int($default_value) && !\is_float($default_value)) {
            $default_value = '\'' . $this->escape($default_value) . '\'';
        }

        $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' ALTER ' . $field_name . ' SET DEFAULT ' . $default_value);

        if (!$allow_null) {
            $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' ALTER ' . $field_name . ' SET NOT NULL');
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
}
