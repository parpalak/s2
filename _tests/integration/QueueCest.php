<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use IntegrationTester;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\PDO;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;
use S2\Rose\Storage\Exception\InvalidEnvironmentException;

/**
 * @group queue
 */
class QueueCest
{
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DbLayerException
     */
    public function testQueue(IntegrationTester $I): void
    {
        /** @var \PDO $pdo */
        $pdo = $I->grabService(\PDO::class);
        // Tests are wrapped in a transaction, so we need to stop it
        // and to start a new one since we want to test commit and rollback.
        $pdo->rollBack();

        /** @var DbLayer $publisherDbLayer */
        $publisherDbLayer = $I->grabService(DbLayer::class);
        $publisherDbLayer->delete('queue');

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
         *
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

        if ($driverName !== 'sqlite') {
            /**
             * In Sqlite there is no FOR UPDATE and runQueue returns the same item.
             * Skip it in the test.
             */
            $consumerApplication2 = $I->createApplication();
            /** @var QueueConsumer $queueConsumer */
            $queueConsumer2 = $consumerApplication2->container->get(QueueConsumer::class);
            $I->assertFalse($queueConsumer2->runQueue(), 'No jobs in parallel process are available');
        }

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

        // Start a transaction as if it was an external transaction from tests wrapper
        $pdo->beginTransaction();
    }
}
