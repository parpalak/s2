<?php
/**
 * A database abstract layer class.
 * Contains default implementation for MySQL database.
 *
 * @copyright (C) 2009-2023 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
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
        // $this->pdo->beginTransaction(); // MySQL does not support DDL in transactions so for S2 they are useless
    }

    public function endTransaction(): void
    {
        if ($this->transactionLevel > 0) {
            --$this->transactionLevel;
            // $this->pdo->commit(); // MySQL does not support DDL in transactions so for S2 they are useless
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
    public function buildAndQuery(array $query, array $params = []): \PDOStatement
    {
        $sql = $this->build($query);

        return $this->query($sql, $params);
    }

    /**
     * @throws DbLayerException
     */
    public function query($sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute($params);

            return $stmt;
        } catch (\PDOException $e) {
//            if ($this->transactionLevel > 0) {
//                $this->pdo->rollBack();
//                --$this->transactionLevel;
//            }

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

    public function fetchColumn(\PDOStatement $statement): array|false
    {
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function numRows(\PDOStatement $statement): int
    {
        // TODO check if it works
        return $statement->rowCount();
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
        // TODO maybe one has to deal with closing cursor here
        return true;
//        if ($this->link_id) {
//            if (!is_bool($this->query_result) && $this->query_result) {
//                @mysqli_free_result($this->query_result);
//            }
//
//            return @mysqli_close($this->link_id);
//        }
//
//        return false;
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
    public function tableExists(string $table_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SHOW TABLES LIKE \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\'');
        return $this->numRows($result) > 0;
    }


    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $table_name, string $field_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SHOW COLUMNS FROM ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' LIKE \'' . $this->escape($field_name) . '\'');
        return $this->numRows($result) > 0;
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
    public function addField(string $table_name, string $field_name, string $field_type, bool $allow_null, $default_value = null, ?string $after_field = null, bool $no_prefix = false): void
    {
        if ($this->fieldExists($table_name, $field_name, $no_prefix)) {
            return;
        }

        $field_type = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $field_type);

        if ($default_value !== null && !\is_int($default_value) && !\is_float($default_value)) {
            $default_value = '\'' . $this->escape($default_value) . '\'';
        }

        $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' ADD ' . $field_name . ' ' . $field_type . ($allow_null ? ' ' : ' NOT NULL') . ($default_value !== null ? ' DEFAULT ' . $default_value : ' ') . ($after_field != null ? ' AFTER ' . $after_field : ''));
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

        if ($default_value !== null && !\is_int($default_value) && !\is_float($default_value)) {
            $default_value = '\'' . $this->escape($default_value) . '\'';
        }

        $this->query('ALTER TABLE ' . ($no_prefix ? '' : $this->prefix) . $table_name . ' MODIFY ' . $field_name . ' ' . $field_type . ($allow_null ? ' ' : ' NOT NULL') . ($default_value !== null ? ' DEFAULT ' . $default_value : ' ') . ($after_field != null ? ' AFTER ' . $after_field : ''));
    }


    /**
     * @throws DbLayerException
     */
    public function dropField(string $table_name, string $field_name, bool $no_prefix = false): void
    {
        if (!$this->fieldExists($table_name, $field_name, $no_prefix)) {
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
}
