<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

class SqliteCreateTableQuery
{
    private array $columns = [];
    private array $unique = [];
    private array $foreignKeys = [];
    private ?string $primaryKey = null;
    private ?string $tableName = null;

    public function __construct(
        private readonly string $sql,
        private array           $indexes
    ) {
        $this->parseSql();
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function getUnique(): array
    {
        return $this->unique;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getColumnNames(): array
    {
        return array_keys($this->columns);
    }

    public function withNewField(string $fieldName, string $fieldType, bool $allowNull, $defaultValue = null, ?string $afterField = null): self
    {
        $instance = clone $this;
        $instance->addField($fieldName, $fieldType, $allowNull, $defaultValue, $afterField);

        return $instance;
    }

    public function withAlteredField(string $fieldName, string $fieldType, bool $allowNull, string|int|float|bool|null $defaultValue = null, ?string $afterField = null): self
    {
        $instance = clone $this;
        $instance->alterField($fieldName, $fieldType, $allowNull, $defaultValue, $afterField);

        return $instance;
    }

    public function withNewForeignKey(string $fkName, array $columns, string $referenceTable, array $referenceColumns, ?string $onDelete, ?string $onUpdate): self
    {
        $instance = clone $this;
        $instance->addForeignKey($fkName, $columns, $referenceTable, $referenceColumns, $onDelete, $onUpdate);

        return $instance;
    }

    public function withoutForeignKey(string $fkName): self
    {
        $instance = clone $this;
        unset($instance->foreignKeys[$fkName]);

        return $instance;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function withTableName(string $tableName): self
    {
        $instance            = clone $this;
        $instance->tableName = $tableName;

        return $instance;
    }

    public function __toString(): string
    {
        $newTable = 'CREATE TABLE ' . $this->tableName . ' (';

        foreach ($this->columns as $columnName => $columnDetails) {
            $newTable .= "\n{$columnName} {$columnDetails},";
        }

        if ($this->primaryKey) {
            $newTable .= "\n{$this->primaryKey},";
        }

        foreach ($this->unique as $uniqueKey) {
            $newTable .= "\n{$uniqueKey},";
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $newTable .= "\n{$foreignKey},";
        }

        return rtrim($newTable, ',') . "\n);";
    }

    private function addField(string $fieldName, string $fieldType, bool $allowNull, string|int|float|bool|null $defaultValue = null, ?string $afterField = null): void
    {
        $fieldDefinition = $this->getFieldDefinition($fieldType, $allowNull, $defaultValue);

        $this->columns = self::arrayInsert($this->columns, [$fieldName => $fieldDefinition], $afterField);
    }

    private function alterField(string $fieldName, string $fieldType, bool $allowNull, string|int|float|bool|null $defaultValue = null, ?string $afterField = null): void
    {
        $fieldDefinition = $this->getFieldDefinition($fieldType, $allowNull, $defaultValue);
        if ($afterField === null) {
            $this->columns[$fieldName] = $fieldDefinition;
        } else {
            unset($this->columns[$fieldName]);
            $this->columns = self::arrayInsert($this->columns, [$fieldName => $fieldDefinition], $afterField);
        }
    }

    private function addForeignKey(string $fkName, array $columns, string $referenceTable, array $referenceColumns, ?string $onDelete, ?string $onUpdate): void
    {
        $foreignKeySQL = 'CONSTRAINT ' . $fkName . ' FOREIGN KEY (' . implode(',', $columns) . ')' .
            ' REFERENCES ' . $referenceTable . ' (' . implode(',', $referenceColumns) . ')';

        if ($onDelete !== null) {
            $foreignKeySQL .= ' ON DELETE ' . $onDelete;
        }

        if ($onUpdate !== null) {
            $foreignKeySQL .= ' ON UPDATE ' . $onUpdate;
        }

        $this->foreignKeys[] = $foreignKeySQL;
    }

    private static function arrayInsert(array $input, array $replacement, ?string $afterKey): array
    {
        // Determine the proper offset if we're using a string
        if (\is_string($afterKey)) {
            $offset = array_search($afterKey, array_keys($input), true);
            if ($offset === false) {
                throw new \InvalidArgumentException(sprintf('Unknown offset "%s".', $afterKey));
            }
        } else {
            $offset = \count($input);
        }

        return array_merge(\array_slice($input, 0, $offset), $replacement, \array_slice($input, $offset));
    }

    private function getFieldDefinition(string $fieldType, bool $allowNull, string|int|float|bool|null $defaultValue): string
    {
        $fieldDefinition = $fieldType;
        if (!$allowNull) {
            $fieldDefinition .= ' NOT NULL';
        }

        if ($defaultValue !== null) {
            if (\is_bool($defaultValue)) {
                $defaultValue = $defaultValue ? '1' : '0';
            } elseif (\is_string($defaultValue)) {
                $defaultValue = '\'' . addslashes($defaultValue) . '\'';
            }
            $fieldDefinition .= ' DEFAULT ' . $defaultValue;
        }

        return $fieldDefinition;
    }

    private function parseSql(): void
    {
        $lines = preg_split("#[\n\r]#", $this->sql);

        foreach ($lines as $line) {
            $line = trim($line);
            $line = rtrim($line, ',');
            if (str_starts_with($line, 'CREATE TABLE ')) {
                // 'CREATE TABLE test_table ('
                $matchNum = preg_match('/CREATE TABLE "?(\w+)"? \(/', $line, $matches);
                if ($matchNum === 1) {
                    $this->tableName = $matches[1];
                } else {
                    throw new \RuntimeException('Parse error: ' . $line);
                }
                continue;
            }

            if (str_starts_with($line, 'PRIMARY KEY')) {
                $this->primaryKey = $line;
            } elseif (str_starts_with($line, 'UNIQUE')) {
                $this->unique[] = $line;
            } elseif (str_starts_with($line, 'CONSTRAINT') && str_contains($line, 'FOREIGN KEY')) {
                $matchNum = preg_match('/CONSTRAINT "?(\w+)"? FOREIGN KEY/', $line, $matches);
                if ($matchNum === 1) {
                    $constraintName = $matches[1];
                } else {
                    throw new \RuntimeException('Parse error: ' . $line);
                }
                $this->foreignKeys[$constraintName] = $line;
            } elseif (str_starts_with($line, 'CREATE INDEX') || str_starts_with($line, 'CREATE UNIQUE INDEX')) {
                $this->indexes[] = $line;
            } else {
                $columnName = substr($line, 0, (int)strpos($line, ' '));
                if ($columnName) {
                    $this->columns[$columnName] = trim(substr($line, strpos($line, ' ')));
                }
            }
        }
    }
}
