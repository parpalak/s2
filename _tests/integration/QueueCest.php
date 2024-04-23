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

        // Test another write
        $queuePublisher->publish('test_id0', 'code', ['data']);

        $consumerApplication = $I->createApplication();
        /** @var QueueConsumer $queueConsumer */
        $queueConsumer = $consumerApplication->container->get(QueueConsumer::class);
        $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        $I->assertFalse($queueConsumer->runQueue(), 'No more jobs');

        // Test serial run
        $queuePublisher->publish('test_id', 'code', ['data']);
        $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        $I->assertFalse($queueConsumer->runQueue(), 'No more jobs');

        $queuePublisher->publish('test_id2', 'code', ['data']);

        /**
         * Some copy-paste to simulate a parallel run
         * @see QueueConsumer::runQueue()
         */
        /** @var PDO $consumerPdo */
        $consumerPdo = $consumerApplication->container->get(\PDO::class);
        $consumerPdo->beginTransaction();

        $driverName = $consumerPdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql        = match ($driverName) {
            'mysql', 'pgsql' => 'SELECT * FROM queue LIMIT 1 FOR UPDATE NOWAIT',
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
        $queuePublisher->publish('test_id3', 'code', ['data']);

        $consumerPdo->rollBack();
        $statement = null;

        $queuePublisher->publish('test_id4', 'code', ['data']);

        if ($driverName !== 'sqlite') {
            // Sqlite loses test_id3 being written during parallel consumer transaction.
            $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        }
        $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        $I->assertTrue($queueConsumer->runQueue(), 'Job was processed');
        $I->assertFalse($queueConsumer->runQueue(), 'No more jobs');
    }
}
