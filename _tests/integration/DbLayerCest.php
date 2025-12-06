<?php /** @noinspection PhpUnused */
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\DbLayerSqlite;
use S2\Cms\Pdo\SchemaBuilderInterface;

/**
 * @group db
 */
class DbLayerCest
{
    private ?\Pdo $pdo;
    private ?DbLayer $dbLayer;
    private string $tableName = 'config_dl_test';

    /**
     * @throws DbLayerException
     */
    public function _before(\IntegrationTester $I): void
    {
        $this->pdo     = $I->grabService(\PDO::class);
        $this->dbLayer = $I->grabService(DbLayer::class);

        /**
         * DDL causes implicit commits in MySQL, so isolate copy creation outside the shared test transaction
         * @see \Helper\Integration::_before
         */
        $this->pdo->rollBack();
        $this->recreateConfigCopy();
        $this->pdo->beginTransaction();
    }

    public function _after(\IntegrationTester $I): void
    {
        try {
            $this->dbLayer?->dropTable($this->tableName);
        } catch (\Throwable) {
        }
    }

    /**
     * @throws DbLayerException
     */
    public function testTableExists(\IntegrationTester $I): void
    {
        $I->assertTrue($this->dbLayer->tableExists($this->tableName));
        $I->assertFalse($this->dbLayer->tableExists('not_a_config'));
    }

    /**
     * @throws DbLayerException
     */
    public function testFieldExists(\IntegrationTester $I): void
    {
        $I->assertTrue($this->dbLayer->fieldExists($this->tableName, 'name'));
        $I->assertFalse($this->dbLayer->fieldExists($this->tableName, 'not_a_field'));
    }

    /**
     * @throws DbLayerException
     */
    public function testIndexExists(\IntegrationTester $I): void
    {
        $I->assertTrue($this->dbLayer->indexExists('art_comments', 'sort_idx'));
        $I->assertFalse($this->dbLayer->indexExists('art_comments', 'not_an_index'));
    }

    /**
     * @throws DbLayerException
     */
    public function testInsertOnConflictDoNothing(\IntegrationTester $I): void
    {
        $data = $this->getAllConfigByName('S2_FAVORITE_URL');

        $I->assertCount(1, $data);
        $I->assertEquals(['name' => 'S2_FAVORITE_URL', 'value' => 'favorite'], $data[0]);

        // No rows with this name
        $data = $this->getAllConfigByName('TEST');
        $I->assertCount(0, $data);

        // Add a new row
        $this->dbLayer->insert($this->tableName)
            ->values(['name' => "'TEST'", 'value' => "'test'"])
            ->onConflictDoNothing('name')
            ->execute()
        ;

        // There should be one row
        $data = $this->getAllConfigByName('TEST');
        $I->assertCount(1, $data);

        // Add a new row that will be ignored
        $this->dbLayer->insert($this->tableName)
            ->values(['name' => "'TEST'", 'value' => "'test'"])
            ->onConflictDoNothing('name')
            ->execute()
        ;

        // There should be still one row
        $data = $this->getAllConfigByName('TEST');
        $I->assertCount(1, $data);
    }

    /**
     * @throws DbLayerException
     */
    public function testInsertOrUpdate(\IntegrationTester $I): void
    {
        $data = $this->getAllConfigByName('S2_FAVORITE_URL');
        $I->assertCount(1, $data);
        $I->assertEquals(['name' => 'S2_FAVORITE_URL', 'value' => 'favorite'], $data[0]);

        $this->dbLayer
            ->upsert($this->tableName)
            ->setKey('name', ':name')->setParameter('name', 'S2_FAVORITE_URL')
            ->setValue('value', ':value')->setParameter('value', 'favorite2')
            ->execute()
        ;

        $data = $this->getAllConfigByName('S2_FAVORITE_URL');
        $I->assertCount(1, $data);
        $I->assertEquals(['name' => 'S2_FAVORITE_URL', 'value' => 'favorite2'], $data[0]);

        $this->dbLayer->upsert($this->tableName)
            ->setKey('name', ':name')->setParameter('name', 'S2_UNKNOWN')
            ->setValue('value', ':value')->setParameter('value', 'unknown')
            ->execute()
        ;

        $data = $this->getAllConfigByName('S2_UNKNOWN');
        $I->assertCount(1, $data);
        $I->assertEquals(['name' => 'S2_UNKNOWN', 'value' => 'unknown'], $data[0]);
    }

