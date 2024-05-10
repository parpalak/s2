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

class PDO extends NativePdo
{
    protected array $log = [];
    private array $connectionParams;
    private bool $isConnected = false;
    private array $connectionCallbacks = [];
    private ?\Exception $connectionException = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $dsn, ?string $username = null, ?string $passwd = null, ?array $options = null)
    {
        $this->connectionParams = [$dsn, $username, $passwd, $options];
    }

    public function addConnectionCallback(callable $callback): void
    {
        $this->connectionCallbacks[] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        $this->connectIfRequired();

        return parent::beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(int $attribute): mixed
    {
        $this->connectIfRequired();

        return parent::getAttribute($attribute);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->connectIfRequired();

        return parent::prepare($query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        $this->connectIfRequired();

        return parent::quote($string, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement): int|false
    {
        $this->connectIfRequired();

        $start  = microtime(true);
        $result = parent::exec($statement);
        $this->addLog($statement, microtime(true) - $start);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->connectIfRequired();

        $start = microtime(true);

        // Here is a fix in this line.
        $result = parent::query(...\func_get_args());
        $this->addLog($query, microtime(true) - $start);

        return $result;
    }

    /**
     * Add query to logged queries.
     */
    public function addLog(string $statement, float $time): void
    {
        $this->log[] = [
            'statement' => $statement,
            'time'      => $time
        ];
    }

    /**
     * Return logged queries.
     */
    public function cleanLogs(): array
    {
        $result    = $this->log;
        $this->log = [];

        return $result;
    }

    public function getQueryCount(): int
    {
        return \count($this->log);
    }

    /**
     * @throws \Exception
     */
    private function connectIfRequired(): void
    {
        if (!$this->isConnected) {
            if ($this->connectionException !== null) {
                throw $this->connectionException;
            }
            $start = microtime(true);

            try {
                parent::__construct(...$this->connectionParams);
            } catch (\Exception $e) {
                $this->connectionException = $e;
                throw $e;
            }
            $this->setAttribute(self::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$this]]);
            $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->isConnected = true;

            $this->addLog('PDO connect', microtime(true) - $start);

            foreach ($this->connectionCallbacks as $callback) {
                $callback();
            }
        }
    }
}
