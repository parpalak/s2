<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Dashboard;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Pdo\QueryBuilder\UnionAll;

readonly class DashboardArticleProvider implements DashboardStatProviderInterface
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private DbLayer          $dbLayer,
        private string           $rootDir,
    ) {
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getHtml(): string
    {
        return $this->templateRenderer->render(
            $this->rootDir . '_admin/templates/dashboard/article-item.php.inc',
            $this->countArticles()
        );
    }

    /**
     * @throws DbLayerException
     */
    public function countArticles(): array
    {
        $baseQuery      = $this->dbLayer
            ->select('id')
            ->from('articles')
            ->where('parent_id = :parent_id')
            ->andWhere('published = 1')
        ;
        $recursiveQuery = $this->dbLayer
            ->select('a.id')
            ->from('articles AS a')
            ->innerJoin('article_tree AS at', 'a.parent_id = at.id')
            ->where('a.published = 1')
        ;
        $result         = $this->dbLayer
            ->withRecursive('article_tree', new UnionAll($baseQuery, $recursiveQuery))
            ->select('SUM(CASE (' .
                $this->dbLayer->select('COUNT(*)')
                    ->from('articles')
                    ->where('parent_id = at.id')
                    ->andWhere('published = 1')
                    ->getSql()
                . ') WHEN 0 THEN 1 ELSE 0 END) AS articles_num')
            ->addSelect('SUM((' .
                $this->dbLayer->select('COUNT(*)')
                    ->from('art_comments')
                    ->where('article_id = at.id')
                    ->andWhere('shown = 1')
                    ->getSql()
                . ')) AS comments_num')
            ->from('article_tree AS at')
            ->setParameter('parent_id', ArticleProvider::ROOT_ID)
            ->execute()
        ;

        $data = $result->fetchAssoc();
        return $data;
    }
}