    /**
     * @throws DbLayerException
     */
    public function testFieldManagement(\IntegrationTester $I): void
    {
        // Tests are wrapped in a transaction, so we need to stop it
        // and to start a new one since we want to test DDL, and it is not transactional in MySQL.
        $this->pdo->rollBack();

        $data = $this->dbLayer->select('*')
            ->from($this->tableName)
            ->limit(1)
            ->execute()->fetchAssocAll()
        ;

        $I->assertCount(1, $data);
        $I->assertEquals(['name', 'value'], array_keys($data[0]));

        $this->dbLayer->addField($this->tableName, 'test_field', SchemaBuilderInterface::TYPE_UNSIGNED_INTEGER, null, true);

        $result = $this->dbLayer->select('test_field')
            ->from($this->tableName)
            ->limit(1)
            ->execute()
        ;
        $data   = $result->fetchAssoc();
        $result->freeResult(); // There are table locks in SQLite without this
        $I->assertEquals(['test_field' => null], $data);

        $this->dbLayer->renameField($this->tableName, 'test_field', 'test_field2');

        $result = $this->dbLayer->select('test_field2')
            ->from($this->tableName)
            ->limit(1)
            ->execute()
        ;
        $data   = $result->fetchAssoc();
        $result->freeResult(); // There are table locks in SQLite without this
        $I->assertEquals(['test_field2' => null], $data);

        $this->dbLayer->insert($this->tableName)
            ->setValue('name', ':name')->setParameter('name', 'test_name')
            ->setValue('value', ':value')->setParameter('value', 'test_value')
            ->setValue('test_field2', ':test_field2')->setParameter('test_field2', 123)
            ->execute()
        ;

        $result = $this->dbLayer->select('test_field2')
            ->from($this->tableName)
            ->where('test_field2 = :val')->setParameter(':val', 123)
            ->limit(1)
            ->execute()
        ;
        $data   = $result->result();
        $result->freeResult(); // There are table locks in SQLite without this
        $I->assertEquals(123, $data);

        if (!$this->dbLayer instanceof DbLayerSqlite) {
            $e = null;
            try {
                $this->dbLayer->alterField($this->tableName, 'test_field2', SchemaBuilderInterface::TYPE_STRING, 255, false, 'default_test');
            } catch (DbLayerException $e) {
            }
            $I->assertInstanceOf(DbLayerException::class, $e);
        }

        $this->dbLayer->update($this->tableName)
            ->set('test_field2', '0')
            ->where('test_field2 IS NULL')
            ->execute()
        ;

        $this->dbLayer->alterField($this->tableName, 'test_field2', SchemaBuilderInterface::TYPE_STRING, 255, false, 'default_test');

        $result = $this->dbLayer->select('test_field2')
            ->from($this->tableName)
            ->where('test_field2 = :val')->setParameter(':val', '123')
            ->limit(1)
            ->execute()
        ;
        $data   = $result->result();
        $I->assertEquals('123', $data);

        $this->dbLayer->dropField($this->tableName, 'test_field2');

        $data = $this->dbLayer->select('*')
            ->from($this->tableName)
            ->limit(1)
            ->execute()->fetchAssoc()
        ;
        $I->assertEquals(['name', 'value'], array_keys($data));

        // Start a transaction as if it was an external transaction from tests wrapper
        $this->pdo->beginTransaction();
    }

