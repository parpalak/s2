<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use Codeception\Example;
use IntegrationTester;
use Psr\Log\NullLogger;
use S2\Cms\Model\MigrationManager;
use S2\Cms\Pdo\DbLayerSqlite;
use S2\Cms\Pdo\SchemaBuilderInterface;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;

class SpamDigestMigrationCest
{
    /**
     * @dataProvider blogProvider
     */
    public function testUpgradePreservesExistingJobsAndComments(IntegrationTester $I, Example $example): void
    {
        // Use a separate database so DDL cannot commit the suite's transaction.
        $pdo = new \PDO('sqlite::memory:');
        $db = new DbLayerSqlite($pdo, 'upgrade_');
        $db->createTable('queue', static function (SchemaBuilderInterface $table) {
            $table->addString('id', 80, default: null)->addString('code', 80, default: null)
                ->addText('payload', nullable: false)->setPrimaryKey(['id', 'code']);
        });
        $pdo->exec("INSERT INTO upgrade_queue VALUES ('old_job', 'old_code', '[]')");
        $db->createTable('art_comments', static function (SchemaBuilderInterface $table) {
            $table->addIdColumn()->addText('text', nullable: false);
        });
        $pdo->exec("INSERT INTO upgrade_art_comments VALUES (1, 'Existing comment')");
        $pdo->exec('CREATE TABLE upgrade_config (name VARCHAR(80) PRIMARY KEY, value TEXT)');
        $pdo->exec("INSERT INTO upgrade_config VALUES ('S2_DB_REVISION', '24')");
        if ($example['blog']) {
            $db->createTable('s2_blog_comments', static function (SchemaBuilderInterface $table) {
                $table->addIdColumn()->addText('text', nullable: false);
            });
            $pdo->exec("INSERT INTO upgrade_s2_blog_comments VALUES (1, 'Existing blog comment')");
        }

        (new MigrationManager($db, 'sqlite'))->migrate(24, 25);

        $I->assertEquals('25', $db->select('value')->from('config')->execute()->result());
        $I->assertSame(['id' => 1, 'text' => 'Existing comment', 'spam_check_status' => ''], $db->select('*')->from('art_comments')->execute()->fetchAssoc());
        if ($example['blog']) {
            $I->assertSame('', $db->select('spam_check_status')->from('s2_blog_comments')->execute()->result());
        }
        $I->assertEquals(0, $db->select('available_at')->from('queue')->execute()->result());
        $I->assertTrue($db->tableExists('spam_notifications'));

        $publisher = new QueuePublisher($pdo, 'upgrade_');
        $publisher->publish('future_job', 'future_code', [], time() + 3600);
        $consumer = new QueueConsumer($pdo, 'upgrade_', new NullLogger());
        $I->assertTrue($consumer->runQueue());
        $I->assertFalse($consumer->runQueue());
        $I->assertSame(['future_job'], $db->select('id')->from('queue')->execute()->fetchColumn());
    }

    protected function blogProvider(): array
    {
        return [['blog' => false], ['blog' => true]];
    }
}
