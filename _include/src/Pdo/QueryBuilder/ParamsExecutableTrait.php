<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\QueryResult;

trait ParamsExecutableTrait
{
    private readonly QueryExecutorInterface $queryExecutor;

    private array $paramValues = [];
    private array $paramTypes = [];

    /**
     * @throws DbLayerException
     */
    public function getSql(): string
    {
        return $this->compiler->getSql($this);
    }

    /**
     * @throws DbLayerException
     */
    public function execute(array $params = [], array $types = []): QueryResult
    {
        $pdoStatement = $this->queryExecutor->query(
            $this->getSql(),
            array_merge($this->paramValues, $params),
            array_merge($this->paramTypes, $types)
        );

        return new QueryResult($pdoStatement);
    }

    public function setParameter(string $name, mixed $value, ?int $type = null): self
    {
        $this->paramValues[$name] = $value;
        if ($type !== null) {
            $this->paramTypes[$name] = $type;
        }
        return $this;
    }
}
