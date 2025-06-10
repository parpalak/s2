<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

class UpdateBuilder
{
    use ParamsExecutableTrait;
    use JoinTrait;
    use WhereTrait;

    private ?string $table = null;
    private array $columnExpressions = [];

    public function __construct(
        private readonly UpdateCompilerInterface $compiler,
        private readonly QueryExecutorInterface  $queryExecutor,
    ) {
    }

    public function update(string $table): static
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
            throw new DbLayerException('No table to update has been specified.');
        }
        return $this->table;
    }

    public function set(string $column, string $expression): static
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
}
