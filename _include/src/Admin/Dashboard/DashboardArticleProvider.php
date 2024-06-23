<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Dashboard;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;

readonly class DashboardArticleProvider implements DashboardStatProviderInterface
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private DbLayer          $dbLayer,
        private string           $rootDir,
    ) {
    }

    public function getHtml(): string
    {
        return $this->templateRenderer->render(
            $this->rootDir . '_admin/templates/dashboard/article-item.php.inc',
            $this->countArticles()
        );
    }

    private function countArticles(): array
    {
        $tablePrefix = $this->dbLayer->getPrefix();
        $parentId    = ArticleProvider::ROOT_ID;

        $sql = <<<SQL
WITH RECURSIVE article_tree AS (
    SELECT id
    FROM {$tablePrefix}articles
    WHERE published = 1 AND parent_id = {$parentId}
    UNION ALL
    SELECT a.id
    FROM {$tablePrefix}articles a
    INNER JOIN article_tree at ON a.parent_id = at.id
    WHERE a.published = 1
)
SELECT
    SUM(CASE (SELECT COUNT(*) FROM {$tablePrefix}articles WHERE parent_id = at.id AND published = 1) WHEN 0 THEN 1 ELSE 0 END) AS articles_num,
    SUM((SELECT COUNT(*) FROM {$tablePrefix}art_comments WHERE article_id = at.id AND shown = 1)) AS comments_num
    FROM article_tree AS at

SQL;

        $result = $this->dbLayer->query($sql);
        $data   = $this->dbLayer->fetchAssoc($result);
        return $data;
    }
}
