<?php
/**
 * Forked to fix a bug with PDO::query()
 * @see https://github.com/filisko/pdo-plus for original code
 */

namespace S2\Core\Pdo;

use PDO as NativePdo;

class PDO extends NativePdo
{
    protected array $log = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($dsn, $username = null, $passwd = null, $options = null)
    {
        parent::__construct($dsn, $username, $passwd, $options);
        $this->setAttribute(self::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$this]]);
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        $start  = microtime(true);
        $result = parent::exec($statement);
        $this->addLog($statement, microtime(true) - $start);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = [])
    {
        $start  = microtime(true);

        // Here is a fix in this line.
        $result = parent::query(...\func_get_args());
        $this->addLog($statement, microtime(true) - $start);

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
}
