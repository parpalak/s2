<?php
/**
 * PDO wrapper for lazy connections and logging.
 *
 * Forked from https://github.com/filisko/pdo-plus
 * 1. Fixed a bug with PDO::query()
 * 2. Made connections lazy
 * 3. Updated code to PHP 8.2
 *
 * @copyright 2023-2024 Roman Parpalak, based on code (c) 2021 Filis Futsarov
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

use PDO as NativePdo;
use PDOStatement as NativePdoStatement;

class PDOStatement extends NativePdoStatement
{
    protected NativePdo $pdo;

    /**
     * For binding simulations purposes.
     */
    protected array $bindings = [];

    protected function __construct(NativePdo $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam(
        int|string $param,
        mixed      &$var,
        int        $type = NativePdo::PARAM_STR,
        int        $maxLength = null,
        mixed      $driverOptions = null
    ): bool {
        $this->bindings[$param] = $var;
        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue(int|string $param, mixed $value, int $type = NativePdo::PARAM_STR): bool
    {
        $this->bindings[$param] = $value;
        return parent::bindValue($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $this->bindings = $params;
        }

        $statement = $this->addValuesToQuery($this->bindings, $this->queryString);

        $start  = microtime(true);
        $result = parent::execute($params);
        $this->pdo->addLog($statement, microtime(true) - $start);
        return $result;
    }

    private function addValuesToQuery(array $bindings, string $query): string
    {
        $indexed = array_is_list($bindings);

        foreach ($bindings as $param => $value) {
            $value = match (true) {
                $value === null => 'null',
                \is_int($value), \is_float($value) => (string)$value,
                is_numeric($value) => $value,
                default => $this->pdo->quote($value),
            };

            if ($indexed) {
                $query = preg_replace('/\?/', $value, $query, 1);
            } else {
                $query = str_replace(":$param", $value, $query);
            }
        }

        return $query;
    }
}
