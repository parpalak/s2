<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\DbLayerSqlite;

/**
 * @group db
 */
class DbLayerCest
{
    private ?\Pdo $pdo;
    private ?DbLayer $dbLayer;

    public function _before(\IntegrationTester $I)
    {
        $this->pdo     = $I->grabService(\PDO::class);
        $this->dbLayer = $I->grabService(DbLayer::class);
    }

    /**
     * @throws DbLayerException
     */
    public function testTableExists(\IntegrationTester $I): void
    {
        $I->assertTrue($this->dbLayer->tableExists('config'));
        $I->assertFalse($this->dbLayer->tableExists('not_a_config'));
    }

    /**
     * @throws DbLayerException
     */
    public function testFieldExists(\IntegrationTester $I): void
    {
        $I->assertTrue($this->dbLayer->fieldExists('config', 'name'));
        $I->assertFalse($this->dbLayer->fieldExists('config', 'not_a_field'));
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
    public function testInsertOrUpdate(\IntegrationTester $I): void
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '*',
            'FROM'   => 'config',
            'WHERE'  => 'name = :name',
        ], [
            'name' => 'S2_FAVORITE_URL',
        ]);
        $data   = $this->dbLayer->fetchAssocAll($result);
        $I->assertCount(1, $data);
        $I->assertEquals(['name' => 'S2_FAVORITE_URL', 'value' => 'favorite'], $data[0]);

        $this->dbLayer->buildAndQuery([
            'UPSERT' => 'name, value',
            'INTO'   => 'config',
            'UNIQUE' => 'name',
            'VALUES' => ':name, :value',
        ], [
            'name'  => 'S2_FAVORITE_URL',
            'value' => 'favorite2',
        ]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '*',
            'FROM'   => 'config',
            'WHERE'  => 'name = :name',
        ], [
            'name' => 'S2_FAVORITE_URL',
        ]);
        $data   = $this->dbLayer->fetchAssocAll($result);
        $I->assertCount(1, $data);
        $I->assertEquals(['name' => 'S2_FAVORITE_URL', 'value' => 'favorite2'], $data[0]);

        $this->dbLayer->buildAndQuery([
            'UPSERT' => 'name, value',
            'INTO'   => 'config',
            'UNIQUE' => 'name',
            'VALUES' => ':name, :value',
        ], [
            'name'  => 'S2_UNKNOWN',
            'value' => 'unknown',
        ]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '*',
            'FROM'   => 'config',
            'WHERE'  => 'name = :name',
        ], [
            'name' => 'S2_UNKNOWN',
        ]);
        $data   = $this->dbLayer->fetchAssocAll($result);
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

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '*',
            'FROM'   => 'config',
            'LIMIT'  => 1,
        ]);
        $data   = $this->dbLayer->fetchAssocAll($result);

        $I->assertCount(1, $data);
        $I->assertEquals(['name', 'value'], array_keys($data[0]));

        $this->dbLayer->addField('config', 'test_field', 'INT(10) UNSIGNED', true);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'test_field',
            'FROM'   => 'config',
            'LIMIT'  => 1,
        ]);
        $data   = $this->dbLayer->fetchAssoc($result);
        $this->dbLayer->freeResult($result); // There are table locks in SQLite without this
        $I->assertEquals(['test_field' => null], $data);

        $this->dbLayer->renameField('config', 'test_field', 'test_field2');

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'test_field2',
            'FROM'   => 'config',
            'LIMIT'  => 1,
        ]);
        $data   = $this->dbLayer->fetchAssoc($result);
        $this->dbLayer->freeResult($result); // There are table locks in SQLite without this
        $I->assertEquals(['test_field2' => null], $data);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'name, value, test_field2',
            'INTO'   => 'config',
            'VALUES' => ':name, :value, :test_field2',
        ], [
            'name'        => 'test_name',
            'value'       => 'test_value',
            'test_field2' => 123,
        ]);
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'test_field2',
            'FROM'   => 'config',
            'WHERE'  => 'test_field2 = 123',
        ]);
        $data   = $this->dbLayer->result($result);
        $this->dbLayer->freeResult($result); // There are table locks in SQLite without this
        $I->assertEquals(123, $data);

        if (!$this->dbLayer instanceof DbLayerSqlite) {
            $e = null;
            try {
                $this->dbLayer->alterField('config', 'test_field2', 'VARCHAR(255)', false, 'default_test');
            } catch (DbLayerException $e) {
            }
            $I->assertInstanceOf(DbLayerException::class, $e);
        }

        $this->dbLayer->buildAndQuery([
            'UPDATE' => 'config',
            'SET'    => 'test_field2 = 0',
            'WHERE'  => 'test_field2 IS NULL',
        ]);
        $this->dbLayer->alterField('config', 'test_field2', 'VARCHAR(255)', false, 'default_test');

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'test_field2',
            'FROM'   => 'config',
            'WHERE'  => 'test_field2 = \'123\'',
        ]);
        $data   = $this->dbLayer->result($result);
        $I->assertEquals('123', $data);

        $this->dbLayer->dropField('config', 'test_field2');

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '*',
            'FROM'   => 'config',
            'LIMIT'  => 1,
        ]);
        $data   = $this->dbLayer->fetchAssoc($result);
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
        $this->dbLayer->alterField('config', 'value', 'VARCHAR(191)', false, '');

        $I->assertFalse($this->dbLayer->indexExists('config', 'value_idx'));
        $this->dbLayer->addIndex('config', 'value_idx', ['value']);

        // Cleaning from other tests
        $this->dbLayer->buildAndQuery([
            'DELETE' => 'config',
            'WHERE'  => 'value = :value',
        ], [
            'value' => 'test_value',
        ]);

        // Test values are not unique
        $this->dbLayer->buildAndQuery([
            'INSERT' => 'name, value',
            'INTO'   => 'config',
            'VALUES' => ':name, :value',
        ], [
            'name'  => 'test_name1',
            'value' => 'test_value',
        ]);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'name, value',
            'INTO'   => 'config',
            'VALUES' => ':name, :value',
        ], [
            'name'  => 'test_name2',
            'value' => 'test_value',
        ]);

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => '*',
            'FROM'   => 'config',
            'WHERE'  => 'value = :value',
        ], [
            'value' => 'test_value',
        ]);
        $data   = $this->dbLayer->fetchAssocAll($result);
        $I->assertCount(2, $data);

        $this->dbLayer->dropIndex('config', 'value_idx');
        $e = null;
        try {
            $this->dbLayer->addIndex('config', 'value_idx', ['value'], true);
        } catch (DbLayerException $e) {
        }
        $I->assertNotNull($e);

        $this->dbLayer->buildAndQuery([
            'DELETE' => 'config',
        ]);

        $this->dbLayer->addIndex('config', 'value_idx', ['value'], true);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'name, value',
            'INTO'   => 'config',
            'VALUES' => ':name, :value',
        ], [
            'name'  => 'test_name1',
            'value' => 'test_value',
        ]);

        $e = null;

        try {
            $this->dbLayer->buildAndQuery([
                'INSERT' => 'name, value',
                'INTO'   => 'config',
                'VALUES' => ':name, :value',
            ], [
                'name'  => 'test_name2',
                'value' => 'test_value',
            ]);
        } catch (DbLayerException $e) {
        }
        $I->assertNotNull($e);

        // Test that creating new field does not break indexes. Useful for SQLite where tables are recreated on field creation
        $I->assertTrue($this->dbLayer->indexExists('config', 'value_idx'));
        $this->dbLayer->addField('config', 'new_field', 'VARCHAR(255)', true);
        $I->assertTrue($this->dbLayer->indexExists('config', 'value_idx'));
        $this->dbLayer->dropField('config', 'new_field');
        $I->assertTrue($this->dbLayer->indexExists('config', 'value_idx'));

        $this->dbLayer->dropIndex('config', 'value_idx');

        $this->dbLayer->alterField('config', 'value', 'TEXT', false);

        // Start a transaction as if it was an external transaction from tests wrapper
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
}
