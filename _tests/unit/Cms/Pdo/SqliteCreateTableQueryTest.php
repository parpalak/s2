<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace unit\Cms\Pdo;

use Codeception\Test\Unit;
use S2\Cms\Pdo\SqliteCreateTableQuery;

class SqliteCreateTableQueryTest extends Unit
{
    public function testParseSql(): void
    {
        $sql = "CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE(name),
            CONSTRAINT fk_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
        );";

        $query = new SqliteCreateTableQuery($sql, []);

        $this->assertEquals('PRIMARY KEY (id)', $query->getPrimaryKey());
        $this->assertEquals([
            'id'   => 'INTEGER PRIMARY KEY',
            'name' => 'TEXT NOT NULL'
        ], $query->getColumns());
        $this->assertEquals(['UNIQUE(name)'], $query->getUnique());
        $this->assertEquals([
            'fk_post' => 'CONSTRAINT fk_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE'
        ], $query->getForeignKeys());
    }

    public function testAddField(): void
    {
        $sql = "CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        );";

        $query = new SqliteCreateTableQuery($sql, []);
        $query = $query->withNewField('description', 'TEXT', true, null, 'name');

        $this->assertEquals([
            'id'          => 'INTEGER PRIMARY KEY',
            'name'        => 'TEXT NOT NULL',
            'description' => 'TEXT'
        ], $query->getColumns());

        $query = $query->withNewField('age', 'INTEGER', false, 0, 'id');

        $this->assertEquals([
            'id'          => 'INTEGER PRIMARY KEY',
            'age'         => 'INTEGER NOT NULL DEFAULT 0',
            'name'        => 'TEXT NOT NULL',
            'description' => 'TEXT'
        ], $query->getColumns());
    }

    public function testAlterField(): void
    {
        $sql = "CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT DEFAULT ''
        );";

        $query = new SqliteCreateTableQuery($sql, []);
        $query = $query->withAlteredField('name', 'TEXT', false, 'Some Name', 'description');

        $this->assertEquals([
            'id'          => 'INTEGER PRIMARY KEY',
            'description' => 'TEXT DEFAULT \'\'',
            'name'        => 'TEXT NOT NULL DEFAULT \'Some Name\'',
        ], $query->getColumns());

        $query = $query->withAlteredField('description', 'TEXT', true, 'test');
        $this->assertEquals([
            'id'          => 'INTEGER PRIMARY KEY',
            'description' => 'TEXT DEFAULT \'test\'',
            'name'        => 'TEXT NOT NULL DEFAULT \'Some Name\'',
        ], $query->getColumns());
    }

    public function testToString(): void
    {
        $sql = "CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        );";

        $query = new SqliteCreateTableQuery($sql, []);
        $query = $query->withNewField('description', 'TEXT', true);

        $expectedSql = "CREATE TABLE test_table (
id INTEGER PRIMARY KEY,
name TEXT NOT NULL,
description TEXT
);";

        $this->assertEquals(trim($expectedSql), trim($query->__toString()));
    }

    public function testAddIndex(): void
    {
        $sql = "CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        );";

        $query = new SqliteCreateTableQuery($sql, ['CREATE INDEX idx_name ON test_table(name)']);

        $this->assertEquals(['CREATE INDEX idx_name ON test_table(name)'], $query->getIndexes());
    }
}
