<?php
/**
 * @copyright 2023-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Queue;

use Psr\Log\LoggerInterface;

class QueueConsumer
{
    /**
     * @var QueueHandlerInterface[]
     */
    private array $handlers;

    public function __construct(
        private readonly \PDO            $pdo,
        private readonly string          $dbPrefix,
        private readonly LoggerInterface $logger,
        QueueHandlerInterface            ...$handlers
    ) {
        $this->handlers = $handlers;
    }

    /**
     * Fetches and processes a job from the queue.
     *
     * The queue is stored in the 'queue' table of database. Jobs are fetched and locked in a transaction.
     *
     * NOWAIT prevents parallel job processing. It can be dangerous in case of several heavy jobs
     * (PHP-FPM workers can be exhausted).
     *
     * @return bool
     */
    public function runQueue(): bool
    {
        $driverName = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql        = match ($driverName) {
            'mysql', 'pgsql' => 'SELECT * FROM ' . $this->dbPrefix . 'queue LIMIT 1 FOR UPDATE NOWAIT',
            'sqlite' => 'SELECT * FROM ' . $this->dbPrefix . 'queue LIMIT 1',
            default => throw new \RuntimeException(sprintf('Driver "%s" is not supported.', $driverName)),
        };

        $outerTransaction = $this->pdo->inTransaction();
        if ($driverName === 'sqlite') {
            $this->pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
        } else {
            if (!$outerTransaction) {
                $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
        }

        if (!$outerTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $job = null;
            try {
                $statement = $this->pdo->query($sql);
                $job       = $statement->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                $message = $e->getMessage();
                if (
                    ($driverName === 'mysql' && (str_contains($message, 'Lock wait timeout exceeded') || str_contains($message, 'NOWAIT is set')))
                    || ($driverName === 'pgsql' && (str_contains($message, 'Lock not available')))
                ) {
                    $this->logger->notice('No jobs were found due to locks in parallel process.', ['exception' => $e]);
                } else {
                    $this->logger->warning('Failed to fetch queue item: ' . $message, ['exception' => $e]);
                }
            }
            if (!$job) {
                if (!$outerTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
            $this->logger->notice('Found queue item', $job);

            try {
                foreach ($this->handlers as $handler) {
                    if ($handler->handle($job['id'], $job['code'], $payload)) {
                        $this->logger->notice('Queue item has been processed', $job);
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Throwable occurred while processing queue: ' . $e->getMessage(), ['exception' => $e]);
            }

            $statement = $this->pdo->prepare('DELETE FROM ' . $this->dbPrefix . 'queue WHERE id = :id AND code = :code');
            $statement->execute([
                'id'   => $job['id'],
                'code' => $job['code'],
            ]);

            if (!$outerTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Unknown throwable occurred, do rollback: ' . $e->getMessage(), ['exception' => $e]);
            if (!$outerTransaction) {
                $this->pdo->rollBack();
            }
        }

        return true;
    }
}
