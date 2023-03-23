<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Queue;

class QueuePublisher
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function publish(string $id, string $code, array $payload = []): void
    {
        if (\strlen($id) > 80) {
            throw new \DomainException('Id length must not exceed 80 characters');
        }
        if (\strlen($code) > 80) {
            throw new \DomainException('Id length must not exceed 80 characters');
        }

        $statement = $this->pdo->prepare('INSERT IGNORE INTO queue (id, code, payload) VALUES (:id, :code, :payload)');
        try {
            $statement->execute([
                'id'      => $id,
                'code'    => $code,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }
}
