<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

/**
 * @group article
 */
class ArticleProviderCest
{
    private ?ArticleProvider $articleProvider;
    private ?DbLayer $dbLayer;

    public function _before(\IntegrationTester $I)
    {
        $this->articleProvider = $I->grabService(ArticleProvider::class);
        $this->dbLayer         = $I->grabService(DbLayer::class);
    }

    /**
     * @throws DbLayerException
     */
    public function testPath(\IntegrationTester $I): void
    {
        $id0 = 0;
        // Main page
        $id1 = $this->getChildId($id0);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'parent_id, title, create_time, modify_time, published, template, url',
            'INTO'   => 'articles',
            'VALUES' => "$id1, 'level1', 0, 1, 1, '', 'level1'"
        ]);
        $id2 = $this->getChildId($id1);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'parent_id, title, create_time, modify_time, published, template, url',
            'INTO'   => 'articles',
            'VALUES' => "$id2, 'level2', 0, 1, 1, '', 'level2'"
        ]);
        $id3 = $this->getChildId($id2);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'parent_id, title, create_time, modify_time, published, template, url',
            'INTO'   => 'articles',
            'VALUES' => "$id3, 'level3', 0, 1, 0, '', 'level3'"
        ]);
        $id4 = $this->getChildId($id3);

        $this->dbLayer->buildAndQuery([
            'INSERT' => 'parent_id, title, create_time, modify_time, published, template, url',
            'INTO'   => 'articles',
            'VALUES' => "$id4, 'level4', 0, 1, 1, '', 'level4'"
        ]);
        $id5 = $this->getChildId($id4);

        $I->assertEquals('', $this->articleProvider->pathFromId($id0));
        $I->assertEquals('/', $this->articleProvider->pathFromId($id1));
        $I->assertEquals('/level1', $this->articleProvider->pathFromId($id2));
        $I->assertEquals('/level1/level2', $this->articleProvider->pathFromId($id3));
        $I->assertEquals('/level1/level2/level3', $this->articleProvider->pathFromId($id4));
        $I->assertEquals('/level1/level2/level3/level4', $this->articleProvider->pathFromId($id5));
        $I->assertEquals('', $this->articleProvider->pathFromId($id0, true));
        $I->assertEquals('/', $this->articleProvider->pathFromId($id1, true));
        $I->assertEquals('/level1', $this->articleProvider->pathFromId($id2, true));
        $I->assertEquals('/level1/level2', $this->articleProvider->pathFromId($id3, true));
        $I->assertEquals(null, $this->articleProvider->pathFromId($id4, true));
        $I->assertEquals(null, $this->articleProvider->pathFromId($id5, true));
        $I->assertEquals(null, $this->articleProvider->pathFromId(100000));
    }

    /**
     * @throws DbLayerException
     */
    private function getChildId(int $parentId): int
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id',
            'FROM'   => 'articles',
            'WHERE'  => 'parent_id = :id'
        ], [':id' => $parentId]);

        return $this->dbLayer->result($result);
    }
}
