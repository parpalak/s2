<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

class SchemaBuilder implements SchemaBuilderInterface
{
    public const COLUMN_PROPERTY_TYPE     = 'type';
    public const COLUMN_PROPERTY_NULLABLE = 'nullable';
    public const COLUMN_PROPERTY_DEFAULT  = 'default';
    public const COLUMN_PROPERTY_LENGTH   = 'length';

    public const FK_PROPERTY_COLUMNS         = 'columns';
    public const FK_PROPERTY_FOREIGN_TABLE   = 'foreignTable';
    public const FK_PROPERTY_FOREIGN_COLUMNS = 'foreignColumns';
    public const FK_PROPERTY_ON_DELETE       = 'onDelete';
    public const FK_PROPERTY_ON_UPDATE       = 'onUpdate';

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $columns = [];

    /**
     * @var string[]
     */
    public array $primaryKey = [];

    /**
     * @var array<string, array{columns: string[]}>
     */
    public array $uniqueIndexes = [];

    /**
     * @var array<string, array{columns: string[]}>
     */
    public array $indexes = [];

    /**
     * @var array<string, array{columns: string[], foreignTable: string, foreignColumns: string[], onDelete: string, onUpdate: string}>
     */
    public array $foreignKeys = [];

    public function addIdColumn(string $name = 'id'): self
    {
        $this->addColumn($name, self::TYPE_SERIAL);
        $this->primaryKey = [$name];

        return $this;
    }

    public function addColumn(
        string               $name,
        string               $type,
        bool                 $nullable = false,
        string|int|bool|null $default = null,
        int                  $length = null,
    ): self {
        $this->columns[$name] = [
            self::COLUMN_PROPERTY_TYPE     => $type,
            self::COLUMN_PROPERTY_NULLABLE => $nullable,
            self::COLUMN_PROPERTY_DEFAULT  => $default,
            self::COLUMN_PROPERTY_LENGTH   => $length,
        ];
        return $this;
    }

    public function addString(string $name, int $length = 255, bool $nullable = false, ?string $default = ''): self
    {
        return $this->addColumn($name, self::TYPE_STRING, $nullable, $default, $length);
    }

    public function addText(string $name, bool $nullable = true): self
    {
        return $this->addColumn($name, self::TYPE_TEXT, $nullable);
    }

    public function addLongText(string $name, bool $nullable = true): self
    {
        return $this->addColumn($name, self::TYPE_LONGTEXT, $nullable);
    }

    public function addInteger(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = 0): self
    {
        return $this->addColumn($name, $unsigned ? self::TYPE_UNSIGNED_INTEGER : self::TYPE_INTEGER, $nullable, $default, null);
    }

    public function addBoolean(string $name, bool $nullable = false, bool $default = false): self
    {
        return $this->addColumn($name, self::TYPE_BOOLEAN, $nullable, $default);
    }

    public function setPrimaryKey(array $columns): self
    {
        $this->primaryKey = $columns;
        return $this;
    }

    public function addUniqueIndex(string $indexName, array $columns): self
    {
        $this->uniqueIndexes[$indexName] = $columns;
        return $this;
    }

    public function addIndex(string $indexName, array $columns): self
    {
        $this->indexes[$indexName] = $columns;
        return $this;
    }

    public function addForeignKey(
        string  $name,
        array   $columns,
        string  $foreignTable,
        array   $foreignColumns,
        ?string $onDelete = null,
        ?string $onUpdate = null,
    ): self {
        $this->foreignKeys[$name] = [
            self::FK_PROPERTY_COLUMNS         => $columns,
            self::FK_PROPERTY_FOREIGN_TABLE   => $foreignTable,
            self::FK_PROPERTY_FOREIGN_COLUMNS => $foreignColumns,
            self::FK_PROPERTY_ON_DELETE       => $onDelete,
            self::FK_PROPERTY_ON_UPDATE       => $onUpdate,
        ];
        return $this;
    }
}
