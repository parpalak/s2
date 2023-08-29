<?php

namespace unit;

use Codeception\Test\Unit;
use Psr\Log\NullLogger;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerPostgres;
use S2\Cms\Pdo\DbLayerSqlite;
use S2\Cms\Pdo\PDO;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;
use S2\Rose\Storage\Exception\InvalidEnvironmentException;

class QueueTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    /**
     * @dataProvider connections
     */
    public function testQueue(\PDO $publisherPdo, \PDO $consumerPdo, DbLayer $publisherDbLayer)
    {
        $publisherDbLayer->dropTable('queue');
        $publisherDbLayer->createTable('queue', array(
            'FIELDS'      => array(
                'id'      => array(
                    'datatype'   => 'VARCHAR(80)',
                    'allow_null' => false,
                ),
                'code'    => array(
                    'datatype'   => 'VARCHAR(80)',
                    'allow_null' => false
                ),
                'payload' => array(
                    'datatype'   => 'TEXT',
                    'allow_null' => false
                ),
            ),
            'PRIMARY KEY' => array('id', 'code')
        ));

        $queuePublisher = new QueuePublisher($publisherPdo);
        $queuePublisher->publish('test_id', 'code', ['data']);

        // Test duplication
        $queuePublisher->publish('test_id', 'code', ['data']);

        $queueConsumer = new QueueConsumer($consumerPdo, new NullLogger());
        self::assertTrue($queueConsumer->runQueue(), 'Job was processed');
        self::assertFalse($queueConsumer->runQueue(), 'No more jobs');

        $queuePublisher->publish('test_id2', 'code', ['data']);

        // Some copy-paste from QueuePublisher::publish() to simulate a parallel run
        $consumerPdo->beginTransaction();
        $driverName = $consumerPdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql        = match ($driverName) {
            'mysql' => 'SELECT * FROM queue LIMIT 1 FOR UPDATE', // TODO figure out how to detect support for SKIP LOCKED to make a fallback
            'pgsql' => 'SELECT * FROM queue LIMIT 1 FOR UPDATE SKIP LOCKED',
            'sqlite' => 'SELECT * FROM queue LIMIT 1',
            default => throw new InvalidEnvironmentException(sprintf('Driver "%s" is not supported.', $driverName)),
        };
        $statement = $consumerPdo->query($sql);
        $job       = $statement->fetch(\PDO::FETCH_ASSOC);

        // Test no lock wait when the parallel transaction is running.
        $queuePublisher->publish('test_id2', 'code', ['data']);

        $consumerPdo->rollBack();
    }

    private function connections(): array
    {
        $db_name = 's2_test';

        return [
            [
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=$db_name;charset=utf8mb4", 'root', ''),
                new PDO("mysql:host=127.0.0.1;dbname=$db_name;charset=utf8mb4", 'root', ''),
                new DbLayer($pdo),
            ],
            [
                $pdo = new PDO("pgsql:host=127.0.0.1;dbname=$db_name", 'postgres', '12345'),
                new PDO("pgsql:host=127.0.0.1;dbname=$db_name", 'postgres', '12345'),
                new DbLayerPostgres($pdo),
            ],
            [
                $pdo = new PDO("sqlite:$db_name"),
                new PDO("sqlite:$db_name"),
                new DbLayerSqlite($pdo),
            ],
        ];
    }
}
