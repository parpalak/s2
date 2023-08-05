<?php
/**
 * Forked to fix a bug with PDO::query() and to make connections lazy.
 * @see https://github.com/filisko/pdo-plus for original code
 */

namespace S2\Cms\Pdo;

use PDO as NativePdo;

if (PHP_VERSION_ID < 80000) {
    class PDO extends NativePdo
    {
        protected array $log = [];
        private array $connectionParams;
        private bool $isConnected = false;

        /**
         * {@inheritdoc}
         */
        public function __construct($dsn, $username = null, $passwd = null, $options = null)
        {
            $this->connectionParams = [$dsn, $username, $passwd, $options];
        }

        /**
         * {@inheritdoc}
         */
        public function getAttribute($attribute)
        {
            $this->connectIfRequired();

            return parent::getAttribute($attribute);
        }

        /**
         * {@inheritdoc}
         */
        public function prepare($query, $options = [])
        {
            $this->connectIfRequired();

            return parent::prepare($query, $options);
        }

        /**
         * {@inheritdoc}
         * @return int|false
         */
        public function exec($statement)
        {
            $this->connectIfRequired();
            $start  = microtime(true);
            $result = parent::exec($statement);
            $this->addLog($statement, microtime(true) - $start);

            return $result;
        }

        /**
         * {@inheritdoc}
         * @return \PDOStatement|false
         */
        public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = [])
        {
            $this->connectIfRequired();
            $start = microtime(true);

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

        public function getQueryCount(): int
        {
            return \count($this->log);
        }

        private function connectIfRequired(): void
        {
            if (!$this->isConnected) {
                $start = microtime(true);

                parent::__construct(...$this->connectionParams);
                $this->setAttribute(self::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$this]]);
                $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->isConnected = true;

                $this->addLog('PDO connect', microtime(true) - $start);
            }
        }
    }
} else {
    class PDO extends NativePdo
    {
        protected array $log = [];
        private array $connectionParams;
        private bool $isConnected = false;

        /**
         * {@inheritdoc}
         */
        public function __construct($dsn, $username = null, $passwd = null, $options = null)
        {
            $this->connectionParams = [$dsn, $username, $passwd, $options];
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
        public function exec($statement): int|false
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

        private function connectIfRequired(): void
        {
            if (!$this->isConnected) {
                $start = microtime(true);

                parent::__construct(...$this->connectionParams);
                $this->setAttribute(self::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$this]]);
                $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->isConnected = true;

                $this->addLog('PDO connect', microtime(true) - $start);
            }
        }
    }
}
