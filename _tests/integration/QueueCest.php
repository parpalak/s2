<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace integration;

use IntegrationTester;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\PDO;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;
use S2\Rose\Storage\Exception\InvalidEnvironmentException;

class QueueCest
{
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testQueue(IntegrationTester $I)
    {
        /** @var DbLayer $publisherDbLayer */
        $publisherDbLayer = $I->grabService(DbLayer::class);
        $publisherDbLayer->buildAndQuery(['DELETE' => 'queue']);

        /** @var QueuePublisher $queuePublisher */
        $queuePublisher = $I->grabService(QueuePublisher::class);
        $queuePublisher->publish('test_id', 'code', ['data']);

        // Test duplication
        $queuePublisher->publish('test_id', 'code', ['data']);

        $consumerApplication = $I->createApplication();
        $queueConsumer       = $consumerApplication->container->get(QueueConsumer::class);
        $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        $I->assertFalse($queueConsumer->runQueue(), 'No more jobs');

        $queuePublisher->publish('test_id2', 'code', ['data']);

        // Some copy-paste from QueuePublisher::publish() to simulate a parallel run
        /** @var PDO $consumerPdo */
        $consumerPdo = $consumerApplication->container->get(\PDO::class);
        $consumerPdo->beginTransaction();

        $driverName = $consumerPdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql        = match ($driverName) {
            'mysql' => 'SELECT * FROM queue LIMIT 1 FOR UPDATE', // TODO figure out how to detect support for SKIP LOCKED to make a fallback
            'pgsql' => 'SELECT * FROM queue LIMIT 1 FOR UPDATE SKIP LOCKED',
            'sqlite' => 'SELECT * FROM queue LIMIT 1',
            default => throw new InvalidEnvironmentException(sprintf('Driver "%s" is not supported.', $driverName)),
        };
        $statement  = $consumerPdo->query($sql);
        $job        = $statement->fetch(\PDO::FETCH_ASSOC);

        $I->assertEquals('test_id2', $job['id']);
        $I->assertEquals('code', $job['code']);
        $I->assertEquals('["data"]', $job['payload']);

        // Test no lock wait when the parallel transaction is running.
        $queuePublisher->publish('test_id2', 'code', ['data']);

        $consumerPdo->rollBack();
    }
}
