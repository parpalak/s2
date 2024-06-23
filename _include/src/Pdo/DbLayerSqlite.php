<?php
/**
 * SQLite database layer class.
 *
 * @copyright (C) 2011-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

class DbLayerSqlite extends DbLayer
{
    protected const DATATYPE_TRANSFORMATIONS = [
        '/^SERIAL$/'                                                         => 'INTEGER',
        '/^(TINY|SMALL|MEDIUM|BIG)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$/i' => 'INTEGER',
        '/^(TINY|MEDIUM|LONG)?TEXT$/i'                                       => 'TEXT'
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
        $sql    = 'SELECT sqlite_version()';
        $result = $this->query($sql);
        [$ver] = $this->fetchRow($result);
        $this->freeResult($result);

        return [
            'name'    => 'SQLite',
            'version' => $ver
        ];
    }

    private static function array_insert(&$input, $offset, $element, $key = null): void
    {
        // Determine the proper offset if we're using a string
        if (\is_string($offset)) {
            $offset = array_search($offset, array_keys($input), true);
            if ($offset === false) {
                throw new \InvalidArgumentException(sprintf('Unknown offset "%s".', $offset));
            }
        } elseif ($offset === null) {
            // Append
            $offset = \count($input);
        }

        if ($key === null) {
            $key = $offset;
        }

        // Out of bounds checks
        if ($offset > \count($input)) {
            $offset = \count($input);
        } elseif ($offset < 0) {
            $offset = 0;
        }

        $input = array_merge(\array_slice($input, 0, $offset), [$key => $element], \array_slice($input, $offset));
    }


    /**
     * @throws DbLayerException
     */
    public function tableExists(string $table_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM sqlite_master WHERE name = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\' AND type=\'table\'');
        $return = (bool)$this->fetchRow($result);
        $this->freeResult($result);
        return $return;
    }


    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $table_name, string $field_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SELECT sql FROM sqlite_master WHERE name = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\' AND type=\'table\'');
        $return = $this->result($result);
        $this->freeResult($result);
        if (!$return) {
            return false;
        }

        return (bool)preg_match('#[\r\n]' . preg_quote($field_name, '#') . ' #', $return);
    }


    /**
     * @throws DbLayerException
     */
    public function indexExists(string $table_name, string $index_name, bool $no_prefix = false): bool
    {
        $result = $this->query('SELECT 1 FROM sqlite_master WHERE tbl_name = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\' AND name = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_' . $this->escape($index_name) . '\' AND type=\'index\'');
        $return = (bool)$this->fetchRow($result);
        $this->freeResult($result);
        return $return;
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
    private function get_table_info($table_name, $no_prefix = false)
    {
        // Grab table info
        $result = $this->query('SELECT sql FROM sqlite_master WHERE tbl_name = \'' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '\' ORDER BY type DESC');

        $table            = array();
        $table['indices'] = array();
        $i                = 0;
        while ($cur_index = $this->fetchAssoc($result)) {
            if (empty($cur_index['sql'])) {
                continue;
            }

            if (!isset($table['sql'])) {
                $table['sql'] = $cur_index['sql'];
            } else {
                $table['indices'][] = $cur_index['sql'];
            }

            $i++;
        }
        $this->freeResult($result);

        if (!$i) {
            return $table;
        }

        // Work out the columns in the table currently
        $table_lines      = explode("\n", $table['sql']);
        $table['columns'] = array();
        foreach ($table_lines as $table_line) {
            $table_line = trim($table_line);
            if (str_starts_with($table_line, 'CREATE TABLE')) {
                continue;
            }

            if (str_starts_with($table_line, 'PRIMARY KEY')) {
                $table['primary_key'] = $table_line;
            } elseif (str_starts_with($table_line, 'UNIQUE')) {
                $table['unique'] = $table_line;
            } elseif (substr($table_line, 0, (int)strpos($table_line, ' ')) != '') {
                $table['columns'][substr($table_line, 0, strpos($table_line, ' '))] = trim(substr($table_line, strpos($table_line, ' ')));
            }
        }

        return $table;
    }


    /**
     * @throws DbLayerException
     */
    public function addField(string $table_name, string $field_name, string $field_type, bool $allow_null, $default_value = null, ?string $after_field = null, bool $no_prefix = false): void
    {
        if ($this->fieldExists($table_name, $field_name, $no_prefix)) {
            return;
        }

        $table = $this->get_table_info($table_name, $no_prefix);

        // Create temp table
        $now      = time();
        $tmptable = str_replace('CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . ' (', 'CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_t' . $now . ' (', $table['sql']);
        $result   = $this->query($tmptable);
        $this->freeResult($result);

        $result = $this->query('INSERT INTO ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_t' . $now . ' SELECT * FROM ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name));
        $this->freeResult($result);

        // Create new table sql
        $field_type = preg_replace(array_keys(self::DATATYPE_TRANSFORMATIONS), array_values(self::DATATYPE_TRANSFORMATIONS), $field_type);
        $query      = $field_type;
        if (!$allow_null) {
            $query .= ' NOT NULL';
        }
        if ($default_value === null || $default_value === '') {
            $default_value = '\'\'';
        }

        $query .= ' DEFAULT ' . $default_value;

        $old_columns = array_keys($table['columns']);
        self::array_insert($table['columns'], $after_field, $query . ',', $field_name);

        $new_table = 'CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . ' (';

        foreach ($table['columns'] as $cur_column => $column_details) {
            $new_table .= "\n" . $cur_column . ' ' . $column_details;
        }

        if (isset($table['unique'])) {
            $new_table .= "\n" . $table['unique'] . ',';
        }

        if (isset($table['primary_key'])) {
            $new_table .= "\n" . $table['primary_key'];
        }

        $new_table = trim($new_table, ',') . "\n" . ');';

        // Drop old table
        $this->dropTable($table_name, $no_prefix);

        // Create new table
        $result = $this->query($new_table);
        $this->freeResult($result);

        // Recreate indexes
        if (!empty($table['indices'])) {
            foreach ($table['indices'] as $cur_index) {
                $result = $this->query($cur_index);
                $this->freeResult($result);
            }
        }

        // Copy content back
        $result = $this->query('INSERT INTO ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . ' (' . implode(', ', $old_columns) . ') SELECT * FROM ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_t' . $now);
        $this->freeResult($result);

        // Drop temp table
        $this->dropTable($table_name . '_t' . $now, $no_prefix);
    }

    public function alterField(string $table_name, string $field_name, string $field_type, bool $allow_null, $default_value = null, ?string $after_field = null, bool $no_prefix = false): void
    {
        throw new \LogicException('Not implemented');
    }

    /**
     * @throws DbLayerException
     */
    public function dropField(string $table_name, string $field_name, bool $no_prefix = false): void
    {
        if (!$this->fieldExists($table_name, $field_name, $no_prefix)) {
            return;
        }

        $table = $this->get_table_info($table_name, $no_prefix);

        // Create temp table
        $now      = time();
        $tmptable = str_replace('CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . ' (', 'CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_t' . $now . ' (', $table['sql']);
        $result   = $this->query($tmptable);
        $this->freeResult($result);

        $result = $this->query('INSERT INTO ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_t' . $now . ' SELECT * FROM ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name));
        $this->freeResult($result);

        // Work out the columns we need to keep and the sql for the new table
        unset($table['columns'][$field_name]);
        $new_columns = array_keys($table['columns']);

        $new_table = 'CREATE TABLE ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . ' (';

        foreach ($table['columns'] as $cur_column => $column_details) {
            $new_table .= "\n" . $cur_column . ' ' . $column_details;
        }

        if (isset($table['unique'])) {
            $new_table .= "\n" . $table['unique'] . ',';
        }

        if (isset($table['primary_key'])) {
            $new_table .= "\n" . $table['primary_key'];
        }

        $new_table = trim($new_table, ',') . "\n" . ');';

        // Drop old table
        $this->dropTable($table_name, $no_prefix);

        // Create new table
        $result = $this->query($new_table);
        $this->freeResult($result);

        // Recreate indexes
        if (!empty($table['indices'])) {
            foreach ($table['indices'] as $cur_index) {
                $result = $this->query($cur_index);
                $this->freeResult($result);
            }
        }

        //Copy content back
        $result = $this->query('INSERT INTO ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . ' SELECT ' . implode(', ', $new_columns) . ' FROM ' . ($no_prefix ? '' : $this->prefix) . $this->escape($table_name) . '_t' . $now);
        $this->freeResult($result);

        // Drop temp table
        $this->dropTable($table_name . '_t' . $now, $no_prefix);
    }

    public function addIndex(string $tableName, string $indexName, array $indexFields, bool $unique = false, bool $noPrefix = false): void
    {
        if ($this->indexExists($tableName, $indexName, $noPrefix)) {
            return;
        }

        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;
        $this->query(
            'CREATE ' . ($unique ? 'UNIQUE ' : '')
            . 'INDEX ' . $tableNameWithPrefix . '_' . $indexName
            . ' ON ' . $tableNameWithPrefix . '(' . implode(',', $indexFields) . ')'
        );
    }

    public function dropIndex(string $tableName, string $indexName, bool $noPrefix = false): void
    {
        if (!$this->indexExists($tableName, $indexName, $noPrefix)) {
            return;
        }

        $tableNameWithPrefix = ($noPrefix ? '' : $this->prefix) . $tableName;
        $this->query('DROP INDEX ' . $tableNameWithPrefix . '_' . $indexName);
    }
}
