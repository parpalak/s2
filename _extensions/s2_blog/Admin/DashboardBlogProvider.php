<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Admin\Dashboard\DashboardStatProviderInterface;
use S2\Cms\Pdo\DbLayer;

readonly class DashboardBlogProvider implements DashboardStatProviderInterface
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
            $this->rootDir . '_extensions/s2_blog/views/dashboard/blog-item.php.inc',
            [
                'posts_num'    => $this->countPosts(),
                'comments_num' => $this->countComments()
            ]
        );
    }

    private function countPosts(): int
    {
        $query  = [
            'SELECT' => 'count(*)',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'published = 1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);
        return $this->dbLayer->result($result);
    }

    private function countComments(): int
    {
        $query  = [
            'SELECT' => 'count(*)',
            'FROM'   => 's2_blog_comments AS c',
            'JOINS'  => [
                [
                    'INNER JOIN' => 's2_blog_posts AS p',
                    'ON'         => 'p.id = c.post_id'
                ]
            ],
            'WHERE'  => 'c.shown = 1 AND p.published = 1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);
        return $this->dbLayer->result($result);
    }
}
