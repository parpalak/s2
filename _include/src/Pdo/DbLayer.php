<?php /** @noinspection SqlDialectInspection */
/**
 * A database abstract layer class.
 * Contains default implementation for MySQL database.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

use S2\Cms\Pdo\QueryBuilder\DeleteBuilder;
use S2\Cms\Pdo\QueryBuilder\DeleteCommonCompiler;
use S2\Cms\Pdo\QueryBuilder\InsertBuilder;
use S2\Cms\Pdo\QueryBuilder\InsertMysqlCompiler;
use S2\Cms\Pdo\QueryBuilder\SelectBuilder;
use S2\Cms\Pdo\QueryBuilder\SelectCommonCompiler;
use S2\Cms\Pdo\QueryBuilder\UnionAll;
use S2\Cms\Pdo\QueryBuilder\UpdateBuilder;
use S2\Cms\Pdo\QueryBuilder\UpdateCommonCompiler;
use S2\Cms\Pdo\QueryBuilder\UpsertBuilder;
use S2\Cms\Pdo\QueryBuilder\UpsertMysqlCompiler;

class DbLayer implements QueryBuilder\QueryExecutorInterface
{
    protected int $transactionLevel = 0;

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

    /**
     * @throws DbLayerException
     */
    public function query($sql, array $params = [], array $types = []): QueryResult
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

            return new QueryResult($stmt);
        } catch (\PDOException $e) {
            if ($this->transactionLevel > 0) {
                try {
                    $this->pdo->rollBack();
                } catch (\PDOException $e) {
                    throw new DbLayerException('An exception occured on rollback: ' . $e->getMessage(), 0, $sql, $e->getPrevious());
                }
                --$this->transactionLevel;
            }

            throw new DbLayerException(
                \sprintf("%s. Failed query: %s. Error code: %s.", $e->getMessage(), $sql, $e->getCode()),
                $e->errorInfo[1] ?? 0,
                $sql,
                $e
            );
        }
    }

    public function insertId(): false|string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * @throws DbLayerException
     */
    public function getVersion(): array
    {
        $result = $this->select('VERSION()')->execute();

        return [
            'name'    => 'MySQL',
            'version' => $result->result(),
        ];
    }

    /**
     * @throws DbLayerException
     */
    public function tableExists(string $tableName): bool
    {
        $result = $this->query('SHOW TABLES LIKE :name', [
            'name' => $this->prefix . $tableName
        ]);
        return \count($result->fetchAssocAll()) > 0;
    }


    /**
     * @throws DbLayerException
     */
    public function fieldExists(string $tableName, string $fieldName): bool
    {
        $result = $this->query('SHOW COLUMNS FROM `' . $this->prefix . $tableName . '` LIKE :column', [
            'column' => $fieldName,
        ]);

        return \count($result->fetchAssocAll()) > 0;
    }


    /**
     * @throws DbLayerException
     */
    public function indexExists(string $tableName, string $indexName): bool
    {
        $result = $this->query('SHOW INDEX FROM ' . $this->prefix . $tableName);
        while ($currentIndex = $result->fetchAssoc()) {
            if (strtolower($currentIndex['Key_name']) === strtolower($this->prefix . $tableName . '_' . $indexName)) {
                return true;
            }
        }

        return false;
    }


    /**
     * @throws DbLayerException
     */
    public function createTable(string $tableName, callable $tableDefinition): void
    {
        if ($this->tableExists($tableName)) {
            return;
        }

        $schemaBuilder = new SchemaBuilder();
        $tableDefinition($schemaBuilder);

        $query = 'CREATE TABLE ' . $this->prefix . $tableName . " (\n";

        // Go through every schema element and add it to the query
        foreach ($schemaBuilder->columns as $fieldName => $fieldData) {
            $type = static::convertType($fieldData[SchemaBuilder::COLUMN_PROPERTY_TYPE], $fieldData[SchemaBuilder::COLUMN_PROPERTY_LENGTH]);

            $query .= $fieldName . ' ' . $type;

            if (!$fieldData[SchemaBuilder::COLUMN_PROPERTY_NULLABLE]) {
                $query .= ' NOT NULL';
            } elseif (!isset($fieldData[SchemaBuilder::COLUMN_PROPERTY_DEFAULT])) {
                $query .= ' DEFAULT NULL';
            }

            if (isset($fieldData[SchemaBuilder::COLUMN_PROPERTY_DEFAULT])) {
                $defaultValue = self::convertDefaultValue($fieldData[SchemaBuilder::COLUMN_PROPERTY_DEFAULT], $fieldData[SchemaBuilder::COLUMN_PROPERTY_TYPE]);
                if (\is_string($defaultValue)) {
                    $defaultValue = $this->pdo->quote($defaultValue);
                }
                $query .= ' DEFAULT ' . $defaultValue;
            }

            $query .= ",\n";
        }

        // If we have a primary key, add it
        if (\count($schemaBuilder->primaryKey) > 0) {
            $query .= 'PRIMARY KEY (' . implode(',', $schemaBuilder->primaryKey) . '),' . "\n";
        }

        // Add unique keys
        foreach ($schemaBuilder->uniqueIndexes as $keyName => $keyFields) {
            $query .= 'UNIQUE KEY ' . $this->prefix . $tableName . '_' . $keyName . '(' . implode(',', $keyFields) . '),' . "\n";
        }

        // Add indexes
        foreach ($schemaBuilder->indexes as $index_name => $index_fields) {
            $query .= 'KEY ' . $this->prefix . $tableName . '_' . $index_name . '(' . implode(',', $index_fields) . '),' . "\n";
        }

        // We remove the last two characters (a newline and a comma) and add on the ending
        $query = substr($query, 0, -2) . "\n" . ') ENGINE = InnoDB CHARACTER SET utf8mb4';

        $this->query($query);

        // Add foreign keys
        foreach ($schemaBuilder->foreignKeys as $keyName => $foreignKey) {
            $this->addForeignKey(
                $tableName,
                $keyName,
                $foreignKey[SchemaBuilder::FK_PROPERTY_COLUMNS],
                $foreignKey[SchemaBuilder::FK_PROPERTY_FOREIGN_TABLE],
                $foreignKey[SchemaBuilder::FK_PROPERTY_FOREIGN_COLUMNS],
                $foreignKey[SchemaBuilder::FK_PROPERTY_ON_DELETE] ?? null,
                $foreignKey[SchemaBuilder::FK_PROPERTY_ON_UPDATE] ?? null,
            );
        }
    }

    /**
     * @throws DbLayerException
     */
    public function dropTable(string $tableName): void
    {
        if (!$this->tableExists($tableName)) {
            return;
        }

        $this->query('DROP TABLE ' . $this->prefix . $tableName);
    }


    /**
     * @throws DbLayerException
     */
    public function addField(
        string                $tableName,
        string                $fieldName,
        string                $fieldType,
        ?int                  $fieldLength,
        bool                  $allowNull,
        string|int|float|null $defaultValue = null,
        ?string               $afterField = null
    ): void {
        if ($this->fieldExists($tableName, $fieldName)) {
            return;
        }

        $fieldType = self::convertType($fieldType, $fieldLength);

        $this->query(
            \sprintf("ALTER TABLE %s ADD %s %s%s%s%s",
                $this->prefix . $tableName,
                $fieldName,
                $fieldType,
                $allowNull ? '' : ' NOT NULL',
                $defaultValue !== null ? ' DEFAULT :default' : '',
                $afterField !== null ? ' AFTER ' . $afterField : ''
            ),
            $defaultValue !== null ? ['default' => $defaultValue] : []
        );
    }

    /**
     * @throws DbLayerException
     */
    public function renameField(string $tableName, string $oldFieldName, string $newFieldName): void
    {
        $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' RENAME COLUMN ' . $oldFieldName . ' TO ' . $newFieldName);
    }

    /**
     * @throws DbLayerException
     */
    public function alterField(
        string                $tableName,
        string                $fieldName,
        string                $fieldType,
        ?int                  $fieldLength,
        bool                  $allowNull,
        string|int|float|null $defaultValue = null,
        ?string               $afterField = null
    ): void {
        if (!$this->fieldExists($tableName, $fieldName)) {
            return;
        }

        $fieldType = self::convertType($fieldType, $fieldLength);

        $this->query(
            \sprintf("ALTER TABLE %s MODIFY %s %s%s%s%s",
                $this->prefix . $tableName,
                $fieldName,
                $fieldType,
                $allowNull ? '' : ' NOT NULL',
                $defaultValue !== null ? ' DEFAULT :default' : '',
                $afterField !== null ? ' AFTER ' . $afterField : ''
            ),
            $defaultValue !== null ? ['default' => $defaultValue] : []
        );
    }


    /**
     * @throws DbLayerException
     */
    public function dropField(string $tableName, string $fieldName): void
    {
        if (!$this->tableExists($tableName) || !$this->fieldExists($tableName, $fieldName)) {
            return;
        }

        $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' DROP ' . $fieldName);
    }


    /**
     * @throws DbLayerException
     */
    public function addIndex(string $tableName, string $indexName, array $indexFields, bool $unique = false): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $this->prefix . $tableName . '_' . $indexName . ' (' . implode(',', $indexFields) . ')');
    }


    /**
     * @throws DbLayerException
     */
    public function dropIndex(string $tableName, string $indexName): void
    {
        if (!$this->indexExists($tableName, $indexName)) {
            return;
        }

        $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' DROP INDEX ' . $this->prefix . $tableName . '_' . $indexName);
    }

    /**
     * @throws DbLayerException
     */
    public function foreignKeyExists(string $tableName, string $fkName): bool
    {
        $tableNameWithPrefix = $this->prefix . $tableName;

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

        return (bool)$result->result();
    }

    /**
     * @throws DbLayerException
     */
    public function addForeignKey(string $tableName, string $fkName, array $columns, string $referenceTable, array $referenceColumns, ?string $onDelete = null, ?string $onUpdate = null): void
    {
        if ($this->foreignKeyExists($tableName, $fkName)) {
            return;
        }

        $tableNameWithPrefix = $this->prefix . $tableName;

        $query = 'ALTER TABLE ' . $tableNameWithPrefix . ' ADD CONSTRAINT ' . $tableNameWithPrefix . '_' . $fkName .
            ' FOREIGN KEY (' . implode(',', $columns) . ')' .
            ' REFERENCES ' . $this->prefix . $referenceTable . ' (' . implode(',', $referenceColumns) . ')';

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
    public function dropForeignKey(string $tableName, string $fkName): void
    {
        if (!$this->foreignKeyExists($tableName, $fkName)) {
            return;
        }

        $tableNameWithPrefix = $this->prefix . $tableName;

        $query = 'ALTER TABLE ' . $tableNameWithPrefix . ' DROP FOREIGN KEY ' . $tableNameWithPrefix . '_' . $fkName;

        $this->query($query);
    }

    public function select(string ...$expressions): SelectBuilder
    {
        return (new SelectBuilder(new SelectCommonCompiler($this->prefix), $this))->select(...$expressions);
    }

    public function withRecursive(string $name, UnionAll|SelectBuilder $param): SelectBuilder
    {
        return (new SelectBuilder(new SelectCommonCompiler($this->prefix), $this))->withRecursive($name, $param);
    }

    public function update(string $table): UpdateBuilder
    {
        return (new UpdateBuilder(new UpdateCommonCompiler($this->prefix), $this))->update($table);
    }

    public function insert(string $table): InsertBuilder
    {
        return (new InsertBuilder(new InsertMysqlCompiler($this->prefix), $this))->insert($table);
    }

    public function delete(string $table): DeleteBuilder
    {
        return (new DeleteBuilder(new DeleteCommonCompiler($this->prefix), $this))->delete($table);
    }

    public function upsert(string $table): UpsertBuilder
    {
        return (new UpsertBuilder(new UpsertMysqlCompiler($this->prefix), $this))->upsert($table);
    }

    protected static function convertType(string $type, ?int $length): string
    {
        return match ($type) {
            SchemaBuilderInterface::TYPE_SERIAL => 'INT(10) UNSIGNED AUTO_INCREMENT',
            SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER => 'INT(10) UNSIGNED',
            SchemaBuilderInterface::TYPE_INTEGER => 'INT(11)',
            SchemaBuilderInterface::TYPE_BOOLEAN => 'TINYINT(1)',
            SchemaBuilderInterface::TYPE_LONGTEXT => 'LONGTEXT',
            SchemaBuilderInterface::TYPE_TEXT => 'TEXT',
            SchemaBuilderInterface::TYPE_STRING => 'VARCHAR(' . $length . ')',
            default => $type
        };
    }

    protected static function convertDefaultValue(string|int|bool $value, string $type): string|int
    {
        return match ($type) {
            SchemaBuilderInterface::TYPE_SERIAL => throw new \InvalidArgumentException('SERIAL type cannot have a default value'),
            SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER,
            SchemaBuilderInterface::TYPE_BOOLEAN,
            SchemaBuilderInterface::TYPE_INTEGER => (int)$value,
            default => (string)$value
        };
    }
}
