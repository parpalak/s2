<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

class InsertBuilder
{
    use ParamsExecutableTrait;

    private ?string $table = null;
    private array $columnExpressions = [];
    /**
     * @var string[]
     */
    private array $uniqueColumns = [];

    public function __construct(
        private readonly InsertCompilerInterface $compiler,
        private readonly QueryExecutorInterface  $queryExecutor,
    ) {
    }

    public function insert(string $table): static
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

    public function values(array $columnExpressions): self
    {
        $this->columnExpressions = $columnExpressions;
        return $this;
    }

    public function setValue(string $column, string $expression): self
    {
        $this->columnExpressions[$column] = $expression;
        return $this;
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

    public function onConflictDoNothing(string ...$uniqueColumns): self
    {
        $this->uniqueColumns = $uniqueColumns;
        return $this;
    }

    public function getUniqueColumnsForConflictDoNothing(): array
    {
        return $this->uniqueColumns;
    }
}
