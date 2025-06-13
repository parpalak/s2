<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

class UpsertBuilder
{
    use ParamsExecutableTrait;

    private ?string $table = null;
    private array $columnExpressions = [];
    /**
     * @var string[]
     */
    private array $uniqueColumns = [];

    public function __construct(
        private readonly UpsertCompilerInterface $compiler,
        private readonly QueryExecutorInterface  $queryExecutor,
    ) {
    }

    public function upsert(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @throws DbLayerException
     */
    public function getTable(): string
    {
        if ($this->table === null) {
            throw new DbLayerException('No table to insert into has been specified.');
        }
        return $this->table;
    }

    /**
     * Specifies a column as a usual field, not a part of the unique key.
     * If there is a row that matches the unique key, the row will be updated.
     */
    public function setValue(string $column, string $expression): self
    {
        $this->columnExpressions[$column] = $expression;
        return $this;
    }

    /**
     * Specifies a column as a part of the unique key.
     */
    public function setKey(string $column, string $expression): self
    {
        $this->uniqueColumns[] = $column;
        return $this->setValue($column, $expression);
    }

    /**
     * @throws DbLayerException
     */
    public function getColumnExpressions(): array
    {
        if (\count($this->columnExpressions) === 0) {
            throw new DbLayerException('No fields to update have been specified.');
        }
        return $this->columnExpressions;
    }

    public function getUniqueColumns(): array
    {
        return $this->uniqueColumns;
    }
}
