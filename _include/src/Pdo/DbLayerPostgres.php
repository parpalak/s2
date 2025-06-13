<?php
/**
 * PostgreSQL database layer class.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
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
        return \count($result->fetchAssocAll()) > 0;
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
        return \count($result->fetchAssocAll()) > 0;
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
        return \count($result->fetchAssocAll()) > 0;
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
                // The SERIAL datatype is a special case where we don't need to say not null
                if ($fieldData[SchemaBuilder::COLUMN_PROPERTY_TYPE] !== SchemaBuilderInterface::TYPE_SERIAL) {
                    $query .= ' NOT NULL';
                }
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
            $query .= 'UNIQUE (' . implode(',', $keyFields) . '),' . "\n";
        }

        // We remove the last two characters (a newline and a comma) and add on the ending
        $query = substr($query, 0, -2) . "\n" . ')';

        $result = $this->query($query);
        $result->freeResult();

        // Add indexes
        foreach ($schemaBuilder->indexes as $indexName => $indexFields) {
            $this->addIndex($tableName, $indexName, $indexFields);
        }

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

        $this->query('ALTER TABLE ' . $this->prefix . $tableName . ' ALTER COLUMN ' . $fieldName . ' TYPE ' . $fieldType);

        if ($defaultValue !== null) {
            if (!\is_int($defaultValue) && !\is_float($defaultValue)) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
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

        return (bool)$result->result();
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

    protected static function convertType(string $type, ?int $length): string
    {
        return match ($type) {
            SchemaBuilderInterface::TYPE_SERIAL => 'SERIAL',
            SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER,
            SchemaBuilderInterface::TYPE_INTEGER => 'INTEGER',
            SchemaBuilderInterface::TYPE_FLOAT => 'REAL',
            SchemaBuilderInterface::TYPE_DOUBLE => 'DOUBLE PRECISION',
            SchemaBuilderInterface::TYPE_BOOLEAN => 'SMALLINT',
            SchemaBuilderInterface::TYPE_LONGTEXT,
            SchemaBuilderInterface::TYPE_TEXT => 'TEXT',
            SchemaBuilderInterface::TYPE_STRING => 'VARCHAR(' . $length . ')',
            default => $type
        };
    }
}
