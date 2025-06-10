<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Admin\Dashboard\DashboardStatProviderInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class DashboardBlogProvider implements DashboardStatProviderInterface
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
            $this->rootDir . '_extensions/s2_blog/views/dashboard/blog-item.php.inc',
            [
                'posts_num'    => $this->countPosts(),
                'comments_num' => $this->countComments()
            ]
        );
    }

    /**
     * @throws DbLayerException
     */
    private function countPosts(): int
    {
        return $this->dbLayer->select('count(*)')
            ->from('s2_blog_posts')
            ->where('published = 1')
            ->execute()->result()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function countComments(): int
    {
        return $this->dbLayer->select('count(*)')
            ->from('s2_blog_comments AS c')
            ->innerJoin('s2_blog_posts AS p', 'p.id = c.post_id')
            ->where('c.shown = 1')
            ->andWhere('p.published = 1')
            ->execute()
            ->result()
        ;
    }
}
