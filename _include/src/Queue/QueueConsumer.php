<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Queue;

use Psr\Log\LoggerInterface;

class QueueConsumer
{
    private \PDO $pdo;

    /**
     * @var QueueHandlerInterface[]
     */
    private array $handlers;
    private LoggerInterface $logger;

    public function __construct(\PDO $pdo, LoggerInterface $logger, QueueHandlerInterface ...$handlers)
    {
        $this->pdo      = $pdo;
        $this->handlers = $handlers;
        $this->logger   = $logger;
    }

    /**
     * @throws \JsonException
     */
    public function runQueue(): bool
    {
        $this->pdo->exec('START TRANSACTION');

        $statement = $this->pdo->query('SELECT * FROM queue LIMIT 1 FOR UPDATE SKIP LOCKED');
        $job       = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!$job) {
            return false;
        }

        $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
        $this->logger->notice('Found queue item', $job);

        foreach ($this->handlers as $handler) {
            if ($handler->handle($job['id'], $job['code'], $payload)) {
                $this->logger->notice('Queue item has been processed', $job);
            }
        }

        $statement = $this->pdo->prepare('DELETE FROM queue WHERE id = :id AND code = :code');
        $statement->execute([
            'id'   => $job['id'],
            'code' => $job['code'],
        ]);

        return true;
    }
}