    /**
     * @throws DbLayerException
     */
    public function testIndexManagement(\IntegrationTester $I): void
    {
        // Tests are wrapped in a transaction, so we need to stop it
        // and to start a new one since we want to test DDL, and it is not transactional in MySQL.
        $this->pdo->rollBack();

        // Otherwise MySQL gives error:
        // SQLSTATE[42000]: Syntax error or access violation: 1170 BLOB/TEXT column 'value' used in key specification without a key length.
        // Failed query: ALTER TABLE config ADD INDEX config_value_idx (value). Error code: 42000.
        $this->dbLayer->alterField($this->tableName, 'value', SchemaBuilderInterface::TYPE_STRING, 191, false, '');

        $I->assertFalse($this->dbLayer->indexExists($this->tableName, 'value_idx'));
        $this->dbLayer->addIndex($this->tableName, 'value_idx', ['value']);

        // Cleaning from other tests
        $this->dbLayer->delete($this->tableName)
            ->where('value = :value')->setParameter('value', 'test_value')
            ->execute()
        ;

        // Test values are not unique
        foreach (['test_name1', 'test_name2'] as $name) {
            $this->dbLayer->insert($this->tableName)
                ->setValue('name', ':name')->setParameter('name', $name)
                ->setValue('value', ':value')->setParameter('value', 'test_value')
                ->execute()
            ;
        }

        $data = $this->dbLayer->select('*')
            ->from($this->tableName)
            ->where('value = :value')->setParameter('value', 'test_value')
            ->execute()
            ->fetchAssocAll()
        ;
        $I->assertCount(2, $data);

        $this->dbLayer->dropIndex($this->tableName, 'value_idx');
        $e = null;
        try {
            $this->dbLayer->addIndex($this->tableName, 'value_idx', ['value'], true);
        } catch (DbLayerException $e) {
        }
        $I->assertNotNull($e);

        $this->dbLayer->delete($this->tableName)->execute();

        $this->dbLayer->addIndex($this->tableName, 'value_idx', ['value'], true);

        $this->dbLayer->insert($this->tableName)
            ->setValue('name', ':name')->setParameter('name', 'test_name1')
            ->setValue('value', ':value')->setParameter('value', 'test_value')
            ->execute()
        ;

        $e = null;

        try {
            $this->dbLayer->insert($this->tableName)
                ->setValue('name', ':name')->setParameter('name', 'test_name2')
                ->setValue('value', ':value')->setParameter('value', 'test_value')
                ->execute()
            ;
        } catch (DbLayerException $e) {
        }
        $I->assertNotNull($e);

        // Test that creating new field does not break indexes. Useful for SQLite where tables are recreated on field creation
        $I->assertTrue($this->dbLayer->indexExists($this->tableName, 'value_idx'));
        $this->dbLayer->addField($this->tableName, 'new_field', SchemaBuilderInterface::TYPE_STRING, 255, true);
        $I->assertTrue($this->dbLayer->indexExists($this->tableName, 'value_idx'));
        $this->dbLayer->dropField($this->tableName, 'new_field');
        $I->assertTrue($this->dbLayer->indexExists($this->tableName, 'value_idx'));

        $this->dbLayer->dropIndex($this->tableName, 'value_idx');

        $this->dbLayer->alterField($this->tableName, 'value', SchemaBuilderInterface::TYPE_TEXT, null, false);

        // Start a transaction as if it was an external transaction from tests wrapper
        $this->pdo->beginTransaction();
    }

    /**
     * @throws DbLayerException
     */
    public function testAddFieldWithDefaultValueFailsWithPlaceholder(\IntegrationTester $I): void
    {
//        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
//            $I->markTestSkipped('Specific for MySQL server-side prepares');
//        }

        // Tests are wrapped in a transaction, so we need to stop it
        // and to start a new one since we want to test DDL, and it is not transactional in MySQL.
        $this->pdo->rollBack();

        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $initialEmulate = $this->pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        }

