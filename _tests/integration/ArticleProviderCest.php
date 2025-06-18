<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use S2\Cms\Admin\Dashboard\DashboardArticleProvider;
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
    private ?DashboardArticleProvider $dashboardArticleProvider;

    public function _before(\IntegrationTester $I)
    {
        $this->articleProvider          = $I->grabService(ArticleProvider::class);
        $this->dbLayer                  = $I->grabService(DbLayer::class);
        $this->dashboardArticleProvider = $I->grabAdminService(DashboardArticleProvider::class);
    }

    /**
     * @throws DbLayerException
     */
    public function testPath(\IntegrationTester $I): void
    {
        $id0 = 0;
        // Main page
        $id1 = $this->getChildId($id0);

        $qb = $this->dbLayer
            ->insert('articles')
            ->setValue('parent_id', ':parent_id')->setParameter('parent_id', $id1)
            ->setValue('title', ':title')->setParameter('title', 'level1')
            ->setValue('create_time', '0')
            ->setValue('modify_time', '1')
            ->setValue('published', ':published')->setParameter('published', 1)
            ->setValue('template', ':template')->setParameter('template', 'site.php')
            ->setValue('url', ':url')->setParameter('url', 'level1')
            ->setValue('excerpt', '\'\'')
            ->setValue('pagetext', '\'\'')
        ;

        $qb->execute();

        $id2 = $this->getChildId($id1);

        $qb
            ->setParameter('parent_id', $id2)
            ->setParameter('title', 'level2')
            ->setParameter('url', 'level2')
            ->setParameter('published', 1)
            ->setParameter('template', '')
            ->execute()
        ;

        $id3 = $this->getChildId($id2);

        $qb
            ->setParameter('parent_id', $id3)
            ->setParameter('title', 'level3')
            ->setParameter('url', 'level3')
            ->setParameter('published', 0)
            ->execute()
        ;
        $id4 = $this->getChildId($id3);

        $qb
            ->setParameter('parent_id', $id4)
            ->setParameter('title', 'level4')
            ->setParameter('url', 'level4')
            ->setParameter('published', 1)
            ->execute()
        ;
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

        $I->assertEquals('', $this->articleProvider->findInheritedTemplate($id0));
        $I->assertEquals('', $this->articleProvider->findInheritedTemplate($id1));
        $I->assertEquals('mainpage.php', $this->articleProvider->findInheritedTemplate($id2));
        $I->assertEquals('site.php', $this->articleProvider->findInheritedTemplate($id3));
        $I->assertEquals('site.php', $this->articleProvider->findInheritedTemplate($id4));
        $I->assertEquals('site.php', $this->articleProvider->findInheritedTemplate($id5));

        $I->assertEquals(1, $this->dashboardArticleProvider->countArticles()['articles_num']);
    }

    /**
     * @throws DbLayerException
     */
    private function getChildId(int $parentId): int
    {
        $result = $this->dbLayer
            ->select('id')
            ->from('articles')
            ->where('parent_id = :id')->setParameter('id', $parentId)
            ->execute()
        ;

        return $result->result();
    }
}
