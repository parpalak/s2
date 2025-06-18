<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

class SelectBuilder
{
    use ParamsExecutableTrait;
    use JoinTrait;
    use WhereTrait;

    /**
     * @var string[]
     */
    private array $selectExpressions = [];
    private ?string $table = null;

    /**
     * @var string[]
     */
    private array $groupBy = [];

    /**
     * @var string[]
     */
    private array $orderBy = [];

    /**
     * @var string[]
     */
    private array $having = [];

    private ?int $limit = null;
    private ?int $offset = null;

    /**
     * @var (SelectBuilder|UnionAll)[]
     */
    private array $withRecursive = [];

    public function __construct(
        private readonly SelectCompilerInterface $compiler,
        private readonly QueryExecutorInterface  $queryExecutor,
    ) {
    }

    public function select(string ...$expressions): self
    {
        $this->selectExpressions = $expressions;
        return $this;
    }

    public function addSelect(string ...$expressions): self
    {
        $this->selectExpressions = array_merge($this->selectExpressions, $expressions);
        return $this;
    }

    /**
     * @throws DbLayerException
     */
    public function getSelect(): array
    {
        if (\count($this->selectExpressions) === 0) {
            throw new DbLayerException('No expressions to select.');
        }
        return $this->selectExpressions;
    }

    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function groupBy(string ...$expressions): self
    {
        $this->groupBy = [];
        return $this->addGroupBy(...$expressions);
    }

    public function addGroupBy(string ...$expressions): self
    {
        $this->groupBy = array_merge($this->groupBy, $expressions);
        return $this;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function having(string ...$conditions): self
    {
        $this->having = [];
        return $this->andHaving(...$conditions);
    }

    public function andHaving(string ...$conditions): self
    {
        $this->having = array_merge($this->having, $conditions);
        return $this;
    }

    public function getHaving(): array
    {
        return $this->having;
    }

    public function orderBy(string ...$expressions): self
    {
        $this->orderBy = [];
        return $this->addOrderBy(...$expressions);
    }

    public function addOrderBy(string ...$expressions): self
    {
        $this->orderBy = array_merge($this->orderBy, $expressions);
        return $this;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function withRecursive(string $name, SelectBuilder|UnionAll $param): self
    {
        $this->withRecursive[$name] = $param;
        return $this;
    }

    /**
     * @return (SelectBuilder|UnionAll)[]
     */
    public function getWithRecursive(): array
    {
        return $this->withRecursive;
    }
}
