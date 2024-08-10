<?php
/**
 * A database abstract layer class.
 * Contains default implementation for MySQL database.
 *
 * @copyright (C) 2009-2023 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package       S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

class DbLayer
{
    protected int $transactionLevel = 0;
    protected const DATATYPE_TRANSFORMATIONS = [
        '/^SERIAL$/' => 'INT(10) UNSIGNED AUTO_INCREMENT'
    ];

    public function __construct(
        protected \PDO   $pdo,
        protected string $prefix = ''
    ) {
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

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

    public function build(array $query): string
    {
        $sql = '';

        if (isset($query['SELECT'])) {
            $sql = 'SELECT ' . $query['SELECT'] . ' FROM ' . (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . $query['FROM'];

            foreach ($query['JOINS'] ?? [] as $join) {
                $sql .= ' ' . key($join) . ' ' . (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . current($join) . ' ON ' . $join['ON'];
            }

            if (!empty($query['WHERE'])) {
                $sql .= ' WHERE ' . $query['WHERE'];
            }
            if (!empty($query['GROUP BY'])) {
                $sql .= ' GROUP BY ' . $query['GROUP BY'];
            }
            if (!empty($query['HAVING'])) {
                $sql .= ' HAVING ' . $query['HAVING'];
            }
            if (!empty($query['ORDER BY'])) {
                $sql .= ' ORDER BY ' . $query['ORDER BY'];
            }
            if (!empty($query['LIMIT'])) {
                $sql .= ' LIMIT ' . $query['LIMIT'];
            }
        } else if (isset($query['INSERT'])) {
            $sql = 'INSERT INTO ' . (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . $query['INTO'];

            if (!empty($query['INSERT'])) {
                $sql .= ' (' . $query['INSERT'] . ')';
            }

            if (\is_array($query['VALUES'])) {
                $sql .= ' VALUES(' . implode('),(', $query['VALUES']) . ')';
            } else {
                $sql .= ' VALUES(' . $query['VALUES'] . ')';
            }
        } else if (isset($query['UPDATE'])) {
            $query['UPDATE'] = (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . $query['UPDATE'];

            $sql = 'UPDATE ' . $query['UPDATE'] . ' SET ' . $query['SET'];

            if (!empty($query['WHERE'])) {
                $sql .= ' WHERE ' . $query['WHERE'];
            }
        } else if (isset($query['DELETE'])) {
            $sql = 'DELETE FROM ' . (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . $query['DELETE'];

            if (!empty($query['WHERE'])) {
                $sql .= ' WHERE ' . $query['WHERE'];
            }
        } else if (isset($query['REPLACE'])) {
            $sql = 'REPLACE INTO ' . (isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix) . $query['INTO'];

            if (!empty($query['REPLACE'])) {
                $sql .= ' (' . $query['REPLACE'] . ')';
            }

            $sql .= ' VALUES(' . $query['VALUES'] . ')';
        }

        return $sql;
    }

    /**
     * @throws DbLayerException
     */
    public function buildAndQuery(array $query, array $params = [], array $types = []): \PDOStatement
    {
        $sql = $this->build($query);

        return $this->query($sql, $params, $types);
    }

    /**
     * @throws DbLayerException
     */
    public function query($sql, array $params = [], array $types = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            if ($types !== []) {
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, $types[$key] ?? \PDO::PARAM_STR);
                }
                $stmt->execute();
            } else {
                $stmt->execute($params);
            }

            return $stmt;
        } catch (\PDOException $e) {
            if ($this->transactionLevel > 0) {
                $this->pdo->rollBack();
                --$this->transactionLevel;
            }

            throw new DbLayerException(
                sprintf("%s. Failed query: %s. Error code: %s.", $e->getMessage(), $sql, $e->getCode()),
                $e->errorInfo[1] ?? 0,
                $sql,
                $e
            );
        }
    }

    public function fetchAssocAll(\PDOStatement $statement): array
    {
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function result(\PDOStatement $statement, $row = 0, $col = 0): mixed
    {
        for ($i = $row; $i--;) {
            $curRow = $statement->fetch();
            if ($curRow === false) {
                return false;
            }
        }

        $curRow = $statement->fetch();
        if ($curRow === false) {
            return false;
        }

        return $curRow[$col] ?? false;
    }


    public function fetchAssoc(\PDOStatement $statement): array|false
    {
        return $statement->fetch(\PDO::FETCH_ASSOC);
    }


    public function fetchRow(\PDOStatement $statement): array|false
    {
        return $statement->fetch(\PDO::FETCH_NUM);
    }

    public function fetchColumn(\PDOStatement $statement): array
    {
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function affectedRows(\PDOStatement $statement): int
    {
        return $statement->rowCount();
    }

    public function insertId(): false|string
    {
        return $this->pdo->lastInsertId();
    }

    public function freeResult(\PDOStatement $statement): true
    {
        $statement->closeCursor();
        return true;
    }

    public function escape($str): string
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection TODO remove is_array after adding type hinting */
        // return is_array($str) ? '' : $this->pdo->quote($str);
        if (\is_array($str)) {
            return ''; // array_map(__METHOD__, $inp);
        }

        if (\is_string($str) && $str !== '') {
            return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $str);
        }

        return $str;
    }


    public function close(): bool
    {
        if ($this->transactionLevel > 0) {
            $this->pdo->commit();
        }

        return true;
    }


    /**
     * @throws DbLayerException
     */
    public function getVersion(): array
    {
        $statement = $this->query('SELECT VERSION()');

        return [
            'name'    => 'MySQL',
            'version' => $this->result($statement),
        ];
    }


    /**
     * @throws DbLayerException
     */
    public function tableExists(string $tableName, bool $noPrefix = false): bool
    {
        $result = $this->query('SHOW TABLES LIKE \'' . ($noPrefix ? '' : $this->prefix) . $this->escape($tableName) . '\'');
        return \count($result->fetchAll()) > 0;
    }


    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $tableName, string $fieldName, bool $noPrefix = false): bool
    {
        $result = $this->query('SHOW COLUMNS FROM ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' LIKE \'' . $this->escape($fieldName) . '\'');

        return \count($result->fetchAll()) > 0;
    }


    /**
     * @throws DbLayerException
     */
    public function indexExists(string $table_name, string $index_name, bool $no_prefix = false): bool
    {
        $exists = false;

        $result = $this->query('SHOW INDEX FROM ' . ($no_prefix ? '' : $this->prefix) . $table_name);
        while ($cur_index = $this->fetchAssoc($result)) {
            if (strtolower($cur_index['Key_name']) === strtolower(($no_prefix ? '' : $this->prefix) . $table_name . '_' . $index_name)) {
                $exists = true;
                break;
            }
        }

        return $exists;
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
                $query .= 'UNIQUE KEY ' . ($no_prefix ? '' : $this->prefix) . $table_name . '_' . $key_name . '(' . implode(',', $key_fields) . '),' . "\n";
            }
        }

        // Add indexes
        if (isset($schema['INDEXES'])) {
            foreach ($schema['INDEXES'] as $index_name => $index_fields) {
                $query .= 'KEY ' . ($no_prefix ? '' : $this->prefix) . $table_name . '_' . $index_name . '(' . implode(',', $index_fields) . '),' . "\n";
            }
        }

        // We remove the last two characters (a newline and a comma) and add on the ending
        $query = substr($query, 0, -2) . "\n" . ') ENGINE = InnoDB CHARACTER SET utf8mb4';

        $this->query($query);

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
    public function dropTable(string $table_name, $no_prefix = false): void
    {
        if (!$this->tableExists($table_name, $no_prefix)) {
            return;
        }

        $this->query('DROP TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name);
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

        if ($defaultValue !== null && !\is_int($defaultValue) && !\is_float($defaultValue)) {
            $defaultValue = '\'' . $this->escape($defaultValue) . '\'';
        }

        $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ADD ' . $fieldName . ' ' . $fieldType . ($allowNull ? ' ' : ' NOT NULL') . ($defaultValue !== null ? ' DEFAULT ' . $defaultValue : ' ') . ($afterField != null ? ' AFTER ' . $afterField : ''));
    }

    /**
     * @throws DbLayerException
     */
    public function renameField(string $table_name, string $old_field_name, string $new_field_name, bool $no_prefix = false): void
    {
        $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' RENAME COLUMN ' . $old_field_name . ' TO ' . $new_field_name);
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

        if ($defaultValue !== null && !\is_int($defaultValue) && !\is_float($defaultValue)) {
            $defaultValue = '\'' . $this->escape($defaultValue) . '\'';
        }

        $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' MODIFY ' . $fieldName . ' ' . $fieldType . ($allowNull ? ' ' : ' NOT NULL') . ($defaultValue !== null ? ' DEFAULT ' . $defaultValue : ' ') . ($afterField !== null ? ' AFTER ' . $afterField : ''));
    }


    /**
     * @throws DbLayerException
     */
    public function dropField(string $table_name, string $field_name, bool $no_prefix = false): void
    {
        if (!$this->tableExists($table_name) || !$this->fieldExists($table_name, $field_name, $no_prefix)) {
            return;
        }

        $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' DROP ' . $field_name);
    }


    /**
     * @throws DbLayerException
     */
    public function addIndex(string $tableName, string $indexName, array $indexFields, bool $unique = false, bool $noPrefix = false): void
    {
        if ($this->indexExists($tableName, $indexName, $noPrefix)) {
            return;
        }

        $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . ($noPrefix ? '' : $this->prefix) . $tableName . '_' . $indexName . ' (' . implode(',', $indexFields) . ')');
    }


    /**
     * @throws DbLayerException
     */
    public function dropIndex(string $tableName, string $indexName, bool $noPrefix = false): void
    {
        if (!$this->indexExists($tableName, $indexName, $noPrefix)) {
            return;
        }

        $this->query('ALTER TABLE ' . ($noPrefix ? '' : $this->prefix) . $tableName . ' DROP INDEX ' . ($noPrefix ? '' : $this->prefix) . $tableName . '_' . $indexName);
    }

    /**
     * @throws DbLayerException
     */
    public function foreignKeyExists(string $tableName, string $fkName, bool $noPrefix = false): bool
    {
        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;

        // Query to check if the foreign key exists
        $sql = 'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND CONSTRAINT_NAME = :foreign_key_name
                AND REFERENCED_TABLE_NAME IS NOT NULL';

        $result = $this->query($sql, [
            'table_name'       => $tableNameWithPrefix,
            'foreign_key_name' => $tableNameWithPrefix . '_' . $fkName
        ]);

        return (bool)$this->result($result);
    }

    /**
     * @throws DbLayerException
     */
    public function addForeignKey(string $tableName, string $fkName, array $columns, string $referenceTable, array $referenceColumns, ?string $onDelete = null, ?string $onUpdate = null, bool $noPrefix = false): void
    {
        if ($this->foreignKeyExists($tableName, $fkName, $noPrefix)) {
            return;
        }

        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;

        $query = 'ALTER TABLE ' . $tableNameWithPrefix . ' ADD CONSTRAINT ' . $tableNameWithPrefix . '_' . $fkName .
            ' FOREIGN KEY (' . implode(',', $columns) . ')' .
            ' REFERENCES ' . ($noPrefix ? '' : $this->prefix) . $referenceTable . ' (' . implode(',', $referenceColumns) . ')';

        if ($onDelete !== null) {
            $query .= ' ON DELETE ' . $onDelete;
        }

        if ($onUpdate !== null) {
            $query .= ' ON UPDATE ' . $onUpdate;
        }

        $this->query($query);
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

        $query = 'ALTER TABLE ' . $tableNameWithPrefix . ' DROP FOREIGN KEY ' . $tableNameWithPrefix . '_' . $fkName;

        $this->query($query);
    }
}