        $this->dbLayer->dropField($this->tableName, 'with_str_default'); // in case it already exists
        $this->dbLayer->dropField($this->tableName, 'with_bool_default'); // in case it already exists

        try {
            $exception = null;
            try {
                $this->dbLayer->addField($this->tableName, 'with_str_default', SchemaBuilderInterface::TYPE_STRING, 10, false, 'foo');
                $this->dbLayer->addField($this->tableName, 'with_bool_default', SchemaBuilderInterface::TYPE_BOOLEAN, null, false, true);
            } catch (DbLayerException $exception) {
            }
            $I->assertNull($exception, 'addField should not fail on default value');

            $exception = null;
            try {
                $this->dbLayer->alterField($this->tableName, 'with_str_default', SchemaBuilderInterface::TYPE_STRING, 10, false, 'bar');
                $this->dbLayer->alterField($this->tableName, 'with_bool_default', SchemaBuilderInterface::TYPE_BOOLEAN, null, false, true);
            } catch (DbLayerException $exception) {
            }
            $I->assertNull($exception, 'alterField should not fail on default value');
        } finally {
            $this->dbLayer->dropField($this->tableName, 'with_str_default');
            $this->dbLayer->dropField($this->tableName, 'with_bool_default');
            $this->dbLayer->dropField($this->tableName, 'with_default');
            if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $initialEmulate);
            }
            $this->pdo->beginTransaction();
        }
    }

    /**
     * @throws DbLayerException
     */
    public function testCreateTableStringWithoutLength(\IntegrationTester $I): void
    {
        // Tests are wrapped in a transaction, so we need to stop it
        // and to start a new one since we want to test DDL, and it is not transactional in MySQL.
        $this->pdo->rollBack();

        $tableName = 'tmp_without_length';
        $this->dbLayer->dropTable($tableName);

        $exception = null;
        try {
            $this->dbLayer->createTable($tableName, static function (SchemaBuilderInterface $table) {
                $table->addColumn('name', SchemaBuilderInterface::TYPE_STRING, false, '', null);
            });
        } catch (DbLayerException $exception) {
        }

        $I->assertNull($exception, 'createTable with TYPE_STRING and null length should be supported');

        $this->dbLayer->dropTable($tableName);
        $this->pdo->beginTransaction();
    }

    /**
     * @throws DbLayerException
     */
    public function testForeignKeyManagement(\IntegrationTester $I): void
    {
        $I->assertTrue($this->dbLayer->foreignKeyExists('articles', 'fk_user'));
        $this->dbLayer->dropForeignKey('articles', 'fk_user');
        $I->assertFalse($this->dbLayer->foreignKeyExists('articles', 'fk_user'));
        $this->dbLayer->addForeignKey('articles', 'fk_user', ['user_id'], 'users', ['id'], 'SET NULL');
        $I->assertTrue($this->dbLayer->foreignKeyExists('articles', 'fk_user'));
    }

    /**
     * @throws DbLayerException
     */
    private function getAllConfigByName(string $name): array
    {
        return $this->dbLayer->select('*')
            ->from($this->tableName)
            ->where('name = :name')->setParameter('name', $name)
            ->execute()->fetchAssocAll()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function recreateConfigCopy(): void
    {
        try {
            $this->dbLayer->dropTable($this->tableName);
        } catch (DbLayerException) {
        }

        $this->dbLayer->createTable($this->tableName, function (SchemaBuilderInterface $table) {
            $table
                ->addString('name', 191)
                ->addText('value', nullable: false)
                ->setPrimaryKey(['name'])
            ;
        });

        $rows = $this->dbLayer->select('*')->from('config')->execute()->fetchAssocAll();
        foreach ($rows as $row) {
            $this->dbLayer->insert($this->tableName)
                ->setValue('name', ':name')->setParameter('name', $row['name'])
                ->setValue('value', ':value')->setParameter('value', $row['value'])
                ->execute()
            ;
        }
    }
}
