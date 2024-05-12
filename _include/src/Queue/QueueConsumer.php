<?php
/**
 * @copyright 2023-2024 Roman Parpalak
 * @license MIT
 * @package S2
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
        private \PDO            $pdo,
        private string          $dbPrefix,
        private LoggerInterface $logger,
        QueueHandlerInterface   ...$handlers
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

        if ($driverName === 'sqlite') {
            $this->pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
        }

        $this->pdo->beginTransaction();

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
                    $this->logger->notice('No jobs was found due to locks in parallel process.', ['exception' => $e]);
                } else {
                    $this->logger->warning('Failed to fetch queue item: ' . $message, ['exception' => $e]);
                }
            }
            if (!$job) {
                $this->pdo->rollBack();
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

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->logger->warning('Unknown throwable occurred, do rollback: ' . $e->getMessage(), ['exception' => $e]);
            $this->pdo->rollBack();
        }

        return true;
    }
}
